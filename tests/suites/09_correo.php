<?php
declare(strict_types=1);

use App\Core\Database;
use App\Domain\Correo;

/** Correo del último aviso de un tipo para un destinatario. */
$correo = fn(string $email, string $tipo): ?array => Database::one('SELECT * FROM correos_salientes WHERE destinatario = ? AND tipo = ? ORDER BY id DESC LIMIT 1', [$email, $tipo]);
$p102 = pid('SAN-000102');
$p106 = pid('SAN-000106');   // paciente sin cuenta en el portal

T::group('Correo: avisos y privacidad');
$hm = new Http();
$hm->login(...$MEDICO);
$antes = count_table('SELECT COUNT(*) FROM correos_salientes');
$hm->submit("/pacientes/$p102/consultas/nueva", "/pacientes/$p102/consultas", ['fecha_hora' => date('Y-m-d\TH:i', strtotime('+5 days')), 'estado' => 'programada', 'motivo' => 'MOTIVO-PRIVADO de control']);
$c = $correo($PACIENTE2[0], 'consulta_programada');
T::ok($c !== null && $c['estado'] === 'pendiente', 'Programar una consulta deja el aviso por correo en la cola del paciente');
T::ok($c && str_contains($c['cuerpo_texto'], 'Se programó una consulta para usted para el') && str_contains($c['asunto'], 'Nueva consulta programada'), 'El correo indica fecha y hora de la consulta');
T::ok($c && !str_contains($c['cuerpo_texto'] . $c['cuerpo_html'] . $c['asunto'], 'MOTIVO-PRIVADO'), 'El correo no incluye el motivo de la consulta (información clínica)');
T::ok($c && str_contains($c['cuerpo_html'], '/correo/baja/') && str_contains($c['cuerpo_texto'], 'no incluye información médica'), 'Incluye el enlace para dejar de recibirlo y la nota de privacidad');
T::ok(count_table('SELECT COUNT(*) FROM correos_salientes') === $antes + 1, 'El médico que programó no recibe correo de su propia acción');

// Sin cuenta en el portal: solo con consentimiento registrado
$hm->submit("/pacientes/$p106/consultas/nueva", "/pacientes/$p106/consultas", ['fecha_hora' => date('Y-m-d\TH:i', strtotime('+6 days')), 'estado' => 'programada', 'motivo' => 'Control']);
T::ok(count_table('SELECT COUNT(*) FROM correos_salientes WHERE paciente_id = ? AND usuario_id IS NULL', [$p106]) === 0, 'Sin consentimiento, el paciente sin cuenta no recibe correos');
Database::run('UPDATE pacientes SET acepta_correo = 1 WHERE id = ?', [$p106]);
$hm->submit("/pacientes/$p106/consultas/nueva", "/pacientes/$p106/consultas", ['fecha_hora' => date('Y-m-d\TH:i', strtotime('+7 days')), 'estado' => 'programada', 'motivo' => 'Control']);
$sc = Database::one('SELECT * FROM correos_salientes WHERE paciente_id = ? AND usuario_id IS NULL ORDER BY id DESC LIMIT 1', [$p106]);
T::ok($sc !== null && $sc['tipo'] === 'consulta_programada' && !str_contains($sc['cuerpo_html'], '/correo/baja/'), 'Con consentimiento recibe el aviso de su cita (sin enlace al portal, que no usa)');
$hm->logout();

T::group('Correo: preferencias y bajas');
$hp = new Http();
$hp->login(...$PACIENTE2);
T::has($hp->get('/perfil'), 'name="correos[]"', 'El perfil permite elegir qué avisos llegan por correo');
$hp->submit('/perfil', '/perfil/notificaciones', ['tipos' => array_keys(App\Domain\Notificaciones::tiposDe('paciente')), 'correos' => []]);
$uid2 = (int) Database::value('SELECT id FROM usuarios WHERE email = ?', [$PACIENTE2[0]]);
T::ok(!Correo::prefiere($uid2, 'consulta_programada') && (int) Database::value("SELECT activo FROM notificacion_preferencias WHERE usuario_id = ? AND tipo = 'consulta_programada'", [$uid2]) === 1, 'Puede apagar el correo y conservar el aviso en el sistema');
$hp->submit('/perfil', '/perfil/notificaciones', ['tipos' => array_keys(App\Domain\Notificaciones::tiposDe('paciente')), 'correos' => ['consulta_programada']]);
T::ok(Correo::prefiere($uid2, 'consulta_programada'), 'Y volver a activarlo');
$hp->logout();

$token = Correo::tokenBaja($uid2, 'consulta_programada');
$anon = new Http();
T::has($anon->get('/correo/baja/' . $token), 'Dejar de recibir estos correos', 'El enlace de baja funciona sin iniciar sesión');
$falso = substr($token, 0, -1) . (substr($token, -1) === 'a' ? 'b' : 'a');
T::has($anon->get('/correo/baja/' . $falso), 'El enlace no es válido', 'Un enlace alterado se rechaza (firma HMAC)');
$anon->submit('/correo/baja/' . $token, '/correo/baja/' . $token, []);
T::ok(!Correo::prefiere($uid2, 'consulta_programada'), 'Confirmar la baja apaga ese aviso por correo');
T::ok(Correo::prefiere($uid2, 'recordatorio_consulta'), 'Los demás avisos por correo siguen activos');

T::group('Correo: envío, cola y administración');
$r = Correo::procesar(200);
T::ok($r['enviados'] >= 2 && count_table("SELECT COUNT(*) FROM correos_salientes WHERE estado = 'pendiente'") === 0, 'La cola se envía (modo archivo en pruebas)');
$enviado = Database::one("SELECT * FROM correos_salientes WHERE estado = 'enviado' ORDER BY id DESC LIMIT 1");
T::ok($enviado && $enviado['enviado_en'] !== null && (int) $enviado['intentos'] === 1, 'Cada correo enviado registra fecha e intentos');
T::ok(count(glob(__DIR__ . '/../../storage/mail/*.eml')) >= 2, 'Los mensajes quedan en storage/mail como archivos .eml');

$ha = new Http();
$ha->login(...$ADMIN);
$pag = $ha->get('/configuracion/correo');
T::ok($pag['status'] === 200 && str_contains($pag['body'], 'Bandeja de salida') && str_contains($pag['body'], 'Modo archivo'), 'El administrador ve el estado del correo y la bandeja de salida');
T::ok(!str_contains($pag['body'], 'MAIL_PASSWORD=') , 'La página no expone credenciales');
$ha->submit('/configuracion/correo', '/configuracion/correo/prueba', []);
T::has($ha->follow('/configuracion/correo'), 'Correo de prueba enviado', 'Envía un correo de prueba a su cuenta');
$ha->submit('/configuracion/correo', '/configuracion/correo', ['correo_activo' => '1', 'correo_tipos' => ['recordatorio_consulta'], 'correo_limite_hora' => '300']);
T::ok(Correo::tiposPorCorreo() === ['recordatorio_consulta'], 'Elige qué tipos de aviso se envían por correo');
$ha->submit('/configuracion/correo', '/configuracion/correo', ['correo_activo' => '1', 'correo_tipos' => array_keys(Correo::PLANTILLAS), 'correo_limite_hora' => '1']);
T::ok(Database::value("SELECT valor FROM configuracion WHERE clave = 'correo_limite_hora'") !== '1', 'Rechaza un límite por hora fuera de rango');
$ha->logout();

$hm2 = new Http();
$hm2->login(...$MEDICO);
T::status($hm2->get('/configuracion/correo'), 403, 'Un médico no accede a la configuración del correo');
$hm2->logout();

// Recordatorios de la tarea programada: no dependen de que el usuario inicie sesión
$cons = (int) Database::value("SELECT c.id FROM consultas c JOIN pacientes p ON p.id = c.paciente_id WHERE c.estado = 'programada' AND p.usuario_id IS NOT NULL ORDER BY c.fecha_hora LIMIT 1");
Database::run('UPDATE consultas SET fecha_hora = (NOW() + INTERVAL 3 HOUR) WHERE id = ?', [$cons]);
$n = App\Domain\Notificaciones::recordatoriosProgramados();
T::ok($n >= 1 && count_table("SELECT COUNT(*) FROM notificaciones WHERE clave = ?", ['rec-c' . $cons]) >= 2, 'La tarea genera recordatorios para paciente y médico sin que entren al sistema');
$antes = count_table('SELECT COUNT(*) FROM correos_salientes');
App\Domain\Notificaciones::recordatoriosProgramados();
T::ok(count_table('SELECT COUNT(*) FROM correos_salientes') === $antes, 'Ejecutarla de nuevo no duplica recordatorios ni correos');
$salida = shell_exec('"' . PHP_BINARY . '" ' . escapeshellarg(__DIR__ . '/../../bin/tareas.php') . ' 2>&1');
T::ok(str_contains((string) $salida, 'Correos enviados'), 'bin/tareas.php se ejecuta desde la consola', trim((string) $salida));
