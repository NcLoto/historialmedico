<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Pager;
use App\Core\Passwords;
use App\Core\Policy;
use App\Core\Request;
use App\Core\Validator;
use App\Domain\HistorialSections;
use App\Repositories\HistorialRepository;
use App\Repositories\PacienteRepository;

final class PacienteController extends Controller
{
    private const RULES = [
        'nombres'             => 'required|max:80',
        'apellidos'           => 'required|max:80',
        'documento_identidad' => 'required|regex:/^[0-9A-Za-z\-]{6,20}$/',
        'fecha_nacimiento'    => 'required|past',
        'sexo'                => 'required|in:F,M',
        'tipo_sangre'         => 'in:A+,A-,B+,B-,AB+,AB-,O+,O-',
        'estado_civil'        => 'in:soltero,casado,unido,divorciado,viudo',
        'ocupacion'           => 'max:80',
        'telefono'            => 'phone',
        'email'               => 'email|max:150',
        'acepta_correo'       => 'in:0,1',
        'direccion'           => 'max:200',
        'contacto_nombre'     => 'max:120',
        'contacto_parentesco' => 'max:40',
        'contacto_telefono'   => 'phone',
    ];
    private const LABELS = [
        'nombres' => 'Nombres', 'apellidos' => 'Apellidos', 'documento_identidad' => 'Documento de identidad',
        'fecha_nacimiento' => 'Fecha de nacimiento', 'sexo' => 'Sexo', 'tipo_sangre' => 'Tipo de sangre',
        'estado_civil' => 'Estado civil', 'ocupacion' => 'Ocupación', 'telefono' => 'Teléfono', 'email' => 'Correo',
        'direccion' => 'Dirección', 'contacto_nombre' => 'Nombre del contacto', 'contacto_parentesco' => 'Parentesco',
        'contacto_telefono' => 'Teléfono del contacto',
    ];

    /** Los pacientes llegan a su propia ficha desde "Mi historial médico". */
    public function mine(Request $r): void
    {
        $pid = Auth::isPatient() ? Auth::patientId() : null;
        if (!$pid) {
            throw new HttpException(Auth::isPatient() ? 404 : 403);
        }
        $this->redirect('pacientes/' . $pid);
    }

    public function index(Request $r): void
    {
        $data = $this->searchData($r);
        $this->view('pacientes/index', ['title' => 'Pacientes'] + $data);
    }

    /** Fragmento HTML para la búsqueda en vivo. */
    public function search(Request $r): void
    {
        $this->view('pacientes/_resultados', $this->searchData($r), null);
    }

    /** Sugerencias para el buscador de la barra superior (máximo 6, solo pacientes activos). */
    public function suggest(Request $r): void
    {
        $q = mb_substr($r->str('q'), 0, 100);
        $rows = mb_strlen($q) < 2 ? [] : PacienteRepository::search($q, 'activos', 6, 0, Auth::can('historial.ver'))['rows'];
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: private, no-store');
        echo json_encode(array_map(fn($p) => [
            'id' => (int) $p['id'], 'nombre' => $p['apellidos'] . ', ' . $p['nombres'], 'codigo' => $p['codigo'],
            'documento' => $p['documento_identidad'], 'edad' => edad($p['fecha_nacimiento']), 'alerta' => (int) $p['alergias_graves'] > 0,
            'url' => url('pacientes/' . $p['id']),
        ], $rows), JSON_UNESCAPED_UNICODE);
        exit;
    }

    private function searchData(Request $r): array
    {
        $q = mb_substr($r->str('q'), 0, 100);
        $estado = in_array($r->str('estado'), ['activos', 'inactivos', 'todos'], true) ? $r->str('estado') : 'activos';
        $perPage = 15;
        $count = PacienteRepository::search($q, $estado, 1, 0, false)['total'];
        $pager = Pager::make($count, $r->input('pagina', 1), $perPage);
        $res = PacienteRepository::search($q, $estado, $perPage, $pager->offset(), Auth::can('historial.ver'));
        return ['q' => $q, 'estado' => $estado, 'rows' => $res['rows'], 'pager' => $pager, 'clinico' => Auth::can('historial.ver')];
    }

    public function create(Request $r): void
    {
        $this->view('pacientes/form', ['title' => 'Registrar paciente', 'p' => null, 'puedeCuenta' => Auth::can('usuarios.crear')]);
    }

    public function store(Request $r): void
    {
        $v = $this->validated($r, null);
        $crearCuenta = Auth::can('usuarios.crear') && !empty($r->post['crear_cuenta']);
        if ($crearCuenta) {
            if (!$v->clean()['email']) {
                $v->addError('email', 'El correo es obligatorio para crear la cuenta de acceso.');
            } elseif (Database::value('SELECT id FROM usuarios WHERE email = ?', [mb_strtolower($v->clean()['email'])])) {
                $v->addError('email', 'Ya existe una cuenta de usuario con ese correo.');
            }
        }
        if ($v->fails()) {
            $this->invalid($v, $r, 'pacientes/nuevo');
        }
        $d = $this->normalize($v->clean());
        $d['codigo'] = PacienteRepository::nextCode();
        $d['creado_por'] = Auth::user()['id'];

        $temporal = null;
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            if ($crearCuenta) {
                $temporal = Passwords::temporary();
                $d['usuario_id'] = Database::insert('usuarios', [
                    'rol_id' => (int) Database::value("SELECT id FROM roles WHERE codigo = 'paciente'"),
                    'email' => $d['email'], 'password_hash' => Passwords::hash($temporal),
                    'nombres' => $d['nombres'], 'apellidos' => $d['apellidos'], 'debe_cambiar_clave' => 1,
                ]);
            }
            $id = Database::insert('pacientes', $d);
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
        Audit::log('crear', 'pacientes', 'Registro de paciente ' . $d['codigo'], $id, $id);
        if ($crearCuenta) {
            Audit::log('crear', 'usuarios', 'Cuenta de acceso creada junto con el paciente ' . $d['codigo'], (int) $d['usuario_id'], $id);
            $this->flash('info', 'Contraseña temporal de ' . $d['email'] . ': ' . $temporal . ' — anótela ahora, no se volverá a mostrar. Deberá cambiarla al ingresar.');
        }
        $this->flash('success', 'Paciente registrado con el código ' . $d['codigo'] . '.');
        $this->redirect('pacientes/' . $id);
    }

    public function edit(Request $r, array $p): void
    {
        $paciente = Policy::patient((int) $p['id']);
        $this->view('pacientes/form', ['title' => 'Editar paciente', 'p' => $paciente, 'puedeCuenta' => false]);
    }

    public function update(Request $r, array $p): void
    {
        $paciente = Policy::patient((int) $p['id']);
        $v = $this->validated($r, (int) $paciente['id']);
        if ($v->fails()) {
            $this->invalid($v, $r, 'pacientes/' . $paciente['id'] . '/editar');
        }
        $d = $this->normalize($v->clean());
        Database::update('pacientes', $d, 'id = ?', [$paciente['id']]);
        $campos = Audit::changedFields($paciente, $d);
        Audit::log('editar', 'pacientes', 'Datos personales actualizados' . ($campos ? ' (' . implode(', ', $campos) . ')' : ' (sin cambios)'), (int) $paciente['id'], (int) $paciente['id'], ['campos' => $campos]);
        $this->flash('success', 'Datos del paciente actualizados.');
        $this->redirect('pacientes/' . $paciente['id']);
    }

    public function toggle(Request $r, array $p): void
    {
        $paciente = Policy::patient((int) $p['id']);
        $nuevo = $paciente['activo'] ? 0 : 1;
        Database::update('pacientes', ['activo' => $nuevo], 'id = ?', [$paciente['id']]);
        Audit::log('estado', 'pacientes', ($nuevo ? 'Paciente reactivado ' : 'Paciente inactivado ') . $paciente['codigo'], (int) $paciente['id'], (int) $paciente['id']);
        $this->flash('success', $nuevo ? 'Paciente reactivado.' : 'Paciente inactivado. Su historial se conserva.');
        $this->redirect('pacientes/' . $paciente['id']);
    }

    public function show(Request $r, array $p): void
    {
        $paciente = Policy::patient((int) $p['id']);
        $id = (int) $paciente['id'];
        $clinico = Auth::can('historial.ver');
        $data = ['title' => nombre_completo($paciente), 'p' => $paciente, 'clinico' => $clinico];

        if ($clinico) {
            $data['resumen'] = PacienteRepository::clinicalSummary($id);
            $data['vitales'] = PacienteRepository::lastVitals($id);
            $data['secciones'] = HistorialSections::all();
            $data['historial'] = HistorialRepository::allFor($id);
        }
        if (Auth::can('consultas.ver')) {
            $data['consultas'] = Database::all(
                "SELECT c.id, c.fecha_hora, c.estado, c.motivo, u.nombres, u.apellidos,
                        (SELECT GROUP_CONCAT(d.descripcion SEPARATOR ', ') FROM diagnosticos d WHERE d.consulta_id = c.id AND d.tipo = 'principal') AS diagnostico
                   FROM consultas c JOIN medicos m ON m.id = c.medico_id JOIN usuarios u ON u.id = m.usuario_id
                  WHERE c.paciente_id = ? ORDER BY c.fecha_hora DESC LIMIT 8",
                [$id]
            );
            $data['consultas_total'] = (int) Database::value('SELECT COUNT(*) FROM consultas WHERE paciente_id = ?', [$id]);
        }
        if (Auth::can('documentos.ver')) {
            $data['documentos'] = Database::all(
                "SELECT d.id, d.titulo, d.fecha_documento, d.cargado_en, c.nombre AS categoria, c.grupo
                   FROM documentos_medicos d JOIN categorias_documentos c ON c.id = d.categoria_id
                  WHERE d.paciente_id = ? ORDER BY d.cargado_en DESC, d.id DESC LIMIT 8",
                [$id]
            );
            $data['documentos_total'] = (int) Database::value('SELECT COUNT(*) FROM documentos_medicos WHERE paciente_id = ?', [$id]);
        }
        if ($clinico && !Auth::isPatient()) {
            $data['cambios'] = Database::all(
                "SELECT usuario_nombre, accion, modulo, descripcion, creado_en FROM auditoria
                  WHERE paciente_id = ? AND accion <> 'consultar' ORDER BY id DESC LIMIT 15",
                [$id]
            );
        }
        Audit::log('consultar', 'pacientes', $clinico ? 'Consulta de ficha médica' : 'Consulta de datos personales', $id, $id);
        $this->view('pacientes/ficha', $data);
    }

    private function validated(Request $r, ?int $ignoreId): Validator
    {
        $v = Validator::make($r->post, self::RULES, self::LABELS);
        $c = $v->clean();
        if (!isset($v->errors()['documento_identidad']) && $c['documento_identidad'] !== null
            && Database::value('SELECT id FROM pacientes WHERE documento_identidad = ? AND id <> ?', [$c['documento_identidad'], $ignoreId ?? 0])) {
            $v->addError('documento_identidad', 'Ya existe un paciente con ese documento de identidad.');
        }
        return $v;
    }

    private function normalize(array $c): array
    {
        if (!empty($c['email'])) {
            $c['email'] = mb_strtolower($c['email']);
        }
        // Consentimiento para avisos de citas por correo: sin correo no tiene efecto
        $c['acepta_correo'] = ($c['acepta_correo'] ?? '') === '1' && !empty($c['email']) ? 1 : 0;
        return $c;
    }
}
