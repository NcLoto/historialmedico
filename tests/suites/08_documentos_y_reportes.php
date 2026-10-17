<?php
declare(strict_types=1);

use App\Core\Database;

$p101 = pid('SAN-000101');
$p102 = pid('SAN-000102');
$conIndicaciones = (int) Database::value("SELECT id FROM consultas WHERE paciente_id = ? AND motivo = 'Control de diabetes e hipertensión'", [$p101]);
$conMetformina = (int) Database::value("SELECT id FROM consultas WHERE paciente_id = ? AND motivo = 'Revisión de resultados de laboratorio'", [$p101]);
$conProgramada = (int) Database::value("SELECT id FROM consultas WHERE paciente_id = ? AND estado = 'programada' ORDER BY id LIMIT 1", [$p101]);
$con102 = (int) Database::value("SELECT id FROM consultas WHERE paciente_id = ? AND estado = 'realizada' ORDER BY id LIMIT 1", [$p102]);
$docBase = ['doc_papel' => 'a4', 'doc_letra' => 'normal', 'doc_encabezado' => 'barra', 'doc_color_modo' => 'acento', 'doc_color' => '#0e7490',
    'doc_logo' => '1', 'doc_contacto' => '1', 'doc_generado_por' => '1', 'doc_verificacion' => '1',
    'doc_pie' => 'Documento confidencial. Contiene información médica protegida.', 'doc_firma_texto' => 'Firma y sello del médico tratante',
    'doc_marca_agua_texto' => 'CONFIDENCIAL', 'doc_receta_nota' => 'Receta válida por 30 días a partir de su emisión. No se automedique.',
    'doc_secciones' => array_keys(\App\Domain\Impresos::SECCIONES), 'doc_tipos_permitidos' => ['pdf', 'jpg', 'png', 'webp'], 'archivo_max_mb' => '15'];
$cfg = fn(string $k) => (string) Database::value('SELECT valor FROM configuracion WHERE clave = ?', [$k]);
$codigoDe = fn(string $pdf) => preg_match('/de verificaci.n ([A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4})/u', pdf_text($pdf), $m) ? str_replace('-', '', $m[1]) : '';

$ha = new Http();
$ha->login(...$ADMIN);
$hm = new Http();
$hm->login(...$MEDICO);

T::group('Configuración de documentos');
T::ok($ha->get('/configuracion/documentos')['status'] === 200, 'El administrador abre Configuración → Documentos');
T::status($hm->get('/configuracion/documentos'), 403, 'El médico no puede abrirla');
$ha->submit('/configuracion/documentos', '/configuracion/documentos', ['doc_tipos_permitidos' => [], 'doc_secciones' => []] + $docBase);
$f = $ha->follow('/configuracion/documentos')['body'];
T::ok(str_contains($f, 'al menos un formato') && str_contains($f, 'al menos una sección'), 'Exige al menos un formato y una sección');
$ha->submit('/configuracion/documentos', '/configuracion/documentos', ['doc_marca_agua' => '1', 'doc_marca_agua_texto' => ''] + $docBase);
T::has($ha->follow('/configuracion/documentos'), 'texto de la marca de agua', 'Marca de agua activa exige texto');
$ha->submit('/configuracion/documentos', '/configuracion/documentos', ['doc_color_modo' => 'propio', 'doc_color' => 'rojo'] + $docBase);
T::has($ha->follow('/configuracion/documentos'), 'color válido', 'Valida el color propio');
$ha->submit('/configuracion/documentos', '/configuracion/documentos', ['archivo_max_mb' => '99'] + $docBase);
T::has($ha->follow('/configuracion/documentos'), 'entre 1 y 40', 'Valida el tamaño máximo de archivo');

$ha->submit('/configuracion/documentos', '/configuracion/documentos', ['doc_papel' => 'carta', 'doc_encabezado' => 'minimo', 'doc_letra' => 'grande', 'doc_marca_agua' => '1',
    'doc_marca_agua_texto' => 'USO INTERNO', 'doc_color_modo' => 'propio', 'doc_color' => '#7c3aed', 'doc_secciones' => ['alertas', 'antecedentes', 'enfermedades']] + $docBase);
T::ok($cfg('doc_papel') === 'carta' && $cfg('doc_encabezado') === 'minimo' && $cfg('doc_color') === '#7c3aed' && $cfg('doc_marca_agua') === '1', 'Guarda papel, encabezado, color y marca de agua');
$r = $hm->get("/pacientes/$p101/exportar.pdf");
$t = pdf_text($r['body']);
T::ok(str_contains($r['body'], '/MediaBox [0 0 612.00 792.00]'), 'El PDF usa tamaño carta');
T::ok(str_contains($t, '(USO INTERNO) Tj'), 'El PDF lleva la marca de agua configurada');
T::ok(mb_stripos($t, 'Alertas clínicas') !== false && mb_stripos($t, 'Consultas recientes') === false && mb_stripos($t, 'Antecedentes familiares') === false, 'El resumen solo incluye las secciones elegidas');
T::ok(str_contains($t, '14.30 Tf'), 'Letra grande: el título crece un 10 %');

$ha->submit('/configuracion/documentos', '/configuracion/documentos', $docBase);
T::ok($cfg('doc_papel') === 'a4' && $cfg('doc_color') === '' && $cfg('doc_marca_agua') === '0', 'Vuelve a la configuración predeterminada (color del sistema)');

T::group('Documentos: verificación de autenticidad');
$antes = count_table('SELECT COUNT(*) FROM documentos_emitidos');
$r = $hm->get("/pacientes/$p101/exportar.pdf");
$codigo = $codigoDe($r['body']);
T::ok(strlen($codigo) === 12 && count_table('SELECT COUNT(*) FROM documentos_emitidos') === $antes + 1, 'Cada PDF lleva un código de verificación y queda registrado');
T::ok(count_table("SELECT COUNT(*) FROM documentos_emitidos WHERE codigo = ? AND tipo = 'resumen' AND paciente_id = ?", [$codigo, $p101]) === 1, 'El registro guarda tipo y paciente');
$hs = new Http();
$v = $hs->get('/verificar/' . $codigo);
T::ok($v['status'] === 200 && str_contains($v['body'], 'Documento auténtico') && str_contains($v['body'], 'Resumen clínico'), 'La verificación pública confirma el documento sin iniciar sesión');
T::ok(!str_contains($v['body'], 'Ramírez') && !str_contains($v['body'], 'Penicilina') && str_contains($v['body'], 'C. H. R. S.'), 'Solo muestra iniciales del paciente, nunca contenido clínico');
T::has($hs->get('/verificar/ABCDEFGHJKLM'), 'Código no encontrado', 'Un código inexistente se informa claramente');
$r = $hs->get('/verificar?codigo=' . strtolower(implode('-', str_split($codigo, 4))));
T::ok($r['status'] === 302 && str_ends_with((string) $r['location'], '/verificar/' . $codigo), 'El formulario acepta el código con guiones y en minúsculas');
T::status($hs->get('/verificar'), 200, 'El formulario de verificación es público');
$ha->submit('/configuracion/documentos', '/configuracion/documentos', ['doc_verificacion' => '0'] + $docBase);
$antes = count_table('SELECT COUNT(*) FROM documentos_emitidos');
$r = $hm->get("/pacientes/$p101/exportar.pdf");
T::ok($codigoDe($r['body']) === '' && count_table('SELECT COUNT(*) FROM documentos_emitidos') === $antes, 'Sin verificación no se imprime ni registra código');
$ha->submit('/configuracion/documentos', '/configuracion/documentos', $docBase);

T::group('Documentos: receta, constancia, carné y muestra');
$r = $hm->get("/consultas/$conMetformina/receta.pdf");
$t = pdf_text($r['body']);
T::ok($r['status'] === 200 && mb_stripos($t, 'Receta médica') !== false && str_contains($t, 'Metformina') && str_contains($t, '850 mg'), 'Receta con los medicamentos indicados');
T::ok(str_contains($t, 'Penicilina') && str_contains($t, 'Receta válida por 30 días'), 'Incluye alergias conocidas y la nota configurada');
T::ok(str_contains($t, 'Colegiado 14872') && str_contains($t, 'Firma y sello del médico tratante'), 'Incluye el bloque de firma');
$r = $hm->get("/consultas/$conIndicaciones/receta.pdf");
T::ok($r['status'] === 302 && str_contains((string) $r['location'], "/consultas/$conIndicaciones"), 'Sin medicamentos no hay receta: vuelve a la consulta');
T::has($hm->follow("/consultas/$conIndicaciones"), 'no tiene medicamentos indicados', 'Y lo explica');
$t = pdf_text($hm->get("/consultas/$conMetformina/constancia.pdf")['body']);
T::ok(mb_stripos($t, 'Constancia de atención médica') !== false && str_contains($t, 'hace constar') && !str_contains($t, 'Diabetes mellitus'), 'Constancia sin información diagnóstica');
T::ok($hm->get("/consultas/$conProgramada/constancia.pdf")['status'] === 302, 'No hay constancia de una consulta programada');
$t = pdf_text($hm->get("/pacientes/$p101/vacunas.pdf")['body']);
T::ok(mb_stripos($t, 'Carné de vacunación') !== false && str_contains($t, 'Influenza estacional') && str_contains($t, 'Neumococo'), 'Carné de vacunación con las dosis aplicadas');
$detalle = $hm->get("/consultas/$conMetformina")['body'];
T::ok(str_contains($detalle, 'receta.pdf') && str_contains($detalle, 'constancia.pdf') && str_contains($detalle, 'exportar.pdf'), 'El menú «Documentos» de la consulta ofrece los tres impresos');
T::hasNot($hm->get("/consultas/$conIndicaciones"), 'receta.pdf', 'Sin medicamentos, el menú no ofrece receta');
$antes = count_table('SELECT COUNT(*) FROM documentos_emitidos');
$r = $ha->get('/configuracion/documentos/muestra.pdf');
T::ok($r['status'] === 200 && str_starts_with($r['body'], '%PDF') && str_contains($r['headers']['content-disposition'] ?? '', 'inline'), 'El PDF de muestra se abre en el navegador');
T::ok(count_table('SELECT COUNT(*) FROM documentos_emitidos') === $antes && str_contains(pdf_text($r['body']), 'MUES-TRA0-0000'), 'La muestra no se registra como documento emitido');

$hp = new Http();
$hp->login(...$PACIENTE);
T::ok(str_starts_with($hp->get("/consultas/$conMetformina/receta.pdf")['body'], '%PDF'), 'El paciente descarga su propia receta');
T::ok(str_starts_with($hp->get("/pacientes/$p101/vacunas.pdf")['body'], '%PDF'), 'Y su carné de vacunación');
T::status($hp->get("/consultas/$con102/receta.pdf"), 403, 'No la de otro paciente');
T::status($hp->get("/pacientes/$p102/vacunas.pdf"), 403, 'Ni el carné de otro paciente');
T::status($hp->get('/configuracion/documentos/muestra.pdf'), 403, 'Ni la muestra de configuración');
$hp->logout();
T::status($ha->get("/consultas/$conMetformina/receta.pdf"), 403, 'El administrador no emite recetas');
T::status($ha->get("/pacientes/$p101/vacunas.pdf"), 403, 'Ni carnés de vacunación');

T::group('Documentos: formatos permitidos y categorías');
$png = sys_get_temp_dir() . '/fmt.png';
imagepng(imagecreatetruecolor(20, 20), $png);
$ha->submit('/configuracion/documentos', '/configuracion/documentos', ['doc_tipos_permitidos' => ['pdf']] + $docBase);
T::ok($cfg('doc_tipos_permitidos') === 'pdf', 'Restringe los adjuntos a PDF');
$hm->submit("/pacientes/$p101/documentos/nuevo", "/pacientes/$p101/documentos", ['categoria_id' => '4', 'titulo' => 'Imagen'], ['archivo' => [$png, 'image/png', 'img.png']]);
T::has($hm->follow("/pacientes/$p101/documentos/nuevo"), 'Formatos aceptados: PDF', 'Una imagen se rechaza indicando los formatos aceptados');
T::has($hm->get("/pacientes/$p101/documentos/nuevo"), 'accept=".pdf"', 'El selector de archivos solo ofrece PDF');
$ha->submit('/configuracion/documentos', '/configuracion/documentos', $docBase);
T::ok($cfg('doc_tipos_permitidos') === 'pdf,jpg,png,webp,jpeg', 'Restaura todos los formatos (JPG incluye JPEG)');
@unlink($png);

$ha->submit('/configuracion/documentos', '/configuracion/categorias', ['nombre' => 'Holter de 24 horas', 'grupo' => 'examen']);
$cat = (int) Database::value("SELECT id FROM categorias_documentos WHERE nombre = 'Holter de 24 horas'");
T::ok($cat > 0, 'Crea una categoría de documentos');
$ha->submit('/configuracion/documentos', '/configuracion/categorias', ['nombre' => 'Holter de 24 horas', 'grupo' => 'examen']);
T::has($ha->follow('/configuracion/documentos'), 'Ya existe una categoría', 'Rechaza nombres duplicados');
$ha->submit('/configuracion/documentos', "/configuracion/categorias/$cat", ['nombre' => 'Holter', 'grupo' => 'imagen']);
T::ok(Database::value('SELECT CONCAT(nombre, "|", grupo) FROM categorias_documentos WHERE id = ?', [$cat]) === 'Holter|imagen', 'Renombra y cambia el grupo');
$ha->submit('/configuracion/documentos', "/configuracion/categorias/$cat/eliminar");
T::ok(count_table('SELECT COUNT(*) FROM categorias_documentos WHERE id = ?', [$cat]) === 0, 'Elimina una categoría sin documentos');
$usada = (int) Database::value('SELECT categoria_id FROM documentos_medicos LIMIT 1');
$ha->submit('/configuracion/documentos', "/configuracion/categorias/$usada/eliminar");
T::ok(count_table('SELECT COUNT(*) FROM categorias_documentos WHERE id = ?', [$usada]) === 1, 'No elimina una categoría con documentos');
T::has($ha->follow('/configuracion/documentos'), 'No se puede eliminar', 'Y explica por qué');

T::group('Reportes');
$idx = $hm->get('/reportes')['body'];
T::ok(str_contains($idx, '/reportes/consultas') && str_contains($idx, '/reportes/alergias') && !str_contains($idx, '/reportes/auditoria'), 'El médico ve los reportes clínicos, no el de auditoría');
$idx = $ha->get('/reportes')['body'];
T::ok(str_contains($idx, '/reportes/auditoria') && str_contains($idx, '/reportes/pacientes') && !str_contains($idx, '/reportes/consultas') && !str_contains($idx, '/reportes/alergias'), 'El administrador ve los administrativos, no los clínicos');
T::status($hm->get('/reportes/auditoria'), 403, 'El médico no abre el reporte de auditoría');
T::status($ha->get('/reportes/consultas'), 403, 'El administrador no abre el reporte de consultas');
T::status($ha->get('/reportes/inexistente'), 404, 'Un reporte inexistente responde 404');
$hp = new Http();
$hp->login(...$PACIENTE);
T::status($hp->get('/reportes'), 403, 'El paciente no tiene reportes');
$hp->logout();
foreach (['consultas', 'alergias', 'vacunas', 'pacientes', 'documentos'] as $rep) {
    $r = $hm->get("/reportes/$rep?desde=" . date('Y-m-d', strtotime('-400 days')));
    T::ok($r['status'] === 200 && !preg_match('/\b(Warning|Notice|Fatal error)\b:/', $r['body']), "Vista previa del reporte «{$rep}»");
}
$r = $ha->get('/reportes/auditoria');
T::ok($r['status'] === 200 && str_contains($r['body'], 'Eventos'), 'Vista previa del reporte de auditoría con resumen');
$r = $hm->get('/reportes/consultas?medico=todos&desde=' . date('Y-m-d', strtotime('-400 days')));
T::ok(str_contains($r['body'], 'Control de diabetes e hipertensión') && str_contains($r['body'], 'Total de consultas'), 'El reporte de consultas lista y resume');
$r = $hm->get('/reportes/consultas?medico=todos&estado=programada&desde=' . date('Y-m-d', strtotime('-400 days')) . '&hasta=' . date('Y-m-d', strtotime('+200 days')));
T::ok(!str_contains($r['body'], '>Realizada<') && str_contains($r['body'], 'Programada'), 'Filtra por estado');
$r = $hm->get('/reportes/consultas?medico=todos&formato=csv&desde=' . date('Y-m-d', strtotime('-400 days')));
T::ok(str_contains($r['headers']['content-type'] ?? '', 'text/csv') && str_contains($r['body'], 'Fecha;Paciente;Código;Médico;Motivo;Diagnóstico;Estado'), 'Exporta el reporte en CSV');
$r = $hm->get('/reportes/alergias?formato=pdf');
$t = pdf_text($r['body']);
T::ok(str_starts_with($r['body'], '%PDF') && mb_stripos($t, 'Pacientes con alergias activas') !== false && str_contains($t, 'Penicilina'), 'Exporta el reporte de alergias en PDF');
T::ok(count_table("SELECT COUNT(*) FROM auditoria WHERE modulo = 'reportes' AND accion = 'exportar'") >= 2, 'Las descargas de reportes quedan auditadas');
$r = $hm->get('/reportes/consultas?desde=2026-13-45&hasta=abc');
T::status($r, 200, 'Fechas inválidas no rompen el reporte');

T::group('Búsqueda global de pacientes');
$r = $hm->get('/pacientes/sugerencias?q=ramir');
$j = json_decode($r['body'], true);
T::ok($r['status'] === 200 && is_array($j) && count($j) >= 1 && $j[0]['codigo'] === 'SAN-000101', 'Sugiere pacientes por apellido');
T::ok($j[0]['alerta'] === true, 'Marca a los pacientes con alergia grave');
T::ok(json_decode($hm->get('/pacientes/sugerencias?q=r')['body'], true) === [], 'Con menos de 2 caracteres no busca');
T::ok(count(json_decode($hm->get('/pacientes/sugerencias?q=a')['body'], true)) === 0, 'Tampoco con una sola letra');
$j = json_decode($ha->get('/pacientes/sugerencias?q=ramir')['body'], true);
T::ok(is_array($j) && $j[0]['alerta'] === false, 'Para el administrador no expone datos clínicos (sin alerta de alergia)');
$hp = new Http();
$hp->login(...$PACIENTE);
T::status($hp->get('/pacientes/sugerencias?q=ram'), 403, 'El paciente no puede buscar pacientes');
T::hasNot($hp->get('/'), 'global-search', 'Y no ve el buscador');
$hp->logout();
T::has($hm->get('/'), 'data-quick-search', 'El médico ve el buscador global');

$ha->logout();
$hm->logout();
