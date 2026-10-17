<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Tabla de rutas: [método, patrón, 'Controlador@acción', acceso, opciones?]
 * acceso: 'public' (invitados), 'auth' (cualquier sesión) o 'modulo.accion' (permiso exigido en backend).
 * opciones: 'staff' bloquea a pacientes aunque tengan el permiso.
 */
final class Router
{
    private const PLACEHOLDERS = [
        '{id}'    => '(?P<id>[0-9]+)',
        '{rid}'   => '(?P<rid>[0-9]+)',
        '{sec}'   => '(?P<sec>[a-z_]+)',
        '{token}' => '(?P<token>[A-Za-z0-9]{20,100})',
        '{codigo}' => '(?P<codigo>[A-Za-z0-9]{12})',
    ];

    public function __construct(private array $routes)
    {
    }

    public function dispatch(Request $req): void
    {
        $pathMatched = false;
        foreach ($this->routes as $route) {
            [$method, $pattern, $handler, $access] = $route;
            $options = $route[4] ?? [];
            $regex = '#^' . strtr($pattern, self::PLACEHOLDERS) . '$#';
            if (!preg_match($regex, $req->path, $m)) {
                continue;
            }
            $pathMatched = true;
            if ($method !== $req->method && !($method === 'GET' && $req->method === 'HEAD')) {
                continue;
            }
            $params = array_filter($m, 'is_string', ARRAY_FILTER_USE_KEY);
            $this->authorize($req, $access, $options);
            if ($req->isPost()) {
                Csrf::verify($req);
            }
            [$class, $action] = explode('@', $handler);
            $class = 'App\\Controllers\\' . $class;
            (new $class())->$action($req, $params);
            return;
        }
        throw new HttpException($pathMatched ? 405 : 404);
    }

    private function authorize(Request $req, string $access, array $options): void
    {
        if ($access === 'public') {
            return;
        }
        $user = Auth::user();
        if (!$user) {
            if (Auth::$expired) {
                Flash::add('warning', 'Su sesión expiró por inactividad. Inicie sesión nuevamente.');
            }
            $next = $req->method === 'GET' && $req->path !== '/' ? '?next=' . rawurlencode($req->path) : '';
            header('Location: ' . url('login') . $next);
            exit;
        }
        $forzar = !empty($user['debe_cambiar_clave']) && !in_array($req->path, ['/perfil', '/perfil/clave', '/logout'], true);
        if ($forzar) {
            Flash::add('info', 'Debe cambiar su contraseña antes de continuar.');
            header('Location: ' . url('perfil'));
            exit;
        }
        if ($access !== 'auth' && !Auth::can($access)) {
            throw new HttpException(403);
        }
        if (in_array('staff', $options, true)) {
            Policy::staffOnly();
        }
    }
}
