<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Color;
use App\Core\Config;
use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Request;

/** Entrega el logo, el favicon y la imagen de fondo cargados en Configuración. Es público: la pantalla de acceso también los muestra. */
final class MarcaController extends Controller
{

    public static function dir(): string
    {
        return dirname(__DIR__, 2) . '/storage/branding';
    }

    /** Paleta derivada del color de acento personalizado (CSS dinámico, sin estilos en línea). */
    public function tema(Request $r): void
    {
        header('Content-Type: text/css; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        header_remove('Pragma');
        header_remove('Expires');
        header('Cache-Control: public, max-age=86400');
        echo Color::css(Config::get('color_acento'));
        echo \App\Domain\Marca::cssCristal(Config::all(), url('marca/fondo') . '?v=' . substr(md5(Config::get('fondo_archivo')), 0, 8));
        echo \App\Domain\Marca::cssBarra(Config::all());
        exit;
    }

    public function show(Request $r, array $p): void
    {
        $tipo = $p['sec'];
        if (!in_array($tipo, ['logo', 'favicon', 'fondo'], true)) {
            throw new HttpException(404);
        }
        $name = Config::get($tipo . '_archivo');
        if (!preg_match(\App\Domain\Marca::NOMBRE, $name, $m) || $m[1] !== $tipo || !is_file(self::dir() . '/' . $name)) {
            throw new HttpException(404);
        }
        header('Content-Type: ' . \App\Domain\Marca::MIME[$m[2]]);
        // Un SVG abierto directamente no puede ejecutar nada: política sin scripts y documento aislado.
        header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; img-src data:; sandbox");
        header('Content-Length: ' . filesize(self::dir() . '/' . $name));
        header('X-Content-Type-Options: nosniff');
        header_remove('Pragma');
        header_remove('Expires');
        header('Cache-Control: public, max-age=86400');
        readfile(self::dir() . '/' . $name);
        exit;
    }
}
