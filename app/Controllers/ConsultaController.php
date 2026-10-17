<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Config;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Flash;
use App\Core\HttpException;
use App\Core\Pager;
use App\Core\Policy;
use App\Core\Request;
use App\Core\Validator;
use App\Domain\DocumentoService;
use App\Repositories\MedicoRepository;

final class ConsultaController extends Controller
{
    private const RULES = [
        'fecha_hora'          => 'required|regex:/^\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}$/',
        'estado'              => 'required|in:programada,realizada,cancelada',
        'motivo'              => 'required|max:255',
        'enfermedad_actual'   => 'max:4000',
        'presion_sistolica'   => 'int|between:40,300',
        'presion_diastolica'  => 'int|between:20,200',
        'frecuencia_cardiaca' => 'int|between:20,250',
        'temperatura'         => 'decimal|between:30,45',
        'saturacion_o2'       => 'int|between:0,100',
        'peso_kg'             => 'decimal|between:0.5,500',
        'talla_cm'            => 'decimal|between:20,260',
        'examen_fisico'       => 'max:4000',
        'plan'                => 'max:4000',
        'notas_internas'      => 'max:4000',
    ];
    private const LABELS = [
        'fecha_hora' => 'Fecha y hora', 'estado' => 'Estado', 'motivo' => 'Motivo de consulta', 'enfermedad_actual' => 'Enfermedad actual',
        'presion_sistolica' => 'Presión sistólica', 'presion_diastolica' => 'Presión diastólica', 'frecuencia_cardiaca' => 'Frecuencia cardíaca',
        'temperatura' => 'Temperatura', 'saturacion_o2' => 'Saturación de oxígeno', 'peso_kg' => 'Peso', 'talla_cm' => 'Talla',
        'examen_fisico' => 'Examen físico', 'plan' => 'Plan', 'notas_internas' => 'Notas internas',
    ];

    private const BASE = "SELECT c.*, p.codigo, p.nombres AS pnombres, p.apellidos AS papellidos, p.fecha_nacimiento, p.activo AS paciente_activo,
            u.nombres AS mnombres, u.apellidos AS mapellidos, e.nombre AS especialidad, m.consultorio, m.usuario_id AS medico_usuario
        FROM consultas c
        JOIN pacientes p ON p.id = c.paciente_id
        JOIN medicos m ON m.id = c.medico_id
        JOIN usuarios u ON u.id = m.usuario_id
        JOIN especialidades e ON e.id = m.especialidad_id";

    public function index(Request $r): void
    {
        $esPaciente = Auth::isPatient();
        $where = ['1=1'];
        $params = [];
        $pacienteId = null;
        if ($esPaciente) {
            $pacienteId = Auth::patientId() ?? 0;
        } elseif ($r->str('paciente') !== '') {
            $pacienteId = (int) $r->str('paciente');
        }
        if ($pacienteId !== null) {
            $where[] = 'c.paciente_id = ?';
            $params[] = $pacienteId;
        }
        $medico = !$esPaciente ? MedicoRepository::byUser((int) Auth::user()['id']) : null;
        $soloMias = $medico && ($r->query === [] || $r->str('mias') === '1');
        if ($soloMias) {
            $where[] = 'c.medico_id = ?';
            $params[] = $medico['id'];
        }
        $estado = in_array($r->str('estado'), ['programada', 'realizada', 'cancelada'], true) ? $r->str('estado') : '';
        if ($estado !== '') {
            $where[] = 'c.estado = ?';
            $params[] = $estado;
        }
        $q = mb_substr($r->str('q'), 0, 100);
        foreach (array_filter(preg_split('/\s+/', $q)) as $t) {
            $like = '%' . addcslashes($t, '%_\\') . '%';
            $where[] = '(p.nombres LIKE ? OR p.apellidos LIKE ? OR p.codigo LIKE ? OR c.motivo LIKE ?)';
            array_push($params, $like, $like, $like, $like);
        }
        $w = implode(' AND ', $where);
        $total = (int) Database::value("SELECT COUNT(*) FROM consultas c JOIN pacientes p ON p.id = c.paciente_id WHERE $w", $params);
        $pager = Pager::make($total, $r->input('pagina', 1), 15);
        $order = $estado === 'programada' ? 'c.fecha_hora ASC' : 'c.fecha_hora DESC';
        $rows = Database::all(
            self::BASE . " WHERE $w ORDER BY $order LIMIT 15 OFFSET " . $pager->offset(),
            $params
        );
        $ids = array_column($rows, 'id');
        $diag = [];
        if ($ids) {
            $in = implode(',', array_fill(0, count($ids), '?'));
            foreach (Database::all("SELECT consulta_id, GROUP_CONCAT(descripcion SEPARATOR ', ') AS d FROM diagnosticos WHERE consulta_id IN ($in) AND tipo = 'principal' GROUP BY consulta_id", $ids) as $d) {
                $diag[$d['consulta_id']] = $d['d'];
            }
        }
        $filtrado = $pacienteId !== null && !$esPaciente ? Database::one('SELECT id, codigo, nombres, apellidos FROM pacientes WHERE id = ?', [$pacienteId]) : null;
        $this->view('consultas/index', [
            'title' => $esPaciente ? 'Mis consultas' : 'Consultas', 'rows' => $rows, 'diag' => $diag, 'pager' => $pager, 'q' => $q,
            'estado' => $estado, 'soloMias' => $soloMias, 'tieneMedico' => $medico !== null, 'filtrado' => $filtrado, 'esPaciente' => $esPaciente,
        ]);
    }

    public function show(Request $r, array $p): void
    {
        $c = $this->find((int) $p['id']);
        $id = (int) $c['id'];
        Audit::log('consultar', 'consultas', 'Consulta del detalle de la consulta del ' . fecha($c['fecha_hora']), $id, (int) $c['paciente_id']);
        $this->view('consultas/show', [
            'title' => 'Consulta del ' . fecha($c['fecha_hora']), 'c' => $c, 'esPaciente' => Auth::isPatient(),
            'diagnosticos' => Database::all("SELECT * FROM diagnosticos WHERE consulta_id = ? ORDER BY tipo, id", [$id]),
            'tratamientos' => Database::all('SELECT * FROM tratamientos WHERE consulta_id = ? ORDER BY id', [$id]),
            'documentos' => Auth::can('documentos.ver') ? Database::all(
                'SELECT d.id, d.titulo, d.extension, c.nombre AS categoria FROM documentos_medicos d JOIN categorias_documentos c ON c.id = d.categoria_id WHERE d.consulta_id = ? ORDER BY d.id', [$id]
            ) : [],
        ]);
    }

    public function create(Request $r, array $p): void
    {
        $paciente = $this->patientForConsulta((int) $p['id']);
        $this->view('consultas/form', $this->formData($paciente, null) + ['title' => 'Nueva consulta']);
    }

    public function store(Request $r, array $p): void
    {
        $paciente = $this->patientForConsulta((int) $p['id']);
        $pid = (int) $paciente['id'];
        $medico = $this->currentDoctor();
        [$v, $diag, $trat] = $this->validated($r);

        $adjunto = null;
        $tieneArchivo = isset($_FILES['archivo']) && ($_FILES['archivo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
        if ($tieneArchivo) {
            if (!Auth::can('documentos.crear')) {
                throw new HttpException(403);
            }
            [$vd, $up] = DocumentoService::validate($r->post, $_FILES['archivo'], $pid, 'doc_');
            foreach ($vd->errors() as $k => $msg) {
                $v->addError('doc_' . $k, $msg);
            }
            if ($up['error']) {
                $v->addError('archivo', $up['error']);
            }
            $adjunto = [$vd, $up];
        }
        if ($v->fails()) {
            Flash::keepInput($r->post);
            Flash::keepErrors($v->errors());
            Flash::add('error', 'Revise los campos marcados.' . ($tieneArchivo ? ' Si había seleccionado un archivo, vuelva a adjuntarlo.' : ''));
            $this->redirect("pacientes/$pid/consultas/nueva");
        }

        $c = $v->clean();
        $c['fecha_hora'] = str_replace('T', ' ', $c['fecha_hora']) . ':00';
        $c = $this->decimals($c);
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $id = Database::insert('consultas', $c + ['paciente_id' => $pid, 'medico_id' => $medico['id'], 'creado_por' => Auth::user()['id']]);
            $this->saveChildren($id, $diag, $trat);
            if (!empty($r->post['agregar_medicamentos']) && $c['estado'] === 'realizada') {
                $this->addMedications($pid, $id, $trat, $diag, $c['fecha_hora']);
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
        Audit::log('crear', 'consultas', 'Consulta registrada (' . $c['estado'] . ')', $id, $pid);
        if ($c['estado'] === 'programada') {
            \App\Domain\Notificaciones::aPaciente($pid, 'consulta_programada', 'Consulta programada para el ' . fecha_hora($c['fecha_hora']), $c['motivo'] . ', Dr(a). ' . nombre_completo($medico), '/consultas/' . $id, 'info', null, 'para el ' . fecha_larga($c['fecha_hora']));
        } elseif ($c['estado'] === 'realizada') {
            \App\Domain\Notificaciones::aPaciente($pid, 'consulta_registrada', 'Su consulta del ' . fecha($c['fecha_hora']) . ' está disponible', $c['motivo'], '/consultas/' . $id, 'exito');
        }
        if ($adjunto) {
            DocumentoService::store($pid, $id, $adjunto[0], $_FILES['archivo'], $adjunto[1]);
        }
        $this->flash('success', $c['estado'] === 'programada' ? 'Consulta programada.' : 'Consulta registrada.');
        $this->redirect("consultas/$id");
    }

    public function edit(Request $r, array $p): void
    {
        $c = $this->find((int) $p['id']);
        $paciente = Policy::patient((int) $c['paciente_id']);
        $this->view('consultas/form', $this->formData($paciente, $c) + ['title' => 'Editar consulta']);
    }

    public function update(Request $r, array $p): void
    {
        $c0 = $this->find((int) $p['id']);
        $id = (int) $c0['id'];
        [$v, $diag, $trat] = $this->validated($r);
        if ($v->fails()) {
            $this->invalid($v, $r, "consultas/$id/editar");
        }
        $c = $v->clean();
        $c['fecha_hora'] = str_replace('T', ' ', $c['fecha_hora']) . ':00';
        $c = $this->decimals($c);
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            Database::update('consultas', $c, 'id = ?', [$id]);
            Database::run('DELETE FROM diagnosticos WHERE consulta_id = ?', [$id]);
            Database::run('DELETE FROM tratamientos WHERE consulta_id = ?', [$id]);
            $this->saveChildren($id, $diag, $trat);
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
        $campos = Audit::changedFields($c0, $c);
        Audit::log('editar', 'consultas', 'Consulta modificada' . ($campos ? ' (' . implode(', ', $campos) . ')' : ''), $id, (int) $c0['paciente_id'], ['campos' => $campos]);
        if ($c0['estado'] === 'programada' && $c['estado'] === 'realizada') {
            \App\Domain\Notificaciones::aPaciente((int) $c0['paciente_id'], 'consulta_registrada', 'Su consulta del ' . fecha($c['fecha_hora']) . ' está disponible', $c['motivo'], '/consultas/' . $id, 'exito');
        }
        $this->flash('success', 'Consulta actualizada.');
        $this->redirect("consultas/$id");
    }

    /** Cancela una consulta programada. */
    public function status(Request $r, array $p): void
    {
        $c = $this->find((int) $p['id']);
        if ($c['estado'] !== 'programada') {
            $this->flash('error', 'Solo se pueden cancelar consultas programadas.');
            $this->redirect('consultas/' . $c['id']);
        }
        Database::update('consultas', ['estado' => 'cancelada'], 'id = ?', [$c['id']]);
        Audit::log('estado', 'consultas', 'Consulta programada cancelada', (int) $c['id'], (int) $c['paciente_id']);
        \App\Domain\Notificaciones::aPaciente((int) $c['paciente_id'], 'consulta_cancelada', 'Consulta del ' . fecha_hora($c['fecha_hora']) . ' cancelada', $c['motivo'] . '. Comuníquese con el hospital para reprogramarla.', '/consultas/' . $c['id'], 'aviso', null, 'del ' . fecha_larga($c['fecha_hora']));
        \App\Domain\Notificaciones::enviar((int) $c['medico_usuario'], 'consulta_cancelada', 'Consulta cancelada: ' . $c['pnombres'] . ' ' . $c['papellidos'], fecha_hora($c['fecha_hora']) . ', ' . $c['motivo'], '/consultas/' . $c['id'], 'aviso', (int) $c['paciente_id'], null, false, 'programada en su agenda para el ' . fecha_larga($c['fecha_hora']));
        $this->flash('success', 'Consulta cancelada.');
        $this->redirect('consultas/' . $c['id']);
    }

    // ------------------------------------------------------------------ privados

    private function find(int $id): array
    {
        $c = Database::one(self::BASE . ' WHERE c.id = ?', [$id]);
        if (!$c) {
            throw new HttpException(Auth::isPatient() ? 403 : 404);
        }
        Policy::ownsRecord((int) $c['paciente_id']);
        return $c;
    }

    private function patientForConsulta(int $id): array
    {
        $p = Policy::patient($id);
        if (!$p['activo']) {
            throw new HttpException(403);
        }
        return $p;
    }

    private function currentDoctor(): array
    {
        $m = MedicoRepository::byUser((int) Auth::user()['id']);
        if (!$m || !$m['activo']) {
            throw new HttpException(403);
        }
        return $m;
    }

    private function formData(array $paciente, ?array $c): array
    {
        $diag = $c ? Database::all('SELECT * FROM diagnosticos WHERE consulta_id = ? ORDER BY tipo, id', [$c['id']]) : [];
        $trat = $c ? Database::all('SELECT * FROM tratamientos WHERE consulta_id = ? ORDER BY id', [$c['id']]) : [];
        // Tras un error de validación se restauran las filas enviadas.
        if (is_array(Flash::oldValue('diag_desc', null))) {
            $diag = $this->zipRows(['tipo' => 'diag_tipo', 'codigo_cie10' => 'diag_codigo', 'descripcion' => 'diag_desc']);
            $trat = $this->zipRows(['tipo' => 'trat_tipo', 'descripcion' => 'trat_desc', 'dosis' => 'trat_dosis', 'frecuencia' => 'trat_frec', 'duracion' => 'trat_dur']);
        }
        return [
            'p' => $paciente, 'c' => $c, 'diag' => $diag, 'trat' => $trat,
            'categorias' => DocumentoService::categoriasAgrupadas(), 'maxMb' => Config::int('archivo_max_mb', 15),
            'puedeAdjuntar' => $c === null && Auth::can('documentos.crear'),
        ];
    }

    /** Reconstruye filas repetibles a partir de los arreglos enviados en el formulario anterior. */
    private function zipRows(array $map): array
    {
        $cols = [];
        foreach ($map as $field => $name) {
            $cols[$field] = (array) Flash::oldValue($name, []);
        }
        $rows = [];
        for ($i = 0, $n = max(array_map('count', $cols)); $i < $n; $i++) {
            $rows[] = array_map(fn($col) => (string) ($col[$i] ?? ''), $cols);
        }
        return $rows;
    }

    /** @return array{0:Validator,1:array,2:array} */
    private function validated(Request $r): array
    {
        $v = Validator::make($r->post, self::RULES, self::LABELS);
        $c = $v->clean();
        if ($c['estado'] === 'programada' && !$v->errors() && strtotime(str_replace('T', ' ', $c['fecha_hora'])) < time() - 86400) {
            $v->addError('fecha_hora', 'Una consulta programada debe tener fecha futura.');
        }
        $diag = [];
        $tipos = (array) ($r->post['diag_tipo'] ?? []);
        $cods = (array) ($r->post['diag_codigo'] ?? []);
        foreach ((array) ($r->post['diag_desc'] ?? []) as $i => $desc) {
            $desc = trim((string) $desc);
            if ($desc === '') {
                continue;
            }
            $cod = trim((string) ($cods[$i] ?? ''));
            if (mb_strlen($desc) > 255 || ($cod !== '' && !preg_match('/^[A-Za-z][0-9]{2}(\.[0-9A-Za-z]{1,4})?$/', $cod))) {
                $v->addError('diagnosticos', 'Revise los diagnósticos: la descripción admite hasta 255 caracteres y el código CIE-10 debe tener formato como E11.9.');
            }
            $diag[] = ['tipo' => ($tipos[$i] ?? '') === 'secundario' ? 'secundario' : 'principal', 'codigo_cie10' => $cod !== '' ? strtoupper($cod) : null, 'descripcion' => $desc];
        }
        if (($c['estado'] ?? '') === 'realizada' && !$diag) {
            $v->addError('diagnosticos', 'Una consulta realizada requiere al menos un diagnóstico.');
        }
        $trat = [];
        $tt = (array) ($r->post['trat_tipo'] ?? []);
        $td = (array) ($r->post['trat_dosis'] ?? []);
        $tf = (array) ($r->post['trat_frec'] ?? []);
        $tu = (array) ($r->post['trat_dur'] ?? []);
        foreach ((array) ($r->post['trat_desc'] ?? []) as $i => $desc) {
            $desc = trim((string) $desc);
            if ($desc === '') {
                continue;
            }
            $row = ['tipo' => in_array($tt[$i] ?? '', ['medicamento', 'procedimiento', 'indicacion', 'referencia'], true) ? $tt[$i] : 'medicamento',
                'descripcion' => $desc, 'dosis' => trim((string) ($td[$i] ?? '')) ?: null, 'frecuencia' => trim((string) ($tf[$i] ?? '')) ?: null, 'duracion' => trim((string) ($tu[$i] ?? '')) ?: null];
            if (mb_strlen($desc) > 255 || mb_strlen((string) $row['dosis']) > 80 || mb_strlen((string) $row['frecuencia']) > 80 || mb_strlen((string) $row['duracion']) > 80) {
                $v->addError('tratamientos', 'Revise los tratamientos: algún campo supera la longitud permitida.');
            }
            $trat[] = $row;
        }
        return [$v, $diag, $trat];
    }

    private function decimals(array $c): array
    {
        foreach (['temperatura', 'peso_kg', 'talla_cm'] as $k) {
            if ($c[$k] !== null) {
                $c[$k] = str_replace(',', '.', (string) $c[$k]);
            }
        }
        return $c;
    }

    private function saveChildren(int $consultaId, array $diag, array $trat): void
    {
        foreach ($diag as $d) {
            Database::insert('diagnosticos', ['consulta_id' => $consultaId] + $d);
        }
        foreach ($trat as $t) {
            Database::insert('tratamientos', ['consulta_id' => $consultaId] + $t);
        }
    }

    /** Copia a la lista de medicamentos vigentes los fármacos indicados en la consulta. */
    private function addMedications(int $pacienteId, int $consultaId, array $trat, array $diag, string $fecha): void
    {
        $motivo = $diag[0]['descripcion'] ?? null;
        foreach ($trat as $t) {
            if ($t['tipo'] !== 'medicamento') {
                continue;
            }
            Database::insert('medicamentos', [
                'paciente_id' => $pacienteId, 'consulta_id' => $consultaId, 'nombre' => mb_substr($t['descripcion'], 0, 120),
                'dosis' => $t['dosis'], 'via' => 'oral', 'frecuencia' => $t['frecuencia'], 'motivo' => $motivo ? mb_substr($motivo, 0, 150) : null,
                'fecha_inicio' => substr($fecha, 0, 10), 'activo' => 1, 'registrado_por' => Auth::user()['id'],
            ]);
        }
    }
}
