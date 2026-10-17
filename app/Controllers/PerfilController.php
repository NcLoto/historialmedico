<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Flash;
use App\Core\HttpException;
use App\Core\Passwords;
use App\Core\Request;
use App\Core\Validator;
use App\Domain\Avatar;
use App\Domain\Correo;
use App\Domain\Notificaciones;
use App\Repositories\MedicoRepository;
use App\Repositories\PacienteRepository;

/**
 * Perfil de la cuenta: datos personales y foto, seguridad (contraseña y actividad de acceso), notificaciones y tema.
 * Los datos de identidad (nombre, DPI, fecha de nacimiento, especialidad, colegiado) los gestiona la administración;
 * cada usuario actualiza su foto y sus datos de contacto.
 */
final class PerfilController extends Controller
{
    private const EVENTOS = [
        'login' => 'Inicio de sesión', 'logout' => 'Cierre de sesión', 'login_fallido' => 'Intento fallido',
        'login_bloqueado' => 'Intento en cuenta bloqueada', 'cambio_clave' => 'Cambio de contraseña',
    ];
    private const LABELS = [
        'nombres' => 'Nombres', 'apellidos' => 'Apellidos', 'telefono' => 'Teléfono', 'direccion' => 'Dirección',
        'contacto_nombre' => 'Nombre del contacto', 'contacto_parentesco' => 'Parentesco', 'contacto_telefono' => 'Teléfono del contacto',
    ];

    public function show(Request $r): void
    {
        $this->seccion('datos');
    }

    public function seguridad(Request $r): void
    {
        $u = Auth::user();
        $this->seccion('seguridad', [
            'actividad' => Database::all(
                "SELECT accion, descripcion, ip, creado_en FROM auditoria
                  WHERE modulo = 'autenticacion' AND (usuario_id = ? OR registro_id = ?) ORDER BY id DESC LIMIT 12",
                [$u['id'], $u['id']]
            ),
            'ultimoCambio' => Database::value("SELECT MAX(creado_en) FROM auditoria WHERE accion = 'cambio_clave' AND registro_id = ?", [$u['id']]),
            'eventos' => self::EVENTOS,
        ]);
    }

    public function notificaciones(Request $r): void
    {
        $u = Auth::user();
        $tipos = Notificaciones::tiposDe($u['rol_codigo']);
        $this->seccion('notificaciones', [
            'tiposNotif' => $tipos,
            'prefsNotif' => Notificaciones::preferencias((int) $u['id'], $u['rol_codigo']),
            'notifApagados' => Notificaciones::tiposApagados(),
            'correoActivo' => Correo::activo(),
            'tiposCorreo' => Correo::tiposPorCorreo(),
            'prefsCorreo' => array_combine(array_keys($tipos), array_map(fn($t) => Correo::prefiere((int) $u['id'], $t), array_keys($tipos))),
        ]);
    }

    public function apariencia(Request $r): void
    {
        $this->seccion('apariencia');
    }

    /** Datos de contacto. El paciente actualiza los de su ficha; médicos y personal, los de su cuenta. */
    public function datos(Request $r): void
    {
        $u = Auth::user();
        $editaNombre = Auth::can('usuarios.editar');
        $pid = Auth::isPatient() ? Auth::patientId() : null;
        $medico = $u['rol_codigo'] === 'medico' ? MedicoRepository::byUser((int) $u['id']) : null;
        $rules = ['telefono' => 'phone'];
        if ($editaNombre) {
            $rules += ['nombres' => 'required|max:80', 'apellidos' => 'required|max:80'];
        }
        if ($pid) {
            $rules += ['direccion' => 'max:200', 'contacto_nombre' => 'max:120', 'contacto_parentesco' => 'max:40', 'contacto_telefono' => 'phone'];
        }
        $v = Validator::make($r->post, $rules, self::LABELS);
        if ($v->fails()) {
            $this->invalid($v, $r, 'perfil');
        }
        $c = $v->clean();
        $antes = $pid ? PacienteRepository::find($pid) : ($medico ? ['telefono' => $medico['telefono']] : []);
        $antes += Database::one('SELECT nombres, apellidos, telefono AS telefono_cuenta FROM usuarios WHERE id = ?', [$u['id']]);

        $cambios = [];
        $cuenta = [];
        if ($editaNombre) {
            foreach (['nombres', 'apellidos'] as $k) {
                if ($c[$k] !== $antes[$k]) {
                    $cuenta[$k] = $c[$k];
                    $cambios[] = self::LABELS[$k];
                }
            }
        }
        if ($pid) {
            $ficha = array_intersect_key($c, array_flip(['telefono', 'direccion', 'contacto_nombre', 'contacto_parentesco', 'contacto_telefono']));
            $ficha = array_filter($ficha, fn($val, $k) => $val !== ($antes[$k] ?? null), ARRAY_FILTER_USE_BOTH);
            if ($ficha) {
                Database::update('pacientes', $ficha, 'id = ?', [$pid]);
                array_push($cambios, ...array_map(fn($k) => self::LABELS[$k], array_keys($ficha)));
            }
        } elseif ($medico) {
            if ($c['telefono'] !== $medico['telefono']) {
                Database::update('medicos', ['telefono' => $c['telefono']], 'id = ?', [$medico['id']]);
                $cambios[] = self::LABELS['telefono'];
            }
        } elseif ($c['telefono'] !== $antes['telefono_cuenta']) {
            $cuenta['telefono'] = $c['telefono'];
            $cambios[] = self::LABELS['telefono'];
        }
        if ($cuenta) {
            Database::update('usuarios', $cuenta, 'id = ?', [$u['id']]);
        }
        if (!$cambios) {
            $this->flash('info', 'No había cambios que guardar.');
            $this->redirect('perfil');
        }
        // Se registra qué campos cambiaron, nunca sus valores.
        Audit::log('editar', $pid ? 'pacientes' : 'usuarios', 'Datos de perfil actualizados por el titular: ' . implode(', ', $cambios), $pid ?: (int) $u['id'], $pid);
        $this->flash('success', 'Datos actualizados.');
        $this->redirect('perfil');
    }

    public function foto(Request $r): void
    {
        $u = Auth::user();
        $actual = $u['foto_archivo'] ?? null;
        if ($r->str('accion') === 'quitar') {
            Database::update('usuarios', ['foto_archivo' => null], 'id = ?', [$u['id']]);
            Avatar::borrar($actual);
            Audit::log('editar', 'usuarios', 'Foto de perfil quitada', (int) $u['id']);
            $this->flash('success', 'Foto de perfil quitada.');
            $this->redirect('perfil#foto');
        }
        $res = Avatar::guardar($_FILES['foto'] ?? null);
        if (!$res['ok']) {
            $this->flash('error', 'No se cambió la foto: ' . $res['mensaje']);
            $this->redirect('perfil#foto');
        }
        Database::update('usuarios', ['foto_archivo' => $res['nombre']], 'id = ?', [$u['id']]);
        Avatar::borrar($actual);
        Audit::log('editar', 'usuarios', 'Foto de perfil actualizada', (int) $u['id']);
        $this->flash('success', 'Foto de perfil actualizada.');
        $this->redirect('perfil#foto');
    }

    /**
     * Entrega la foto de una cuenta. El personal ve la de cualquier cuenta; un paciente, la suya y las del
     * personal (nunca la de otro paciente).
     */
    public function avatar(Request $r, array $p): void
    {
        $yo = Auth::user();
        $u = Database::one('SELECT u.id, u.foto_archivo, r.codigo AS rol FROM usuarios u JOIN roles r ON r.id = u.rol_id WHERE u.id = ?', [(int) $p['id']]);
        if (!$u || !preg_match(Avatar::NOMBRE, (string) $u['foto_archivo']) || !is_file($ruta = Avatar::dir() . '/' . $u['foto_archivo'])) {
            throw new HttpException(404);
        }
        if (Auth::isPatient() && $u['rol'] === 'paciente' && (int) $u['id'] !== (int) $yo['id']) {
            throw new HttpException(404);
        }
        header('Content-Type: image/jpeg');
        header('Content-Length: ' . filesize($ruta));
        header('X-Content-Type-Options: nosniff');
        header_remove('Pragma');
        header_remove('Expires');
        header('Cache-Control: private, max-age=604800');
        readfile($ruta);
        exit;
    }

    public function theme(Request $r): void
    {
        $tema = $r->str('tema');
        if (!in_array($tema, ['sistema', 'claro', 'oscuro'], true)) {
            $this->flash('error', 'Tema no válido.');
            $this->redirect('perfil/apariencia');
        }
        Database::update('usuarios', ['tema' => $tema], 'id = ?', [Auth::user()['id']]);
        Audit::log('editar', 'usuarios', 'Preferencia de tema: ' . $tema, (int) Auth::user()['id']);
        $this->flash('success', 'Tema actualizado.');
        $this->redirect('perfil/apariencia');
    }

    public function password(Request $r): void
    {
        $u = Auth::user();
        $actual = (string) ($r->post['password_actual'] ?? '');
        $nueva = (string) ($r->post['password_nueva'] ?? '');
        $confirmar = (string) ($r->post['password_confirmar'] ?? '');

        $hash = (string) Database::value('SELECT password_hash FROM usuarios WHERE id = ?', [$u['id']]);
        $errors = [];
        if (!password_verify($actual, $hash)) {
            $errors['password_actual'] = 'La contraseña actual no es correcta.';
        } elseif ($msg = Passwords::validate($nueva)) {
            $errors['password_nueva'] = $msg;
        } elseif ($nueva !== $confirmar) {
            $errors['password_confirmar'] = 'Las contraseñas no coinciden.';
        } elseif (password_verify($nueva, $hash)) {
            $errors['password_nueva'] = 'La nueva contraseña debe ser distinta de la actual.';
        }
        if ($errors) {
            Flash::keepErrors($errors);
            $this->flash('error', 'No se cambió la contraseña. Revise los campos marcados.');
            $this->redirect('perfil/seguridad');
        }
        Database::update('usuarios', ['password_hash' => Passwords::hash($nueva), 'debe_cambiar_clave' => 0], 'id = ?', [$u['id']]);
        session_regenerate_id(true);
        Audit::log('cambio_clave', 'autenticacion', 'El usuario cambió su contraseña', (int) $u['id']);
        $this->flash('success', 'Contraseña actualizada.');
        $this->redirect('perfil/seguridad');
    }

    /** Encabezado común (foto, nombre, rol, datos de la cuenta) y la sección pedida. */
    private function seccion(string $seccion, array $datos = []): void
    {
        $u = Auth::user();
        $pid = Auth::isPatient() ? Auth::patientId() : null;
        $this->view('perfil/show', $datos + [
            'title'    => 'Mi perfil',
            'seccion'  => $seccion,
            'yo'       => $u,
            'cuenta'   => Database::one('SELECT ultimo_acceso, creado_en, telefono FROM usuarios WHERE id = ?', [$u['id']]),
            'paciente' => $pid ? PacienteRepository::find($pid) : null,
            'medico'   => $u['rol_codigo'] === 'medico' ? MedicoRepository::byUser((int) $u['id']) : null,
            'editaNombre' => Auth::can('usuarios.editar'),
        ]);
    }
}
