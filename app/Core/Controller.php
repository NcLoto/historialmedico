<?php
declare(strict_types=1);

namespace App\Core;

abstract class Controller
{
    protected function view(string $view, array $data = [], ?string $layout = 'app'): void
    {
        View::render($view, $data, $layout);
    }

    protected function redirect(string $path): never
    {
        header('Location: ' . url($path));
        exit;
    }

    protected function flash(string $type, string $message): void
    {
        Flash::add($type, $message);
    }

    /** Guarda errores y valores previos y vuelve al formulario. */
    protected function invalid(Validator $v, Request $r, string $backTo): never
    {
        Flash::keepInput($r->post);
        Flash::keepErrors($v->errors());
        Flash::add('error', 'Revise los campos marcados.');
        $this->redirect($backTo);
    }
}
