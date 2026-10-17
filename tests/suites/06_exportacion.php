<?php
declare(strict_types=1);

use App\Core\Database;

/** Texto de un PDF generado por Pdf.php: descomprime los flujos y devuelve el contenido en claro. */
function pdf_text(string $pdf): string
{
    preg_match_all('/stream\n(.*?)\nendstream/s', $pdf, $m);
    $out = '';
    foreach ($m[1] as $s) {
        $d = @gzuncompress($s);
        $out .= $d !== false ? $d : '';
    }
    return mb_convert_encoding($out, 'UTF-8', 'Windows-1252');
}

$p101 = pid('SAN-000101');
$p102 = pid('SAN-000102');
$cons101 = (int) Database::value("SELECT id FROM consultas WHERE paciente_id = ? AND estado = 'realizada' ORDER BY id LIMIT 1", [$p101]);
$cons102 = (int) Database::value('SELECT id FROM consultas WHERE paciente_id = ? ORDER BY id LIMIT 1', [$p102]);
Database::run("UPDATE consultas SET notas_internas = 'NOTA-RESERVADA-DEL-MEDICO' WHERE id = ?", [$cons101]);

T::group('Exportación: PDF');
$hm = new Http();
$hm->login(...$MEDICO);
$antes = count_table("SELECT COUNT(*) FROM auditoria WHERE accion = 'exportar'");
$r = $hm->get("/pacientes/$p101/exportar.pdf");
T::ok($r['status'] === 200 && ($r['headers']['content-type'] ?? '') === 'application/pdf' && str_starts_with($r['body'], '%PDF-1.4') && str_ends_with(trim($r['body']), '%%EOF'), 'El médico exporta el resumen clínico en PDF válido');
T::ok(str_contains($r['headers']['content-disposition'] ?? '', 'attachment') && str_contains($r['headers']['content-disposition'] ?? '', 'resumen_clinico_SAN-000101.pdf'), 'Se descarga con nombre descriptivo');
$t = pdf_text($r['body']);
foreach (['Hospital Sanitas', 'Resumen clínico', 'Carlos Humberto Ramírez Soto', 'Penicilina', 'Metformina', 'Diabetes mellitus tipo 2', 'Documento confidencial', 'Página 1 de'] as $needle) {
    T::ok(mb_stripos($t, $needle) !== false, "El PDF contiene «{$needle}»");
}
T::ok(str_contains($t, 'Andrés Ovalle Fuentes'), 'El pie indica quién generó el documento');
T::ok(!str_contains($t, 'NOTA-RESERVADA'), 'El resumen no incluye notas internas');
T::ok(count_table("SELECT COUNT(*) FROM auditoria WHERE accion = 'exportar'") === $antes + 1, 'La exportación queda auditada');

$r = $hm->get("/consultas/$cons101/exportar.pdf");
T::ok($r['status'] === 200 && str_starts_with($r['body'], '%PDF'), 'Exporta el informe de una consulta');
$t = pdf_text($r['body']);
T::ok(mb_stripos($t, 'Informe de consulta') !== false && str_contains($t, 'Control de diabetes e hipertensión') && str_contains($t, 'Dieta hipocalórica') && str_contains($t, 'Colegiado 14872'), 'El informe incluye motivo, tratamiento y firma con colegiado');
T::ok(!str_contains($t, 'NOTA-RESERVADA') && !str_contains($t, 'Notas internas'), 'El informe nunca incluye las notas internas (puede entregarse al paciente)');
T::status($hm->get('/consultas/999999/exportar.pdf'), 404, 'Consulta inexistente: 404');
T::status($hm->get('/pacientes/999999/exportar.pdf'), 404, 'Paciente inexistente: 404');
$hm->logout();

T::group('Exportación: permisos de los PDF');
$hp = new Http();
$hp->login(...$PACIENTE);
$r = $hp->get("/pacientes/$p101/exportar.pdf");
T::ok($r['status'] === 200 && str_starts_with($r['body'], '%PDF'), 'El paciente exporta su propio resumen');
T::ok($hp->get("/consultas/$cons101/exportar.pdf")['status'] === 200 && !str_contains(pdf_text($hp->get("/consultas/$cons101/exportar.pdf")['body']), 'NOTA-RESERVADA'), 'El paciente exporta su consulta sin notas internas');
T::status($hp->get("/pacientes/$p102/exportar.pdf"), 403, 'El paciente no exporta el resumen de otro paciente');
T::status($hp->get("/consultas/$cons102/exportar.pdf"), 403, 'El paciente no exporta la consulta de otro');
T::status($hp->get('/pacientes/exportar.csv'), 403, 'El paciente no exporta listados');
T::status($hp->get('/usuarios/exportar.csv'), 403, 'El paciente no exporta usuarios');
T::status($hp->get('/auditoria/exportar.csv'), 403, 'El paciente no exporta la bitácora');
$hp->logout();

$ha = new Http();
$ha->login(...$ADMIN);
T::status($ha->get("/pacientes/$p101/exportar.pdf"), 403, 'El administrador no exporta información clínica');
T::status($ha->get("/consultas/$cons101/exportar.pdf"), 403, 'El administrador no exporta consultas');
$hs = new Http();
foreach (["/pacientes/$p101/exportar.pdf", "/consultas/$cons101/exportar.pdf", '/pacientes/exportar.csv', '/auditoria/exportar.csv'] as $path) {
    $r = $hs->get($path);
    T::ok($r['status'] === 302 && str_contains((string) $r['location'], '/login'), "Sin sesión, $path exige iniciar sesión");
}

T::group('Exportación: CSV');
$hm = new Http();
$hm->login(...$MEDICO);
$r = $hm->get('/pacientes/exportar.csv');
T::ok($r['status'] === 200 && str_contains($r['headers']['content-type'] ?? '', 'text/csv') && str_contains($r['headers']['content-disposition'] ?? '', 'attachment'), 'El médico exporta el listado de pacientes');
T::ok(str_starts_with($r['body'], "\xEF\xBB\xBF"), 'El CSV lleva BOM UTF-8 (Excel muestra bien las tildes)');
T::ok(str_contains($r['body'], 'Ramírez Soto') && str_contains($r['body'], 'SAN-000101') && substr_count($r['body'], "\n") >= 11, 'Contiene los pacientes (cabecera + 10 filas)');
T::ok(!str_contains($r['body'], 'Penicilina') && !str_contains($r['body'], 'Diabetes'), 'El listado no contiene datos clínicos');
$r = $hm->get('/pacientes/exportar.csv?q=gomez');
T::ok(str_contains($r['body'], 'Gómez Archila') && !str_contains($r['body'], 'Ramírez Soto'), 'El CSV respeta el filtro de búsqueda');
T::status($hm->get('/usuarios/exportar.csv'), 403, 'El médico no exporta usuarios');
T::status($hm->get('/auditoria/exportar.csv'), 403, 'El médico no exporta la bitácora');
$hm->logout();

$r = $ha->get('/usuarios/exportar.csv');
T::ok($r['status'] === 200 && str_contains($r['body'], 'admin@sanitas.local') && !str_contains($r['body'], 'password'), 'El administrador exporta usuarios (sin contraseñas)');
$r = $ha->get('/auditoria/exportar.csv?accion=login');
T::ok($r['status'] === 200 && str_contains($r['body'], 'Inicio de sesión') && !str_contains($r['body'], 'Cierre de sesión'), 'La bitácora exportada respeta los filtros');
$r = $ha->get('/pacientes/exportar.csv?estado=todos');
T::ok($r['status'] === 200, 'El administrador exporta datos personales de pacientes');
T::ok(count_table("SELECT COUNT(*) FROM auditoria WHERE accion = 'exportar' AND modulo IN ('pacientes','usuarios','auditoria','consultas')") >= 6, 'Cada exportación (CSV y PDF) queda en la bitácora');

// Inyección de fórmulas
$ha->submit('/pacientes/nuevo', '/pacientes', ['nombres' => '=HYPERLINK("http://x.test","clic")', 'apellidos' => '+cmd|calc', 'documento_identidad' => '9988776650101', 'fecha_nacimiento' => '1990-01-01', 'sexo' => 'M']);
$r = $ha->get('/pacientes/exportar.csv?q=cmd&estado=todos');
T::ok(str_contains($r['body'], "'=HYPERLINK") && str_contains($r['body'], "'+cmd"), 'Los textos que empiezan con = o + se neutralizan (sin inyección de fórmulas)');

T::group('Exportación: logo institucional en el PDF');
$logo = sys_get_temp_dir() . '/logo_pdf.png';
imagepng(imagecreatetruecolor(160, 60), $logo);
$ha->submit('/configuracion/apariencia', '/configuracion/marca', ['accion' => 'subir_logo'], ['archivo' => [$logo, 'image/png', 'logo.png']]);
$hm = new Http();
$hm->login(...$MEDICO);
$r = $hm->get("/pacientes/$p101/exportar.pdf");
T::ok(str_contains($r['body'], '/DCTDecode') && str_contains($r['body'], '/Im1 '), 'El PDF incrusta el logo cargado en Configuración');
$hm->logout();
$ha->submit('/configuracion/apariencia', '/configuracion/marca', ['accion' => 'quitar_logo']);
@unlink($logo);
$ha->logout();
Database::run('UPDATE consultas SET notas_internas = NULL WHERE id = ?', [$cons101]);

T::group('Exportación: listados en PDF');
$hp = new Http();
$hp->login(...$PACIENTE);
foreach (['/pacientes/exportar.pdf', '/usuarios/exportar.pdf', '/auditoria/exportar.pdf', '/medicos/exportar.pdf', '/medicos/exportar.csv'] as $path) {
    T::status($hp->get($path), 403, "El paciente no exporta $path");
}
$r = $hp->get('/documentos/exportar.pdf');
$t = pdf_text($r['body']);
$ajenos = (int) Database::value('SELECT COUNT(*) FROM documentos_medicos WHERE paciente_id <> ?', [$p101]);
$tituloAjeno = (string) Database::value('SELECT titulo FROM documentos_medicos WHERE paciente_id <> ? ORDER BY id LIMIT 1', [$p101]);
T::ok($r['status'] === 200 && mb_stripos($t, 'Mis documentos') !== false && ($ajenos === 0 || !str_contains($t, $tituloAjeno)), 'El paciente exporta solo sus propios documentos');
$hp->logout();

$hm = new Http();
$hm->login(...$MEDICO);
$r = $hm->get('/pacientes/exportar.pdf?q=gomez');
$t = pdf_text($r['body']);
T::ok($r['status'] === 200 && str_starts_with($r['body'], '%PDF') && mb_stripos($t, 'Listado de pacientes') !== false, 'El médico exporta el listado de pacientes en PDF');
T::ok(str_contains($t, 'Gómez Archila') && !str_contains($t, 'Ramírez Soto') && str_contains($t, 'gomez'), 'El PDF respeta el filtro y lo declara en los criterios');
T::ok(str_contains($r['body'], '/MediaBox [0 0 841.89 595.28]'), 'Los listados anchos se imprimen en horizontal');
T::ok(!str_contains($t, 'Penicilina') && !str_contains($t, 'Diabetes'), 'El listado de pacientes no contiene datos clínicos');
T::ok((int) Database::value("SELECT COUNT(*) FROM documentos_emitidos WHERE tipo = 'listado'") >= 1, 'El listado queda registrado con código de verificación');
$r = $hm->get('/documentos/exportar.pdf?paciente=' . $p101);
T::ok($r['status'] === 200 && mb_stripos(pdf_text($r['body']), 'Listado de documentos') !== false, 'El médico exporta los documentos de un paciente');
T::status($hm->get('/usuarios/exportar.pdf'), 403, 'El médico no exporta usuarios en PDF');
T::status($hm->get('/auditoria/exportar.pdf'), 403, 'El médico no exporta la bitácora en PDF');
$hm->logout();

$ha = new Http();
$ha->login(...$ADMIN);
$r = $ha->get('/usuarios/exportar.pdf');
$t = pdf_text($r['body']);
T::ok($r['status'] === 200 && str_contains($t, 'admin@sanitas.local') && str_contains($r['body'], '/MediaBox [0 0 595.28 841.89]'), 'El administrador exporta usuarios en PDF (vertical)');
$r = $ha->get('/auditoria/exportar.pdf?accion=login');
$t = pdf_text($r['body']);
T::ok($r['status'] === 200 && str_contains($t, 'Inicio de sesión') && !str_contains($t, 'Cierre de sesión'), 'La bitácora en PDF respeta los filtros');
$r = $ha->get('/medicos/exportar.pdf');
T::ok($r['status'] === 200 && str_contains(pdf_text($r['body']), 'Ovalle Fuentes'), 'El administrador exporta médicos en PDF');
$r = $ha->get('/medicos/exportar.csv');
T::ok(str_contains($r['headers']['content-type'] ?? '', 'text/csv') && str_contains($r['body'], 'Colegiado') && str_contains($r['body'], '14872'), 'Y en CSV');
T::has($ha->get('/medicos'), 'medicos/exportar.pdf', 'Médicos ofrece el menú Exportar');
T::ok(count_table("SELECT COUNT(*) FROM auditoria WHERE accion = 'exportar' AND descripcion LIKE '%exportado en PDF (%registros%'") >= 4, 'Cada listado en PDF queda en la bitácora');
$ha->logout();
