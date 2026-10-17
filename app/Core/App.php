<?php
declare(strict_types=1);

namespace App\Core;

final class App
{
    /** Arranque para tareas de consola (sin sesión ni cabeceras HTTP). */
    public static function bootCli(): void
    {
        $root = dirname(__DIR__, 2);
        require_once __DIR__ . '/helpers.php';
        spl_autoload_register(function (string $class) use ($root): void {
            if (str_starts_with($class, 'App\\')) {
                $file = $root . '/app/' . str_replace('\\', '/', substr($class, 4)) . '.php';
                if (is_file($file)) {
                    require $file;
                }
            }
        });
        Env::load($root . '/.env');
        date_default_timezone_set('America/Guatemala');
    }

    public static function run(): void
    {
        $root = dirname(__DIR__, 2);
        require __DIR__ . '/helpers.php';
        spl_autoload_register(function (string $class) use ($root): void {
            if (str_starts_with($class, 'App\\')) {
                $file = $root . '/app/' . str_replace('\\', '/', substr($class, 4)) . '.php';
                if (is_file($file)) {
                    require $file;
                }
            }
        });
        Env::load($root . '/.env');
        $debug = Env::get('APP_DEBUG', 'false') === 'true';
        ini_set('display_errors', $debug ? '1' : '0');
        error_reporting(E_ALL);
        date_default_timezone_set('America/Guatemala');

        self::securityHeaders();
        $request = Request::capture();

        try {
            Auth::startSession();
            $routes = require $root . '/config/routes.php';
            (new Router($routes))->dispatch($request);
        } catch (HttpException $e) {
            self::renderError($e->status, $request);
        } catch (\Throwable $e) {
            Logger::error('Error no controlado en ' . $request->method . ' ' . $request->path, $e);
            if ($debug) {
                throw $e;
            }
            self::renderError(500, $request);
        }
    }

    private static function securityHeaders(): void
    {
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('Referrer-Policy: same-origin');
        header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
        header("Content-Security-Policy: default-src 'self'; img-src 'self' data: blob:; style-src 'self' https://fonts.googleapis.com; font-src https://fonts.gstatic.com; script-src 'self'; frame-ancestors 'self'; form-action 'self'; base-uri 'none'");
        header('Cache-Control: private, no-store');
    }

    public static function renderError(int $status, Request $request): void
    {
        if (!headers_sent()) {
            // 419 (token CSRF vencido) no es un código estándar y Apache lo convierte en 500: se emite 400.
            http_response_code($status === 419 ? 400 : $status);
        }
        if (ob_get_level() > 0) {
            ob_clean();
        }
        if ($status === 403 && Auth::user()) {
            Audit::log('acceso_denegado', 'seguridad', 'Acceso denegado: ' . $request->method . ' ' . mb_substr($request->path, 0, 150));
        }
        $known = [403, 404, 405, 419, 500];
        $code = in_array($status, $known, true) ? $status : 500;
        $layout = Auth::user() ? 'app' : 'auth';
        View::render('errors/' . $code, ['title' => 'Error ' . $code], $layout);
    }
}
