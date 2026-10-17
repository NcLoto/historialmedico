<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Flash;
use App\Core\Passwords;
use App\Core\Request;

final class AuthController extends Controller
{
    public function loginForm(Request $r): void
    {
        if (Auth::check()) {
            $this->redirect('/');
        }
        $this->view('auth/login', ['title' => 'Iniciar sesión', 'next' => $this->safeNext($r->str('next'))], 'auth');
    }

    public function login(Request $r): void
    {
        $email = mb_strtolower($r->str('email'));
        $password = (string) ($r->post['password'] ?? '');
        $next = $this->safeNext($r->str('next'));
        $back = 'login' . ($next !== '' ? '?next=' . rawurlencode($next) : '');

        if ($email === '' || $password === '') {
            Flash::keepInput(['email' => $email]);
            $this->flash('error', 'Ingrese su correo y contraseña.');
            $this->redirect($back);
        }
        $res = Auth::attempt($email, $password);
        if (!$res['ok']) {
            Flash::keepInput(['email' => $email]);
            $this->flash('error', $res['message']);
            $this->redirect($back);
        }
        if (!empty($res['cambiar'])) {
            $_SESSION['cambio_next'] = $next;
            $this->redirect('login/cambiar-clave');
        }
        $this->redirect($next !== '' ? $next : '/');
    }

    /** Paso obligatorio tras entrar con una contraseña temporal: definir la propia antes de abrir la sesión. */
    public function changeForm(Request $r): void
    {
        $u = Auth::pendingChange();
        if (!$u) {
            $this->flash('warning', 'La solicitud venció. Inicie sesión de nuevo.');
            $this->redirect('login');
        }
        $this->view('auth/cambiar', ['title' => 'Definir contraseña', 'cuenta' => $u], 'auth');
    }

    public function change(Request $r): void
    {
        $u = Auth::pendingChange();
        if (!$u) {
            $this->flash('warning', 'La solicitud venció. Inicie sesión de nuevo.');
            $this->redirect('login');
        }
        $nueva = (string) ($r->post['password_nueva'] ?? '');
        $confirmar = (string) ($r->post['password_confirmar'] ?? '');
        $error = Passwords::validate($nueva);
        if (!$error && $nueva !== $confirmar) {
            $error = 'Las contraseñas no coinciden.';
        } elseif (!$error && password_verify($nueva, $u['password_hash'])) {
            $error = 'La nueva contraseña debe ser distinta de la temporal.';
        }
        if ($error) {
            Flash::keepErrors(['password_nueva' => $error]);
            $this->redirect('login/cambiar-clave');
        }
        Database::update('usuarios', ['password_hash' => Passwords::hash($nueva), 'debe_cambiar_clave' => 0], 'id = ?', [$u['id']]);
        Audit::log('cambio_clave', 'autenticacion', 'El usuario definió su contraseña tras recibir una temporal', (int) $u['id'], null, null, ['id' => $u['id'], 'nombres' => $u['nombres'], 'apellidos' => $u['apellidos'], 'rol_codigo' => null]);
        $next = (string) ($_SESSION['cambio_next'] ?? '');
        Auth::completeLogin((int) $u['id']);
        $this->flash('success', 'Contraseña actualizada. Bienvenido.');
        $this->redirect($next !== '' ? $next : '/');
    }

    public function cancelChange(Request $r): void
    {
        unset($_SESSION['cambio_pendiente'], $_SESSION['cambio_next']);
        $this->redirect('login');
    }

    public function logout(Request $r): void
    {
        Auth::logout();
        $this->flash('success', 'Sesión cerrada correctamente.');
        $this->redirect('login');
    }

    public function forgotForm(Request $r): void
    {
        $this->view('auth/forgot', ['title' => 'Recuperar contraseña'], 'auth');
    }

    public function forgot(Request $r): void
    {
        $email = mb_strtolower($r->str('email'));
        $u = $email !== '' ? Database::one('SELECT id, nombres, apellidos, email, activo FROM usuarios WHERE email = ?', [$email]) : null;

        if ($u && $u['activo']) {
            $recent = (int) Database::value('SELECT COUNT(*) FROM password_resets WHERE usuario_id = ? AND creado_en > (NOW() - INTERVAL 1 HOUR)', [$u['id']]);
            if ($recent < 3) {
                $token = bin2hex(random_bytes(24));
                Database::run('UPDATE password_resets SET usado_en = NOW() WHERE usuario_id = ? AND usado_en IS NULL', [$u['id']]);
                Database::insert('password_resets', [
                    'usuario_id' => $u['id'],
                    'token_hash' => hash('sha256', $token),
                    'expira_en'  => date('Y-m-d H:i:s', time() + 3600),
                ]);
                $this->deliver($u, url('restablecer/' . $token));
                Audit::log('recuperacion', 'autenticacion', 'Solicitud de restablecimiento de contraseña', (int) $u['id'], null, null, $u + ['rol_codigo' => null]);
            }
        }
        // Misma respuesta exista o no la cuenta, para no revelar qué correos están registrados.
        $this->flash('info', 'Si el correo corresponde a una cuenta activa, le enviamos un enlace para restablecer la contraseña. Vence en 60 minutos.');
        $this->redirect('login');
    }

    public function resetForm(Request $r, array $p): void
    {
        $row = $this->validToken($p['token']);
        $this->view('auth/reset', ['title' => 'Nueva contraseña', 'token' => $p['token'], 'valid' => $row !== null], 'auth');
    }

    public function reset(Request $r, array $p): void
    {
        $row = $this->validToken($p['token']);
        if (!$row) {
            $this->flash('error', 'El enlace no es válido o ya venció. Solicite uno nuevo.');
            $this->redirect('olvide-contrasena');
        }
        $pass = (string) ($r->post['password'] ?? '');
        $confirm = (string) ($r->post['password_confirmar'] ?? '');
        $error = Passwords::validate($pass) ?? ($pass !== $confirm ? 'Las contraseñas no coinciden.' : null);
        if ($error) {
            Flash::keepErrors(['password' => $error]);
            $this->redirect('restablecer/' . $p['token']);
        }
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        Database::update('usuarios', [
            'password_hash' => Passwords::hash($pass), 'intentos_fallidos' => 0, 'bloqueado_hasta' => null, 'debe_cambiar_clave' => 0,
        ], 'id = ?', [$row['usuario_id']]);
        Database::run('UPDATE password_resets SET usado_en = NOW() WHERE usuario_id = ? AND usado_en IS NULL', [$row['usuario_id']]);
        $pdo->commit();
        $u = Database::one('SELECT u.*, r.codigo AS rol_codigo FROM usuarios u JOIN roles r ON r.id = u.rol_id WHERE u.id = ?', [$row['usuario_id']]);
        Audit::log('cambio_clave', 'autenticacion', 'Contraseña restablecida mediante enlace', (int) $u['id'], null, null, $u);
        $this->flash('success', 'Contraseña actualizada. Ya puede iniciar sesión.');
        $this->redirect('login');
    }

    private function validToken(string $token): ?array
    {
        return Database::one(
            'SELECT id, usuario_id FROM password_resets WHERE token_hash = ? AND usado_en IS NULL AND expira_en > NOW()',
            [hash('sha256', $token)]
        );
    }

    /** Sin servidor de correo en el entorno académico, el mensaje se deposita en storage/mail. */
    /** Envía el enlace de restablecimiento al instante (no espera a la cola: vence en 60 minutos). */
    private function deliver(array $u, string $link): void
    {
        $base = rtrim(url(''), '/');
        $ruta = $base !== '' && str_starts_with($link, $base) ? substr($link, strlen($base)) : $link;
        $r = \App\Domain\Correo::enviarAhora($u['email'], $u['nombres'], 'recuperacion', 'Restablecer contraseña', [
            'Recibimos una solicitud para restablecer la contraseña de su cuenta.',
            'Use el botón para crear una nueva. El enlace vence en 60 minutos y solo funciona una vez.',
        ], ['Crear nueva contraseña', \App\Domain\Correo::urlAbsoluta(ltrim($ruta, '/'))], (int) $u['id']);
        if (!$r['ok']) {
            \App\Core\Logger::error('No se pudo enviar el correo de recuperación a la cuenta #' . $u['id'] . ': ' . $r['error']);
        }
    }

    /** Solo rutas internas como destino posterior al login (evita redirecciones abiertas). */
    private function safeNext(string $next): string
    {
        return ($next !== '' && $next[0] === '/' && !str_starts_with($next, '//') && !str_contains($next, '\\')) ? $next : '';
    }
}
