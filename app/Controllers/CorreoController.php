<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Config;
use App\Core\Controller;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Mailer;
use App\Core\Pager;
use App\Core\Request;
use App\Core\Validator;
use App\Domain\Correo;
use App\Domain\Notificaciones;

/** Configuración del correo, bandeja de salida, correo de prueba y bajas desde el enlace del correo. */
final class CorreoController extends Controller
{
    private const ESTADOS = ['pendiente' => 'Pendientes', 'enviado' => 'Enviados', 'fallido' => 'Fallidos', 'cancelado' => 'Cancelados'];

    public function index(Request $r): void
    {
        $estado = array_key_exists($r->str('estado'), self::ESTADOS) ? $r->str('estado') : '';
        $w = $estado !== '' ? 'WHERE estado = ?' : '';
        $par = $estado !== '' ? [$estado] : [];
        $pager = Pager::make((int) Database::value("SELECT COUNT(*) FROM correos_salientes $w", $par), $r->input('pagina', 1), 20);
        $ultima = Config::get('tareas_ultima_ejecucion');
        $this->view('configuracion/correo', [
            'title' => 'Configuración: Correo', 'seccion' => 'correo', 'cfg' => Config::all(), 'estadoMail' => Mailer::estado(),
            'tipos' => Notificaciones::TIPOS, 'tiposCorreo' => Correo::tiposPorCorreo(), 'estados' => self::ESTADOS, 'estado' => $estado,
            'filas' => Database::all("SELECT id, destinatario, nombre, tipo, asunto, estado, intentos, ultimo_error, creado_en, enviado_en FROM correos_salientes $w ORDER BY id DESC LIMIT 20 OFFSET " . $pager->offset(), $par),
            'pager' => $pager,
            'stats' => Database::one("SELECT COALESCE(SUM(estado = 'pendiente'), 0) AS pendientes, COALESCE(SUM(estado = 'fallido'), 0) AS fallidos,
                                             COALESCE(SUM(estado = 'enviado' AND enviado_en >= (NOW() - INTERVAL 1 DAY)), 0) AS enviados_24h FROM correos_salientes"),
            'tareaAtrasada' => $ultima === '' || strtotime($ultima) < time() - 15 * 60, 'ultimaTarea' => $ultima,
        ]);
    }

    public function update(Request $r): void
    {
        $post = $r->post;
        $v = Validator::make($post, ['correo_limite_hora' => 'required|int|between:10,5000'], ['correo_limite_hora' => 'Límite por hora']);
        if ($v->fails()) {
            $this->invalid($v, $r, 'configuracion/correo');
        }
        $tipos = array_values(array_intersect(array_keys(Correo::PLANTILLAS), (array) ($post['correo_tipos'] ?? [])));
        $antes = Config::all();
        $nuevo = ['correo_activo' => isset($post['correo_activo']) ? '1' : '0', 'correo_tipos' => implode(',', $tipos), 'correo_limite_hora' => (string) (int) $post['correo_limite_hora']];
        foreach ($nuevo as $k => $val) {
            Database::run('INSERT INTO configuracion (clave, valor) VALUES (?, ?) ON DUPLICATE KEY UPDATE valor = VALUES(valor)', [$k, $val]);
        }
        Config::reset();
        $cambios = Audit::diff($antes, $nuevo);
        Audit::log('editar', 'configuracion', 'Configuración de correo modificada' . ($cambios ? ' (' . implode(', ', array_keys($cambios)) . ')' : ' (sin cambios)'), null, null, ['cambios' => $cambios]);
        $this->flash('success', 'Configuración de correo guardada.');
        $this->redirect('configuracion/correo');
    }

    /** Envía un correo de prueba al instante, a la cuenta del administrador o a la dirección indicada. */
    public function test(Request $r): void
    {
        $u = Auth::user();
        $para = trim($r->str('para')) ?: $u['email'];
        if (!filter_var($para, FILTER_VALIDATE_EMAIL)) {
            $this->flash('error', 'Escriba una dirección de correo válida.');
            $this->redirect('configuracion/correo');
        }
        $res = Correo::enviarAhora($para, $u['nombres'], 'prueba', 'Correo de prueba', [
            'Este es un correo de prueba del sistema de historial médico.',
            'Si lo recibió, el envío de notificaciones por correo está configurado correctamente.',
        ], ['Abrir el sistema', Correo::urlAbsoluta('')], (int) $u['id']);
        Audit::log('editar', 'configuracion', 'Correo de prueba ' . ($res['ok'] ? 'enviado' : 'fallido') . ' a ' . $para);
        $modo = Mailer::driver() === 'archivo' ? ' (modo archivo: quedó guardado en storage/mail, no salió a internet)' : '';
        $this->flash($res['ok'] ? 'success' : 'error', $res['ok'] ? 'Correo de prueba enviado a ' . $para . $modo . '.' : 'No se pudo enviar: ' . $res['error']);
        $this->redirect('configuracion/correo');
    }

    /** Procesa la cola ahora (útil mientras no esté configurada la tarea programada). */
    public function process(Request $r): void
    {
        Notificaciones::recordatoriosProgramados();
        $res = Correo::procesar(50);
        Database::run("INSERT INTO configuracion (clave, valor) VALUES ('tareas_ultima_ejecucion', ?) ON DUPLICATE KEY UPDATE valor = VALUES(valor)", [date('Y-m-d H:i:s')]);
        $this->flash($res['fallidos'] ? 'warning' : 'success', sprintf('Cola procesada: %s, %s%s.', cantidad($res['enviados'], 'enviado', 'enviados'),
            cantidad($res['pendientes'], 'pendiente', 'pendientes'), $res['fallidos'] ? ', ' . cantidad($res['fallidos'], 'fallido', 'fallidos') : ''));
        $this->redirect('configuracion/correo');
    }

    public function show(Request $r, array $p): void
    {
        $c = Database::one('SELECT * FROM correos_salientes WHERE id = ?', [(int) $p['id']]);
        if (!$c) {
            throw new HttpException(404);
        }
        $this->view('configuracion/correo_ver', ['title' => 'Correo: ' . $c['asunto'], 'seccion' => 'correo', 'c' => $c, 'estados' => self::ESTADOS]);
    }

    public function retry(Request $r, array $p): void
    {
        $n = Database::run("UPDATE correos_salientes SET estado = 'pendiente', intentos = 0, ultimo_error = NULL, siguiente_intento = NOW() WHERE id = ? AND estado IN ('fallido', 'cancelado')", [(int) $p['id']]);
        if ($n) {
            Audit::log('editar', 'configuracion', 'Correo #' . (int) $p['id'] . ' devuelto a la cola');
        }
        $this->flash($n ? 'success' : 'info', $n ? 'El correo volvió a la cola; se enviará en la próxima ejecución.' : 'Ese correo no está fallido ni cancelado.');
        $this->redirect('configuracion/correo/' . (int) $p['id']);
    }

    public function cancel(Request $r, array $p): void
    {
        $n = Database::run("UPDATE correos_salientes SET estado = 'cancelado' WHERE id = ? AND estado = 'pendiente'", [(int) $p['id']]);
        $this->flash($n ? 'success' : 'info', $n ? 'Correo cancelado: no se enviará.' : 'Solo se pueden cancelar correos pendientes.');
        $this->redirect('configuracion/correo/' . (int) $p['id']);
    }

    // ------------------------------------------------------------ Baja desde el enlace del correo (sin sesión)

    public function unsubscribe(Request $r, array $p): void
    {
        $baja = Correo::leerBaja($p['token']);
        $this->view('auth/baja', ['title' => 'Avisos por correo', 'valido' => $baja !== null, 'token' => $p['token'],
            'tipoNombre' => $baja ? ($baja[1] === 'todos' ? 'todos los avisos' : mb_strtolower(Notificaciones::TIPOS[$baja[1]][0] ?? 'estos avisos')) : '', 'hecho' => false], 'auth');
    }

    public function unsubscribeConfirm(Request $r, array $p): void
    {
        $baja = Correo::leerBaja($p['token']);
        $actor = $baja ? Database::one('SELECT id, nombres, apellidos FROM usuarios WHERE id = ?', [$baja[0]]) : null;
        if (!$actor) {
            throw new HttpException(404);
        }
        $todos = $r->str('alcance') === 'todos';
        Correo::darDeBaja($baja[0], $todos ? 'todos' : $baja[1]);
        Audit::log('editar', 'usuarios', 'Baja de avisos por correo (' . ($todos ? 'todos' : $baja[1]) . ') desde el enlace del correo', $baja[0], null, null, $actor + ['rol_codigo' => null]);
        $this->view('auth/baja', ['title' => 'Avisos por correo', 'valido' => true, 'token' => $p['token'], 'hecho' => true,
            'tipoNombre' => $todos || $baja[1] === 'todos' ? 'todos los avisos' : mb_strtolower(Notificaciones::TIPOS[$baja[1]][0] ?? 'estos avisos')], 'auth');
    }
}
