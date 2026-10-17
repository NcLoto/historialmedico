<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Config;
use App\Core\Controller;
use App\Core\Csv;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Pdf;
use App\Core\Policy;
use App\Core\Request;
use App\Domain\HistorialSections;
use App\Domain\Impresos;
use App\Repositories\HistorialRepository;
use App\Repositories\PacienteRepository;

/**
 * Documentos imprimibles (resumen clínico, informe de consulta, receta, constancia, carné de vacunación,
 * muestra de diseño) y tablas CSV. Toda exportación queda auditada; los PDF se registran como documentos emitidos.
 */
final class ExportController extends Controller
{
    private const GRIS = [98, 98, 98];

    // ------------------------------------------------------------ Resumen clínico
    public function pacientePdf(Request $r, array $p): void
    {
        $pac = Policy::patient((int) $p['id']);
        $id = (int) $pac['id'];
        [$pdf, $codigo] = Impresos::crear('resumen', 'Resumen clínico ' . $pac['codigo'], $id);
        $this->resumen($pdf, $pac, Impresos::secciones());
        Audit::log('exportar', 'pacientes', 'Resumen clínico exportado en PDF' . ($codigo ? ' (código ' . $codigo . ')' : ''), $id, $id);
        Impresos::enviar($pdf, 'resumen_clinico_' . $pac['codigo'] . '.pdf');
    }

    // ------------------------------------------------------------ Informe de consulta
    public function consultaPdf(Request $r, array $p): void
    {
        $c = $this->consulta((int) $p['id']);
        $id = (int) $c['id'];
        $diag = Database::all('SELECT * FROM diagnosticos WHERE consulta_id = ? ORDER BY tipo, id', [$id]);
        $trat = Database::all('SELECT * FROM tratamientos WHERE consulta_id = ? ORDER BY id', [$id]);
        [$pdf, $codigo] = Impresos::crear('consulta', 'Informe de consulta ' . $c['codigo'], (int) $c['paciente_id'], $id);

        $pdf->title('Informe de consulta', fecha_hora($c['fecha_hora']) . ', ' . ucfirst_mb($c['estado']));
        $this->cabeceraConsulta($pdf, $c);
        $pdf->heading('Motivo de consulta');
        $pdf->para($c['motivo']);
        if ($c['enfermedad_actual']) {
            $pdf->heading('Enfermedad actual');
            $pdf->para($c['enfermedad_actual']);
        }
        if ($vit = $this->vitales($c)) {
            $pdf->heading('Signos vitales');
            $pdf->pairs($vit);
        }
        if ($c['examen_fisico']) {
            $pdf->heading('Examen físico');
            $pdf->para($c['examen_fisico']);
        }
        if ($diag) {
            $pdf->heading('Diagnósticos');
            $pdf->table(['Diagnóstico', 'CIE-10', 'Tipo'], array_map(fn($d) => [$d['descripcion'], $d['codigo_cie10'] ?? '', etiqueta($d['tipo'])], $diag), [6, 2, 2]);
        }
        if ($trat) {
            $pdf->heading('Tratamiento e indicaciones');
            $pdf->table(['Indicación', 'Tipo', 'Dosis', 'Frecuencia', 'Duración'], array_map(fn($t) => [$t['descripcion'], etiqueta($t['tipo']), $t['dosis'] ?? '', $t['frecuencia'] ?? '', $t['duracion'] ?? ''], $trat), [5, 2, 2, 3, 2]);
        }
        if ($c['plan']) {
            $pdf->heading('Plan');
            $pdf->para($c['plan']);
        }
        $this->firma($pdf, $c);
        Audit::log('exportar', 'consultas', 'Informe de consulta exportado en PDF' . ($codigo ? ' (código ' . $codigo . ')' : ''), $id, (int) $c['paciente_id']);
        Impresos::enviar($pdf, 'consulta_' . $c['codigo'] . '_' . date('Ymd', strtotime($c['fecha_hora'])) . '.pdf');
    }

    // ------------------------------------------------------------ Receta médica
    public function recetaPdf(Request $r, array $p): void
    {
        $c = $this->consulta((int) $p['id']);
        $id = (int) $c['id'];
        $meds = Database::all("SELECT * FROM tratamientos WHERE consulta_id = ? AND tipo = 'medicamento' ORDER BY id", [$id]);
        if (!$meds) {
            $this->flash('info', 'Esta consulta no tiene medicamentos indicados, por lo que no hay receta que emitir.');
            $this->redirect('consultas/' . $id);
        }
        $otras = Database::all("SELECT * FROM tratamientos WHERE consulta_id = ? AND tipo <> 'medicamento' ORDER BY id", [$id]);
        $diag = Database::value("SELECT GROUP_CONCAT(descripcion SEPARATOR ', ') FROM diagnosticos WHERE consulta_id = ? AND tipo = 'principal'", [$id]);
        $alergias = Database::value("SELECT GROUP_CONCAT(agente SEPARATOR ', ') FROM alergias WHERE paciente_id = ? AND activa = 1", [$c['paciente_id']]);
        [$pdf, $codigo] = Impresos::crear('receta', 'Receta médica ' . $c['codigo'], (int) $c['paciente_id'], $id);

        $pdf->title('Receta médica', 'Emitida el ' . fecha($c['fecha_hora']));
        $pdf->pairs([
            'Paciente' => $c['pn'] . ' ' . $c['pa'], 'Código de paciente' => $c['codigo'], 'Edad' => edad($c['fecha_nacimiento']),
            'Diagnóstico' => (string) $diag, 'Alergias conocidas' => $alergias ?: 'Ninguna registrada',
        ]);
        $pdf->heading('Medicamentos indicados');
        $pdf->table(['Medicamento', 'Dosis', 'Frecuencia', 'Duración'], array_map(fn($t) => [$t['descripcion'], $t['dosis'] ?? '', $t['frecuencia'] ?? '', $t['duracion'] ?? ''], $meds), [5, 2, 4, 2], 9.5);
        if ($otras) {
            $pdf->heading('Indicaciones adicionales');
            foreach ($otras as $t) {
                $pdf->para('• ' . $t['descripcion'] . ($t['frecuencia'] ? ' (' . $t['frecuencia'] . ')' : ''));
            }
        }
        if ($nota = trim(Config::get('doc_receta_nota'))) {
            $pdf->note($nota, 'Importante');
        }
        $this->firma($pdf, $c);
        Audit::log('exportar', 'consultas', 'Receta médica emitida en PDF' . ($codigo ? ' (código ' . $codigo . ')' : ''), $id, (int) $c['paciente_id']);
        Impresos::enviar($pdf, 'receta_' . $c['codigo'] . '_' . date('Ymd', strtotime($c['fecha_hora'])) . '.pdf');
    }

    // ------------------------------------------------------------ Constancia de atención
    public function constanciaPdf(Request $r, array $p): void
    {
        $c = $this->consulta((int) $p['id']);
        $id = (int) $c['id'];
        if ($c['estado'] !== 'realizada') {
            $this->flash('info', 'Solo se emiten constancias de consultas realizadas.');
            $this->redirect('consultas/' . $id);
        }
        [$pdf, $codigo] = Impresos::crear('constancia', 'Constancia de atención ' . $c['codigo'], (int) $c['paciente_id'], $id);
        $hospital = Config::get('hospital_nombre', 'Hospital Sanitas');
        $pdf->title('Constancia de atención médica');
        $pdf->space(6);
        $pdf->para('A quien interese:', 10.5, true);
        $pdf->space(4);
        $pdf->para(sprintf(
            '%s hace constar que %s, con documento de identidad %s y código de paciente %s, fue atendido(a) en consulta de %s el día %s a las %s horas por Dr(a). %s, colegiado %s.',
            $hospital, $c['pn'] . ' ' . $c['pa'], $c['documento_identidad'], $c['codigo'], mb_strtolower($c['especialidad']),
            fecha($c['fecha_hora']), date('H:i', strtotime($c['fecha_hora'])), $c['mn'] . ' ' . $c['ma'], $c['colegiado']
        ), 10.5);
        $pdf->para('Se extiende la presente a solicitud del interesado para los usos que estime convenientes. Esta constancia no incluye información diagnóstica.', 10.5);
        $pdf->space(4);
        $pdf->para(Config::get('hospital_direccion') ? 'Lugar y fecha: ' . Config::get('hospital_direccion') . ', ' . fecha(date('Y-m-d')) . '.' : 'Fecha de emisión: ' . fecha(date('Y-m-d')) . '.', 10);
        $this->firma($pdf, $c);
        Audit::log('exportar', 'consultas', 'Constancia de atención emitida en PDF' . ($codigo ? ' (código ' . $codigo . ')' : ''), $id, (int) $c['paciente_id']);
        Impresos::enviar($pdf, 'constancia_' . $c['codigo'] . '_' . date('Ymd', strtotime($c['fecha_hora'])) . '.pdf');
    }

    // ------------------------------------------------------------ Carné de vacunación
    public function vacunasPdf(Request $r, array $p): void
    {
        $pac = Policy::patient((int) $p['id']);
        $id = (int) $pac['id'];
        $vac = Database::all('SELECT * FROM vacunas WHERE paciente_id = ? ORDER BY fecha_aplicacion DESC, id DESC', [$id]);
        // Formato carné: A6 vertical, para imprimir y llevar consigo (no hoja carta).
        [$pdf, $codigo] = Impresos::crear('vacunas', 'Carné de vacunación ' . $pac['codigo'], $id, null, true, null, ['papel' => 'carne', 'escala' => 0.82]);
        $pdf->title('Carné de vacunación', nombre_completo($pac));
        $pdf->pairs(['Código' => $pac['codigo'], 'Nacimiento' => fecha($pac['fecha_nacimiento']), 'Documento' => $pac['documento_identidad'], 'Tipo de sangre' => $pac['tipo_sangre'] ?? '']);
        $pendientes = array_filter($vac, fn($v) => $v['proxima_dosis'] && $v['proxima_dosis'] <= date('Y-m-d', strtotime('+60 days')));
        if ($pendientes) {
            $pdf->note(implode("\n", array_map(fn($v) => $v['vacuna'] . ': próxima dosis ' . fecha($v['proxima_dosis']) . ($v['proxima_dosis'] < date('Y-m-d') ? ' (vencida)' : ''), $pendientes)), 'Dosis pendientes');
        }
        $pdf->heading('Vacunas aplicadas');
        $vac
            ? $pdf->table(['Vacuna', 'Dosis', 'Aplicada', 'Lote', 'Próxima'], array_map(fn($v) => [$v['vacuna'], $v['dosis'] ?? '', fecha($v['fecha_aplicacion']), $v['lote'] ?? '', fecha($v['proxima_dosis'], '')], $vac), [3.6, 1.6, 2.4, 2.2, 2.4], 7.5)
            : $pdf->para('No hay vacunas registradas.', 9, false, self::GRIS);
        Audit::log('exportar', 'pacientes', 'Carné de vacunación exportado en PDF' . ($codigo ? ' (código ' . $codigo . ')' : ''), $id, $id);
        Impresos::enviar($pdf, 'carne_vacunacion_' . $pac['codigo'] . '.pdf');
    }

    // ------------------------------------------------------------ Muestra de diseño (datos ficticios)
    public function muestraPdf(Request $r): void
    {
        [$pdf] = Impresos::crear('muestra', 'Documento de muestra', null, null, false, Config::get('doc_verificacion') === '1' ? 'MUESTRA00000' : null);
        $pdf->title('Documento de muestra', 'Vista previa del diseño configurado, con datos ficticios');
        $pdf->heading('Datos del paciente');
        $pdf->pairs(['Nombre' => 'Paciente de demostración', 'Código' => 'SAN-000000', 'Nacimiento' => '01/01/1980 (46 años)', 'Tipo de sangre' => 'O+']);
        $pdf->heading('Medicamentos indicados');
        $pdf->table(['Medicamento', 'Dosis', 'Frecuencia', 'Duración'], [['Medicamento de ejemplo A', '500 mg', 'Cada 8 horas', '7 días'], ['Medicamento de ejemplo B', '10 mg', 'Una vez al día', 'Continuo'], ['Medicamento de ejemplo C', '20 mg', 'Por la noche', '30 días']], [5, 2, 4, 2]);
        if ($nota = trim(Config::get('doc_receta_nota'))) {
            $pdf->note($nota, 'Importante');
        }
        $pdf->para('Este documento solo muestra cómo se verán los impresos del sistema con la configuración actual: papel, encabezado, colores, tamaño de letra, pie, marca de agua y firma.', 9, false, self::GRIS);
        $pdf->signature('Dr(a). Nombre del médico', 'Especialidad, Colegiado 00000', Config::get('doc_firma_texto', 'Firma y sello'));
        Impresos::enviar($pdf, 'muestra_documento.pdf', false);
    }

    // ------------------------------------------------------------ CSV
    public function pacientesCsv(Request $r): void
    {
        $estado = in_array($r->str('estado'), ['activos', 'inactivos', 'todos'], true) ? $r->str('estado') : 'activos';
        $rows = PacienteRepository::exportRows(mb_substr($r->str('q'), 0, 100), $estado);
        Audit::log('exportar', 'pacientes', 'Listado de pacientes exportado en CSV (' . count($rows) . ' registros)');
        Csv::send('pacientes_' . date('Ymd') . '.csv',
            ['Código', 'Nombres', 'Apellidos', 'Documento', 'Nacimiento', 'Sexo', 'Tipo de sangre', 'Teléfono', 'Correo', 'Dirección', 'Estado', 'Cuenta de acceso', 'Registrado'],
            array_map(fn($p) => [$p['codigo'], $p['nombres'], $p['apellidos'], $p['documento_identidad'], $p['fecha_nacimiento'], $p['sexo'], $p['tipo_sangre'], $p['telefono'], $p['email'], $p['direccion'], $p['activo'] ? 'Activo' : 'Inactivo', $p['con_cuenta'] ? 'Sí' : 'No', $p['creado_en']], $rows));
    }

    public function usuariosCsv(Request $r): void
    {
        [$w, $params] = UsuarioController::filtros($r);
        $rows = Database::all("SELECT u.nombres, u.apellidos, u.email, r.nombre AS rol, u.activo, u.ultimo_acceso, u.creado_en FROM usuarios u JOIN roles r ON r.id = u.rol_id WHERE $w ORDER BY u.apellidos, u.nombres LIMIT 20000", $params);
        Audit::log('exportar', 'usuarios', 'Listado de usuarios exportado en CSV (' . count($rows) . ' registros)');
        Csv::send('usuarios_' . date('Ymd') . '.csv', ['Nombres', 'Apellidos', 'Correo', 'Rol', 'Estado', 'Último acceso', 'Creado'],
            array_map(fn($u) => [$u['nombres'], $u['apellidos'], $u['email'], $u['rol'], $u['activo'] ? 'Activo' : 'Inactivo', $u['ultimo_acceso'], $u['creado_en']], $rows));
    }

    public function auditoriaCsv(Request $r): void
    {
        $f = AuditoriaController::filtros($r);
        $rows = Database::all(
            'SELECT a.creado_en, a.usuario_nombre, a.rol, a.accion, a.modulo, a.descripcion, p.codigo AS paciente, a.ip ' . AuditoriaController::FROM . ' WHERE ' . $f['where'] . ' ORDER BY a.id DESC LIMIT 20000',
            $f['params']
        );
        Audit::log('exportar', 'auditoria', 'Bitácora exportada en CSV (' . count($rows) . ' registros)');
        Csv::send('auditoria_' . date('Ymd_His') . '.csv', ['Fecha y hora', 'Usuario', 'Rol', 'Acción', 'Módulo', 'Descripción', 'Paciente', 'IP'],
            array_map(fn($a) => [$a['creado_en'], $a['usuario_nombre'], $a['rol'], accion_label($a['accion']), modulo_label($a['modulo']), $a['descripcion'], $a['paciente'], $a['ip']], $rows));
    }

    public function medicosCsv(Request $r): void
    {
        [$rows, ] = $this->medicos($r);
        Audit::log('exportar', 'medicos', 'Listado de médicos exportado en CSV (' . count($rows) . ' registros)');
        Csv::send('medicos_' . date('Ymd') . '.csv', ['Apellidos', 'Nombres', 'Especialidad', 'Colegiado', 'Consultorio', 'Teléfono', 'Correo', 'Estado'],
            array_map(fn($m) => [$m['apellidos'], $m['nombres'], $m['especialidad'], $m['colegiado'], $m['consultorio'], $m['telefono'], $m['email'], $m['activo'] ? 'Activo' : 'Inactivo'], $rows));
    }

    public function documentosCsv(Request $r): void
    {
        $rows = DocumentoController::exportRows($r);
        $f = DocumentoController::filtros($r);
        Audit::log('exportar', 'documentos', 'Listado de documentos exportado en CSV (' . count($rows) . ' registros)', null, $f['paciente'] ?: null);
        Csv::send('documentos_' . date('Ymd') . '.csv', ['Documento', 'Código de paciente', 'Paciente', 'Categoría', 'Fecha', 'Formato', 'Tamaño (bytes)', 'Cargado por', 'Cargado el'],
            array_map(fn($d) => [$d['titulo'], $d['codigo'], $d['papellidos'] . ', ' . $d['pnombres'], $d['categoria'], $d['fecha_documento'], strtoupper($d['extension']), $d['tamano_bytes'], trim(($d['unombres'] ?? '') . ' ' . ($d['uapellidos'] ?? '')), $d['cargado_en']], $rows));
    }

    // ------------------------------------------------------------ Listados en PDF (mismo diseño que los documentos clínicos)
    public function pacientesPdf(Request $r): void
    {
        $estado = in_array($r->str('estado'), ['activos', 'inactivos', 'todos'], true) ? $r->str('estado') : 'activos';
        $q = mb_substr($r->str('q'), 0, 100);
        $rows = PacienteRepository::exportRows($q, $estado);
        $this->listado('Listado de pacientes', 'pacientes', ['Búsqueda' => $q, 'Estado' => ['activos' => 'Activos', 'inactivos' => 'Inactivos', 'todos' => 'Todos'][$estado]],
            ['Código', 'Paciente', 'Documento', 'Nacimiento', 'Sexo', 'Sangre', 'Teléfono', 'Correo', 'Cuenta', 'Estado'],
            array_map(fn($p) => [$p['codigo'], $p['apellidos'] . ', ' . $p['nombres'], $p['documento_identidad'], fecha($p['fecha_nacimiento']) . ' (' . edad($p['fecha_nacimiento']) . ')',
                $p['sexo'] === 'F' ? 'F' : 'M', $p['tipo_sangre'] ?? '', $p['telefono'] ?? '', $p['email'] ?? '', $p['con_cuenta'] ? 'Con acceso' : 'Sin cuenta', $p['activo'] ? 'Activo' : 'Inactivo'], $rows),
            [1.6, 3.4, 2.2, 2.4, 0.8, 0.9, 1.6, 3.2, 1.6, 1.3], 'listado_pacientes_' . date('Ymd') . '.pdf', true);
    }

    public function medicosPdf(Request $r): void
    {
        [$rows, $esp] = $this->medicos($r);
        $this->listado('Listado de médicos', 'medicos', ['Búsqueda' => mb_substr($r->str('q'), 0, 100), 'Especialidad' => $esp],
            ['Médico', 'Especialidad', 'Colegiado', 'Consultorio', 'Teléfono', 'Correo', 'Estado'],
            array_map(fn($m) => ['Dr(a). ' . $m['apellidos'] . ', ' . $m['nombres'], $m['especialidad'], $m['colegiado'], $m['consultorio'] ?? '', $m['telefono'] ?? '', $m['email'], $m['activo'] ? 'Activo' : 'Inactivo'], $rows),
            [3.6, 2.6, 1.3, 1.8, 1.4, 3.2, 1.2], 'listado_medicos_' . date('Ymd') . '.pdf', true);
    }

    public function usuariosPdf(Request $r): void
    {
        [$w, $params] = UsuarioController::filtros($r);
        $rows = Database::all("SELECT u.nombres, u.apellidos, u.email, r.nombre AS rol, u.activo, u.ultimo_acceso, u.creado_en FROM usuarios u JOIN roles r ON r.id = u.rol_id WHERE $w ORDER BY u.apellidos, u.nombres LIMIT 5000", $params);
        $rol = (int) $r->str('rol') ? (string) Database::value('SELECT nombre FROM roles WHERE id = ?', [(int) $r->str('rol')]) : '';
        $this->listado('Listado de usuarios', 'usuarios',
            ['Búsqueda' => mb_substr($r->str('q'), 0, 100), 'Rol' => $rol, 'Estado' => ['activos' => 'Activos', 'inactivos' => 'Inactivos'][$r->str('estado')] ?? ''],
            ['Usuario', 'Correo', 'Rol', 'Estado', 'Último acceso', 'Creado'],
            array_map(fn($u) => [$u['apellidos'] . ', ' . $u['nombres'], $u['email'], $u['rol'], $u['activo'] ? 'Activo' : 'Inactivo', fecha_hora($u['ultimo_acceso'], 'Nunca'), fecha($u['creado_en'])], $rows),
            [3.4, 3.4, 1.7, 1.2, 2, 1.5], 'listado_usuarios_' . date('Ymd') . '.pdf', false);
    }

    public function auditoriaPdf(Request $r): void
    {
        $f = AuditoriaController::filtros($r);
        $rows = Database::all(
            'SELECT a.creado_en, a.usuario_nombre, a.rol, a.accion, a.modulo, a.descripcion, p.codigo AS paciente, a.ip ' . AuditoriaController::FROM . ' WHERE ' . $f['where'] . ' ORDER BY a.id DESC LIMIT 5000',
            $f['params']
        );
        $this->listado('Bitácora de auditoría', 'auditoria', [
                'Búsqueda' => mb_substr($r->str('q'), 0, 100), 'Acción' => $r->str('accion') !== '' ? accion_label($r->str('accion')) : '',
                'Módulo' => $r->str('modulo') !== '' ? modulo_label($r->str('modulo')) : '',
                'Desde' => preg_match('/^\d{4}-\d{2}-\d{2}$/', $r->str('desde')) ? fecha($r->str('desde')) : '',
                'Hasta' => preg_match('/^\d{4}-\d{2}-\d{2}$/', $r->str('hasta')) ? fecha($r->str('hasta')) : '',
            ],
            ['Fecha y hora', 'Usuario', 'Acción', 'Módulo', 'Descripción', 'Paciente', 'IP'],
            array_map(fn($a) => [fecha_hora($a['creado_en']), $a['usuario_nombre'] . ($a['rol'] ? ' (' . etiqueta($a['rol']) . ')' : ''), accion_label($a['accion']), modulo_label($a['modulo']), $a['descripcion'], $a['paciente'] ?? '', $a['ip'] ?? ''], $rows),
            [2, 3, 1.6, 1.7, 5.4, 1.4, 1.2], 'auditoria_' . date('Ymd_His') . '.pdf', true);
    }

    public function documentosPdf(Request $r): void
    {
        $f = DocumentoController::filtros($r);
        $rows = DocumentoController::exportRows($r);
        $paciente = $f['paciente'] ? Database::one('SELECT codigo, nombres, apellidos FROM pacientes WHERE id = ?', [$f['paciente']]) : null;
        $conPaciente = !Auth::isPatient();
        $this->listado(Auth::isPatient() ? 'Mis documentos' : 'Listado de documentos', 'documentos', [
                'Paciente' => $paciente ? nombre_completo($paciente) . ' (' . $paciente['codigo'] . ')' : '', 'Búsqueda' => $f['q'],
                'Tipo' => ['examen' => 'Exámenes', 'imagen' => 'Imágenes', 'documento' => 'Documentos'][$f['grupo']] ?? '',
            ],
            array_values(array_filter(['Documento', $conPaciente ? 'Paciente' : null, 'Categoría', 'Fecha', 'Archivo', 'Cargado por'])),
            array_map(fn($d) => array_values(array_filter([
                $d['titulo'], $conPaciente ? $d['papellidos'] . ', ' . $d['pnombres'] . ' (' . $d['codigo'] . ')' : null, $d['categoria'], fecha($d['fecha_documento'] ?: $d['cargado_en']),
                strtoupper($d['extension']) . ', ' . bytes_legible((int) $d['tamano_bytes']), trim(($d['unombres'] ?? '') . ' ' . ($d['uapellidos'] ?? '')) ?: '—',
            ], fn($x) => $x !== null)), $rows),
            $conPaciente ? [4, 3.4, 2, 1.4, 1.6, 2.4] : [5, 2.4, 1.5, 1.8, 2.6], 'listado_documentos_' . date('Ymd') . '.pdf', false, $f['paciente'] ?: null);
    }

    // ------------------------------------------------------------ Partes comunes

    /**
     * Listado institucional: título, criterios aplicados (filtros y total) y la tabla. Se registra como documento
     * emitido (verificable) y queda en la auditoría.
     */
    private function listado(string $titulo, string $modulo, array $criterios, array $headers, array $rows, array $widths, string $archivo, bool $horizontal, ?int $pacienteId = null): never
    {
        [$pdf, $codigo] = Impresos::crear('listado', $titulo, $pacienteId, null, true, null, $horizontal ? ['orientacion' => 'horizontal'] : []);
        $n = count($rows);
        $pdf->title($titulo, $n === 1 ? '1 registro' : number_format($n, 0, ',', '.') . ' registros');
        $criterios = array_filter($criterios, fn($v) => $v !== null && $v !== '');
        $pdf->heading('Criterios del listado');
        $pdf->pairs(($criterios ?: ['Filtros' => 'Ninguno: incluye todos los registros']) + ['Total de registros' => (string) $n]);
        $pdf->heading('Registros');
        $rows ? $pdf->table($headers, $rows, $widths, 8) : $pdf->para('No hay registros con estos criterios.', 9, false, self::GRIS);
        Audit::log('exportar', $modulo, $titulo . ' exportado en PDF (' . $n . ' registros' . ($codigo ? ', código ' . $codigo : '') . ')', null, $pacienteId);
        Impresos::enviar($pdf, $archivo);
    }

    /** @return array{0:list<array>,1:string} médicos filtrados y nombre de la especialidad elegida */
    private function medicos(Request $r): array
    {
        $esp = (int) $r->str('especialidad') ?: null;
        $rows = \App\Repositories\MedicoRepository::all(mb_substr($r->str('q'), 0, 100), $esp);
        return [$rows, $esp ? (string) Database::value('SELECT nombre FROM especialidades WHERE id = ?', [$esp]) : ''];
    }

    /** Cuerpo del resumen clínico con las secciones elegidas en Configuración. */
    private function resumen(Pdf $pdf, array $pac, array $sec): void
    {
        $id = (int) $pac['id'];
        $resumen = PacienteRepository::clinicalSummary($id);
        $h = HistorialRepository::allFor($id);
        $s = HistorialSections::all();
        $on = fn(string $k) => in_array($k, $sec, true);

        $pdf->title('Resumen clínico', nombre_completo($pac) . ', ' . $pac['codigo']);
        $pdf->heading('Datos del paciente');
        $pdf->pairs([
            'Nombre' => nombre_completo($pac), 'Código' => $pac['codigo'], 'Documento de identidad' => $pac['documento_identidad'],
            'Nacimiento' => fecha($pac['fecha_nacimiento']) . ' (' . edad($pac['fecha_nacimiento']) . ')', 'Sexo' => $pac['sexo'] === 'F' ? 'Femenino' : 'Masculino',
            'Tipo de sangre' => $pac['tipo_sangre'] ?? '', 'Teléfono' => $pac['telefono'] ?? '', 'Correo' => $pac['email'] ?? '',
            'Contacto de emergencia' => trim(($pac['contacto_nombre'] ?? '') . ($pac['contacto_parentesco'] ? ' (' . $pac['contacto_parentesco'] . ')' : '') . ' ' . ($pac['contacto_telefono'] ?? '')),
        ]);
        if ($on('vitales') && ($v = PacienteRepository::lastVitals($id))) {
            $pdf->heading('Último control (' . fecha($v['fecha_hora']) . ')');
            $pdf->pairs($this->vitales($v));
        }
        if ($on('alertas')) {
            $pdf->heading('Alertas clínicas');
            $pdf->para('Alergias', 9, true);
            $resumen['alergias']
                ? $pdf->table(['Agente', 'Tipo', 'Severidad', 'Reacción'], array_map(fn($a) => [$a['agente'], etiqueta($a['tipo']), HistorialSections::SEVERIDAD[$a['severidad']], $a['reaccion'] ?? ''], $resumen['alergias']), [3, 2, 2, 4])
                : $pdf->para('Sin alergias registradas.', 9, false, self::GRIS);
            $pdf->para('Condiciones vigentes', 9, true);
            $pdf->para($resumen['enfermedades'] ? implode(', ', array_column($resumen['enfermedades'], 'nombre')) : 'Sin condiciones vigentes registradas.', 9.5);
            $pdf->para('Medicación vigente', 9, true);
            $resumen['medicamentos']
                ? $pdf->table(['Medicamento', 'Dosis', 'Frecuencia'], array_map(fn($m) => [$m['nombre'], $m['dosis'] ?? '', $m['frecuencia'] ?? ''], $resumen['medicamentos']), [4, 2, 5])
                : $pdf->para('Sin medicación vigente registrada.', 9, false, self::GRIS);
        }
        $on('antecedentes') && $this->section($pdf, 'Antecedentes personales', ['Tipo', 'Descripción', 'Fecha'], array_map(fn($x) => [HistorialSections::display($s['antecedentes'], 'tipo', $x['tipo']), $x['descripcion'], $x['fecha_aproximada'] ?? ''], $h['antecedentes']), [2, 6, 2]);
        $on('enfermedades') && $this->section($pdf, 'Enfermedades', ['Enfermedad', 'CIE-10', 'Estado', 'Diagnóstico'], array_map(fn($x) => [$x['nombre'] . ($x['cronica'] ? ' (crónica)' : ''), $x['codigo_cie10'] ?? '', etiqueta($x['estado']), fecha($x['fecha_diagnostico'], '')], $h['enfermedades']), [5, 1.5, 2, 2]);
        $on('cirugias') && $this->section($pdf, 'Cirugías', ['Procedimiento', 'Fecha', 'Centro'], array_map(fn($x) => [$x['procedimiento'], fecha($x['fecha']), $x['centro'] ?? ''], $h['cirugias']), [5, 2, 4]);
        $on('vacunas') && $this->section($pdf, 'Vacunas', ['Vacuna', 'Dosis', 'Aplicada', 'Próxima dosis'], array_map(fn($x) => [$x['vacuna'], $x['dosis'] ?? '', fecha($x['fecha_aplicacion']), fecha($x['proxima_dosis'], '')], $h['vacunas']), [5, 2, 2, 2]);
        $on('habitos') && $this->section($pdf, 'Hábitos de salud', ['Tipo', 'Descripción', 'Frecuencia'], array_map(fn($x) => [HistorialSections::display($s['habitos'], 'tipo', $x['tipo']), $x['descripcion'], $x['frecuencia'] ?? ''], $h['habitos']), [2, 5, 3]);
        $on('familiares') && $this->section($pdf, 'Antecedentes familiares', ['Familiar', 'Condición', 'Edad de inicio'], array_map(fn($x) => [HistorialSections::display($s['familiares'], 'parentesco', $x['parentesco']), $x['condicion'], $x['edad_inicio'] ? $x['edad_inicio'] . ' años' : ''], $h['familiares']), [3, 5, 2]);
        if ($on('consultas')) {
            $consultas = Database::all(
                "SELECT c.fecha_hora, c.motivo, u.nombres, u.apellidos,
                        (SELECT GROUP_CONCAT(d.descripcion SEPARATOR ', ') FROM diagnosticos d WHERE d.consulta_id = c.id AND d.tipo = 'principal') AS diagnostico
                   FROM consultas c JOIN medicos m ON m.id = c.medico_id JOIN usuarios u ON u.id = m.usuario_id
                  WHERE c.paciente_id = ? AND c.estado = 'realizada' ORDER BY c.fecha_hora DESC LIMIT 8",
                [$id]
            );
            $this->section($pdf, 'Consultas recientes', ['Fecha', 'Motivo', 'Diagnóstico', 'Médico'], array_map(fn($c) => [fecha($c['fecha_hora']), $c['motivo'], $c['diagnostico'] ?? '', nombre_completo($c)], $consultas), [2, 4, 4, 3]);
        }
    }

    private function consulta(int $id): array
    {
        $c = Database::one(
            "SELECT c.*, pa.codigo, pa.nombres AS pn, pa.apellidos AS pa, pa.fecha_nacimiento, pa.documento_identidad, u.nombres AS mn, u.apellidos AS ma,
                    e.nombre AS especialidad, m.colegiado, m.consultorio
               FROM consultas c JOIN pacientes pa ON pa.id = c.paciente_id JOIN medicos m ON m.id = c.medico_id
               JOIN usuarios u ON u.id = m.usuario_id JOIN especialidades e ON e.id = m.especialidad_id WHERE c.id = ?",
            [$id]
        );
        if (!$c) {
            throw new HttpException(Auth::isPatient() ? 403 : 404);
        }
        Policy::ownsRecord((int) $c['paciente_id']);
        return $c;
    }

    private function cabeceraConsulta(Pdf $pdf, array $c): void
    {
        $pdf->heading('Paciente y médico');
        $pdf->pairs([
            'Paciente' => $c['pn'] . ' ' . $c['pa'], 'Código' => $c['codigo'], 'Edad' => edad($c['fecha_nacimiento']),
            'Médico' => 'Dr(a). ' . $c['mn'] . ' ' . $c['ma'], 'Especialidad' => $c['especialidad'], 'Colegiado' => $c['colegiado'], 'Consultorio' => $c['consultorio'] ?? '',
        ]);
    }

    private function firma(Pdf $pdf, array $c): void
    {
        $pdf->signature('Dr(a). ' . $c['mn'] . ' ' . $c['ma'], $c['especialidad'] . ', Colegiado ' . $c['colegiado'], Config::get('doc_firma_texto', 'Firma y sello del médico tratante'));
    }

    private function vitales(array $v): array
    {
        $n = fn($k) => $v[$k] !== null ? (string) ($v[$k] + 0) : '';
        return array_filter([
            'Presión arterial' => $v['presion_sistolica'] ? $v['presion_sistolica'] . '/' . $v['presion_diastolica'] . ' mmHg' : '',
            'Frecuencia cardíaca' => $v['frecuencia_cardiaca'] ? $v['frecuencia_cardiaca'] . ' lpm' : '', 'Temperatura' => $n('temperatura') ? $n('temperatura') . ' °C' : '',
            'Saturación de oxígeno' => $v['saturacion_o2'] ? $v['saturacion_o2'] . ' %' : '', 'Peso' => $n('peso_kg') ? $n('peso_kg') . ' kg' : '', 'Talla' => $n('talla_cm') ? $n('talla_cm') . ' cm' : '',
        ]);
    }

    private function section(Pdf $pdf, string $title, array $headers, array $rows, array $widths): bool
    {
        $pdf->heading($title);
        $rows ? $pdf->table($headers, $rows, $widths) : $pdf->para('Sin registros.', 9, false, self::GRIS);
        return true;
    }
}
