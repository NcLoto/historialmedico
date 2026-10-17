<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Controller;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;

final class RolController extends Controller
{
    private const MODULOS = [
        'pacientes' => 'Pacientes (datos personales)', 'historial' => 'Historial clínico', 'consultas' => 'Consultas',
        'documentos' => 'Documentos médicos', 'medicos' => 'Médicos', 'usuarios' => 'Usuarios', 'roles' => 'Roles y permisos',
        'auditoria' => 'Auditoría', 'configuracion' => 'Configuración',
    ];
    /** Permisos sin los cuales el administrador perdería el control del sistema. */
    private const ESENCIALES_ADMIN = ['roles.ver', 'roles.editar', 'usuarios.ver'];

    public function index(Request $r): void
    {
        $rows = Database::all(
            'SELECT r.id, r.codigo, r.nombre, r.descripcion,
                    (SELECT COUNT(*) FROM usuarios u WHERE u.rol_id = r.id) AS usuarios,
                    (SELECT COUNT(*) FROM rol_permisos rp WHERE rp.rol_id = r.id) AS permisos
               FROM roles r ORDER BY r.id'
        );
        $this->view('roles/index', ['title' => 'Roles y permisos', 'rows' => $rows, 'total' => (int) Database::value('SELECT COUNT(*) FROM permisos')]);
    }

    public function edit(Request $r, array $p): void
    {
        $rol = $this->find((int) $p['id']);
        $matriz = [];
        foreach (Database::all('SELECT id, modulo, accion, descripcion FROM permisos ORDER BY modulo, FIELD(accion, "ver", "crear", "editar", "descargar", "eliminar")') as $perm) {
            $matriz[$perm['modulo']][$perm['accion']] = $perm;
        }
        $orden = array_values(array_intersect(array_keys(self::MODULOS), array_keys($matriz)));
        $this->view('roles/edit', [
            'title' => 'Permisos de ' . $rol['nombre'], 'rol' => $rol, 'matriz' => $matriz, 'modulos' => self::MODULOS, 'orden' => $orden,
            'asignados' => array_column(Database::all('SELECT permiso_id FROM rol_permisos WHERE rol_id = ?', [$rol['id']]), 'permiso_id', 'permiso_id'),
            'acciones' => ['ver' => 'Ver', 'crear' => 'Crear', 'editar' => 'Editar', 'descargar' => 'Descargar', 'eliminar' => 'Eliminar'],
        ]);
    }

    public function update(Request $r, array $p): void
    {
        $rol = $this->find((int) $p['id']);
        $existentes = array_column(Database::all("SELECT id, CONCAT(modulo, '.', accion) AS codigo FROM permisos"), 'codigo', 'id');
        $nuevos = [];
        foreach ((array) ($r->post['permisos'] ?? []) as $id) {
            if (isset($existentes[(int) $id])) {
                $nuevos[(int) $id] = $existentes[(int) $id];
            }
        }
        if ($rol['codigo'] === 'administrador') {
            $faltan = array_diff(self::ESENCIALES_ADMIN, $nuevos);
            if ($faltan) {
                $this->flash('error', 'El administrador debe conservar estos permisos para no perder el control del sistema: ' . implode(', ', $faltan) . '.');
                $this->redirect('roles/' . $rol['id']);
            }
        }
        $actuales = array_column(Database::all('SELECT rp.permiso_id, CONCAT(p.modulo, ".", p.accion) AS codigo FROM rol_permisos rp JOIN permisos p ON p.id = rp.permiso_id WHERE rp.rol_id = ?', [$rol['id']]), 'codigo', 'permiso_id');
        $agregados = array_values(array_diff($nuevos, $actuales));
        $quitados = array_values(array_diff($actuales, $nuevos));

        $pdo = Database::pdo();
        $pdo->beginTransaction();
        Database::run('DELETE FROM rol_permisos WHERE rol_id = ?', [$rol['id']]);
        foreach (array_keys($nuevos) as $pid) {
            Database::insert('rol_permisos', ['rol_id' => $rol['id'], 'permiso_id' => $pid]);
        }
        $pdo->commit();

        if ($agregados || $quitados) {
            \App\Domain\Notificaciones::aAdministradores('seguridad', 'Permisos del rol ' . $rol['nombre'] . ' modificados', '+' . count($agregados) . ' / -' . count($quitados) . ' permisos, por ' . nombre_completo(\App\Core\Auth::user()), '/roles/' . $rol['id'], 'aviso');
            Audit::log('permisos', 'roles', 'Permisos del rol ' . $rol['nombre'] . ' modificados (+' . count($agregados) . ' / -' . count($quitados) . ')', (int) $rol['id'], null, ['agregados' => $agregados, 'quitados' => $quitados]);
            $this->flash('success', 'Permisos del rol actualizados. Se aplican en la siguiente solicitud de cada usuario.');
        } else {
            $this->flash('info', 'No hubo cambios en los permisos.');
        }
        $this->redirect('roles');
    }

    private function find(int $id): array
    {
        $rol = Database::one('SELECT * FROM roles WHERE id = ?', [$id]);
        if (!$rol) {
            throw new HttpException(404);
        }
        return $rol;
    }
}
