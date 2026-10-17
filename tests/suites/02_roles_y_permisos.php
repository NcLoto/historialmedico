<?php
declare(strict_types=1);

use App\Core\Database;

/** Cada rol recorre sus pantallas (200, sin errores de PHP) y se prueban accesos no autorizados (403). */

$p101 = pid('SAN-000101');   // paciente con cuenta (Carlos)
$p102 = pid('SAN-000102');   // paciente con cuenta (María José)
$p105 = pid('SAN-000105');   // paciente sin cuenta
$cons101 = (int) Database::value('SELECT id FROM consultas WHERE paciente_id = ? ORDER BY id LIMIT 1', [$p101]);
$cons102 = (int) Database::value('SELECT id FROM consultas WHERE paciente_id = ? ORDER BY id LIMIT 1', [$p102]);
$doc101 = (int) Database::value('SELECT id FROM documentos_medicos WHERE paciente_id = ? ORDER BY id LIMIT 1', [$p101]);
$doc102 = (int) Database::value('SELECT id FROM documentos_medicos WHERE paciente_id = ? ORDER BY id LIMIT 1', [$p102]);

function clean_page(array $r): bool
{
    return !preg_match('/\b(Warning|Notice|Deprecated|Fatal error)\b:|Stack trace|Undefined (variable|array key|index)/', $r['body']);
}

// ---------------------------------------------------------------- Administrador
T::group('Administrador: pantallas');
$h = new Http();
$h->login(...$ADMIN);
foreach (['/', '/pacientes', "/pacientes/$p101", '/pacientes/nuevo', "/pacientes/$p101/editar", '/medicos', '/medicos/nuevo', '/especialidades',
          '/usuarios', '/usuarios/nuevo', '/roles', '/roles/1', '/roles/2', '/roles/3', '/documentos', "/documentos/$doc101", '/auditoria', '/configuracion', '/perfil', '/pacientes/buscar?q=ram'] as $path) {
    $r = $h->get($path);
    T::ok($r['status'] === 200 && clean_page($r), "GET $path", 'HTTP ' . $r['status']);
}
T::hasNot($h->get("/pacientes/$p101"), 'Penicilina', 'La ficha del administrador no muestra datos clínicos (alergias)');
T::has($h->get("/pacientes/$p101"), 'Vista administrativa', 'La ficha del administrador explica la restricción');
T::hasNot($h->get("/pacientes"), 'Última consulta', 'El listado del administrador no expone datos clínicos');

T::group('Administrador: accesos denegados');
foreach (["/pacientes/$p101/historial/alergias/nuevo", "/pacientes/$p101/consultas/nueva", "/consultas", "/consultas/$cons101", "/documentos/$doc101/archivo", "/pacientes/$p101/documentos/nuevo", '/mi-historial'] as $path) {
    T::status($h->get($path), 403, "Administrador no puede acceder a $path");
}
$h->logout();

// ---------------------------------------------------------------- Médico
T::group('Médico: pantallas');
$h = new Http();
$h->login(...$MEDICO);
foreach (['/', '/pacientes', "/pacientes/$p101", "/pacientes/$p105", '/pacientes/nuevo', "/pacientes/$p101/editar", "/pacientes/$p101/historial/alergias/nuevo",
          "/pacientes/$p101/consultas/nueva", "/pacientes/$p101/documentos/nuevo", '/consultas', "/consultas?paciente=$p101", "/consultas/$cons101", "/consultas/$cons101/editar",
          '/documentos', "/documentos/$doc101", "/documentos/$doc101/archivo", '/perfil', '/pacientes/buscar?q=gom'] as $path) {
    $r = $h->get($path);
    T::ok($r['status'] === 200 && clean_page($r), "GET $path", 'HTTP ' . $r['status']);
}
T::has($h->get("/pacientes/$p101"), 'Penicilina', 'La ficha del médico muestra alergias');
T::has($h->get("/pacientes/$p101"), 'Historial de cambios', 'La ficha del médico muestra el historial de cambios');
$secciones = ['antecedentes', 'alergias', 'enfermedades', 'cirugias', 'medicamentos', 'vacunas', 'habitos', 'familiares'];
foreach ($secciones as $s) {
    $r = $h->get("/pacientes/$p101/historial/$s/nuevo");
    T::ok($r['status'] === 200 && clean_page($r), "Formulario de sección $s");
}
T::status($h->get("/pacientes/$p101/historial/inexistente/nuevo"), 404, 'Sección inexistente devuelve 404');
T::status($h->get('/pacientes/999999'), 404, 'Paciente inexistente devuelve 404');

T::group('Médico: accesos denegados');
foreach (['/medicos', '/medicos/nuevo', '/usuarios', '/usuarios/nuevo', '/roles', '/roles/1', '/auditoria', '/configuracion', '/especialidades', '/mi-historial'] as $path) {
    T::status($h->get($path), 403, "Médico no puede acceder a $path");
}
$h->logout();

// ---------------------------------------------------------------- Paciente
T::group('Paciente: pantallas propias');
$h = new Http();
$h->login(...$PACIENTE);
T::ok($h->get('/mi-historial')['status'] === 302, 'GET /mi-historial redirige a la ficha propia');
foreach (['/', "/pacientes/$p101", '/consultas', "/consultas/$cons101", '/documentos', "/documentos/$doc101", "/documentos/$doc101/archivo", '/perfil'] as $path) {
    $r = $h->get($path);
    T::ok($r['status'] === 200 && clean_page($r), "GET $path", 'HTTP ' . $r['status']);
}
$ficha = $h->get("/pacientes/$p101");
T::has($ficha, 'Penicilina', 'El paciente ve su propio historial');
T::hasNot($ficha, '/historial/alergias/nuevo', 'El paciente no ve botones de edición del historial');
T::hasNot($ficha, 'Historial de cambios', 'El paciente no ve el historial de cambios del personal');
T::hasNot($ficha, 'Nueva consulta', 'El paciente no ve el botón Nueva consulta');
T::hasNot($h->get("/consultas/$cons101"), 'Notas internas', 'El paciente no ve las notas internas de la consulta');
T::has($h->follow('/mi-historial'), 'Ramírez Soto', 'Mi historial lleva a su propia ficha');

T::group('Paciente: accesos denegados (autorización por registro y por rol)');
T::status($h->get("/pacientes/$p102"), 403, 'No puede ver la ficha de otro paciente');
T::status($h->get("/pacientes/$p105"), 403, 'No puede ver la ficha de un paciente sin cuenta');
T::status($h->get('/pacientes/999999'), 403, 'ID inexistente también responde 403 (no revela qué IDs existen)');
T::status($h->get("/consultas/$cons102"), 403, 'No puede ver la consulta de otro paciente');
T::status($h->get("/documentos/$doc102"), 403, 'No puede ver los datos de un documento ajeno');
T::status($h->get("/documentos/$doc102/archivo"), 403, 'No puede descargar un documento ajeno conociendo su URL');
T::status($h->get("/pacientes/$p102/editar"), 403, 'No puede editar otro paciente');
T::status($h->get("/pacientes/$p101/editar"), 403, 'No puede editar ni su propia ficha');
T::status($h->get('/pacientes'), 403, 'No puede listar pacientes');
T::status($h->get('/pacientes/buscar?q=a'), 403, 'No puede usar la búsqueda de pacientes');
T::status($h->get('/pacientes/nuevo'), 403, 'No puede registrar pacientes');
T::status($h->get("/pacientes/$p101/historial/alergias/nuevo"), 403, 'No puede agregar a su historial');
T::status($h->get("/pacientes/$p101/consultas/nueva"), 403, 'No puede crear consultas');
T::status($h->get("/pacientes/$p101/documentos/nuevo"), 403, 'No puede cargar documentos');
foreach (['/usuarios', '/medicos', '/roles', '/auditoria', '/configuracion', '/admin/pacientes/25'] as $path) {
    $r = $h->get($path);
    T::ok(in_array($r['status'], [403, 404], true), "Paciente no puede acceder a $path", 'HTTP ' . $r['status']);
}
$antes = count_table('SELECT COUNT(*) FROM alergias WHERE paciente_id = ?', [$p101]);
$r = $h->submit('/perfil', "/pacientes/$p101/historial/alergias", ['agente' => 'Intento', 'tipo' => 'otra', 'severidad' => 'leve']);
T::status($r, 403, 'POST de historial por paciente es rechazado');
T::ok(count_table('SELECT COUNT(*) FROM alergias WHERE paciente_id = ?', [$p101]) === $antes, 'No se creó ningún registro');
$r = $h->submit('/perfil', "/pacientes/$p101/estado");
T::status($r, 403, 'POST para inactivar paciente es rechazado');
T::ok(count_table('SELECT activo FROM pacientes WHERE id = ?', [$p101]) === 1, 'El paciente sigue activo');
T::ok(count_table("SELECT COUNT(*) FROM auditoria WHERE accion = 'acceso_denegado' AND usuario_nombre LIKE 'Carlos%'") >= 5, 'Los accesos denegados quedan auditados');
$h->logout();

T::group('Paciente 2 no ve datos del paciente 1');
$h = new Http();
$h->login(...$PACIENTE2);
T::status($h->get("/pacientes/$p101"), 403, 'María José no puede ver a Carlos');
T::hasNot($h->get('/documentos'), 'Perfil metabólico', 'Su listado no incluye documentos de otros');
T::hasNot($h->get('/consultas'), 'diabetes', 'Su listado de consultas no incluye las de otros');
$h->logout();

// ---------------------------------------------------------------- Sin sesión
T::group('Sin sesión');
$h = new Http();
foreach (["/documentos/$doc101/archivo", "/documentos/$doc101", '/auditoria', "/consultas/$cons101", '/usuarios'] as $path) {
    $r = $h->get($path);
    T::ok($r['status'] === 302 && str_contains((string) $r['location'], '/login'), "$path exige iniciar sesión");
}
foreach (['/app/Core/Auth.php', '/.env', '/database/schema.sql', '/storage/documentos_medicos/', '/config/routes.php', '/tests/run.php'] as $path) {
    T::status($h->get($path), 403, "El archivo interno $path no es accesible por web");
}
$r = $h->get('/css/app.css');
T::status($r, 200, 'Los recursos públicos (CSS) sí se sirven');
