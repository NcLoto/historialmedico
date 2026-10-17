<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Pager;
use App\Core\Policy;
use App\Core\Request;
use App\Core\Upload;
use App\Domain\DocumentoService;

final class DocumentoController extends Controller
{
    private const SELECT = "SELECT d.*, c.nombre AS categoria, c.grupo, p.codigo, p.nombres AS pnombres, p.apellidos AS papellidos,
            u.nombres AS unombres, u.apellidos AS uapellidos
        FROM documentos_medicos d
        JOIN categorias_documentos c ON c.id = d.categoria_id
        JOIN pacientes p ON p.id = d.paciente_id
        LEFT JOIN usuarios u ON u.id = d.cargado_por";

    public function index(Request $r): void
    {
        ['where' => $w, 'params' => $params, 'paciente' => $pacienteId, 'grupo' => $grupo, 'q' => $q] = self::filtros($r);
        $total = (int) Database::value("SELECT COUNT(*) FROM documentos_medicos d JOIN categorias_documentos c ON c.id = d.categoria_id JOIN pacientes p ON p.id = d.paciente_id WHERE $w", $params);
        $pager = Pager::make($total, $r->input('pagina', 1), 15);
        $rows = Database::all(self::SELECT . " WHERE $w ORDER BY d.cargado_en DESC, d.id DESC LIMIT 15 OFFSET " . $pager->offset(), $params);
        $filtrado = $pacienteId !== null && !Auth::isPatient()
            ? Database::one('SELECT id, codigo, nombres, apellidos FROM pacientes WHERE id = ?', [$pacienteId]) : null;

        $this->view('documentos/index', [
            'title' => Auth::isPatient() ? 'Mis documentos' : 'Documentos', 'rows' => $rows, 'pager' => $pager,
            'q' => $q, 'grupo' => $grupo, 'filtrado' => $filtrado, 'esPaciente' => Auth::isPatient(),
        ]);
    }

    /** Filtros del listado (también los usan las exportaciones). Un paciente solo ve los suyos. */
    public static function filtros(Request $r): array
    {
        $where = ['1=1'];
        $params = [];
        $pacienteId = null;
        if (Auth::isPatient()) {
            $pacienteId = Auth::patientId() ?? 0;
        } elseif ($r->str('paciente') !== '') {
            $pacienteId = (int) $r->str('paciente');
        }
        if ($pacienteId !== null) {
            $where[] = 'd.paciente_id = ?';
            $params[] = $pacienteId;
        }
        $grupo = in_array($r->str('grupo'), ['examen', 'imagen', 'documento'], true) ? $r->str('grupo') : '';
        if ($grupo !== '') {
            $where[] = 'c.grupo = ?';
            $params[] = $grupo;
        }
        $q = mb_substr($r->str('q'), 0, 100);
        foreach (array_filter(preg_split('/\s+/', $q)) as $t) {
            $like = '%' . addcslashes($t, '%_\\') . '%';
            $where[] = '(d.titulo LIKE ? OR p.nombres LIKE ? OR p.apellidos LIKE ? OR p.codigo LIKE ?)';
            array_push($params, $like, $like, $like, $like);
        }
        return ['where' => implode(' AND ', $where), 'params' => $params, 'paciente' => $pacienteId, 'grupo' => $grupo, 'q' => $q];
    }

    /** Filas del listado para exportar (hasta 5000), con los mismos filtros que la pantalla. */
    public static function exportRows(Request $r): array
    {
        $f = self::filtros($r);
        return Database::all(self::SELECT . ' WHERE ' . $f['where'] . ' ORDER BY d.cargado_en DESC, d.id DESC LIMIT 5000', $f['params']);
    }

    public function show(Request $r, array $p): void
    {
        $d = $this->find((int) $p['id']);
        Audit::log('consultar', 'documentos', 'Consulta de datos del documento: ' . mb_substr($d['titulo'], 0, 120), (int) $d['id'], (int) $d['paciente_id']);
        $this->view('documentos/show', ['title' => $d['titulo'], 'd' => $d, 'puedeVer' => Auth::can('documentos.descargar')]);
    }

    /** Entrega el archivo solo a quien tiene permiso y pertenencia; la ubicación real nunca se expone. */
    public function file(Request $r, array $p): void
    {
        $d = $this->find((int) $p['id']);
        $path = Upload::path($d['nombre_interno']);
        if (!is_file($path)) {
            Audit::log('error', 'documentos', 'Archivo físico no encontrado para el documento ' . $d['id'], (int) $d['id'], (int) $d['paciente_id']);
            throw new HttpException(404);
        }
        $descarga = $r->str('descargar') === '1';
        Audit::log('descargar', 'documentos', ($descarga ? 'Descarga' : 'Visualización') . ' de documento: ' . mb_substr($d['titulo'], 0, 120), (int) $d['id'], (int) $d['paciente_id']);

        $nombre = preg_replace('/[^A-Za-z0-9._-]+/', '_', $d['nombre_original']) ?: 'documento.' . $d['extension'];
        header('Content-Type: ' . $d['tipo_mime']);
        header('Content-Length: ' . filesize($path));
        header('Content-Disposition: ' . ($descarga ? 'attachment' : 'inline') . '; filename="' . $nombre . '"');
        header('X-Content-Type-Options: nosniff');
        header("Content-Security-Policy: default-src 'none'; img-src 'self' data:; style-src 'unsafe-inline'; frame-ancestors 'self'");
        header('Cache-Control: private, no-store');
        readfile($path);
        exit;
    }

    public function create(Request $r, array $p): void
    {
        $paciente = Policy::patient((int) $p['id']);
        $this->view('documentos/form', [
            'title' => 'Adjuntar documento', 'p' => $paciente, 'categorias' => DocumentoService::categoriasAgrupadas(),
            'consultas' => $this->consultasDe((int) $paciente['id']),
            'consultaId' => (int) old('consulta_id', (string) $r->str('consulta')),
            'maxMb' => \App\Core\Config::int('archivo_max_mb', 15),
        ]);
    }

    public function store(Request $r, array $p): void
    {
        $paciente = Policy::patient((int) $p['id']);
        $pid = (int) $paciente['id'];
        if (!$paciente['activo']) {
            throw new HttpException(403);
        }
        [$v, $up] = DocumentoService::validate($r->post, $_FILES['archivo'] ?? null, $pid);
        $consultaId = (int) $r->str('consulta_id') ?: null;
        if ($consultaId && !Database::value('SELECT id FROM consultas WHERE id = ? AND paciente_id = ?', [$consultaId, $pid])) {
            $v->addError('consulta_id', 'La consulta no corresponde a este paciente.');
        }
        if ($up['error']) {
            $v->addError('archivo', $up['error']);
        }
        if ($v->fails()) {
            $this->invalid($v, $r, "pacientes/$pid/documentos/nuevo");
        }
        DocumentoService::store($pid, $consultaId, $v, $_FILES['archivo'], $up);
        $this->flash('success', 'Documento adjuntado correctamente.');
        $this->redirect($consultaId ? "consultas/$consultaId" : "pacientes/$pid#documentos");
    }

    public function destroy(Request $r, array $p): void
    {
        $d = $this->find((int) $p['id']);
        Database::run('DELETE FROM documentos_medicos WHERE id = ?', [$d['id']]);
        @unlink(Upload::path($d['nombre_interno']));
        Audit::log('eliminar', 'documentos', 'Documento eliminado: ' . mb_substr($d['titulo'], 0, 120), (int) $d['id'], (int) $d['paciente_id']);
        $this->flash('success', 'Documento eliminado.');
        $this->redirect('documentos');
    }

    private function find(int $id): array
    {
        $d = Database::one(self::SELECT . ' WHERE d.id = ?', [$id]);
        if (!$d) {
            throw new HttpException(Auth::isPatient() ? 403 : 404);
        }
        Policy::ownsRecord((int) $d['paciente_id']);
        return $d;
    }

    private function consultasDe(int $pacienteId): array
    {
        $out = [];
        foreach (Database::all('SELECT id, fecha_hora, motivo FROM consultas WHERE paciente_id = ? ORDER BY fecha_hora DESC LIMIT 30', [$pacienteId]) as $c) {
            $out[$c['id']] = fecha($c['fecha_hora']) . ', ' . mb_strimwidth($c['motivo'], 0, 60, '…');
        }
        return $out;
    }
}
