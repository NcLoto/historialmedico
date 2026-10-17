<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Controller;
use App\Core\Csv;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;
use App\Domain\HistorialSections;
use App\Domain\Impresos;
use App\Repositories\MedicoRepository;

/**
 * Reportes por periodo con vista previa y descarga en PDF o CSV. Cada reporte exige, además de reportes.ver,
 * el permiso de la información que contiene (por eso el administrador no ve los reportes clínicos).
 */
final class ReporteController extends Controller
{
    /** clave => [título, descripción, permiso, icono] */
    public const REPORTES = [
        'consultas'  => ['Consultas por periodo', 'Consultas con paciente, médico, motivo y diagnóstico principal.', 'consultas.ver', 'stethoscope'],
        'alergias'   => ['Pacientes con alergias activas', 'Listado clínico por severidad para prevenir reacciones.', 'historial.ver', 'alert'],
        'vacunas'    => ['Vacunas pendientes', 'Dosis vencidas y próximas a vencer, con contacto del paciente.', 'historial.ver', 'syringe'],
        'pacientes'  => ['Pacientes registrados', 'Altas de pacientes en el periodo con sus datos de contacto.', 'pacientes.ver', 'users'],
        'documentos' => ['Documentos adjuntos', 'Exámenes, imágenes e informes cargados (solo datos del archivo).', 'documentos.ver', 'folder'],
        'auditoria'  => ['Actividad del sistema', 'Eventos de auditoría del periodo, con resumen por acción.', 'auditoria.ver', 'clipboard'],
    ];
    private const PREVIA = 25;

    public function index(Request $r): void
    {
        $disponibles = array_filter(self::REPORTES, fn($d) => Auth::can($d[2]));
        $this->view('reportes/index', ['title' => 'Reportes', 'reportes' => $disponibles]);
    }

    public function show(Request $r, array $p): void
    {
        $clave = $p['sec'];
        $def = self::REPORTES[$clave] ?? null;
        if (!$def) {
            throw new HttpException(404);
        }
        if (!Auth::can($def[2])) {
            throw new HttpException(403);
        }
        $f = $this->filtros($r, $clave);
        $data = $this->$clave($f);
        $formato = $r->str('formato');

        if ($formato === 'csv') {
            Audit::log('exportar', 'reportes', 'Reporte «' . $def[0] . '» exportado en CSV (' . count($data['filas']) . ' filas, ' . $this->periodo($f) . ')');
            Csv::send($clave . '_' . str_replace('-', '', $f['desde']) . '_' . str_replace('-', '', $f['hasta']) . '.csv', $data['columnas'], $data['filas']);
        }
        if ($formato === 'pdf') {
            [$pdf, $codigo] = Impresos::crear('reporte', $def[0]);
            $pdf->title($def[0], $data['subtitulo'] ?? $this->periodo($f));
            if (!empty($data['resumen'])) {
                $pdf->pairs($data['resumen']);
            }
            $data['filas']
                ? $pdf->table($data['columnas'], $data['filas'], $data['anchos'], 8)
                : $pdf->para('No hay registros para los criterios seleccionados.', 9.5, false, [95, 117, 128]);
            Audit::log('exportar', 'reportes', 'Reporte «' . $def[0] . '» exportado en PDF (' . count($data['filas']) . ' filas, ' . $this->periodo($f) . ')' . ($codigo ? ' (código ' . $codigo . ')' : ''));
            Impresos::enviar($pdf, $clave . '_' . str_replace('-', '', $f['desde']) . '_' . str_replace('-', '', $f['hasta']) . '.pdf');
        }

        $this->view('reportes/show', [
            'title' => $def[0], 'clave' => $clave, 'def' => $def, 'f' => $f, 'rep' => $data, 'previa' => self::PREVIA,
            'medicos' => $clave === 'consultas' ? array_column(array_map(fn($m) => ['id' => $m['id'], 'n' => $m['apellidos'] . ', ' . $m['nombres']], MedicoRepository::all()), 'n', 'id') : [],
            'query' => array_filter($f['query'], fn($v) => $v !== '' && $v !== null),
        ]);
    }

    // ------------------------------------------------------------ Filtros

    private function filtros(Request $r, string $clave): array
    {
        $fecha = function (string $v, string $def): string {
            $d = \DateTimeImmutable::createFromFormat('!Y-m-d', $v);
            return $d && $d->format('Y-m-d') === $v ? $v : $def;
        };
        $desde = $fecha($r->str('desde'), date('Y-m-d', strtotime('-30 days')));
        $hasta = $fecha($r->str('hasta'), date('Y-m-d'));
        if ($desde > $hasta) {
            [$desde, $hasta] = [$hasta, $desde];
        }
        $f = ['desde' => $desde, 'hasta' => $hasta];
        $miMedico = Auth::user()['rol_codigo'] === 'medico' ? MedicoRepository::byUser((int) Auth::user()['id']) : null;
        switch ($clave) {
            case 'consultas':
                $m = $r->str('medico', $miMedico ? (string) $miMedico['id'] : '');
                $f['medico'] = ctype_digit($m) ? (int) $m : null;
                $f['estado'] = in_array($r->str('estado'), ['programada', 'realizada', 'cancelada'], true) ? $r->str('estado') : '';
                break;
            case 'alergias':
                $f['severidad'] = in_array($r->str('severidad'), ['grave', 'moderada', 'leve'], true) ? $r->str('severidad') : '';
                break;
            case 'vacunas':
                $d = (int) $r->str('dias', '60');
                $f['dias'] = in_array($d, [15, 30, 60, 90, 180], true) ? $d : 60;
                break;
            case 'pacientes':
                $f['estado'] = in_array($r->str('estado'), ['activos', 'inactivos'], true) ? $r->str('estado') : '';
                break;
            case 'documentos':
                $f['grupo'] = in_array($r->str('grupo'), ['examen', 'imagen', 'documento'], true) ? $r->str('grupo') : '';
                break;
            case 'auditoria':
                $a = $r->str('accion');
                $f['accion'] = preg_match('/^[a-z_]{2,30}$/', $a) ? $a : '';
                break;
        }
        $f['query'] = $f;
        if ($clave === 'consultas') {
            $f['query']['medico'] = $f['medico'] === null ? 'todos' : $f['medico'];
        }
        return $f;
    }

    private function periodo(array $f): string
    {
        return 'del ' . fecha($f['desde']) . ' al ' . fecha($f['hasta']);
    }

    // ------------------------------------------------------------ Reportes

    private function consultas(array $f): array
    {
        $w = ['c.fecha_hora BETWEEN ? AND ?'];
        $p = [$f['desde'] . ' 00:00:00', $f['hasta'] . ' 23:59:59'];
        if ($f['medico']) {
            $w[] = 'c.medico_id = ?';
            $p[] = $f['medico'];
        }
        if ($f['estado']) {
            $w[] = 'c.estado = ?';
            $p[] = $f['estado'];
        }
        $rows = Database::all(
            "SELECT c.fecha_hora, c.estado, c.motivo, pa.codigo, pa.nombres AS pn, pa.apellidos AS pa, u.nombres AS mn, u.apellidos AS ma,
                    (SELECT GROUP_CONCAT(d.descripcion SEPARATOR ', ') FROM diagnosticos d WHERE d.consulta_id = c.id AND d.tipo = 'principal') AS diag
               FROM consultas c JOIN pacientes pa ON pa.id = c.paciente_id JOIN medicos m ON m.id = c.medico_id JOIN usuarios u ON u.id = m.usuario_id
              WHERE " . implode(' AND ', $w) . ' ORDER BY c.fecha_hora DESC LIMIT 5000',
            $p
        );
        $cuenta = array_count_values(array_column($rows, 'estado'));
        $medico = $f['medico'] ? MedicoRepository::find($f['medico']) : null;
        return [
            'subtitulo' => ucfirst_mb($this->periodo($f)) . ($medico ? ', Dr(a). ' . $medico['nombres'] . ' ' . $medico['apellidos'] : ', Todos los médicos'),
            'resumen' => ['Total de consultas' => (string) count($rows), 'Realizadas' => (string) ($cuenta['realizada'] ?? 0), 'Programadas' => (string) ($cuenta['programada'] ?? 0), 'Canceladas' => (string) ($cuenta['cancelada'] ?? 0)],
            'columnas' => ['Fecha', 'Paciente', 'Código', 'Médico', 'Motivo', 'Diagnóstico', 'Estado'],
            'anchos' => [2, 3, 1.6, 2.6, 3.4, 3.4, 1.6],
            'filas' => array_map(fn($c) => [fecha_hora($c['fecha_hora']), $c['pa'] . ', ' . $c['pn'], $c['codigo'], $c['ma'] . ', ' . $c['mn'], $c['motivo'], (string) $c['diag'], ucfirst_mb($c['estado'])], $rows),
        ];
    }

    private function alergias(array $f): array
    {
        $w = 'a.activa = 1 AND pa.activo = 1' . ($f['severidad'] ? ' AND a.severidad = ?' : '');
        $rows = Database::all(
            "SELECT a.agente, a.tipo, a.severidad, a.reaccion, pa.codigo, pa.nombres, pa.apellidos, pa.telefono,
                    (SELECT MIN(c.fecha_hora) FROM consultas c WHERE c.paciente_id = pa.id AND c.estado = 'programada' AND c.fecha_hora >= NOW()) AS proxima
               FROM alergias a JOIN pacientes pa ON pa.id = a.paciente_id WHERE $w
              ORDER BY FIELD(a.severidad, 'grave', 'moderada', 'leve'), pa.apellidos, a.agente LIMIT 5000",
            $f['severidad'] ? [$f['severidad']] : []
        );
        $cuenta = array_count_values(array_column($rows, 'severidad'));
        return [
            'subtitulo' => 'Alergias vigentes al ' . fecha(date('Y-m-d')) . ($f['severidad'] ? ', Severidad ' . $f['severidad'] : ''),
            'resumen' => ['Alergias activas' => (string) count($rows), 'Graves' => (string) ($cuenta['grave'] ?? 0), 'Moderadas' => (string) ($cuenta['moderada'] ?? 0), 'Pacientes' => (string) count(array_unique(array_column($rows, 'codigo')))],
            'columnas' => ['Paciente', 'Código', 'Agente', 'Tipo', 'Severidad', 'Reacción', 'Próxima consulta'],
            'anchos' => [3, 1.6, 2.4, 1.8, 1.6, 3.6, 2.2],
            'filas' => array_map(fn($a) => [$a['apellidos'] . ', ' . $a['nombres'], $a['codigo'], $a['agente'], etiqueta($a['tipo']), HistorialSections::SEVERIDAD[$a['severidad']], (string) $a['reaccion'], fecha_hora($a['proxima'], 'Sin programar')], $rows),
            'nota_periodo' => false,
        ];
    }

    private function vacunas(array $f): array
    {
        $rows = Database::all(
            'SELECT v.vacuna, v.dosis, v.fecha_aplicacion, v.proxima_dosis, pa.codigo, pa.nombres, pa.apellidos, pa.telefono, pa.email
               FROM vacunas v JOIN pacientes pa ON pa.id = v.paciente_id
              WHERE pa.activo = 1 AND v.proxima_dosis IS NOT NULL AND v.proxima_dosis <= (CURDATE() + INTERVAL ? DAY)
              ORDER BY v.proxima_dosis LIMIT 5000',
            [$f['dias']]
        );
        $hoy = date('Y-m-d');
        $vencidas = count(array_filter($rows, fn($v) => $v['proxima_dosis'] < $hoy));
        return [
            'subtitulo' => 'Dosis vencidas y con fecha en los próximos ' . $f['dias'] . ' días (al ' . fecha($hoy) . ')',
            'resumen' => ['Dosis pendientes' => (string) count($rows), 'Vencidas' => (string) $vencidas, 'Por vencer' => (string) (count($rows) - $vencidas)],
            'columnas' => ['Paciente', 'Código', 'Vacuna', 'Dosis', 'Última aplicación', 'Próxima dosis', 'Estado', 'Teléfono'],
            'anchos' => [3, 1.6, 2.6, 1.4, 1.8, 1.8, 1.4, 1.8],
            'filas' => array_map(fn($v) => [$v['apellidos'] . ', ' . $v['nombres'], $v['codigo'], $v['vacuna'], (string) $v['dosis'], fecha($v['fecha_aplicacion']), fecha($v['proxima_dosis']), $v['proxima_dosis'] < $hoy ? 'Vencida' : 'Por vencer', (string) $v['telefono']], $rows),
            'nota_periodo' => false,
        ];
    }

    private function pacientes(array $f): array
    {
        $w = 'p.creado_en BETWEEN ? AND ?' . ($f['estado'] === 'activos' ? ' AND p.activo = 1' : ($f['estado'] === 'inactivos' ? ' AND p.activo = 0' : ''));
        $rows = Database::all("SELECT p.* FROM pacientes p WHERE $w ORDER BY p.creado_en DESC LIMIT 5000", [$f['desde'] . ' 00:00:00', $f['hasta'] . ' 23:59:59']);
        return [
            'subtitulo' => 'Registrados ' . $this->periodo($f),
            'resumen' => ['Pacientes registrados' => (string) count($rows), 'Con cuenta de acceso' => (string) count(array_filter($rows, fn($p) => $p['usuario_id'])), 'Mujeres' => (string) count(array_filter($rows, fn($p) => $p['sexo'] === 'F')), 'Hombres' => (string) count(array_filter($rows, fn($p) => $p['sexo'] === 'M'))],
            'columnas' => ['Registrado', 'Paciente', 'Código', 'Documento', 'Edad', 'Teléfono', 'Correo', 'Estado'],
            'anchos' => [1.8, 3, 1.6, 2.2, 1.2, 1.6, 3, 1.4],
            'filas' => array_map(fn($p) => [fecha($p['creado_en']), $p['apellidos'] . ', ' . $p['nombres'], $p['codigo'], $p['documento_identidad'], edad($p['fecha_nacimiento']), (string) $p['telefono'], (string) $p['email'], $p['activo'] ? 'Activo' : 'Inactivo'], $rows),
        ];
    }

    private function documentos(array $f): array
    {
        $w = 'd.cargado_en BETWEEN ? AND ?' . ($f['grupo'] ? ' AND c.grupo = ?' : '');
        $p = [$f['desde'] . ' 00:00:00', $f['hasta'] . ' 23:59:59'];
        if ($f['grupo']) {
            $p[] = $f['grupo'];
        }
        $rows = Database::all(
            "SELECT d.titulo, d.extension, d.tamano_bytes, d.cargado_en, c.nombre AS categoria, c.grupo, pa.codigo, u.nombres AS un, u.apellidos AS ua
               FROM documentos_medicos d JOIN categorias_documentos c ON c.id = d.categoria_id JOIN pacientes pa ON pa.id = d.paciente_id LEFT JOIN usuarios u ON u.id = d.cargado_por
              WHERE $w ORDER BY d.cargado_en DESC LIMIT 5000",
            $p
        );
        $g = array_count_values(array_column($rows, 'grupo'));
        return [
            'subtitulo' => 'Cargados ' . $this->periodo($f),
            'resumen' => ['Documentos' => (string) count($rows), 'Exámenes' => (string) ($g['examen'] ?? 0), 'Imágenes' => (string) ($g['imagen'] ?? 0), 'Volumen' => bytes_legible((int) array_sum(array_column($rows, 'tamano_bytes')))],
            'columnas' => ['Fecha de carga', 'Título', 'Categoría', 'Paciente', 'Formato', 'Tamaño', 'Cargado por'],
            'anchos' => [2, 4, 2.6, 1.6, 1.2, 1.2, 2.6],
            'filas' => array_map(fn($d) => [fecha_hora($d['cargado_en']), $d['titulo'], $d['categoria'], $d['codigo'], strtoupper($d['extension']), bytes_legible((int) $d['tamano_bytes']), trim(($d['un'] ?? '') . ' ' . ($d['ua'] ?? ''))], $rows),
        ];
    }

    private function auditoria(array $f): array
    {
        $w = 'a.creado_en BETWEEN ? AND ?' . ($f['accion'] ? ' AND a.accion = ?' : '');
        $p = [$f['desde'] . ' 00:00:00', $f['hasta'] . ' 23:59:59'];
        if ($f['accion']) {
            $p[] = $f['accion'];
        }
        $rows = Database::all("SELECT a.creado_en, a.usuario_nombre, a.accion, a.modulo, a.descripcion, a.ip FROM auditoria a WHERE $w ORDER BY a.id DESC LIMIT 5000", $p);
        $acc = array_count_values(array_column($rows, 'accion'));
        arsort($acc);
        $resumen = ['Eventos' => (string) count($rows), 'Usuarios distintos' => (string) count(array_unique(array_column($rows, 'usuario_nombre')))];
        foreach (array_slice($acc, 0, 4, true) as $a => $n) {
            $resumen[accion_label($a)] = (string) $n;
        }
        return [
            'subtitulo' => ucfirst_mb($this->periodo($f)) . ($f['accion'] ? ', ' . accion_label($f['accion']) : ''),
            'resumen' => $resumen,
            'columnas' => ['Fecha y hora', 'Usuario', 'Acción', 'Módulo', 'Descripción', 'IP'],
            'anchos' => [2, 2.8, 1.8, 1.8, 5, 1.6],
            'filas' => array_map(fn($a) => [fecha_hora($a['creado_en']), $a['usuario_nombre'], accion_label($a['accion']), modulo_label($a['modulo']), $a['descripcion'], (string) $a['ip']], $rows),
            'acciones' => array_column(Database::all('SELECT DISTINCT accion FROM auditoria ORDER BY accion'), 'accion'),
        ];
    }
}
