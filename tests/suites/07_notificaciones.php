<?php
declare(strict_types=1);

use App\Core\Database;

$p101 = pid('SAN-000101');
$p102 = pid('SAN-000102');
$uid = fn(string $email) => (int) Database::value('SELECT id FROM usuarios WHERE email = ?', [$email]);
$notifs = fn(string $email, string $tipo = '') => count_table('SELECT COUNT(*) FROM notificaciones WHERE usuario_id = ?' . ($tipo ? ' AND tipo = ?' : ''), $tipo ? [$uid($email), $tipo] : [$uid($email)]);
$sinLeer = fn(string $email) => count_table('SELECT COUNT(*) FROM notificaciones WHERE usuario_id = ? AND leida_en IS NULL', [$uid($email)]);

T::group('Notificaciones: campana, contador y página');
$hp = new Http();
$hp->login(...$PACIENTE);
$n = $sinLeer($PACIENTE[0]);
$home = $hp->get('/');
T::ok($n > 0 && str_contains($home['body'], 'data-bell-badge>' . $n . '<'), "La campana muestra el contador real ($n sin leer)");
T::ok(str_contains($home['body'], '<title>(' . $n . ')'), 'El título de la pestaña indica las no leídas');
T::has($home, 'id="notif-pop"', 'El panel desplegable de notificaciones está en la página');
$r = $hp->get('/notificaciones/contador');
T::ok($r['status'] === 200 && json_decode($r['body'], true) === ['no_leidas' => $n], 'El contador JSON coincide con MySQL');
$r = $hp->get('/notificaciones');
T::ok($r['status'] === 200 && str_contains($r['body'], 'Nuevo horario de atención en laboratorio'), 'La página lista las notificaciones (incluye el aviso de demostración)');
T::has($hp->get('/notificaciones?ver=no_leidas'), 'Sin leer', 'Filtro de no leídas');
$main = explode('id="contenido"', $hp->get('/notificaciones?tipo=aviso')['body'])[1] ?? '';
T::ok(str_contains($main, 'Nuevo horario') && !str_contains($main, 'Nuevo documento en su historial'), 'Filtro por tipo');

T::group('Notificaciones: lectura, propiedad y destinos');
$id = (int) Database::value("SELECT id FROM notificaciones WHERE usuario_id = ? AND tipo = 'consulta_programada' ORDER BY id LIMIT 1", [$uid($PACIENTE[0])]);
$r = $hp->get("/notificaciones/$id");
T::ok($r['status'] === 302 && str_contains((string) $r['location'], '/consultas/'), 'Abrir una notificación lleva a su destino');
T::ok(Database::value('SELECT leida_en FROM notificaciones WHERE id = ?', [$id]) !== null, 'Y la marca como leída');
$hp->submit('/notificaciones', "/notificaciones/$id/estado", ['volver' => '/notificaciones']);
T::ok(Database::value('SELECT leida_en FROM notificaciones WHERE id = ?', [$id]) === null, 'Se puede volver a marcar como no leída');
$ajena = (int) Database::value('SELECT id FROM notificaciones WHERE usuario_id = ? ORDER BY id LIMIT 1', [$uid($MEDICO[0])]);
T::status($hp->get("/notificaciones/$ajena"), 404, 'No se puede abrir la notificación de otro usuario');
T::status($hp->submit('/notificaciones', "/notificaciones/$ajena/eliminar"), 404, 'Ni eliminarla');
T::ok(count_table('SELECT COUNT(*) FROM notificaciones WHERE id = ?', [$ajena]) === 1, 'La notificación ajena sigue intacta');
Database::run("INSERT INTO notificaciones (usuario_id, tipo, titulo, enlace) VALUES (?, 'aviso', 'Enlace externo', 'https://evil.test/x')", [$uid($PACIENTE[0])]);
$ext = (int) Database::value('SELECT MAX(id) FROM notificaciones');
$r = $hp->get("/notificaciones/$ext");
T::ok(!str_contains((string) $r['location'], 'evil.test'), 'Un enlace externo nunca se usa como destino (sin redirección abierta)');
$hp->submit('/notificaciones', '/notificaciones/leer', ['volver' => '/notificaciones']);
T::ok($sinLeer($PACIENTE[0]) === 0, 'Marcar todas como leídas');
$antes = $notifs($PACIENTE[0]);
$hp->submit('/notificaciones', '/notificaciones/limpiar');
T::ok($notifs($PACIENTE[0]) === 0 && $antes > 0, 'Eliminar las leídas');
$hp->logout();

T::group('Notificaciones: eventos clínicos');
$hm = new Http();
$hm->login(...$MEDICO);
$antesM = $notifs($MEDICO[0]);
$antesP = $notifs($PACIENTE2[0], 'consulta_programada');
$r = $hm->submit("/pacientes/$p102/consultas/nueva", "/pacientes/$p102/consultas", ['fecha_hora' => date('Y-m-d\TH:i', strtotime('+1 day')), 'estado' => 'programada', 'motivo' => 'Control de espirometría']);
preg_match('#/consultas/(\d+)$#', (string) $r['location'], $m);
$prog = (int) ($m[1] ?? 0);
T::ok($notifs($PACIENTE2[0], 'consulta_programada') === $antesP + 1, 'Programar una consulta notifica al paciente');
T::ok(Database::value("SELECT titulo FROM notificaciones WHERE usuario_id = ? AND tipo = 'consulta_programada' ORDER BY id DESC LIMIT 1", [$uid($PACIENTE2[0])]) === 'Consulta programada para el ' . date('d/m/Y H:i', strtotime('+1 day')), 'El título indica fecha y hora');
T::ok($notifs($MEDICO[0]) === $antesM, 'Quien realiza la acción no se notifica a sí mismo');

// Recordatorio automático (consulta dentro de las próximas 48 h)
$hp2 = new Http();
$hp2->login(...$PACIENTE2);
$hp2->get('/');
T::ok(count_table('SELECT COUNT(*) FROM notificaciones WHERE usuario_id = ? AND clave = ?', [$uid($PACIENTE2[0]), 'rec-c' . $prog]) === 1, 'Se genera el recordatorio de la consulta próxima');
$hp2b = new Http();
$hp2b->login(...$PACIENTE2);
$hp2b->get('/');
T::ok(count_table('SELECT COUNT(*) FROM notificaciones WHERE usuario_id = ? AND clave = ?', [$uid($PACIENTE2[0]), 'rec-c' . $prog]) === 1, 'El recordatorio no se duplica');
$hp2b->logout();

// Cancelación
$antesC = $notifs($PACIENTE2[0], 'consulta_cancelada');
$hm->submit("/consultas/$prog", "/consultas/$prog/estado");
T::ok($notifs($PACIENTE2[0], 'consulta_cancelada') === $antesC + 1, 'Cancelar una consulta notifica al paciente');

// Documento nuevo
$png = sys_get_temp_dir() . '/notif.png';
imagepng(imagecreatetruecolor(20, 20), $png);
$antesD = $notifs($PACIENTE2[0], 'documento_nuevo');
$hm->submit("/pacientes/$p102/documentos/nuevo", "/pacientes/$p102/documentos", ['categoria_id' => '4', 'titulo' => 'Radiografía de seguimiento'], ['archivo' => [$png, 'image/png', 'rx.png']]);
T::ok($notifs($PACIENTE2[0], 'documento_nuevo') === $antesD + 1, 'Adjuntar un documento notifica al paciente');
$hm->logout();

// Alergia grave registrada por otro médico → avisa al médico que atenderá al paciente
$antesA = $notifs($MEDICO[0], 'alergia_grave');
$h2 = new Http();
$h2->login(...$MEDICO2);
$h2->submit("/pacientes/$p101/historial/alergias/nuevo", "/pacientes/$p101/historial/alergias", ['agente' => 'Ceftriaxona', 'tipo' => 'medicamento', 'severidad' => 'grave', 'activa' => '1', 'reaccion' => 'Anafilaxia']);
T::ok($notifs($MEDICO[0], 'alergia_grave') === $antesA + 1, 'Una alergia grave avisa al médico con consulta programada');
T::ok(Database::value("SELECT nivel FROM notificaciones WHERE usuario_id = ? AND tipo = 'alergia_grave' ORDER BY id DESC LIMIT 1", [$uid($MEDICO[0])]) === 'critico', 'Con nivel «Importante»');
$h2->submit("/pacientes/$p101/historial/alergias/nuevo", "/pacientes/$p101/historial/alergias", ['agente' => 'Polen', 'tipo' => 'ambiental', 'severidad' => 'leve', 'activa' => '1']);
T::ok($notifs($MEDICO[0], 'alergia_grave') === $antesA + 1, 'Una alergia leve no genera aviso');
$h2->logout();

T::group('Notificaciones: preferencias y configuración');
$hp2 = new Http();
$hp2->login(...$PACIENTE2);
T::has($hp2->get('/perfil'), 'Guardar preferencias', 'El perfil muestra las preferencias de notificación');
$hp2->submit('/perfil', '/perfil/notificaciones', ['tipos' => ['consulta_registrada', 'documento_nuevo']]);
T::ok((int) Database::value("SELECT activo FROM notificacion_preferencias WHERE usuario_id = ? AND tipo = 'consulta_programada'", [$uid($PACIENTE2[0])]) === 0, 'El paciente silencia un tipo de aviso');
$hm = new Http();
$hm->login(...$MEDICO);
$antesP = $notifs($PACIENTE2[0], 'consulta_programada');
$hm->submit("/pacientes/$p102/consultas/nueva", "/pacientes/$p102/consultas", ['fecha_hora' => date('Y-m-d\TH:i', strtotime('+20 days')), 'estado' => 'programada', 'motivo' => 'Revisión anual']);
T::ok($notifs($PACIENTE2[0], 'consulta_programada') === $antesP, 'Un tipo silenciado no llega');
$hp2->submit('/perfil', '/perfil/notificaciones', ['tipos' => array_keys(\App\Domain\Notificaciones::tiposDe('paciente'))]);

$ha = new Http();
$ha->login(...$ADMIN);
T::status($ha->get('/configuracion/notificaciones'), 200, 'El administrador abre Configuración → Notificaciones');
$todos = array_keys(\App\Domain\Notificaciones::TIPOS);
$ha->submit('/configuracion/notificaciones', '/configuracion/notificaciones', ['notif_activas' => '1', 'tipos_activos' => array_values(array_diff($todos, ['documento_nuevo'])), 'notif_recordatorio_horas' => '48', 'notif_retencion_dias' => '90']);
T::ok(Database::value("SELECT valor FROM configuracion WHERE clave = 'notif_tipos_off'") === 'documento_nuevo', 'Desactiva un evento para todo el hospital');
$antesD = $notifs($PACIENTE2[0], 'documento_nuevo');
$hm->submit("/pacientes/$p102/documentos/nuevo", "/pacientes/$p102/documentos", ['categoria_id' => '4', 'titulo' => 'Otra radiografía'], ['archivo' => [$png, 'image/png', 'rx2.png']]);
T::ok($notifs($PACIENTE2[0], 'documento_nuevo') === $antesD, 'Un evento desactivado globalmente no notifica');
T::has($hp2->get('/perfil'), 'Desactivado por la administración del hospital', 'El paciente ve que el evento lo desactivó la administración');
$ha->submit('/configuracion/notificaciones', '/configuracion/notificaciones', ['tipos_activos' => $todos, 'notif_recordatorio_horas' => '48', 'notif_retencion_dias' => '90']);
T::ok(Database::value("SELECT valor FROM configuracion WHERE clave = 'notif_activas'") === '0', 'Apagar todas las notificaciones');
$antesD = $notifs($PACIENTE2[0]);
$hm->submit("/pacientes/$p102/documentos/nuevo", "/pacientes/$p102/documentos", ['categoria_id' => '4', 'titulo' => 'Tercera radiografía'], ['archivo' => [$png, 'image/png', 'rx3.png']]);
T::ok($notifs($PACIENTE2[0]) === $antesD, 'Con las notificaciones apagadas no se genera ninguna');
$ha->submit('/configuracion/notificaciones', '/configuracion/notificaciones', ['notif_activas' => '1', 'tipos_activos' => $todos, 'notif_recordatorio_horas' => '500', 'notif_retencion_dias' => '90']);
T::has($ha->follow('/configuracion/notificaciones'), 'entre 1 y 168', 'Valida la anticipación de los recordatorios');
$ha->submit('/configuracion/notificaciones', '/configuracion/notificaciones', ['notif_activas' => '1', 'tipos_activos' => $todos, 'notif_recordatorio_horas' => '48', 'notif_retencion_dias' => '90']);
T::ok(Database::value("SELECT valor FROM configuracion WHERE clave = 'notif_activas'") === '1', 'Se vuelven a activar');
T::status($hm->submit('/perfil', '/configuracion/notificaciones', ['notif_activas' => '0']), 403, 'El médico no cambia la configuración de notificaciones');
$hm->logout();
$hp2->logout();
@unlink($png);

T::group('Notificaciones: avisos institucionales y seguridad');
$pacientesActivos = count_table("SELECT COUNT(*) FROM usuarios u JOIN roles r ON r.id = u.rol_id WHERE r.codigo = 'paciente' AND u.activo = 1");
$antesAv = count_table("SELECT COUNT(*) FROM notificaciones WHERE tipo = 'aviso'");
$ha->submit('/configuracion/notificaciones', '/notificaciones/aviso', ['titulo' => 'Mantenimiento <b>programado</b>', 'mensaje' => 'El sábado de 6:00 a 8:00 el sistema no estará disponible.', 'nivel' => 'aviso', 'roles' => ['paciente']]);
T::ok(count_table("SELECT COUNT(*) FROM notificaciones WHERE tipo = 'aviso'") === $antesAv + $pacientesActivos, "El aviso llega a los $pacientesActivos pacientes activos");
T::ok(count_table("SELECT COUNT(*) FROM notificaciones n JOIN usuarios u ON u.id = n.usuario_id WHERE n.titulo LIKE 'Mantenimiento%' AND u.rol_id <> 3") === 0, 'Y a nadie más');
T::ok(count_table("SELECT COUNT(*) FROM auditoria WHERE modulo = 'configuracion' AND descripcion LIKE 'Aviso institucional%'") === 1, 'El envío queda en la auditoría');
$hp = new Http();
$hp->login(...$PACIENTE);
$page = $hp->get('/notificaciones')['body'];
T::ok(str_contains($page, 'Mantenimiento &lt;b&gt;programado&lt;/b&gt;') && !str_contains($page, '<b>programado</b>'), 'El título del aviso se muestra escapado');
$hp->logout();
$ha->submit('/configuracion/notificaciones', '/notificaciones/aviso', ['titulo' => 'Sin destinatarios', 'nivel' => 'info']);
T::has($ha->follow('/configuracion/notificaciones'), 'Seleccione al menos un destinatario', 'Exige destinatarios');
$hm = new Http();
$hm->login(...$MEDICO);
T::status($hm->submit('/notificaciones', '/notificaciones/aviso', ['titulo' => 'X', 'nivel' => 'info', 'roles' => ['paciente']]), 403, 'Un médico no puede enviar avisos');
$hm->logout();

$antesS = $notifs($ADMIN[0], 'seguridad');
$hx = new Http();
for ($i = 0; $i < 5; $i++) {
    $hx->login('marta.estrada@sanitas.local', 'clave-equivocada-' . $i);
}
T::ok($notifs($ADMIN[0], 'seguridad') === $antesS + 1, 'Bloquear una cuenta por intentos fallidos avisa a los administradores');
Database::run("UPDATE usuarios SET bloqueado_hasta = NULL, intentos_fallidos = 0 WHERE email = 'marta.estrada@sanitas.local'");
$ha->logout();
