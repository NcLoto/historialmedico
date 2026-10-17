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
use App\Core\Request;
use App\Core\Validator;

final class UsuarioController extends Controller
{
    private const LABELS = ['nombres' => 'Nombres', 'apellidos' => 'Apellidos', 'email' => 'Correo', 'rol_id' => 'Rol', 'paciente_id' => 'Paciente'];

    /** Filtros del listado de usuarios, compartidos con la exportación CSV. @return array{0:string,1:array,2:string,3:?int,4:string} */
    public static function filtros(Request $r): array
    {
        $where = ['1=1'];
        $params = [];
        $q = mb_substr($r->str('q'), 0, 100);
        foreach (array_filter(preg_split('/\s+/', $q)) as $t) {
            $like = '%' . addcslashes($t, '%_\\') . '%';
            $where[] = '(u.nombres LIKE ? OR u.apellidos LIKE ? OR u.email LIKE ?)';
            array_push($params, $like, $like, $like);
        }
        $rol = (int) $r->str('rol') ?: null;
        if ($rol) {
            $where[] = 'u.rol_id = ?';
            $params[] = $rol;
        }
        $estado = in_array($r->str('estado'), ['activos', 'inactivos'], true) ? $r->str('estado') : '';
        if ($estado !== '') {
            $where[] = 'u.activo = ?';
            $params[] = $estado === 'activos' ? 1 : 0;
        }
        return [implode(' AND ', $where), $params, $q, $rol, $estado];
    }

    public function index(Request $r): void
    {
        [$w, $params, $q, $rol, $estado] = self::filtros($r);
        $pager = Pager::make((int) Database::value("SELECT COUNT(*) FROM usuarios u WHERE $w", $params), $r->input('pagina', 1), 15);
        $rows = Database::all(
            "SELECT u.id, u.nombres, u.apellidos, u.email, u.activo, u.ultimo_acceso, u.bloqueado_hasta, u.debe_cambiar_clave, r.nombre AS rol
               FROM usuarios u JOIN roles r ON r.id = u.rol_id WHERE $w ORDER BY u.apellidos, u.nombres LIMIT 15 OFFSET " . $pager->offset(),
            $params
        );
        $this->view('usuarios/index', [
            'title' => 'Usuarios', 'rows' => $rows, 'pager' => $pager, 'q' => $q, 'rol' => $rol, 'estado' => $estado,
            'roles' => array_column(Database::all('SELECT id, nombre FROM roles ORDER BY id'), 'nombre', 'id'), 'yo' => (int) Auth::user()['id'],
        ]);
    }

    public function create(Request $r): void
    {
        $this->view('usuarios/form', ['title' => 'Nuevo usuario', 'u' => null] + $this->catalogs());
    }

    public function store(Request $r): void
    {
        $v = Validator::make($r->post, ['nombres' => 'required|max:80', 'apellidos' => 'required|max:80', 'email' => 'required|email|max:150', 'rol_id' => 'required|int', 'paciente_id' => 'int'], self::LABELS);
        $c = $v->clean();
        $rol = !$v->errors() ? Database::one('SELECT id, codigo, nombre FROM roles WHERE id = ?', [$c['rol_id']]) : null;
        if (!isset($v->errors()['rol_id']) && (!$rol || $rol['codigo'] === 'medico')) {
            $v->addError('rol_id', $rol ? 'Los médicos se registran en el módulo Médicos.' : 'Seleccione un rol válido.');
        }
        if (!isset($v->errors()['email']) && $c['email'] && Database::value('SELECT id FROM usuarios WHERE email = ?', [mb_strtolower($c['email'])])) {
            $v->addError('email', 'Ya existe una cuenta con ese correo.');
        }
        $paciente = null;
        if ($rol && $rol['codigo'] === 'paciente') {
            $paciente = $c['paciente_id'] ? Database::one('SELECT id, codigo FROM pacientes WHERE id = ? AND usuario_id IS NULL', [$c['paciente_id']]) : null;
            if (!$paciente) {
                $v->addError('paciente_id', 'Seleccione el paciente al que pertenece la cuenta (debe existir y no tener cuenta).');
            }
        }
        if ($v->fails()) {
            $this->invalid($v, $r, 'usuarios/nuevo');
        }
        $temporal = Passwords::temporary();
        $email = mb_strtolower($c['email']);
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $id = Database::insert('usuarios', [
                'rol_id' => $rol['id'], 'email' => $email, 'password_hash' => Passwords::hash($temporal),
                'nombres' => $c['nombres'], 'apellidos' => $c['apellidos'], 'debe_cambiar_clave' => 1,
            ]);
            if ($paciente) {
                Database::update('pacientes', ['usuario_id' => $id], 'id = ?', [$paciente['id']]);
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
        Audit::log('crear', 'usuarios', 'Usuario creado con rol ' . $rol['nombre'] . ': ' . $email, $id, $paciente ? (int) $paciente['id'] : null);
        \App\Domain\Notificaciones::aAdministradores('cuentas', 'Nuevo usuario: ' . $c['nombres'] . ' ' . $c['apellidos'], $rol['nombre'] . ', ' . $email . ', creado por ' . nombre_completo(Auth::user()), '/usuarios?q=' . rawurlencode($email), 'info');
        $this->flash('info', 'Contraseña temporal de ' . $email . ': ' . $temporal . ' — anótela ahora, no se volverá a mostrar. Deberá cambiarla al ingresar.');
        $this->flash('success', 'Usuario creado.');
        $this->redirect('usuarios');
    }

    public function edit(Request $r, array $p): void
    {
        $this->view('usuarios/form', ['title' => 'Editar usuario', 'u' => $this->find((int) $p['id'])] + $this->catalogs());
    }

    public function update(Request $r, array $p): void
    {
        $u = $this->find((int) $p['id']);
        $v = Validator::make($r->post, ['nombres' => 'required|max:80', 'apellidos' => 'required|max:80', 'email' => 'required|email|max:150'], self::LABELS);
        $c = $v->clean();
        if (!isset($v->errors()['email']) && $c['email'] && Database::value('SELECT id FROM usuarios WHERE email = ? AND id <> ?', [mb_strtolower($c['email']), $u['id']])) {
            $v->addError('email', 'Ya existe una cuenta con ese correo.');
        }
        if ($v->fails()) {
            $this->invalid($v, $r, 'usuarios/' . $u['id'] . '/editar');
        }
        $c['email'] = mb_strtolower($c['email']);
        Database::update('usuarios', $c, 'id = ?', [$u['id']]);
        $campos = Audit::changedFields($u, $c);
        Audit::log('editar', 'usuarios', 'Usuario actualizado: ' . $c['email'] . ($campos ? ' (' . implode(', ', $campos) . ')' : ''), (int) $u['id'], null, ['campos' => $campos]);
        $this->flash('success', 'Usuario actualizado.');
        $this->redirect('usuarios');
    }

    public function toggle(Request $r, array $p): void
    {
        $u = $this->find((int) $p['id']);
        if ((int) $u['id'] === (int) Auth::user()['id']) {
            $this->flash('error', 'No puede desactivar su propia cuenta.');
            $this->redirect('usuarios');
        }
        $nuevo = $u['activo'] ? 0 : 1;
        if (!$nuevo && $u['rol_codigo'] === 'administrador' && !Database::value("SELECT COUNT(*) FROM usuarios WHERE rol_id = ? AND activo = 1 AND id <> ?", [$u['rol_id'], $u['id']])) {
            $this->flash('error', 'No se puede desactivar al último administrador activo.');
            $this->redirect('usuarios');
        }
        Database::update('usuarios', ['activo' => $nuevo], 'id = ?', [$u['id']]);
        if ($u['rol_codigo'] === 'medico') {
            Database::update('medicos', ['activo' => $nuevo], 'usuario_id = ?', [$u['id']]);
        }
        Audit::log('estado', 'usuarios', ($nuevo ? 'Usuario activado: ' : 'Usuario desactivado: ') . $u['email'], (int) $u['id']);
        $this->flash('success', $nuevo ? 'Usuario activado.' : 'Usuario desactivado. Ya no puede ingresar al sistema.');
        $this->redirect('usuarios');
    }

    /** Pantalla para generar una contraseña temporal o definir una nueva para cualquier cuenta (paciente, médico o personal). */
    public function passwordForm(Request $r, array $p): void
    {
        $u = $this->find((int) $p['id']);
        $this->view('usuarios/clave', [
            'title' => 'Contraseña de ' . nombre_completo($u),
            'u' => $u,
            'esYo' => (int) $u['id'] === (int) Auth::user()['id'],
        ]);
    }

    public function resetPassword(Request $r, array $p): void
    {
        $u = $this->find((int) $p['id']);
        $definir = $r->str('modo') === 'definir';
        $nueva = (string) ($r->post['password_nueva'] ?? '');
        if ($definir) {
            $error = Passwords::validate($nueva);
            if (!$error && $nueva !== (string) ($r->post['password_confirmar'] ?? '')) {
                $error = 'Las contraseñas no coinciden.';
            }
            if ($error) {
                \App\Core\Flash::keepErrors(['password_nueva' => $error]);
                $this->flash('error', 'No se cambió la contraseña. Revise el campo marcado.');
                $this->redirect('usuarios/' . $u['id'] . '/clave');
            }
        }
        $clave = $definir ? $nueva : Passwords::temporary();
        // La contraseña definida por el administrador siempre queda como temporal: el titular debe cambiarla al ingresar.
        Database::update('usuarios', ['password_hash' => Passwords::hash($clave), 'debe_cambiar_clave' => 1, 'intentos_fallidos' => 0, 'bloqueado_hasta' => null], 'id = ?', [$u['id']]);
        Audit::log('cambio_clave', 'usuarios', 'Contraseña ' . ($definir ? 'definida' : 'restablecida') . ' por el administrador: ' . $u['email'], (int) $u['id']);
        if ($definir) {
            $this->flash('success', 'Contraseña actualizada para ' . $u['email'] . '. Deberá cambiarla al ingresar; comuníquesela por un medio seguro.');
        } else {
            $this->flash('info', 'Contraseña temporal de ' . $u['email'] . ': ' . $clave . '. Anótela ahora, no se volverá a mostrar. Deberá cambiarla al ingresar.');
        }
        $this->redirect('usuarios');
    }

    private function find(int $id): array
    {
        $u = Database::one('SELECT u.*, r.codigo AS rol_codigo, r.nombre AS rol_nombre FROM usuarios u JOIN roles r ON r.id = u.rol_id WHERE u.id = ?', [$id]);
        if (!$u) {
            throw new HttpException(404);
        }
        return $u;
    }

    private function catalogs(): array
    {
        return [
            'roles' => array_column(Database::all("SELECT id, nombre FROM roles WHERE codigo <> 'medico' ORDER BY id"), 'nombre', 'id'),
            'pacientes' => array_column(Database::all("SELECT id, CONCAT(apellidos, ', ', nombres, ' (', codigo, ')') AS n FROM pacientes WHERE usuario_id IS NULL AND activo = 1 ORDER BY apellidos, nombres"), 'n', 'id'),
        ];
    }
}
