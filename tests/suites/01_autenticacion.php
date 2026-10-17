<?php
declare(strict_types=1);

use App\Core\Database;

T::group('Autenticación');

$h = new Http();
$r = $h->get('/login');
T::status($r, 200, 'Formulario de login accesible');
T::has($r, 'Iniciar sesión', 'Muestra el formulario');
T::ok(str_contains($r['headers']['content-security-policy'] ?? '', "default-src 'self'"), 'Envía cabecera CSP');
T::ok(($r['headers']['x-content-type-options'] ?? '') === 'nosniff', 'Envía X-Content-Type-Options');

$r = $h->get('/');
T::ok($r['status'] === 302 && str_contains((string) $r['location'], '/login'), 'Sin sesión, / redirige al login');
$r = $h->get('/pacientes/' . pid('SAN-000101'));
T::ok($r['status'] === 302 && str_contains((string) $r['location'], '/login'), 'Sin sesión, la ficha redirige al login');

// POST sin CSRF
$r = $h->request('POST', '/login', ['email' => $ADMIN[0], 'password' => $ADMIN[1]]);
T::status($r, 400, 'POST sin token CSRF es rechazado');
$r = $h->request('POST', '/login', ['_csrf' => 'x', 'email' => $ADMIN[0], 'password' => $ADMIN[1]]);
T::status($r, 400, 'POST con token CSRF inválido es rechazado');

// Login incorrecto
$antes = count_table("SELECT COUNT(*) FROM auditoria WHERE accion = 'login_fallido'");
$r = $h->login($ADMIN[0], 'contraseña-equivocada');
T::ok($r['status'] === 302, 'Login incorrecto redirige al formulario');
$page = $h->follow('/login');
T::has($page, 'Correo o contraseña incorrectos', 'Mensaje genérico de credenciales');
T::ok(count_table("SELECT COUNT(*) FROM auditoria WHERE accion = 'login_fallido'") === $antes + 1, 'El intento fallido queda en auditoría');
$r = $h->login('no.existe@correo.test', 'x');
$page = $h->follow('/login');
T::has($page, 'Correo o contraseña incorrectos', 'Mismo mensaje para correo inexistente (no revela cuentas)');

// Login correcto
$r = $h->login(...$ADMIN);
T::ok($r['status'] === 302 && !str_contains((string) $r['location'], 'login'), 'Login de administrador correcto');
$page = $h->follow('/');
T::has($page, 'Estado general del sistema', 'Administrador ve su dashboard');
T::ok(count_table("SELECT COUNT(*) FROM auditoria WHERE accion = 'login' AND usuario_nombre LIKE 'Verónica%'") === 1, 'El login queda en auditoría');
$r = $h->get('/login');
T::ok($r['status'] === 302, 'Con sesión activa, /login redirige');

// Logout
$r = $h->logout();
T::ok($r['status'] === 302, 'Logout redirige');
$r = $h->get('/');
T::ok($r['status'] === 302 && str_contains((string) $r['location'], '/login'), 'Tras logout, la sesión ya no es válida');
T::ok(count_table("SELECT COUNT(*) FROM auditoria WHERE accion = 'logout'") === 1, 'El logout queda en auditoría');

// Redirección abierta bloqueada
$h2 = new Http();
$h2->submit('/login', '/login', ['email' => $ADMIN[0], 'password' => $ADMIN[1], 'next' => '//evil.example/x']);
$r = $h2->get('/login');
T::ok($r['status'] === 302 && !str_contains((string) $r['location'], 'evil'), 'El parámetro next no permite redirecciones externas');
$h2->logout();

// Bloqueo por intentos fallidos
$h3 = new Http();
for ($i = 0; $i < 5; $i++) {
    $h3->login($MEDICO2[0], 'mala-clave-' . $i);
}
$r = $h3->login($MEDICO2[0], $MEDICO2[1]);
$page = $h3->follow('/login');
T::has($page, 'bloqueada temporalmente', 'Tras 5 fallos la cuenta se bloquea, incluso con la contraseña correcta');
Database::run("UPDATE usuarios SET bloqueado_hasta = NULL, intentos_fallidos = 0 WHERE email = ?", [$MEDICO2[0]]);
$h3->login(...$MEDICO2);
T::has($h3->follow('/'), 'Cardiología', 'Desbloqueada la cuenta, el acceso vuelve a funcionar');
$h3->logout();

// Sesión expirada por inactividad
$h4 = new Http();
$h4->login(...$MEDICO);
T::status($h4->get('/'), 200, 'Médico inicia sesión');
Database::run("UPDATE configuracion SET valor = '0' WHERE clave = 'sesion_minutos'");
sleep(1);
$r = $h4->get('/pacientes');
T::ok($r['status'] === 302 && str_contains((string) $r['location'], '/login'), 'Sesión inactiva es redirigida al login');
Database::run("UPDATE configuracion SET valor = '30' WHERE clave = 'sesion_minutos'");
T::has($h4->follow('/login'), 'sesión expiró', 'Se informa que la sesión expiró');

// Recuperación de contraseña
$h5 = new Http();
$r = $h5->submit('/olvide-contrasena', '/olvide-contrasena', ['email' => $PACIENTE2[0]]);
$msg1 = $h5->follow('/login')['body'];
T::has(['body' => $msg1], 'Si el correo corresponde', 'Mensaje de recuperación');
$r = $h5->submit('/olvide-contrasena', '/olvide-contrasena', ['email' => 'nadie@correo.test']);
$msg2 = $h5->follow('/login')['body'];
T::has(['body' => $msg2], 'Si el correo corresponde', 'Misma respuesta para correo inexistente');
$mail = glob(__DIR__ . '/../../storage/mail/*.eml');
usort($mail, fn($x, $y) => filemtime($x) <=> filemtime($y));
T::ok(count($mail) >= 1, 'Se envía el correo de recuperación (modo archivo: storage/mail)');
$eml = quoted_printable_decode((string) file_get_contents(end($mail)));
T::ok(str_contains($eml, 'Subject:') && str_contains($eml, 'multipart/alternative') && str_contains($eml, 'To: ') , 'El correo es un mensaje MIME con versión de texto y HTML');
preg_match('#/restablecer/([A-Za-z0-9]+)#', $eml, $m);
$token = $m[1] ?? '';
T::ok(strlen($token) === 48, 'El token tiene 192 bits de entropía');
T::ok(count_table('SELECT COUNT(*) FROM password_resets WHERE token_hash = ?', [$token]) === 0, 'El token no se guarda en texto plano');
T::ok(count_table('SELECT COUNT(*) FROM password_resets WHERE token_hash = ?', [hash('sha256', $token)]) === 1, 'Solo se guarda el hash del token');
$r = $h5->submit("/restablecer/$token", "/restablecer/$token", ['password' => 'corta', 'password_confirmar' => 'corta']);
T::has($h5->follow("/restablecer/$token"), 'al menos 10 caracteres', 'Rechaza contraseña débil');
$nueva = 'NuevaClave2026x';
$h5->submit("/restablecer/$token", "/restablecer/$token", ['password' => $nueva, 'password_confirmar' => $nueva]);
T::ok(password_verify($nueva, (string) Database::value('SELECT password_hash FROM usuarios WHERE email = ?', [$PACIENTE2[0]])), 'La contraseña se actualiza (con hash)');
T::has($h5->follow("/restablecer/$token"), 'Enlace no válido', 'El token no puede reutilizarse');
$h5->login($PACIENTE2[0], $nueva);
T::status($h5->get('/'), 200, 'Inicia sesión con la contraseña nueva');
$h5->logout();
// restaurar clave demo
Database::run('UPDATE usuarios SET password_hash = ? WHERE email = ?', [password_hash($DEMO, PASSWORD_DEFAULT), $PACIENTE2[0]]);
Database::run('DELETE FROM password_resets');
T::ok(!str_starts_with((string) Database::value('SELECT password_hash FROM usuarios WHERE id = 1'), 'Sanitas'), 'Las contraseñas no se almacenan en texto plano');
