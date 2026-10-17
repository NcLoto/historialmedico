<?php
declare(strict_types=1);

use App\Core\Database;

$h = new Http();
$h->login(...$MEDICO);
$p101 = pid('SAN-000101');

T::group('Pacientes: búsqueda');
$r = $h->get('/pacientes/buscar?q=ramirez');
T::ok($r['status'] === 200 && str_contains($r['body'], 'Ramírez Soto') && !str_contains($r['body'], 'Gómez'), 'Busca por apellido (sin acentos exactos no requeridos)');
T::has($h->get('/pacientes/buscar?q=2691044550101'), 'Gómez Archila', 'Busca por documento de identidad');
T::has($h->get('/pacientes/buscar?q=SAN-000107'), 'Cifuentes', 'Busca por código de paciente');
T::has($h->get('/pacientes/buscar?q=carlos+ram'), 'Ramírez Soto', 'Busca por varias palabras');
T::has($h->get('/pacientes/buscar?q=zzzxxyy'), 'Sin resultados', 'Estado «sin resultados»');
T::has($h->get('/pacientes/buscar?q=' . urlencode("' OR 1=1 --")), 'Sin resultados', 'Inyección SQL en la búsqueda no devuelve datos');
T::has($h->get('/pacientes/buscar?q=%25'), 'Sin resultados', 'El comodín % se trata como texto');
$r = $h->get('/pacientes/buscar?q=' . urlencode('<script>alert(1)</script>'));
T::ok(!str_contains($r['body'], '<script>alert(1)</script>') && str_contains($r['body'], '&lt;script&gt;'), 'La búsqueda escapa HTML (XSS)');
T::has($h->get('/pacientes?q=ramirez'), 'Ramírez Soto', 'La página completa de pacientes también filtra');

T::group('Pacientes: alta, validación, edición');
$base = ['nombres' => 'Luisa Fernanda', 'apellidos' => 'Aguilar Choc', 'documento_identidad' => '3101220350101', 'fecha_nacimiento' => '1990-04-12', 'sexo' => 'F',
         'tipo_sangre' => 'O+', 'telefono' => '5544-1020', 'email' => 'luisa.aguilar@correo.test', 'direccion' => 'Zona 5, Guatemala'];
$antes = count_table('SELECT COUNT(*) FROM pacientes');
$r = $h->submit('/pacientes/nuevo', '/pacientes', ['nombres' => '', 'sexo' => 'X', 'fecha_nacimiento' => '2999-01-01'] + $base);
T::ok($r['status'] === 302 && str_contains((string) $r['location'], '/pacientes/nuevo'), 'Datos inválidos regresan al formulario');
$form = $h->follow('/pacientes/nuevo');
T::has($form, 'Nombres es obligatorio', 'Muestra error en nombres');
T::ok(count_table('SELECT COUNT(*) FROM pacientes') === $antes, 'No se guarda nada con datos inválidos');
$r = $h->submit('/pacientes/nuevo', '/pacientes', ['documento_identidad' => '2584130210101'] + $base);
T::has($h->follow('/pacientes/nuevo'), 'Ya existe un paciente con ese documento', 'Rechaza documento duplicado');
$r = $h->submit('/pacientes/nuevo', '/pacientes', $base);
T::ok($r['status'] === 302 && preg_match('#/pacientes/(\d+)$#', (string) $r['location'], $m), 'Registra paciente válido');
$nuevoId = (int) ($m[1] ?? 0);
$row = Database::one('SELECT * FROM pacientes WHERE id = ?', [$nuevoId]);
T::ok($row && $row['codigo'] === 'SAN-000111', 'Genera código consecutivo SAN-000111', (string) ($row['codigo'] ?? ''));
T::ok(count_table("SELECT COUNT(*) FROM auditoria WHERE accion='crear' AND modulo='pacientes' AND paciente_id = ?", [$nuevoId]) === 1, 'El alta queda en auditoría');
T::has($h->follow("/pacientes/$nuevoId"), 'Aguilar Choc', 'La ficha muestra al nuevo paciente');
T::has($h->follow("/pacientes/$nuevoId"), 'Sin alergias registradas', 'Ficha vacía muestra mensajes de estado vacío');
$r = $h->submit("/pacientes/$nuevoId/editar", "/pacientes/$nuevoId", ['telefono' => '5599-0000', 'ocupacion' => 'Arquitecta'] + $base);
T::ok($r['status'] === 302, 'Edita al paciente');
T::ok(Database::value('SELECT telefono FROM pacientes WHERE id = ?', [$nuevoId]) === '5599-0000', 'El cambio persiste en MySQL');
$aud = Database::one("SELECT descripcion, detalle FROM auditoria WHERE accion='editar' AND modulo='pacientes' AND paciente_id = ? ORDER BY id DESC LIMIT 1", [$nuevoId]);
T::ok($aud && str_contains($aud['descripcion'], 'telefono') && !str_contains((string) $aud['detalle'], '5599-0000'), 'La auditoría registra el campo editado sin copiar su valor');
$r = $h->submit("/pacientes/$nuevoId/editar", "/pacientes/$nuevoId", ['nombres' => '<b>X</b>'] + $base);
$h->follow("/pacientes/$nuevoId");
T::ok(!str_contains($h->follow("/pacientes/$nuevoId")['body'], '<b>X</b>'), 'Un nombre con HTML se muestra escapado');
$h->logout();

T::group('Pacientes: inactivar y reactivar (administrador)');
$ha = new Http();
$ha->login(...$ADMIN);
$ha->submit("/pacientes/$nuevoId", "/pacientes/$nuevoId/estado");
T::ok(count_table('SELECT activo FROM pacientes WHERE id = ?', [$nuevoId]) === 0, 'Inactivar paciente (no se borra)');
T::ok(count_table('SELECT COUNT(*) FROM pacientes WHERE id = ?', [$nuevoId]) === 1, 'El registro se conserva');
T::hasNot($ha->get('/pacientes/buscar?q=Aguilar+Choc'), 'Aguilar Choc,', 'No aparece en la lista de activos');
T::has($ha->get('/pacientes/buscar?q=Aguilar+Choc&estado=inactivos'), 'Aguilar Choc,', 'Aparece al filtrar inactivos');
$ha->submit("/pacientes/$nuevoId", "/pacientes/$nuevoId/estado");
T::ok(count_table('SELECT activo FROM pacientes WHERE id = ?', [$nuevoId]) === 1, 'Reactivar paciente');

T::group('Pacientes: alta con cuenta de acceso (administrador)');
$datos = ['nombres' => 'Tomás Andrés', 'apellidos' => 'Ixcoy Batz', 'documento_identidad' => '3322110090101', 'fecha_nacimiento' => '1985-09-09', 'sexo' => 'M', 'email' => 'tomas.ixcoy@correo.test', 'crear_cuenta' => '1'];
$r = $ha->submit('/pacientes/nuevo', '/pacientes', $datos);
preg_match('#/pacientes/(\d+)$#', (string) $r['location'], $m);
$tomasId = (int) ($m[1] ?? 0);
$page = $ha->follow("/pacientes/$tomasId");
preg_match('/Contraseña temporal de [^:]+: ([A-Za-z0-9]+)/', $page['body'], $mm);
$temporal = $mm[1] ?? '';
T::ok(strlen($temporal) >= 12, 'Se muestra una contraseña temporal una sola vez');
T::hasNot($ha->get("/pacientes/$tomasId"), $temporal, 'No se vuelve a mostrar la contraseña temporal');
T::ok(count_table("SELECT COUNT(*) FROM usuarios u JOIN pacientes p ON p.usuario_id = u.id WHERE p.id = ? AND u.debe_cambiar_clave = 1 AND u.rol_id = 3", [$tomasId]) === 1, 'La cuenta queda vinculada al paciente con clave temporal');
$ha->logout();
$ht = new Http();
$ht->login('tomas.ixcoy@correo.test', $temporal);
$r = $ht->get('/');
T::ok($r['status'] === 302 && str_contains((string) $r['location'], '/perfil'), 'El primer ingreso obliga a cambiar la contraseña');
T::ok($ht->get('/mi-historial')['status'] === 302 && str_contains((string) $ht->get('/mi-historial')['location'], '/perfil'), 'No puede usar el sistema hasta cambiarla');
$ht->submit('/perfil', '/perfil/clave', ['password_actual' => $temporal, 'password_nueva' => 'abc', 'password_confirmar' => 'abc']);
T::has($ht->follow('/perfil'), 'al menos 10 caracteres', 'Valida la política de contraseñas');
$nueva = 'ClaveNueva2026!';
$ht->submit('/perfil', '/perfil/clave', ['password_actual' => $temporal, 'password_nueva' => $nueva, 'password_confirmar' => $nueva]);
T::status($ht->get('/'), 200, 'Tras cambiarla accede normalmente');
T::has($ht->follow('/mi-historial'), 'Ixcoy Batz', 'Ve su propia ficha vacía');
$ht->logout();

T::group('Historial: ciclo completo en las 8 secciones');
$h = new Http();
$h->login(...$MEDICO);
$casos = [
    'antecedentes' => [['tipo' => 'patologico', 'descripcion' => 'Varicela en la infancia', 'fecha_aproximada' => '1995'], ['descripcion' => 'Varicela y sarampión'], 'descripcion'],
    'alergias' => [['agente' => 'Látex', 'tipo' => 'ambiental', 'severidad' => 'moderada', 'activa' => '1'], ['severidad' => 'grave'], 'severidad'],
    'enfermedades' => [['nombre' => 'Gastritis', 'estado' => 'activa', 'codigo_cie10' => 'K29.7'], ['estado' => 'controlada'], 'estado'],
    'cirugias' => [['procedimiento' => 'Apendicectomía', 'fecha' => '2015-03-02', 'centro' => 'Hospital Sanitas'], ['cirujano' => 'Dr. Prueba'], 'cirujano'],
    'medicamentos' => [['nombre' => 'Omeprazol', 'dosis' => '20 mg', 'via' => 'oral', 'activo' => '1'], ['dosis' => '40 mg'], 'dosis'],
    'vacunas' => [['vacuna' => 'Hepatitis B', 'fecha_aplicacion' => '2024-01-10', 'proxima_dosis' => '2024-07-10'], ['lote' => 'HB-0042'], 'lote'],
    'habitos' => [['tipo' => 'ejercicio', 'descripcion' => 'Corre 5 km', 'frecuencia' => '3 veces por semana'], ['frecuencia' => '4 veces por semana'], 'frecuencia'],
    'familiares' => [['parentesco' => 'madre', 'condicion' => 'Hipertensión', 'edad_inicio' => '50'], ['edad_inicio' => '48'], 'edad_inicio'],
];
$tabla = ['antecedentes' => 'antecedentes_medicos', 'alergias' => 'alergias', 'enfermedades' => 'enfermedades', 'cirugias' => 'cirugias', 'medicamentos' => 'medicamentos', 'vacunas' => 'vacunas', 'habitos' => 'habitos_salud', 'familiares' => 'antecedentes_familiares'];
$visible = ['antecedentes' => 'Varicela en la infancia', 'alergias' => 'Látex', 'enfermedades' => 'Gastritis', 'cirugias' => 'Apendicectomía', 'medicamentos' => 'Omeprazol', 'vacunas' => 'Hepatitis B', 'habitos' => 'Corre 5 km', 'familiares' => 'Hipertensión'];
foreach ($casos as $sec => [$crear, $cambiar, $campoCambio]) {
    $t = $tabla[$sec];
    $antes = count_table("SELECT COUNT(*) FROM `$t` WHERE paciente_id = ?", [$nuevoId]);
    $r = $h->submit("/pacientes/$nuevoId/historial/$sec/nuevo", "/pacientes/$nuevoId/historial/$sec", $crear);
    T::ok($r['status'] === 302 && str_contains((string) $r['location'], "/pacientes/$nuevoId"), "[$sec] crear");
    $id = (int) Database::value("SELECT MAX(id) FROM `$t` WHERE paciente_id = ?", [$nuevoId]);
    T::ok(count_table("SELECT COUNT(*) FROM `$t` WHERE paciente_id = ?", [$nuevoId]) === $antes + 1, "[$sec] persiste en MySQL");
    T::ok((int) Database::value("SELECT registrado_por FROM `$t` WHERE id = ?", [$id]) === (int) Database::value('SELECT id FROM usuarios WHERE email = ?', [$MEDICO[0]]), "[$sec] guarda quién lo registró");
    T::has($h->follow("/pacientes/$nuevoId"), $visible[$sec], "[$sec] aparece en la ficha");
    $r = $h->submit("/pacientes/$nuevoId/historial/$sec/$id/editar", "/pacientes/$nuevoId/historial/$sec/$id", $cambiar + $crear);
    T::ok($r['status'] === 302, "[$sec] editar");
    T::ok((string) Database::value("SELECT `$campoCambio` FROM `$t` WHERE id = ?", [$id]) === (string) $cambiar[$campoCambio], "[$sec] el cambio persiste");
    T::ok(count_table("SELECT COUNT(*) FROM auditoria WHERE modulo='historial' AND accion='editar' AND registro_id = ? AND paciente_id = ?", [$id, $nuevoId]) >= 1, "[$sec] edición auditada");
}

T::group('Historial: validaciones e integridad');
$antes = count_table('SELECT COUNT(*) FROM alergias WHERE paciente_id = ?', [$nuevoId]);
$h->submit("/pacientes/$nuevoId/historial/alergias/nuevo", "/pacientes/$nuevoId/historial/alergias", ['agente' => '', 'tipo' => 'x', 'severidad' => 'extrema']);
$f = $h->follow("/pacientes/$nuevoId/historial/alergias/nuevo");
T::has($f, 'Agente (qué provoca la alergia) es obligatorio', 'Alergia sin agente: mensaje de error');
T::ok(count_table('SELECT COUNT(*) FROM alergias WHERE paciente_id = ?', [$nuevoId]) === $antes, 'No se crea el registro inválido');
$h->submit("/pacientes/$nuevoId/historial/cirugias/nuevo", "/pacientes/$nuevoId/historial/cirugias", ['procedimiento' => 'X', 'fecha' => '2999-12-31']);
T::has($h->follow("/pacientes/$nuevoId/historial/cirugias/nuevo"), 'no puede ser una fecha futura', 'Cirugía con fecha futura rechazada');
$h->submit("/pacientes/$nuevoId/historial/vacunas/nuevo", "/pacientes/$nuevoId/historial/vacunas", ['vacuna' => 'X', 'fecha_aplicacion' => '2024-05-01', 'proxima_dosis' => '2024-01-01']);
T::has($h->follow("/pacientes/$nuevoId/historial/vacunas/nuevo"), 'no puede ser anterior', 'Próxima dosis anterior a la aplicación rechazada');
$h->submit("/pacientes/$nuevoId/historial/familiares/nuevo", "/pacientes/$nuevoId/historial/familiares", ['parentesco' => 'madre', 'condicion' => 'X', 'edad_inicio' => '300']);
T::has($h->follow("/pacientes/$nuevoId/historial/familiares/nuevo"), 'entre 0 y 120', 'Edad fuera de rango rechazada');
$h->submit("/pacientes/$nuevoId/historial/alergias/nuevo", "/pacientes/$nuevoId/historial/alergias", ['agente' => '<img src=x onerror=alert(1)>', 'tipo' => 'otra', 'severidad' => 'leve']);
$ficha = $h->follow("/pacientes/$nuevoId")['body'];
T::ok(!str_contains($ficha, '<img src=x') && str_contains($ficha, '&lt;img src=x'), 'XSS almacenado: el HTML se muestra escapado');
// Un registro solo se puede editar a través del paciente al que pertenece
$otroId = (int) Database::value('SELECT MAX(id) FROM alergias WHERE paciente_id = ?', [$nuevoId]);
T::status($h->get("/pacientes/$p101/historial/alergias/$otroId/editar"), 404, 'No se puede editar un registro usando el paciente equivocado');
$h->submit("/pacientes/$p101/historial/alergias/$otroId/editar", "/pacientes/$p101/historial/alergias/$otroId", ['agente' => 'Hackeado', 'tipo' => 'otra', 'severidad' => 'leve']);
T::ok(Database::value('SELECT agente FROM alergias WHERE id = ?', [$otroId]) !== 'Hackeado', 'El POST cruzado entre pacientes no modifica nada');
// Borrado
$antes = count_table('SELECT COUNT(*) FROM alergias WHERE paciente_id = ?', [$nuevoId]);
$h->submit("/pacientes/$nuevoId", "/pacientes/$nuevoId/historial/alergias/$otroId/eliminar");
T::ok(count_table('SELECT COUNT(*) FROM alergias WHERE paciente_id = ?', [$nuevoId]) === $antes - 1, 'Eliminar un registro del historial');
T::ok(count_table("SELECT COUNT(*) FROM auditoria WHERE accion='eliminar' AND modulo='historial' AND registro_id = ?", [$otroId]) === 1, 'La eliminación queda auditada');
$e = null;
try {
    Database::run('DELETE FROM pacientes WHERE id = ?', [$nuevoId]);
} catch (PDOException $e) {
}
T::ok($e !== null && count_table('SELECT COUNT(*) FROM pacientes WHERE id = ?', [$nuevoId]) === 1, 'MySQL impide borrar un paciente con historial (FK RESTRICT)');
$e = null;
try {
    Database::run("INSERT INTO alergias (paciente_id, agente, tipo, severidad) VALUES (999999, 'x', 'otra', 'leve')");
} catch (PDOException $e) {
}
T::ok($e !== null, 'MySQL impide registros huérfanos (paciente inexistente)');
$h->logout();
