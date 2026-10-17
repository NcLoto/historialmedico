<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Controller;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Passwords;
use App\Core\Request;
use App\Core\Validator;
use App\Repositories\MedicoRepository;

final class MedicoController extends Controller
{
    private const RULES = [
        'nombres' => 'required|max:80', 'apellidos' => 'required|max:80', 'email' => 'required|email|max:150',
        'especialidad_id' => 'required|int', 'colegiado' => 'required|regex:/^[0-9A-Za-z\-]{3,20}$/',
        'telefono' => 'phone', 'consultorio' => 'max:60',
    ];
    private const LABELS = ['nombres' => 'Nombres', 'apellidos' => 'Apellidos', 'email' => 'Correo', 'especialidad_id' => 'Especialidad',
        'colegiado' => 'Número de colegiado', 'telefono' => 'Teléfono', 'consultorio' => 'Consultorio'];

    public function index(Request $r): void
    {
        $q = mb_substr($r->str('q'), 0, 100);
        $esp = (int) $r->str('especialidad') ?: null;
        $this->view('medicos/index', [
            'title' => 'Médicos', 'rows' => MedicoRepository::all($q, $esp), 'q' => $q, 'esp' => $esp,
            'especialidades' => array_column(MedicoRepository::especialidades(), 'nombre', 'id'),
        ]);
    }

    public function create(Request $r): void
    {
        $this->view('medicos/form', ['title' => 'Registrar médico', 'm' => null, 'especialidades' => array_column(MedicoRepository::especialidades(), 'nombre', 'id')]);
    }

    public function store(Request $r): void
    {
        $v = $this->validated($r, 0, 0);
        if ($v->fails()) {
            $this->invalid($v, $r, 'medicos/nuevo');
        }
        $c = $v->clean();
        $c['email'] = mb_strtolower($c['email']);
        $temporal = Passwords::temporary();
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $uid = Database::insert('usuarios', [
                'rol_id' => (int) Database::value("SELECT id FROM roles WHERE codigo = 'medico'"), 'email' => $c['email'],
                'password_hash' => Passwords::hash($temporal), 'nombres' => $c['nombres'], 'apellidos' => $c['apellidos'], 'debe_cambiar_clave' => 1,
            ]);
            $id = Database::insert('medicos', [
                'usuario_id' => $uid, 'especialidad_id' => $c['especialidad_id'], 'colegiado' => $c['colegiado'],
                'telefono' => $c['telefono'], 'consultorio' => $c['consultorio'],
            ]);
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
        Audit::log('crear', 'medicos', 'Médico registrado, colegiado ' . $c['colegiado'], $id);
        Audit::log('crear', 'usuarios', 'Cuenta creada para médico ' . $c['email'], $uid);
        \App\Domain\Notificaciones::aAdministradores('cuentas', 'Nuevo médico: ' . $c['nombres'] . ' ' . $c['apellidos'], 'Colegiado ' . $c['colegiado'] . ', creado por ' . nombre_completo(\App\Core\Auth::user()), '/medicos?q=' . rawurlencode($c['colegiado']), 'info');
        $this->flash('info', 'Contraseña temporal de ' . $c['email'] . ': ' . $temporal . ' — anótela ahora, no se volverá a mostrar. El médico deberá cambiarla al ingresar.');
        $this->flash('success', 'Médico registrado.');
        $this->redirect('medicos');
    }

    public function edit(Request $r, array $p): void
    {
        $m = $this->find((int) $p['id']);
        $this->view('medicos/form', ['title' => 'Editar médico', 'm' => $m, 'especialidades' => array_column(MedicoRepository::especialidades(), 'nombre', 'id')]);
    }

    public function update(Request $r, array $p): void
    {
        $m = $this->find((int) $p['id']);
        $v = $this->validated($r, (int) $m['id'], (int) $m['usuario_id']);
        if ($v->fails()) {
            $this->invalid($v, $r, 'medicos/' . $m['id'] . '/editar');
        }
        $c = $v->clean();
        $c['email'] = mb_strtolower($c['email']);
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            Database::update('usuarios', ['nombres' => $c['nombres'], 'apellidos' => $c['apellidos'], 'email' => $c['email']], 'id = ?', [$m['usuario_id']]);
            Database::update('medicos', ['especialidad_id' => $c['especialidad_id'], 'colegiado' => $c['colegiado'], 'telefono' => $c['telefono'], 'consultorio' => $c['consultorio']], 'id = ?', [$m['id']]);
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
        $campos = Audit::changedFields($m, $c);
        Audit::log('editar', 'medicos', 'Médico actualizado' . ($campos ? ' (' . implode(', ', $campos) . ')' : ''), (int) $m['id'], null, ['campos' => $campos]);
        $this->flash('success', 'Datos del médico actualizados.');
        $this->redirect('medicos');
    }

    public function toggle(Request $r, array $p): void
    {
        $m = $this->find((int) $p['id']);
        $nuevo = $m['activo'] ? 0 : 1;
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        Database::update('medicos', ['activo' => $nuevo], 'id = ?', [$m['id']]);
        Database::update('usuarios', ['activo' => $nuevo], 'id = ?', [$m['usuario_id']]);
        $pdo->commit();
        Audit::log('estado', 'medicos', ($nuevo ? 'Médico reactivado' : 'Médico inactivado') . ', colegiado ' . $m['colegiado'], (int) $m['id']);
        $this->flash('success', $nuevo ? 'Médico reactivado. Su cuenta vuelve a tener acceso.' : 'Médico inactivado. Su cuenta ya no puede ingresar; sus consultas se conservan.');
        $this->redirect('medicos');
    }

    public function especialidades(Request $r): void
    {
        $this->view('medicos/especialidades', ['title' => 'Especialidades', 'rows' => MedicoRepository::especialidades()]);
    }

    public function especialidadStore(Request $r): void
    {
        $v = Validator::make($r->post, ['nombre' => 'required|max:80', 'descripcion' => 'max:255'], ['nombre' => 'Nombre', 'descripcion' => 'Descripción']);
        if (!$v->fails() && Database::value('SELECT id FROM especialidades WHERE nombre = ?', [$v->clean()['nombre']])) {
            $v->addError('nombre', 'Ya existe una especialidad con ese nombre.');
        }
        if ($v->fails()) {
            $this->invalid($v, $r, 'especialidades');
        }
        $id = Database::insert('especialidades', $v->clean());
        Audit::log('crear', 'medicos', 'Especialidad creada: ' . $v->clean()['nombre'], $id);
        $this->flash('success', 'Especialidad agregada.');
        $this->redirect('especialidades');
    }

    public function especialidadUpdate(Request $r, array $p): void
    {
        $id = (int) $p['id'];
        if (!Database::value('SELECT id FROM especialidades WHERE id = ?', [$id])) {
            throw new HttpException(404);
        }
        $v = Validator::make($r->post, ['nombre' => 'required|max:80', 'descripcion' => 'max:255'], ['nombre' => 'Nombre', 'descripcion' => 'Descripción']);
        if (!$v->fails() && Database::value('SELECT id FROM especialidades WHERE nombre = ? AND id <> ?', [$v->clean()['nombre'], $id])) {
            $v->addError('nombre', 'Ya existe una especialidad con ese nombre.');
        }
        if ($v->fails()) {
            $this->invalid($v, $r, 'especialidades');
        }
        Database::update('especialidades', $v->clean(), 'id = ?', [$id]);
        Audit::log('editar', 'medicos', 'Especialidad actualizada: ' . $v->clean()['nombre'], $id);
        $this->flash('success', 'Especialidad actualizada.');
        $this->redirect('especialidades');
    }

    private function find(int $id): array
    {
        $m = MedicoRepository::find($id);
        if (!$m) {
            throw new HttpException(404);
        }
        return $m;
    }

    private function validated(Request $r, int $medicoId, int $usuarioId): Validator
    {
        $v = Validator::make($r->post, self::RULES, self::LABELS);
        $c = $v->clean();
        if (!isset($v->errors()['email']) && $c['email'] && Database::value('SELECT id FROM usuarios WHERE email = ? AND id <> ?', [mb_strtolower($c['email']), $usuarioId])) {
            $v->addError('email', 'Ya existe una cuenta con ese correo.');
        }
        if (!isset($v->errors()['colegiado']) && $c['colegiado'] && Database::value('SELECT id FROM medicos WHERE colegiado = ? AND id <> ?', [$c['colegiado'], $medicoId])) {
            $v->addError('colegiado', 'Ya existe un médico con ese número de colegiado.');
        }
        if (!isset($v->errors()['especialidad_id']) && $c['especialidad_id'] && !Database::value('SELECT id FROM especialidades WHERE id = ?', [$c['especialidad_id']])) {
            $v->addError('especialidad_id', 'Seleccione una especialidad válida.');
        }
        return $v;
    }
}
