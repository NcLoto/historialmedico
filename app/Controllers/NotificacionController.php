<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Pager;
use App\Core\Request;
use App\Core\Validator;
use App\Domain\Notificaciones;

final class NotificacionController extends Controller
{
    public function index(Request $r): void
    {
        $uid = (int) Auth::user()['id'];
        $filtro = in_array($r->str('ver'), ['todas', 'no_leidas'], true) ? $r->str('ver') : 'todas';
        $tipo = isset(Notificaciones::TIPOS[$r->str('tipo')]) ? $r->str('tipo') : '';
        $where = 'usuario_id = ?' . ($filtro === 'no_leidas' ? ' AND leida_en IS NULL' : '') . ($tipo !== '' ? ' AND tipo = ?' : '');
        $params = $tipo !== '' ? [$uid, $tipo] : [$uid];
        $pager = Pager::make((int) Database::value("SELECT COUNT(*) FROM notificaciones WHERE $where", $params), $r->input('pagina', 1), 20);
        $rows = Database::all("SELECT * FROM notificaciones WHERE $where ORDER BY id DESC LIMIT 20 OFFSET " . $pager->offset(), $params);
        $this->view('notificaciones/index', [
            'title' => 'Notificaciones', 'rows' => $rows, 'pager' => $pager, 'filtro' => $filtro, 'tipo' => $tipo,
            'noLeidas' => Notificaciones::noLeidas($uid), 'tipos' => Notificaciones::tiposDe(Auth::user()['rol_codigo']),
        ]);
    }

    /** Marca como leída y lleva al destino. Solo destinos internos. */
    public function open(Request $r, array $p): void
    {
        $n = $this->find((int) $p['id']);
        Database::run('UPDATE notificaciones SET leida_en = COALESCE(leida_en, NOW()) WHERE id = ?', [$n['id']]);
        $destino = $n['enlace'] && str_starts_with($n['enlace'], '/') && !str_starts_with($n['enlace'], '//') ? $n['enlace'] : '/notificaciones';
        $this->redirect(ltrim($destino, '/'));
    }

    public function readAll(Request $r): void
    {
        $n = Database::run('UPDATE notificaciones SET leida_en = NOW() WHERE usuario_id = ? AND leida_en IS NULL', [Auth::user()['id']]);
        $this->flash('success', $n ? "Se marcaron $n notificaciones como leídas." : 'No había notificaciones sin leer.');
        $this->redirect($this->back($r));
    }

    public function toggle(Request $r, array $p): void
    {
        $n = $this->find((int) $p['id']);
        Database::run('UPDATE notificaciones SET leida_en = ? WHERE id = ?', [$n['leida_en'] ? null : date('Y-m-d H:i:s'), $n['id']]);
        $this->redirect($this->back($r));
    }

    public function destroy(Request $r, array $p): void
    {
        $n = $this->find((int) $p['id']);
        Database::run('DELETE FROM notificaciones WHERE id = ?', [$n['id']]);
        $this->flash('success', 'Notificación eliminada.');
        $this->redirect($this->back($r));
    }

    public function clearRead(Request $r): void
    {
        $n = Database::run('DELETE FROM notificaciones WHERE usuario_id = ? AND leida_en IS NOT NULL', [Auth::user()['id']]);
        $this->flash('success', $n ? "Se eliminaron $n notificaciones leídas." : 'No había notificaciones leídas para eliminar.');
        $this->redirect('notificaciones');
    }

    /** Contador para actualizar la campana sin recargar la página. */
    public function count(Request $r): void
    {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['no_leidas' => Notificaciones::noLeidas((int) Auth::user()['id'])]);
        exit;
    }

    public function preferences(Request $r): void
    {
        $u = Auth::user();
        $marcados = (array) ($r->post['tipos'] ?? []);
        $porCorreo = (array) ($r->post['correos'] ?? []);
        $tiposCorreo = \App\Domain\Correo::tiposPorCorreo();
        $cambios = [];
        foreach (array_keys(Notificaciones::tiposDe($u['rol_codigo'])) as $tipo) {
            if (in_array($tipo, Notificaciones::tiposApagados(), true)) {
                continue;   // desactivado por la administración: el campo llega deshabilitado y no debe pisar la preferencia
            }
            $activo = in_array($tipo, $marcados, true) ? 1 : 0;
            // El correo solo se guarda para los tipos que la administración envía por correo (el resto no llega en el formulario)
            $correo = in_array($tipo, $tiposCorreo, true) ? (in_array($tipo, $porCorreo, true) ? 1 : 0) : null;
            Database::run('INSERT INTO notificacion_preferencias (usuario_id, tipo, activo, correo) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE activo = VALUES(activo), correo = COALESCE(VALUES(correo), correo)', [$u['id'], $tipo, $activo, $correo]);
            if (!$activo) {
                $cambios[] = $tipo;
            }
        }
        Audit::log('editar', 'usuarios', 'Preferencias de notificación actualizadas' . ($cambios ? ' (desactivadas: ' . implode(', ', $cambios) . ')' : ''), (int) $u['id']);
        $this->flash('success', 'Preferencias de notificación guardadas.');
        $this->redirect('perfil/notificaciones');
    }

    /** Aviso institucional a uno o más roles. */
    public function broadcast(Request $r): void
    {
        $v = Validator::make($r->post, ['titulo' => 'required|max:160', 'mensaje' => 'max:255', 'nivel' => 'required|in:info,aviso,critico'], ['titulo' => 'Título', 'mensaje' => 'Mensaje', 'nivel' => 'Tipo de aviso']);
        $roles = array_values(array_intersect((array) ($r->post['roles'] ?? []), ['paciente', 'medico', 'administrador']));
        if (!$roles) {
            $v->addError('roles', 'Seleccione al menos un destinatario.');
        }
        if ($v->fails()) {
            $this->invalid($v, $r, 'configuracion/notificaciones');
        }
        $c = $v->clean();
        $n = Notificaciones::aviso($roles, $c['titulo'], $c['mensaje'], $c['nivel']);
        Audit::log('crear', 'configuracion', 'Aviso institucional enviado a ' . implode(', ', $roles) . " ($n destinatarios): " . mb_substr($c['titulo'], 0, 80));
        $this->flash('success', $n ? "Aviso enviado a $n usuario(s)." : 'Ningún usuario recibió el aviso (revise los destinatarios o la configuración de notificaciones).');
        $this->redirect('configuracion/notificaciones');
    }

    private function find(int $id): array
    {
        $n = Database::one('SELECT * FROM notificaciones WHERE id = ? AND usuario_id = ?', [$id, Auth::user()['id']]);
        if (!$n) {
            throw new HttpException(404);
        }
        return $n;
    }

    private function back(Request $r): string
    {
        $b = $r->str('volver');
        return ($b !== '' && $b[0] === '/' && !str_starts_with($b, '//')) ? ltrim($b, '/') : 'notificaciones';
    }
}
