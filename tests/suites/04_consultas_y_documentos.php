<?php
declare(strict_types=1);

use App\Core\Database;

$p101 = pid('SAN-000101');
$p102 = pid('SAN-000102');
$tmp = sys_get_temp_dir();

// Archivos de prueba
$pdf = "$tmp/prueba_lab.pdf";
file_put_contents($pdf, "%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF");
$png = "$tmp/prueba_rx.png";
$im = imagecreatetruecolor(40, 30);
imagepng($im, $png);
$falsoPdf = "$tmp/script.pdf";
file_put_contents($falsoPdf, '<?php echo "pwned"; ?>');
$php = "$tmp/shell.php";
file_put_contents($php, '<?php system($_GET["c"]); ?>');
$exe = "$tmp/programa.exe";
file_put_contents($exe, "MZ\x90\x00fake");
$grande = "$tmp/grande.pdf";
file_put_contents($grande, "%PDF-1.4\n" . str_repeat('A', 2 * 1048576));
$vacio = "$tmp/vacio.pdf";
file_put_contents($vacio, '');
$dobleExt = "$tmp/informe.php.png";
file_put_contents($dobleExt, '<?php echo 1; ?>');

// ---------------------------------------------------------------- Consultas
T::group('Consultas: flujo completo del médico');
$h = new Http();
$h->login(...$MEDICO);
$formUrl = "/pacientes/$p102/consultas/nueva";
$antesC = count_table('SELECT COUNT(*) FROM consultas WHERE paciente_id = ?', [$p102]);
$antesMed = count_table('SELECT COUNT(*) FROM medicamentos WHERE paciente_id = ?', [$p102]);
$antesAud = count_table("SELECT COUNT(*) FROM auditoria WHERE modulo IN ('consultas','documentos')");

// Sin diagnóstico → error
$r = $h->submit($formUrl, "/pacientes/$p102/consultas", ['fecha_hora' => date('Y-m-d\TH:i'), 'estado' => 'realizada', 'motivo' => 'Control de asma']);
T::has($h->follow($formUrl), 'requiere al menos un diagnóstico', 'Consulta realizada sin diagnóstico es rechazada');
T::ok(count_table('SELECT COUNT(*) FROM consultas WHERE paciente_id = ?', [$p102]) === $antesC, 'No se guarda la consulta incompleta');
// Valores fuera de rango
$h->submit($formUrl, "/pacientes/$p102/consultas", ['fecha_hora' => date('Y-m-d\TH:i'), 'estado' => 'realizada', 'motivo' => 'x', 'presion_sistolica' => '900', 'temperatura' => '80', 'diag_desc' => ['Asma']]);
$f = $h->follow($formUrl)['body'];
T::ok(str_contains($f, 'entre 40 y 300') && str_contains($f, 'entre 30 y 45'), 'Signos vitales fuera de rango son rechazados');
T::has(['body' => $f], 'value="Asma"', 'Al fallar, se conservan los diagnósticos ingresados');

// Consulta válida con examen adjunto
$r = $h->submit($formUrl, "/pacientes/$p102/consultas", [
    'fecha_hora' => date('Y-m-d\TH:i'), 'estado' => 'realizada', 'motivo' => 'Control de asma y tos nocturna',
    'enfermedad_actual' => 'Tos nocturna de una semana.', 'presion_sistolica' => '118', 'presion_diastolica' => '76', 'frecuencia_cardiaca' => '80',
    'temperatura' => '36.7', 'saturacion_o2' => '97', 'peso_kg' => '61.5', 'talla_cm' => '165', 'examen_fisico' => 'Sibilancias leves.',
    'plan' => 'Control en 4 semanas.', 'notas_internas' => 'Paciente ansiosa, reforzar educación.',
    'diag_tipo' => ['principal', 'secundario'], 'diag_codigo' => ['J45.0', ''], 'diag_desc' => ['Asma bronquial', 'Rinitis alérgica'],
    'trat_tipo' => ['medicamento', 'indicacion'], 'trat_desc' => ['Budesonida inhalada', 'Evitar polvo y humo'], 'trat_dosis' => ['200 mcg', ''], 'trat_frec' => ['Cada 12 horas', ''], 'trat_dur' => ['4 semanas', ''],
    'agregar_medicamentos' => '1', 'doc_categoria_id' => '4', 'doc_titulo' => 'Radiografía de tórax de control',
], ['archivo' => [$png, 'image/png', 'rx.png']]);
T::ok($r['status'] === 302 && preg_match('#/consultas/(\d+)$#', (string) $r['location'], $m), 'Registra la consulta y redirige al detalle', (string) $r['status'] . ' ' . $r['location']);
$cid = (int) ($m[1] ?? 0);
T::ok(count_table('SELECT COUNT(*) FROM consultas WHERE paciente_id = ?', [$p102]) === $antesC + 1, 'La consulta persiste en MySQL');
$c = Database::one('SELECT c.*, m.usuario_id FROM consultas c JOIN medicos m ON m.id = c.medico_id WHERE c.id = ?', [$cid]);
T::ok($c && (int) $c['usuario_id'] === (int) Database::value('SELECT id FROM usuarios WHERE email = ?', [$MEDICO[0]]), 'La consulta queda asignada al médico autenticado');
T::ok(count_table('SELECT COUNT(*) FROM diagnosticos WHERE consulta_id = ?', [$cid]) === 2, 'Guarda 2 diagnósticos relacionados');
T::ok(count_table('SELECT COUNT(*) FROM tratamientos WHERE consulta_id = ?', [$cid]) === 2, 'Guarda 2 tratamientos relacionados');
T::ok(count_table('SELECT COUNT(*) FROM medicamentos WHERE paciente_id = ? AND consulta_id = ?', [$p102, $cid]) === 1 && count_table('SELECT COUNT(*) FROM medicamentos WHERE paciente_id = ?', [$p102]) === $antesMed + 1, 'El medicamento indicado pasa a la lista de medicamentos vigentes');
T::ok(count_table('SELECT COUNT(*) FROM documentos_medicos WHERE consulta_id = ? AND paciente_id = ?', [$cid, $p102]) === 1, 'El examen adjunto queda ligado a la consulta y al paciente');
T::ok(count_table("SELECT COUNT(*) FROM auditoria WHERE modulo IN ('consultas','documentos')") === $antesAud + 2, 'Consulta y carga de documento quedan en auditoría');
$det = $h->get("/consultas/$cid");
T::ok($det['status'] === 200 && str_contains($det['body'], 'Asma bronquial') && str_contains($det['body'], 'Budesonida inhalada') && str_contains($det['body'], 'Notas internas'), 'El detalle muestra diagnósticos, tratamientos y notas internas al médico');
T::has($h->get("/pacientes/$p102"), 'Control de asma y tos nocturna', 'La consulta aparece en la ficha del paciente');
T::has($h->get('/consultas?q=asma'), 'Control de asma y tos nocturna', 'Aparece en el listado de consultas con búsqueda');
T::has($h->get("/consultas?paciente=$p102&mias=0"), 'Control de asma y tos nocturna', 'Filtro por paciente');

// Edición
$r = $h->submit("/consultas/$cid/editar", "/consultas/$cid", [
    'fecha_hora' => date('Y-m-d\TH:i'), 'estado' => 'realizada', 'motivo' => 'Control de asma (corregido)', 'diag_desc' => ['Asma bronquial'], 'diag_tipo' => ['principal'],
    'trat_desc' => ['Budesonida inhalada'], 'trat_tipo' => ['medicamento'], 'plan' => 'Nuevo plan',
]);
T::ok($r['status'] === 302, 'Edita la consulta');
T::ok(Database::value('SELECT motivo FROM consultas WHERE id = ?', [$cid]) === 'Control de asma (corregido)' && count_table('SELECT COUNT(*) FROM diagnosticos WHERE consulta_id = ?', [$cid]) === 1, 'Los cambios y los diagnósticos reemplazados persisten');
T::ok(count_table("SELECT COUNT(*) FROM auditoria WHERE modulo='consultas' AND accion='editar' AND registro_id = ?", [$cid]) === 1, 'La edición queda auditada');

// Programada y cancelada
$r = $h->submit($formUrl, "/pacientes/$p102/consultas", ['fecha_hora' => date('Y-m-d\TH:i', strtotime('+10 days')), 'estado' => 'programada', 'motivo' => 'Control de seguimiento']);
preg_match('#/consultas/(\d+)$#', (string) $r['location'], $m);
$prog = (int) ($m[1] ?? 0);
T::ok($prog > 0 && Database::value('SELECT estado FROM consultas WHERE id = ?', [$prog]) === 'programada', 'Programa una consulta futura (no exige diagnóstico)');
$h->submit("/consultas/$prog", "/consultas/$prog/estado");
T::ok(Database::value('SELECT estado FROM consultas WHERE id = ?', [$prog]) === 'cancelada', 'Cancela una consulta programada');
$h->submit($formUrl, "/pacientes/$p102/consultas", ['fecha_hora' => date('Y-m-d\TH:i', strtotime('-10 days')), 'estado' => 'programada', 'motivo' => 'En el pasado']);
T::has($h->follow($formUrl), 'debe tener fecha futura', 'Una consulta programada no puede estar en el pasado');

// Paciente inactivo / consulta de paciente inexistente
T::status($h->get('/pacientes/999999/consultas/nueva'), 404, 'Nueva consulta para paciente inexistente: 404');
$h->logout();

T::group('Consultas: visión del paciente');
$hp = new Http();
$hp->login(...$PACIENTE2);
$det = $hp->get("/consultas/$cid");
T::ok($det['status'] === 200 && str_contains($det['body'], 'Asma bronquial'), 'El paciente ve su consulta');
T::hasNot($det, 'Notas internas', 'No ve las notas internas del médico');
T::hasNot($det, 'ansiosa', 'El texto de las notas internas no se filtra');
T::has($hp->get('/consultas'), 'Control de asma', 'Mis consultas muestra su consulta');
T::has($hp->get('/'), 'Próximas consultas', 'El dashboard del paciente muestra próximas consultas');
$hp->logout();

T::group('Consultas: otro médico sin permiso de creación');
$h2 = new Http();
$h2->login(...$MEDICO2);
T::status($h2->get("/pacientes/$p102/consultas/nueva"), 200, 'Otro médico puede registrar consultas');
$h2->logout();

// ---------------------------------------------------------------- Documentos
T::group('Documentos: carga válida y validaciones');
$h = new Http();
$h->login(...$MEDICO);
$docForm = "/pacientes/$p101/documentos/nuevo";
$antes = count_table('SELECT COUNT(*) FROM documentos_medicos WHERE paciente_id = ?', [$p101]);
$base = ['categoria_id' => '2', 'titulo' => 'Química sanguínea octubre', 'fecha_documento' => date('Y-m-d'), 'descripcion' => 'Glucosa y lípidos'];

$r = $h->submit($docForm, "/pacientes/$p101/documentos", $base, ['archivo' => [$pdf, 'application/pdf', 'quimica.pdf']]);
T::ok($r['status'] === 302, 'Carga un PDF válido');
$doc = Database::one('SELECT * FROM documentos_medicos WHERE paciente_id = ? ORDER BY id DESC LIMIT 1', [$p101]);
T::ok($doc && $doc['nombre_original'] === 'quimica.pdf' && $doc['tipo_mime'] === 'application/pdf' && (int) $doc['tamano_bytes'] === filesize($pdf), 'Guarda metadatos: nombre original, tipo MIME y tamaño');
T::ok(preg_match('/^[0-9a-f-]{36}$/', $doc['nombre_interno']) === 1 && $doc['nombre_interno'] !== 'quimica.pdf', 'El nombre interno es aleatorio (UUID)');
T::ok(is_file(__DIR__ . '/../../storage/documentos_medicos/' . $doc['nombre_interno']), 'El archivo se guarda en storage/ (fuera de public/)');
T::ok($doc['sha256'] === hash_file('sha256', $pdf), 'Guarda la huella SHA-256');
T::ok((int) $doc['cargado_por'] === (int) Database::value('SELECT id FROM usuarios WHERE email = ?', [$MEDICO[0]]), 'Registra el usuario que cargó el documento');
$r = $h->submit($docForm, "/pacientes/$p101/documentos", ['titulo' => 'Rx rodilla', 'categoria_id' => '4'], ['archivo' => [$png, 'image/png', 'rx rodilla.png']]);
T::ok($r['status'] === 302, 'Carga una imagen PNG');
T::ok(count_table('SELECT COUNT(*) FROM documentos_medicos WHERE paciente_id = ?', [$p101]) === $antes + 2, 'Ambos documentos persisten');

$rechazos = [
    ['archivo .php', $php, 'application/x-php', 'shell.php', 'Tipo de archivo no permitido'],
    ['archivo .exe', $exe, 'application/octet-stream', 'programa.exe', 'Tipo de archivo no permitido'],
    ['doble extensión .php.png', $dobleExt, 'image/png', 'informe.php.png', 'no coincide con su extensión'],
    ['PDF falso (contenido PHP)', $falsoPdf, 'application/pdf', 'script.pdf', 'no coincide con su extensión'],
    ['archivo vacío', $vacio, 'application/pdf', 'vacio.pdf', 'vacío'],
];
foreach ($rechazos as [$nombre, $path, $mime, $fn, $msg]) {
    $n = count_table('SELECT COUNT(*) FROM documentos_medicos');
    $files = count(glob(__DIR__ . '/../../storage/documentos_medicos/*'));
    $h->submit($docForm, "/pacientes/$p101/documentos", $base, ['archivo' => [$path, $mime, $fn]]);
    T::has($h->follow($docForm), $msg, "Rechaza $nombre");
    T::ok(count_table('SELECT COUNT(*) FROM documentos_medicos') === $n && count(glob(__DIR__ . '/../../storage/documentos_medicos/*')) === $files, "  y no deja rastro de $nombre en BD ni en disco");
}
Database::run("UPDATE configuracion SET valor = '1' WHERE clave = 'archivo_max_mb'");
$h->submit($docForm, "/pacientes/$p101/documentos", $base, ['archivo' => [$grande, 'application/pdf', 'grande.pdf']]);
T::has($h->follow($docForm), 'supera el máximo de 1 MB', 'Rechaza archivos que superan el tamaño máximo configurado');
Database::run("UPDATE configuracion SET valor = '15' WHERE clave = 'archivo_max_mb'");
$h->submit($docForm, "/pacientes/$p101/documentos", ['titulo' => '', 'categoria_id' => '999'], ['archivo' => [$pdf, 'application/pdf', 'a.pdf']]);
$f = $h->follow($docForm)['body'];
T::ok(str_contains($f, 'Título es obligatorio') || str_contains($f, 'categoría válida'), 'Valida título y categoría');
$h->submit($docForm, "/pacientes/$p101/documentos", $base);
T::has($h->follow($docForm), 'Seleccione un archivo', 'Exige seleccionar un archivo');
$h->submit($docForm, "/pacientes/$p101/documentos", $base + ['consulta_id' => (string) $cid], ['archivo' => [$pdf, 'application/pdf', 'a.pdf']]);
T::has($h->follow($docForm), 'no corresponde a este paciente', 'No permite asociar la consulta de otro paciente');

T::group('Documentos: descarga protegida');
$docId = (int) $doc['id'];
$r = $h->get("/documentos/$docId/archivo");
T::ok($r['status'] === 200 && str_starts_with($r['body'], '%PDF'), 'El médico visualiza el archivo');
T::ok(($r['headers']['content-type'] ?? '') === 'application/pdf' && str_contains($r['headers']['content-disposition'] ?? '', 'inline'), 'Cabeceras correctas (Content-Type, inline)');
T::ok(($r['headers']['x-content-type-options'] ?? '') === 'nosniff', 'Envía nosniff');
T::ok(!str_contains($r['headers']['content-disposition'] ?? '', $doc['nombre_interno']), 'No expone el nombre interno');
$r = $h->get("/documentos/$docId/archivo?descargar=1");
T::ok(str_contains($r['headers']['content-disposition'] ?? '', 'attachment'), 'Modo descarga fuerza attachment');
T::ok(count_table("SELECT COUNT(*) FROM auditoria WHERE accion='descargar' AND registro_id = ?", [$docId]) === 2, 'Visualización y descarga quedan auditadas');
T::status($h->get('/documentos/999999/archivo'), 404, 'Documento inexistente: 404');
T::status($h->get('/storage/documentos_medicos/' . $doc['nombre_interno']), 403, 'La URL física del archivo no es accesible por web');
T::status($h->get('/documentos_medicos/' . $doc['nombre_interno']), 404, 'Tampoco existe una ruta pública alternativa');
$h->logout();

$hs = new Http();
$r = $hs->get("/documentos/$docId/archivo");
T::ok($r['status'] === 302 && str_contains((string) $r['location'], '/login'), 'Sin sesión no se puede descargar');

$hp1 = new Http();
$hp1->login(...$PACIENTE);
T::ok(str_starts_with($hp1->get("/documentos/$docId/archivo")['body'], '%PDF'), 'El paciente dueño descarga su documento');
T::has($hp1->get('/documentos'), 'Química sanguínea octubre', 'Mis documentos lista el documento');
T::hasNot($hp1->get('/documentos?paciente=' . $p102), 'Radiografía de tórax de control', 'El filtro por paciente no permite ver documentos ajenos');
T::status($hp1->submit('/perfil', "/documentos/$docId/eliminar"), 403, 'El paciente no puede eliminar documentos');
$hp1->logout();

$hp2 = new Http();
$hp2->login(...$PACIENTE2);
T::status($hp2->get("/documentos/$docId/archivo"), 403, 'Otro paciente NO descarga el documento aunque conozca la URL');
T::status($hp2->get("/documentos/$docId"), 403, 'Otro paciente tampoco ve sus datos');
$hp2->logout();

$ha = new Http();
$ha->login(...$ADMIN);
T::status($ha->get("/documentos/$docId/archivo"), 403, 'El administrador no puede abrir el contenido clínico');
$page = $ha->get("/documentos/$docId");
T::ok($page['status'] === 200 && str_contains($page['body'], 'Contenido no disponible'), 'El administrador ve solo los metadatos');
T::has($ha->get('/documentos'), 'Química sanguínea octubre', 'El administrador lista los documentos');

T::group('Documentos: eliminación (administrador)');
$ha->submit("/documentos/$docId", "/documentos/$docId/eliminar");
T::ok(count_table('SELECT COUNT(*) FROM documentos_medicos WHERE id = ?', [$docId]) === 0, 'Elimina el registro');
T::ok(!is_file(__DIR__ . '/../../storage/documentos_medicos/' . $doc['nombre_interno']), 'Elimina el archivo físico');
T::ok(count_table("SELECT COUNT(*) FROM auditoria WHERE accion='eliminar' AND modulo='documentos' AND registro_id = ?", [$docId]) === 1, 'La eliminación queda auditada');
$ha->logout();

@unlink($pdf); @unlink($png); @unlink($falsoPdf); @unlink($php); @unlink($exe); @unlink($grande); @unlink($vacio); @unlink($dobleExt);
