<?php
declare(strict_types=1);

use App\Core\Database;

$p101 = pid('SAN-000101');
$p105 = pid('SAN-000105');
$cons101 = (int) Database::value('SELECT id FROM consultas WHERE paciente_id = ? ORDER BY id LIMIT 1', [$p101]);

function temp_from(array $r): string
{
    return preg_match('/Contraseña temporal de [^:]+: ([A-Za-z0-9]+)/', $r['body'], $m) ? $m[1] : '';
}

// ---------------------------------------------------------------- Usuarios
T::group('Usuarios (administrador)');
$ha = new Http();
$ha->login(...$ADMIN);
$antes = count_table('SELECT COUNT(*) FROM usuarios');
$ha->submit('/usuarios/nuevo', '/usuarios', ['nombres' => '', 'apellidos' => 'X', 'email' => 'malcorreo', 'rol_id' => '1']);
$f = $ha->follow('/usuarios/nuevo')['body'];
T::ok(str_contains($f, 'Nombres es obligatorio') && str_contains($f, 'no es un correo válido'), 'Valida nombre y correo');
$ha->submit('/usuarios/nuevo', '/usuarios', ['nombres' => 'X', 'apellidos' => 'Y', 'email' => 'admin@sanitas.local', 'rol_id' => '1']);
T::has($ha->follow('/usuarios/nuevo'), 'Ya existe una cuenta con ese correo', 'Rechaza correo duplicado');
$ha->submit('/usuarios/nuevo', '/usuarios', ['nombres' => 'X', 'apellidos' => 'Y', 'email' => 'medico.por.aqui@sanitas.local', 'rol_id' => '2']);
T::has($ha->follow('/usuarios/nuevo'), 'módulo Médicos', 'No permite crear médicos desde Usuarios');
$ha->submit('/usuarios/nuevo', '/usuarios', ['nombres' => 'X', 'apellidos' => 'Y', 'email' => 'pac.sin.ficha@correo.test', 'rol_id' => '3']);
T::has($ha->follow('/usuarios/nuevo'), 'Seleccione el paciente', 'Un usuario Paciente exige vincularse a una ficha');
T::ok(count_table('SELECT COUNT(*) FROM usuarios') === $antes, 'Nada se guarda con datos inválidos');

$r = $ha->submit('/usuarios/nuevo', '/usuarios', ['nombres' => 'Diego', 'apellidos' => 'Arévalo Pérez', 'email' => 'diego.arevalo@sanitas.local', 'rol_id' => '1']);
$tempAdmin = temp_from($ha->follow('/usuarios'));
T::ok($r['status'] === 302 && strlen($tempAdmin) >= 12, 'Crea un administrador con contraseña temporal');
$nuevoUid = (int) Database::value('SELECT id FROM usuarios WHERE email = ?', ['diego.arevalo@sanitas.local']);
T::ok(!str_contains((string) Database::value('SELECT password_hash FROM usuarios WHERE id = ?', [$nuevoUid]), $tempAdmin), 'La contraseña temporal no se guarda en claro');
T::ok(count_table("SELECT COUNT(*) FROM auditoria WHERE modulo='usuarios' AND accion='crear' AND registro_id = ?", [$nuevoUid]) === 1, 'La creación del usuario queda auditada');

$hn = new Http();
$hn->login('diego.arevalo@sanitas.local', $tempAdmin);
T::ok(str_contains((string) $hn->get('/usuarios')['location'], '/perfil'), 'El nuevo usuario debe cambiar la clave antes de operar');
$hn->submit('/perfil', '/perfil/clave', ['password_actual' => 'incorrecta', 'password_nueva' => 'OtraClave2026x', 'password_confirmar' => 'OtraClave2026x']);
T::has($hn->follow('/perfil'), 'contraseña actual no es correcta', 'Pide la contraseña actual correcta');
$hn->submit('/perfil', '/perfil/clave', ['password_actual' => $tempAdmin, 'password_nueva' => 'OtraClave2026x', 'password_confirmar' => 'Distinta2026x']);
T::has($hn->follow('/perfil'), 'no coinciden', 'Valida la confirmación');
$hn->submit('/perfil', '/perfil/clave', ['password_actual' => $tempAdmin, 'password_nueva' => 'OtraClave2026x', 'password_confirmar' => 'OtraClave2026x']);
T::status($hn->get('/usuarios'), 200, 'Con contraseña propia ya opera como administrador');
T::ok(count_table("SELECT COUNT(*) FROM auditoria WHERE accion='cambio_clave' AND usuario_nombre LIKE 'Diego%'") === 1, 'El cambio de contraseña queda auditado');

// Crea usuario paciente vinculado a ficha sin cuenta
$r = $ha->submit('/usuarios/nuevo', '/usuarios', ['nombres' => 'José Luis', 'apellidos' => 'Monzón Reyes', 'email' => 'jlmonzon.acceso@correo.test', 'rol_id' => '3', 'paciente_id' => (string) $p105]);
$tempPac = temp_from($ha->follow('/usuarios'));
T::ok(count_table('SELECT COUNT(*) FROM pacientes WHERE id = ? AND usuario_id IS NOT NULL', [$p105]) === 1 && $tempPac !== '', 'Crea usuario Paciente y lo vincula a la ficha');
$hp = new Http();
$hp->login('jlmonzon.acceso@correo.test', $tempPac);
$hp->submit('/perfil', '/perfil/clave', ['password_actual' => $tempPac, 'password_nueva' => 'MiClavePropia2026', 'password_confirmar' => 'MiClavePropia2026']);
T::has($hp->follow('/mi-historial'), 'Dislipidemia mixta', 'El nuevo paciente ve su propio historial');
T::status($hp->get("/pacientes/$p101"), 403, 'Y no puede ver el de otro paciente');
$hp->logout();
// La ficha ya vinculada no puede recibir otra cuenta
$ha->submit('/usuarios/nuevo', '/usuarios', ['nombres' => 'Otro', 'apellidos' => 'Intento', 'email' => 'otro.intento@correo.test', 'rol_id' => '3', 'paciente_id' => (string) $p105]);
T::has($ha->follow('/usuarios/nuevo'), 'Seleccione el paciente', 'Una ficha con cuenta no se puede vincular de nuevo');

// Edición, desactivación, restablecimiento
$ha->submit("/usuarios/$nuevoUid/editar", "/usuarios/$nuevoUid", ['nombres' => 'Diego Alfonso', 'apellidos' => 'Arévalo Pérez', 'email' => 'diego.arevalo@sanitas.local']);
T::ok(Database::value('SELECT nombres FROM usuarios WHERE id = ?', [$nuevoUid]) === 'Diego Alfonso', 'Edita un usuario');
$myId = (int) Database::value('SELECT id FROM usuarios WHERE email = ?', [$ADMIN[0]]);
$ha->submit('/usuarios', "/usuarios/$myId/estado");
T::ok(count_table('SELECT activo FROM usuarios WHERE id = ?', [$myId]) === 1, 'El administrador no puede desactivar su propia cuenta');
$ha->submit('/usuarios', "/usuarios/$nuevoUid/estado");
T::ok(count_table('SELECT activo FROM usuarios WHERE id = ?', [$nuevoUid]) === 0, 'Desactiva un usuario');
$hd = new Http();
$hd->login('diego.arevalo@sanitas.local', 'OtraClave2026x');
T::has($hd->follow('/login'), 'cuenta está desactivada', 'Una cuenta desactivada no puede ingresar');
T::ok(!str_contains((string) $hn->get('/')['body'] ?? '', 'Estado general'), 'Una sesión abierta de una cuenta desactivada deja de funcionar');
$ha->submit('/usuarios', "/usuarios/$nuevoUid/estado");
$ha->submit('/usuarios', "/usuarios/$nuevoUid/clave");
$t2 = temp_from($ha->follow('/usuarios'));
T::ok($t2 !== '' && count_table('SELECT debe_cambiar_clave FROM usuarios WHERE id = ?', [$nuevoUid]) === 1, 'Restablece la contraseña (temporal, obliga a cambiarla)');
T::has($ha->get('/usuarios?q=arevalo'), 'Arévalo', 'Busca usuarios por nombre');
T::ok(!str_contains($ha->get('/usuarios?estado=inactivos')['body'], 'Castellanos Aguilar, Verónica'), 'Filtra usuarios por estado');

// ---------------------------------------------------------------- Médicos
T::group('Médicos y especialidades (administrador)');
$ha->submit('/medicos/nuevo', '/medicos', ['nombres' => 'Iván', 'apellidos' => 'Solís Cabrera', 'email' => 'ivan.solis@sanitas.local', 'especialidad_id' => '999', 'colegiado' => '14872']);
$f = $ha->follow('/medicos/nuevo')['body'];
T::ok(str_contains($f, 'número de colegiado') && str_contains($f, 'especialidad válida'), 'Valida colegiado duplicado y especialidad');
$r = $ha->submit('/medicos/nuevo', '/medicos', ['nombres' => 'Iván', 'apellidos' => 'Solís Cabrera', 'email' => 'ivan.solis@sanitas.local', 'especialidad_id' => '6', 'colegiado' => '17005', 'consultorio' => 'Consultorio 210']);
$tempMed = temp_from($ha->follow('/medicos'));
T::ok($r['status'] === 302 && $tempMed !== '', 'Registra un médico con su cuenta');
T::ok(count_table("SELECT COUNT(*) FROM medicos m JOIN usuarios u ON u.id = m.usuario_id WHERE m.colegiado = '17005' AND u.rol_id = 2") === 1, 'Se crean usuario (rol médico) y perfil de médico relacionados');
$hm = new Http();
$hm->login('ivan.solis@sanitas.local', $tempMed);
$hm->submit('/perfil', '/perfil/clave', ['password_actual' => $tempMed, 'password_nueva' => 'ClaveMedico2026x', 'password_confirmar' => 'ClaveMedico2026x']);
T::has($hm->get('/'), 'Traumatología', 'El nuevo médico ve su dashboard con su especialidad');
T::status($hm->get("/pacientes/$p101/consultas/nueva"), 200, 'El nuevo médico puede registrar consultas');
$mid = (int) Database::value("SELECT id FROM medicos WHERE colegiado = '17005'");
$ha->submit("/medicos/$mid/editar", "/medicos/$mid", ['nombres' => 'Iván', 'apellidos' => 'Solís Cabrera', 'email' => 'ivan.solis@sanitas.local', 'especialidad_id' => '1', 'colegiado' => '17005', 'consultorio' => 'Consultorio 220']);
T::ok(Database::value('SELECT consultorio FROM medicos WHERE id = ?', [$mid]) === 'Consultorio 220', 'Edita médico');
$ha->submit('/medicos', "/medicos/$mid/estado");
T::ok(count_table('SELECT m.activo + u.activo FROM medicos m JOIN usuarios u ON u.id = m.usuario_id WHERE m.id = ?', [$mid]) === 0, 'Inactivar médico bloquea también su cuenta');
T::status($hm->get("/pacientes/$p101/consultas/nueva"), 302, 'Su sesión abierta deja de ser válida al inactivarlo');
T::has($ha->get('/medicos?q=Solís'), 'Solís Cabrera', 'Busca médicos');
$ha->submit('/especialidades', '/especialidades', ['nombre' => 'Medicina interna']);
T::has($ha->follow('/especialidades'), 'Ya existe una especialidad', 'Rechaza especialidad duplicada');
$ha->submit('/especialidades', '/especialidades', ['nombre' => 'Neurología', 'descripcion' => 'Sistema nervioso']);
T::ok(count_table("SELECT COUNT(*) FROM especialidades WHERE nombre = 'Neurología'") === 1, 'Agrega especialidad');

// ---------------------------------------------------------------- Roles y permisos
T::group('Roles y permisos: efecto inmediato en el backend');
$ids = fn(string ...$codes) => array_map(fn($c) => (int) Database::value("SELECT id FROM permisos WHERE CONCAT(modulo,'.',accion) = ?", [$c]), $codes);
$permMedico = array_column(Database::all('SELECT permiso_id FROM rol_permisos WHERE rol_id = 2'), 'permiso_id');
$sinCrear = array_values(array_diff($permMedico, $ids('consultas.crear')));
$hdoc = new Http();
$hdoc->login(...$MEDICO);
T::status($hdoc->get("/pacientes/$p101/consultas/nueva"), 200, 'Antes: el médico puede crear consultas');
$ha->submit('/roles/2', '/roles/2', ['permisos' => $sinCrear]);
T::status($hdoc->get("/pacientes/$p101/consultas/nueva"), 403, 'Tras quitar consultas.crear al rol, el médico recibe 403 (sin reiniciar sesión)');
T::ok(!str_contains($hdoc->get("/pacientes/$p101")['body'], 'Nueva consulta'), 'La interfaz oculta el botón «Nueva consulta»');
$aud = Database::one("SELECT detalle FROM auditoria WHERE accion='permisos' ORDER BY id DESC LIMIT 1");
T::ok($aud && str_contains($aud['detalle'], 'consultas.crear'), 'El cambio de permisos queda auditado con el detalle');
$ha->submit('/roles/2', '/roles/2', ['permisos' => $permMedico]);
T::status($hdoc->get("/pacientes/$p101/consultas/nueva"), 200, 'Al restaurar el permiso, vuelve a funcionar');
$hdoc->logout();

$permAdmin = array_column(Database::all('SELECT permiso_id FROM rol_permisos WHERE rol_id = 1'), 'permiso_id');
$ha->submit('/roles/1', '/roles/1', ['permisos' => array_values(array_diff($permAdmin, $ids('roles.editar')))]);
T::has($ha->follow('/roles/1'), 'debe conservar estos permisos', 'No se puede quitar roles.editar al administrador (evita bloqueo total)');
T::ok(count_table('SELECT COUNT(*) FROM rol_permisos WHERE rol_id = 1') === count($permAdmin), 'Los permisos del administrador quedan intactos');
$ha->submit('/roles/3', '/roles/3', ['permisos' => array_merge(array_column(Database::all('SELECT permiso_id FROM rol_permisos WHERE rol_id = 3'), 'permiso_id'), $ids('historial.crear', 'historial.editar'))]);
$hp1 = new Http();
$hp1->login(...$PACIENTE);
T::status($hp1->get("/pacientes/$p101/historial/alergias/nuevo"), 403, 'Aunque se otorgue historial.crear al rol Paciente, el backend sigue bloqueándolo (solo personal)');
$hp1->logout();
$ha->submit('/roles/3', '/roles/3', ['permisos' => array_column(Database::all('SELECT id FROM permisos WHERE CONCAT(modulo,".",accion) IN ("pacientes.ver","historial.ver","consultas.ver","documentos.ver","documentos.descargar")'), 'id')]);
T::status($ha->get('/roles/999'), 404, 'Rol inexistente: 404');
$hdoc = new Http();
$hdoc->login(...$MEDICO);
T::status($hdoc->submit('/perfil', '/roles/2', ['permisos' => $ids('usuarios.ver')]), 403, 'El médico no puede modificar permisos (POST directo)');
$hdoc->logout();

// ---------------------------------------------------------------- Configuración
T::group('Configuración: institución y seguridad');
foreach (['/configuracion', '/configuracion/apariencia', '/configuracion/seguridad'] as $path) {
    $r = $ha->get($path);
    T::ok($r['status'] === 200 && !preg_match('/\b(Warning|Notice|Fatal error)\b:/', $r['body']), "GET $path");
}
$ha->submit('/configuracion/seguridad', '/configuracion/seguridad', ['sesion_minutos' => '1', 'archivo_max_mb' => '99', 'intentos_max' => '5', 'bloqueo_minutos' => '15']);
$f = $ha->follow('/configuracion/seguridad')['body'];
T::ok(str_contains($f, 'entre 5 y 480'), 'Seguridad: valida rangos');
$ha->submit('/configuracion/seguridad', '/configuracion/seguridad', ['sesion_minutos' => '30', 'archivo_max_mb' => '15', 'intentos_max' => '5', 'bloqueo_minutos' => '15']);
T::ok(Database::value("SELECT valor FROM configuracion WHERE clave = 'intentos_max'") === '5', 'Seguridad: guarda');
$ha->submit('/configuracion', '/configuracion', ['hospital_nombre' => '', 'hospital_correo' => 'malo']);
$f = $ha->follow('/configuracion')['body'];
T::ok(str_contains($f, 'Nombre del hospital es obligatorio') && str_contains($f, 'no es un correo válido'), 'Institución: valida nombre y correo');
$ha->submit('/configuracion', '/configuracion', ['hospital_nombre' => 'Hospital Sanitas', 'hospital_lema' => 'Cuidamos su historia clínica', 'hospital_direccion' => '7a. avenida 12-45, zona 10', 'hospital_telefono' => '2300-4501', 'hospital_correo' => 'contacto@sanitas.test', 'hospital_horario' => 'Lunes a viernes de 7:00 a 19:00']);
T::ok(Database::value("SELECT valor FROM configuracion WHERE clave = 'hospital_telefono'") === '2300-4501', 'Institución: guarda');
T::has($ha->get('/'), 'Cuidamos su historia clínica', 'El lema se refleja en la barra lateral');
$hdoc = new Http();
$hdoc->login(...$MEDICO);
foreach (['/configuracion', '/configuracion/apariencia', '/configuracion/seguridad', '/configuracion/marca'] as $ep) {
    T::status($hdoc->submit('/perfil', $ep, ['hospital_nombre' => 'X', 'accion' => 'quitar_logo']), 403, "El médico no puede modificar $ep");
}
T::status($hdoc->get('/configuracion/apariencia'), 403, 'El médico no puede abrir Apariencia');
$hdoc->logout();

T::group('Configuración: tema, acento, logo y favicon');
T::has($ha->get('/'), 'data-theme="claro"', 'Tema por defecto del hospital: claro');
$ha->submit('/configuracion/apariencia', '/configuracion/apariencia', ['tema_defecto' => 'rosa', 'color_acento' => 'neon']);
T::ok(Database::value("SELECT valor FROM configuracion WHERE clave = 'tema_defecto'") === 'claro', 'Rechaza un tema no permitido');
$ha->submit('/configuracion/apariencia', '/configuracion/apariencia', ['tema_defecto' => 'oscuro', 'color_acento' => 'azul']);
$page = $ha->get('/')['body'];
T::ok(str_contains($page, 'data-theme="oscuro"') && str_contains($page, 'data-accent="azul"'), 'El tema oscuro y el acento azul se aplican al sistema');
T::has($new = (new Http())->get('/login'), 'data-theme="oscuro"', 'La pantalla de acceso también usa el tema configurado');
// Color de acento libre (cualquier hex), con contraste garantizado
$ha->submit('/configuracion/apariencia', '/configuracion/apariencia', ['tema_defecto' => 'claro', 'color_acento' => '#12345']);
T::ok(Database::value("SELECT valor FROM configuracion WHERE clave = 'color_acento'") === 'azul', 'Rechaza un hexadecimal mal formado');
$ha->submit('/configuracion/apariencia', '/configuracion/apariencia', ['tema_defecto' => 'claro', 'color_acento' => '#1D5FA8']);
T::ok(Database::value("SELECT valor FROM configuracion WHERE clave = 'color_acento'") === 'azul', 'Un hex igual a un atajo se guarda con su nombre');
$ha->submit('/configuracion/apariencia', '/configuracion/apariencia', ['tema_defecto' => 'claro', 'color_acento' => '#FACC15']);
T::ok(Database::value("SELECT valor FROM configuracion WHERE clave = 'color_acento'") === '#facc15', 'Guarda un color libre en minúsculas');
T::has($ha->get('/'), 'data-accent="personalizado"', 'El sistema usa el acento personalizado');
T::has($ha->get('/'), '/tema.css', 'Enlaza la hoja con la paleta derivada');
$css = (new Http())->get('/tema.css');
T::ok($css['status'] === 200 && str_contains($css['headers']['content-type'] ?? '', 'text/css') && str_contains($css['body'], 'data-accent="personalizado"'), '/tema.css se sirve sin sesión (la usa el login)');
preg_match('/data-theme="claro"\]\[data-accent="personalizado"\][^{]*\{[^}]*--brand: (#[0-9a-f]{6})/', $css['body'], $mb);
$lum = fn(string $h) => array_sum(array_map(fn($c, $w) => $w * (($v = hexdec($c) / 255) <= 0.03928 ? $v / 12.92 : (($v + 0.055) / 1.055) ** 2.4), str_split(ltrim($h, '#'), 2), [0.2126, 0.7152, 0.0722]));
T::ok(!empty($mb[1]) && (1.05 / ($lum($mb[1]) + 0.05)) >= 4.5, 'El amarillo se oscurece hasta cumplir contraste 4.5:1 con texto blanco', $mb[1] ?? 'sin --brand');
$ha->submit('/configuracion/apariencia', '/configuracion/apariencia', ['tema_defecto' => 'claro', 'color_acento' => 'cian']);
T::ok(trim((new Http())->get('/tema.css')['body']) === '', 'Con un atajo, /tema.css queda vacío');
$ha->submit('/configuracion/apariencia', '/configuracion/apariencia', ['tema_defecto' => 'oscuro', 'color_acento' => 'azul']);
// Preferencia por usuario
$hu = new Http();
$hu->login(...$MEDICO);
T::has($hu->get('/'), 'data-theme="oscuro"', 'Un usuario sin preferencia hereda el tema del hospital');
$hu->submit('/perfil', '/perfil/tema', ['tema' => 'claro']);
T::has($hu->get('/'), 'data-theme="claro"', 'La preferencia personal tiene prioridad');
$hu->submit('/perfil', '/perfil/tema', ['tema' => 'violeta']);
T::ok(Database::value('SELECT tema FROM usuarios WHERE email = ?', [$MEDICO[0]]) === 'claro', 'Rechaza un tema personal no válido');
$hu->submit('/perfil', '/perfil/tema', ['tema' => 'sistema']);
$hu->logout();
$ha->submit('/configuracion/apariencia', '/configuracion/apariencia', ['tema_defecto' => 'claro', 'color_acento' => 'cian']);

// Logo y favicon
$tmp = sys_get_temp_dir();
$logo = "$tmp/logo_prueba.png";
$im = imagecreatetruecolor(200, 80);
imagepng($im, $logo);
$fav = "$tmp/fav_prueba.png";
imagepng(imagecreatetruecolor(32, 32), $fav);
$svg = "$tmp/logo.svg";
file_put_contents($svg, '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');
$falso = "$tmp/logo_falso.png";
file_put_contents($falso, '<?php echo 1; ?>');
$enorme = "$tmp/logo_enorme.png";
imagepng(imagecreatetruecolor(2500, 100), $enorme);

T::status((new Http())->get('/marca/logo'), 404, 'Sin logo cargado, /marca/logo responde 404');
$ha->submit('/configuracion/apariencia', '/configuracion/marca', ['accion' => 'subir_logo'], ['archivo' => [$svg, 'image/svg+xml', 'logo.svg']]);
T::ok(Database::value("SELECT valor FROM configuracion WHERE clave = 'logo_archivo'") === '', 'Rechaza un SVG con scripts');
T::has($ha->follow('/configuracion/apariencia'), 'No se cargó el logo', 'Y explica por qué no se cargó');
$ha->submit('/configuracion/apariencia', '/configuracion/marca', ['accion' => 'subir_logo'], ['archivo' => [$falso, 'image/png', 'logo.png']]);
T::ok(Database::value("SELECT valor FROM configuracion WHERE clave = 'logo_archivo'") === '', 'Rechaza un PNG falso (contenido PHP)');
$ha->submit('/configuracion/apariencia', '/configuracion/marca', ['accion' => 'subir_logo'], ['archivo' => [$enorme, 'image/png', 'logo.png']]);
$grande = (string) Database::value("SELECT valor FROM configuracion WHERE clave = 'logo_archivo'");
$dim = $grande ? getimagesize(__DIR__ . '/../../storage/branding/' . $grande) : [0, 0];
T::ok($grande !== '' && $dim[0] === 1200 && $dim[1] === 48, 'Un logo de 2500 px se acepta y se reduce a 1200 px conservando la proporción');
$limpio = sys_get_temp_dir() . '/logo_limpio.svg';
file_put_contents($limpio, '<svg xmlns="http://www.w3.org/2000/svg" width="200" height="60"><rect width="200" height="60" fill="#0e7490"/></svg>');
$ha->submit('/configuracion/apariencia', '/configuracion/marca', ['accion' => 'subir_logo'], ['archivo' => [$limpio, 'image/svg+xml', 'logo.svg']]);
$r = (new Http())->get('/marca/logo');
T::ok($r['status'] === 200 && ($r['headers']['content-type'] ?? '') === 'image/svg+xml' && str_contains($r['headers']['content-security-policy'] ?? '', 'sandbox'), 'Un SVG limpio se acepta y se sirve aislado (sin poder ejecutar código)');
$favJpg = sys_get_temp_dir() . '/fav.jpg';
imagejpeg(imagecreatetruecolor(300, 200), $favJpg);
$ha->submit('/configuracion/apariencia', '/configuracion/marca', ['accion' => 'subir_favicon'], ['archivo' => [$favJpg, 'image/jpeg', 'fav.jpg']]);
$fj = (string) Database::value("SELECT valor FROM configuracion WHERE clave = 'favicon_archivo'");
$dimF = $fj ? getimagesize(__DIR__ . '/../../storage/branding/' . $fj) : [0, 0];
T::ok(str_ends_with($fj, '.png') && $dimF[0] === $dimF[1] && $dimF[0] === 256, 'Un favicon JPG rectangular se convierte en icono PNG cuadrado');
$ha->submit('/configuracion/apariencia', '/configuracion/marca', ['accion' => 'quitar_favicon']);
@unlink($limpio); @unlink($favJpg);
$ha->submit('/configuracion/apariencia', '/configuracion/marca', ['accion' => 'subir_logo'], ['archivo' => [$logo, 'image/png', 'logo.png']]);
$nombreLogo = (string) Database::value("SELECT valor FROM configuracion WHERE clave = 'logo_archivo'");
T::ok(preg_match('/^logo-[0-9a-f-]{36}\.png$/', $nombreLogo) === 1 && is_file(__DIR__ . '/../../storage/branding/' . $nombreLogo), 'Sube el logo con nombre aleatorio en storage/branding');
$r = (new Http())->get('/marca/logo');
T::ok($r['status'] === 200 && ($r['headers']['content-type'] ?? '') === 'image/png' && str_starts_with($r['body'], "\x89PNG"), 'El logo se sirve sin sesión (lo usa el login)');
T::has((new Http())->get('/login'), 'class="brand-logo"', 'La pantalla de acceso muestra el logo propio');
T::has($ha->get('/'), 'class="brand-logo"', 'La barra lateral muestra el logo propio');
$ha->submit('/configuracion/apariencia', '/configuracion/marca', ['accion' => 'subir_favicon'], ['archivo' => [$fav, 'image/png', 'fav.png']]);
$nombreFav = (string) Database::value("SELECT valor FROM configuracion WHERE clave = 'favicon_archivo'");
T::ok(preg_match('/^favicon-[0-9a-f-]{36}\.png$/', $nombreFav) === 1, 'Sube el favicon');
T::has($ha->get('/'), '/marca/favicon', 'El favicon propio se enlaza en la cabecera');
// Reemplazo borra el anterior
$ha->submit('/configuracion/apariencia', '/configuracion/marca', ['accion' => 'subir_logo'], ['archivo' => [$logo, 'image/png', 'logo2.png']]);
T::ok(!is_file(__DIR__ . '/../../storage/branding/' . $nombreLogo), 'Al reemplazar el logo se elimina el archivo anterior');
// Quitar
$ha->submit('/configuracion/apariencia', '/configuracion/marca', ['accion' => 'quitar_logo']);
$ha->submit('/configuracion/apariencia', '/configuracion/marca', ['accion' => 'quitar_favicon']);
T::ok(Database::value("SELECT valor FROM configuracion WHERE clave = 'logo_archivo'") === '' && count(glob(__DIR__ . '/../../storage/branding/*.*')) === 0, 'Quitar logo y favicon restablece lo predeterminado y borra los archivos');
T::has($ha->get('/'), '/images/favicon.svg', 'Vuelve el favicon predeterminado');
T::status((new Http())->get('/marca/otra'), 404, 'Un tipo de marca desconocido responde 404');
@unlink($logo); @unlink($fav); @unlink($svg); @unlink($falso); @unlink($enorme);

T::group('Configuración: estilo cristal e imagen de fondo');
$base = ['tema_defecto' => 'claro', 'color_acento' => 'cian'];
T::has($ha->get('/'), 'data-estilo="solido"', 'Por defecto el estilo es sólido');
$ha->submit('/configuracion/apariencia', '/configuracion/apariencia', $base + ['estilo_interfaz' => 'vidrio']);
T::ok(Database::value("SELECT valor FROM configuracion WHERE clave = 'estilo_interfaz'") === 'solido', 'Rechaza un estilo no permitido');
$ha->submit('/configuracion/apariencia', '/configuracion/apariencia', $base + ['estilo_interfaz' => 'cristal', 'cristal_opacidad' => '20', 'cristal_desenfoque' => '18', 'fondo_oscurecer' => '20']);
T::ok(Database::value("SELECT valor FROM configuracion WHERE clave = 'estilo_interfaz'") === 'solido', 'Rechaza una opacidad ilegible (menos de 50 %)');
$ha->submit('/configuracion/apariencia', '/configuracion/apariencia', $base + ['estilo_interfaz' => 'cristal', 'cristal_opacidad' => '80', 'cristal_desenfoque' => '12', 'fondo_oscurecer' => '30']);
$page = $ha->get('/')['body'];
T::ok(str_contains($page, 'data-estilo="cristal"') && str_contains($page, '/tema.css'), 'El estilo cristal se aplica y enlaza /tema.css');
$css = (new Http())->get('/tema.css')['body'];
T::ok(str_contains($css, '--glass-a: 0.8;') && str_contains($css, '--glass-blur: 12px;') && str_contains($css, '--scrim-a: 0.3;') && !str_contains($css, '--fondo-img'), '/tema.css lleva opacidad, desenfoque y oscurecimiento; sin imagen no declara fondo');
T::has((new Http())->get('/login'), 'data-estilo="cristal"', 'La pantalla de acceso también usa el estilo cristal');
$ha->submit('/configuracion/apariencia', '/configuracion/apariencia', $base);
T::ok(Database::value("SELECT valor FROM configuracion WHERE clave = 'cristal_opacidad'") === '80', 'Guardar sin los controles del estilo conserva sus valores');

$foto = "$tmp/fondo.jpg";
$im = imagecreatetruecolor(3200, 1800);
imagefilledrectangle($im, 0, 0, 1600, 1800, imagecolorallocate($im, 30, 90, 160));
imagejpeg($im, $foto, 90);
$gif = "$tmp/fondo.gif";
imagegif(imagecreatetruecolor(40, 40), $gif);
$ha->submit('/configuracion/apariencia', '/configuracion/marca', ['accion' => 'subir_fondo'], ['archivo' => [$gif, 'image/gif', 'fondo.gif']]);
T::ok(Database::value("SELECT valor FROM configuracion WHERE clave = 'fondo_archivo'") === '', 'Rechaza un fondo GIF');
T::has($ha->follow('/configuracion/apariencia'), 'No se cargó la imagen de fondo', 'Y explica por qué');
$ha->submit('/configuracion/apariencia', '/configuracion/marca', ['accion' => 'subir_fondo'], ['archivo' => [$foto, 'image/jpeg', 'fondo.jpg']]);
$nf = (string) Database::value("SELECT valor FROM configuracion WHERE clave = 'fondo_archivo'");
$dimFo = $nf ? getimagesize(__DIR__ . '/../../storage/branding/' . $nf) : [0, 0];
T::ok(preg_match('/^fondo-[0-9a-f-]{36}\.jpg$/', $nf) === 1 && $dimFo[0] === 2560 && $dimFo[1] === 1440, 'Una foto de 3200 px se guarda como JPG reducido a 2560 px');
$r = (new Http())->get('/marca/fondo');
T::ok($r['status'] === 200 && ($r['headers']['content-type'] ?? '') === 'image/jpeg', 'El fondo se sirve sin sesión (lo usa el login)');
T::has($ha->get('/'), 'data-fondo="1"', 'Con imagen, la página marca que hay fondo');
T::ok(str_contains((new Http())->get('/tema.css')['body'], '--fondo-img: url("'), '/tema.css declara la imagen de fondo');
// Fotos reales: extensión que no coincide con el contenido, formatos modernos y cámaras de muchos megapíxeles
$casos = [];
$w = "$tmp/descargada.jpg"; imagewebp(imagecreatetruecolor(1600, 900), $w); $casos['Un WEBP guardado como «.jpg» (típico de imágenes descargadas)'] = [$w, 'image/jpeg', 'descargada.jpg'];
if (function_exists('imageavif')) { $a = "$tmp/foto.jpg"; imageavif(imagecreatetruecolor(1200, 800), $a); $casos['Un AVIF con extensión «.jpg»'] = [$a, 'image/jpeg', 'foto.jpg']; }
$g = "$tmp/camara.jpg"; imagejpeg(imagecreatetruecolor(7000, 5000), $g, 70); $casos['Una foto de cámara de 35 megapíxeles'] = [$g, 'image/jpeg', 'camara.jpg'];
$bm = "$tmp/fondo.bmp"; imagebmp(imagecreatetruecolor(800, 600), $bm); $casos['Un BMP'] = [$bm, 'image/bmp', 'fondo.bmp'];
foreach ($casos as $label => $archivo) {
    $antes = (string) Database::value("SELECT valor FROM configuracion WHERE clave = 'fondo_archivo'");
    $ha->submit('/configuracion/apariencia', '/configuracion/marca', ['accion' => 'subir_fondo'], ['archivo' => $archivo]);
    $ahora = (string) Database::value("SELECT valor FROM configuracion WHERE clave = 'fondo_archivo'");
    $d = $ahora ? getimagesize(__DIR__ . '/../../storage/branding/' . $ahora) : false;
    T::ok($ahora !== $antes && $d !== false && $d[2] === IMAGETYPE_JPEG, "$label se acepta como fondo");
    @unlink($archivo[0]);
}
$gj = "$tmp/animado.jpg"; imagegif(imagecreatetruecolor(40, 40), $gj);
$ha->submit('/configuracion/apariencia', '/configuracion/marca', ['accion' => 'subir_fondo'], ['archivo' => [$gj, 'image/jpeg', 'animado.jpg']]);
T::has($ha->follow('/configuracion/apariencia'), 'El archivo es un GIF', 'Un GIF renombrado a «.jpg» se rechaza diciendo qué es');
@unlink($gj);
$ha->submit('/configuracion/apariencia', '/configuracion/marca', ['accion' => 'quitar_fondo']);
T::ok(Database::value("SELECT valor FROM configuracion WHERE clave = 'fondo_archivo'") === '' && !is_file(__DIR__ . '/../../storage/branding/' . $nf), 'Quitar el fondo borra la imagen');
T::status((new Http())->get('/marca/fondo'), 404, 'Sin fondo, /marca/fondo responde 404');
$ha->submit('/configuracion/apariencia', '/configuracion/apariencia', $base + ['estilo_interfaz' => 'solido']);
T::has($ha->get('/'), 'data-estilo="solido"', 'Se puede volver al estilo sólido');
@unlink($foto); @unlink($gif);

// ---------------------------------------------------------------- Auditoría
T::group('Auditoría');
foreach (['login', 'logout', 'login_fallido', 'crear', 'editar', 'eliminar', 'consultar', 'cargar', 'descargar', 'permisos', 'acceso_denegado', 'cambio_clave', 'estado'] as $accion) {
    T::ok(count_table('SELECT COUNT(*) FROM auditoria WHERE accion = ?', [$accion]) > 0, "Existen eventos de auditoría «{$accion}»");
}
T::ok(count_table('SELECT COUNT(*) FROM auditoria WHERE usuario_nombre = "" OR creado_en IS NULL OR descripcion = ""') === 0, 'Todo evento tiene usuario, fecha y descripción');
T::ok(count_table('SELECT COUNT(*) FROM auditoria WHERE ip IS NOT NULL') > 0, 'Se registra la IP de origen');
T::ok(count_table("SELECT COUNT(*) FROM auditoria WHERE accion = 'consultar' AND modulo = 'pacientes' AND paciente_id IS NOT NULL") > 0, 'La apertura de fichas (información sensible) queda registrada');
$page = $ha->get('/auditoria');
T::ok($page['status'] === 200 && str_contains($page['body'], 'Iniciar sesión') === false && str_contains($page['body'], 'Verónica Castellanos'), 'El administrador consulta la bitácora');
T::has($ha->get('/auditoria?accion=login_fallido'), 'Intento de acceso', 'Filtra por acción');
T::hasNot($ha->get('/auditoria?accion=login_fallido'), '>Cierre de sesión</span>', 'El filtro excluye otras acciones');
T::has($ha->get('/auditoria?q=SAN-000101'), 'SAN-000101', 'Filtra por código de paciente');
T::has($ha->get('/auditoria?desde=2999-01-01'), 'Sin registros', 'Rango sin resultados muestra estado vacío');
T::status($ha->get('/auditoria?pagina=999&desde=abc'), 200, 'Parámetros inválidos no rompen la pantalla');
$sensibles = Database::all("SELECT descripcion, detalle FROM auditoria WHERE modulo IN ('historial','consultas','pacientes') AND accion IN ('crear','editar')");
$fugas = array_filter($sensibles, fn($a) => preg_match('/Penicilina|Látex|Gastritis|Budesonida|Omeprazol|Hepatitis/i', $a['descripcion'] . ($a['detalle'] ?? '')));
T::ok(count($fugas) === 0, 'La bitácora no copia contenido clínico (solo nombres de campos)');

// ---------------------------------------------------------------- Dashboards
T::group('Dashboards con datos reales');
$d = $ha->get('/')['body'];
$usuariosActivos = count_table('SELECT COUNT(*) FROM usuarios WHERE activo = 1');
$pacientesActivos = count_table('SELECT COUNT(*) FROM pacientes WHERE activo = 1');
$medicosActivos = count_table('SELECT COUNT(*) FROM medicos WHERE activo = 1');
T::ok(str_contains($d, '<dt>Usuarios activos</dt><dd class="num">' . $usuariosActivos . '</dd>'), "Admin: usuarios activos = $usuariosActivos (igual a MySQL)");
T::ok(preg_match('/mcard-label">Pacientes activos<\/span>\s*<span class="mcard-value num">' . $pacientesActivos . '</u', $d) === 1, "Admin: pacientes activos = $pacientesActivos (igual a MySQL)");
$c30 = count_table("SELECT COUNT(*) FROM consultas WHERE estado = 'realizada' AND fecha_hora >= (NOW() - INTERVAL 30 DAY) AND fecha_hora <= NOW()");
T::ok(preg_match('/mcard-label">Consultas \(30 días\)<\/span>\s*<span class="mcard-value num">' . $c30 . '</u', $d) === 1, "Admin: consultas realizadas en 30 días = $c30 (igual a MySQL)");
T::ok(str_contains($d, 'class="mcard-grid"') && str_contains($d, 'class="area-line"') && str_contains($d, 'class="donut-svg"') && str_contains($d, '<tfoot>'), 'Admin: tarjetas de indicadores, gráfico de área y anillo (SVG en el servidor) y tablas con totales');
T::ok(!preg_match('/\d\s+\w+\(s\)/u', strip_tags($d)), 'Admin: los plurales están bien escritos (sin «consulta(s)»)');
T::ok(!preg_match('/<[^>]+ style="/', $d), 'Admin: ningún gráfico usa estilos en línea (la política de seguridad los bloquearía)');
T::ok(str_contains($d, '<dt>Médicos activos</dt><dd class="num">' . $medicosActivos . '</dd>'), "Admin: médicos activos = $medicosActivos");
$hdoc = new Http();
$hdoc->login(...$MEDICO);
$d = $hdoc->get('/')['body'];
$pend = count_table("SELECT COUNT(*) FROM consultas c JOIN medicos m ON m.id = c.medico_id JOIN usuarios u ON u.id = m.usuario_id WHERE u.email = ? AND c.estado = 'programada'", [$MEDICO[0]]);
T::ok(preg_match('/mcard-label">En agenda<\/span>\s*<span class="mcard-value num">' . $pend . '</u', $d) === 1, "Médico: consultas en agenda = $pend (igual a MySQL)");
T::has(['body' => $d], 'Agenda de hoy', 'Médico: la agenda del día va primero');
T::has(['body' => $d], 'Pacientes recientes', 'Médico: muestra pacientes recientes');
$hdoc->logout();
$hp1 = new Http();
$hp1->login(...$PACIENTE);
$d = $hp1->get('/')['body'];
$meds = count_table('SELECT COUNT(*) FROM medicamentos WHERE paciente_id = ? AND activo = 1', [$p101]);
T::ok(str_contains($d, '<dt>Medicamentos vigentes</dt><dd>' . $meds . '</dd>'), "Paciente: medicamentos vigentes = $meds");
T::has(['body' => $d], 'Evolución de mis signos vitales', 'Paciente: muestra la evolución de sus signos vitales');
T::has(['body' => $d], 'Alertas clínicas', 'Paciente: muestra información médica importante');
T::has(['body' => $d], 'Penicilina', 'Paciente: la alergia grave aparece en el resumen');
$hp1->logout();

// ---------------------------------------------------------------- Seguridad transversal
T::group('Seguridad transversal');
$h = new Http();
$r = $h->get('/login');
$cookie = '';
$raw = curl_init(Http::$base . '/login');
curl_setopt_array($raw, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true]);
$resp = (string) curl_exec($raw);
curl_close($raw);
preg_match('/Set-Cookie: (SANITASSID=[^\r\n]+)/i', $resp, $mc);
$cookie = $mc[1] ?? '';
T::ok(stripos($cookie, 'HttpOnly') !== false && stripos($cookie, 'SameSite=Lax') !== false, 'La cookie de sesión es HttpOnly y SameSite=Lax', $cookie);

// Regeneración del ID de sesión al iniciar sesión (anti fijación)
$jar = tempnam(sys_get_temp_dir(), 'fx');
$ch = curl_init(Http::$base . '/login');
curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar]);
$page = (string) curl_exec($ch);
unset($ch);   // el archivo de cookies se escribe al destruir el manejador
$antesId = preg_match('/SANITASSID\s+(\S+)/', (string) file_get_contents($jar), $m1) ? $m1[1] : '';
$ch = curl_init(Http::$base . '/login');
curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar, CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => http_build_query(['_csrf' => Http::csrf($page), 'email' => $MEDICO[0], 'password' => $MEDICO[1]])]);
curl_exec($ch);
unset($ch);
$despuesId = preg_match('/SANITASSID\s+(\S+)/', (string) file_get_contents($jar), $m2) ? $m2[1] : '';
T::ok($antesId !== '' && $despuesId !== '' && $antesId !== $despuesId, 'El ID de sesión cambia al iniciar sesión (anti fijación de sesión)');
@unlink($jar);

// CSRF en endpoints que modifican datos (sesión de administrador válida, sin token)
$endpoints = ['/pacientes', "/pacientes/$p101", "/pacientes/$p101/estado", '/medicos', '/usuarios', "/usuarios/$myId/estado", '/roles/2', '/configuracion', '/especialidades', '/logout', '/perfil/clave', "/documentos/1/eliminar"];
foreach ($endpoints as $ep) {
    T::status($ha->request('POST', $ep, ['x' => '1']), 400, "POST $ep sin token CSRF es rechazado");
}
$hdoc = new Http();
$hdoc->login(...$MEDICO);
foreach (["/pacientes/$p101/historial/alergias", "/pacientes/$p101/consultas", "/consultas/$cons101", "/consultas/$cons101/estado", "/pacientes/$p101/documentos", "/pacientes/$p101/historial/alergias/1/eliminar"] as $ep) {
    T::status($hdoc->request('POST', $ep, ['x' => '1']), 400, "POST $ep sin token CSRF es rechazado");
}
T::status($hdoc->get('/logout'), 405, 'Cerrar sesión no se puede hacer con GET');
$hdoc->logout();

// Inyección SQL en login y filtros
$hq = new Http();
$hq->submit('/login', '/login', ['email' => "admin@sanitas.local' OR '1'='1", 'password' => "' OR '1'='1"]);
T::ok($hq->get('/')['status'] === 302, 'Inyección SQL en el login no da acceso');
T::status($ha->get('/auditoria?q=' . urlencode("' OR 1=1; DROP TABLE auditoria; --")), 200, 'Inyección en filtros de auditoría no rompe la consulta');
T::ok(count_table('SELECT COUNT(*) FROM auditoria') > 0, 'La tabla auditoría sigue intacta');

// Errores sin información sensible
$r = $ha->get('/ruta/que/no/existe');
T::ok($r['status'] === 404 && !str_contains($r['body'], 'C:\\') && !str_contains($r['body'], 'Stack trace') && !str_contains($r['body'], 'PDOException'), 'El 404 no expone rutas ni trazas');
T::ok(!preg_match('/\.php:\d+/', $ha->get("/pacientes/abc")['body']), 'Parámetros mal formados no filtran detalles internos');

T::ok(count_table("SELECT COUNT(*) FROM usuarios WHERE password_hash NOT LIKE '$2y$%'") === 0, 'Todas las contraseñas están almacenadas con bcrypt');
$ha->logout();
