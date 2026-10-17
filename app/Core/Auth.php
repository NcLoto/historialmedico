<?php
declare(strict_types=1);

namespace App\Core;

/** Sesión, inicio/cierre de sesión, bloqueo por intentos y permisos del usuario actual. */
final class Auth
{
    private static ?array $user = null;
    private static bool $loaded = false;
    public static bool $expired = false;

    public static function startSession(): void
    {
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        session_name('SANITASSID');
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => rtrim(Env::get('APP_BASE_PATH', ''), '/') . '/',
            'secure'   => $https,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }

    /** Usuario autenticado con rol y permisos, o null. Valida inactividad y estado de la cuenta. */
    public static function user(): ?array
    {
        if (self::$loaded) {
            return self::$user;
        }
        self::$loaded = true;
        $uid = $_SESSION['uid'] ?? null;
        if (!$uid) {
            return null;
        }
        $maxIdle = Config::int('sesion_minutos', 30) * 60;
        if (time() - (int) ($_SESSION['last_activity'] ?? 0) > $maxIdle) {
            self::$expired = true;
            self::destroySession();
            return null;
        }
        $u = Database::one(
            'SELECT u.id, u.rol_id, u.email, u.nombres, u.apellidos, u.activo, u.debe_cambiar_clave, u.tema, u.foto_archivo, r.codigo AS rol_codigo, r.nombre AS rol_nombre
               FROM usuarios u JOIN roles r ON r.id = u.rol_id WHERE u.id = ?',
            [$uid]
        );
        if (!$u || !$u['activo']) {
            self::destroySession();
            return null;
        }
        $_SESSION['last_activity'] = time();
        $u['permisos'] = array_column(Database::all(
            "SELECT CONCAT(p.modulo, '.', p.accion) AS codigo FROM rol_permisos rp JOIN permisos p ON p.id = rp.permiso_id WHERE rp.rol_id = ?",
            [$u['rol_id']]
        ), 'codigo');
        return self::$user = $u;
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function can(string $permission): bool
    {
        $u = self::user();
        return $u !== null && in_array($permission, $u['permisos'], true);
    }

    public static function isPatient(): bool
    {
        return (self::user()['rol_codigo'] ?? '') === 'paciente';
    }

    /** Id del paciente vinculado a la cuenta actual (solo para rol paciente). */
    public static function patientId(): ?int
    {
        $u = self::user();
        if (!$u) {
            return null;
        }
        $id = Database::value('SELECT id FROM pacientes WHERE usuario_id = ?', [$u['id']]);
        return $id === null ? null : (int) $id;
    }

    /** @return array{ok:bool,message:string} */
    public static function attempt(string $email, string $password): array
    {
        $generic = 'Correo o contraseña incorrectos.';
        $u = Database::one(
            'SELECT u.*, r.codigo AS rol_codigo FROM usuarios u JOIN roles r ON r.id = u.rol_id WHERE u.email = ?',
            [mb_strtolower($email)]
        );
        $actorAnon = ['id' => $u['id'] ?? null, 'nombres' => $u ? $u['nombres'] : $email, 'apellidos' => $u ? $u['apellidos'] : '', 'rol_codigo' => $u['rol_codigo'] ?? null];

        if (!$u) {
            password_verify($password, password_hash('relleno-de-tiempo', PASSWORD_DEFAULT));
            Audit::log('login_fallido', 'autenticacion', 'Intento de acceso con correo no registrado: ' . mb_substr($email, 0, 100), null, null, null, $actorAnon);
            return ['ok' => false, 'message' => $generic];
        }

        if ($u['bloqueado_hasta'] && strtotime($u['bloqueado_hasta']) > time()) {
            $min = (int) ceil((strtotime($u['bloqueado_hasta']) - time()) / 60);
            Audit::log('login_bloqueado', 'autenticacion', 'Intento de acceso a cuenta bloqueada', (int) $u['id'], null, null, $actorAnon);
            return ['ok' => false, 'message' => "Cuenta bloqueada temporalmente por intentos fallidos. Intente de nuevo en $min min."];
        }

        if (!password_verify($password, $u['password_hash'])) {
            $max = Config::int('intentos_max', 5);
            $n = (int) $u['intentos_fallidos'] + 1;
            $data = ['intentos_fallidos' => $n];
            if ($n >= $max) {
                $data['intentos_fallidos'] = 0;
                $data['bloqueado_hasta'] = date('Y-m-d H:i:s', time() + Config::int('bloqueo_minutos', 15) * 60);
            }
            Database::update('usuarios', $data, 'id = ?', [$u['id']]);
            Audit::log('login_fallido', 'autenticacion', 'Contraseña incorrecta' . ($n >= $max ? ' (cuenta bloqueada)' : ''), (int) $u['id'], null, null, $actorAnon);
            if ($n >= $max) {
                \App\Domain\Notificaciones::aAdministradores('seguridad', 'Cuenta bloqueada: ' . $u['email'], $max . ' intentos fallidos de acceso. Se desbloquea sola en ' . Config::int('bloqueo_minutos', 15) . ' minutos o al restablecer la contraseña.', '/usuarios?q=' . rawurlencode($u['email']), 'aviso');
            }
            return ['ok' => false, 'message' => $generic];
        }

        if (!$u['activo']) {
            Audit::log('login_fallido', 'autenticacion', 'Acceso a cuenta desactivada', (int) $u['id'], null, null, $actorAnon);
            return ['ok' => false, 'message' => 'La cuenta está desactivada. Comuníquese con el administrador.'];
        }

        if (password_needs_rehash($u['password_hash'], PASSWORD_DEFAULT)) {
            Database::update('usuarios', ['password_hash' => password_hash($password, PASSWORD_DEFAULT)], 'id = ?', [$u['id']]);
        }
        Database::update('usuarios', ['intentos_fallidos' => 0, 'bloqueado_hasta' => null], 'id = ?', [$u['id']]);

        // Contraseña temporal: la clave es correcta, pero no se abre la sesión hasta que el usuario defina la suya.
        if (!empty($u['debe_cambiar_clave'])) {
            session_regenerate_id(true);
            $_SESSION = ['cambio_pendiente' => ['uid' => (int) $u['id'], 'hasta' => time() + 600]];
            return ['ok' => true, 'message' => '', 'cambiar' => true];
        }

        self::completeLogin((int) $u['id']);
        return ['ok' => true, 'message' => ''];
    }

    /** Abre la sesión del usuario (tras validar sus credenciales, o tras fijar su nueva contraseña). */
    public static function completeLogin(int $uid): void
    {
        Database::update('usuarios', ['ultimo_acceso' => date('Y-m-d H:i:s')], 'id = ?', [$uid]);
        session_regenerate_id(true);
        $_SESSION = ['uid' => $uid, 'last_activity' => time()];
        self::$loaded = false;
        self::$user = null;
        Audit::log('login', 'autenticacion', 'Inicio de sesión', $uid, null, null, self::user());
    }

    /** Usuario que acaba de entrar con una contraseña temporal y debe definir la suya (vigente 10 minutos), o null. */
    public static function pendingChange(): ?array
    {
        $p = $_SESSION['cambio_pendiente'] ?? null;
        if (!is_array($p) || ($p['hasta'] ?? 0) < time()) {
            unset($_SESSION['cambio_pendiente']);
            return null;
        }
        $u = Database::one('SELECT id, email, nombres, apellidos, password_hash, activo FROM usuarios WHERE id = ?', [(int) $p['uid']]);
        return $u && $u['activo'] ? $u : null;
    }

    public static function logout(): void
    {
        if (self::user()) {
            Audit::log('logout', 'autenticacion', 'Cierre de sesión', (int) self::user()['id']);
        }
        self::destroySession();
    }

    private static function destroySession(): void
    {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
        self::$user = null;
    }

    public static function forget(): void
    {
        self::$loaded = false;
        self::$user = null;
    }
}
