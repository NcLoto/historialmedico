<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Deriva la paleta de la interfaz a partir de un color de acento cualquiera.
 * Ajusta la luminosidad hasta cumplir contraste WCAG AA (4.5:1) en claro y en oscuro.
 */
final class Color
{
    /** Atajos con ajuste manual: nombre => hex */
    public const PRESETS = ['cian' => '#0e7490', 'azul' => '#1d5fa8', 'verde' => '#157347', 'grafito' => '#3b4d5c'];

    public static function isHex(string $v): bool
    {
        return preg_match('/^#[0-9a-fA-F]{6}$/', $v) === 1;
    }

    /** Hex que se muestra en el selector para un valor guardado (nombre de atajo o hex). */
    public static function toHex(string $stored): string
    {
        return self::PRESETS[$stored] ?? (self::isHex($stored) ? strtolower($stored) : self::PRESETS['cian']);
    }

    /** Color de acento legible sobre blanco (para impresos y PDF). */
    public static function brand(string $stored): string
    {
        return self::tune(self::toHex($stored), '#ffffff', 4.5, -1);
    }

    /** CSS de la paleta personalizada (cadena vacía si se usa un atajo). */
    public static function css(string $stored): string
    {
        if (!self::isHex($stored)) {
            return '';
        }
        [$h, $s, $l] = self::hsl($stored);
        $light = [
            '--brand' => self::tune($stored, '#ffffff', 4.5, -1),
            '--brand-tint' => self::hex($h, min($s, 60), 94),
            '--side-bg' => self::hex($h, min($s, 42), 17),
            '--side-accent' => self::hex($h, 85, 76),
        ];
        $light['--brand-hover'] = self::shift($light['--brand'], -8);

        // Oscuro: el acento se aclara y se desatura un poco para que vibre sin ensuciar; las superficies
        // (barra lateral, recuadros seleccionados) llevan solo un matiz del color, no el color completo.
        $base = self::hex($h, min($s, 62), max($l, 58));
        $brandDark = self::tune($base, '#10252e', 4.5, 1);
        $ink = self::contrast($brandDark, '#04181f') >= self::contrast($brandDark, '#ffffff') ? '#04181f' : '#ffffff';
        $dark = [
            '--brand' => $brandDark,
            '--brand-hover' => self::shift($brandDark, 8),
            '--brand-tint' => self::hex($h, min($s, 28), 18),
            '--brand-ink' => $ink,
            '--side-bg' => self::hex($h, min($s, 22), 9),
            '--side-accent' => self::hex($h, min(max($s, 60), 85), 76),
        ];
        $fmt = fn(array $v) => implode(' ', array_map(fn($k, $x) => "$k: $x;", array_keys($v), $v));
        $a = '[data-accent="personalizado"]';
        return "/* Paleta derivada de $stored */\n"
            . ":root[data-theme=\"claro\"]$a, :root[data-theme=\"auto\"]$a { {$fmt($light)} }\n"
            . ":root[data-theme=\"oscuro\"]$a { {$fmt($dark)} }\n"
            . "@media (prefers-color-scheme: dark) { :root[data-theme=\"auto\"]$a { {$fmt($dark)} } }\n";
    }

    // ------------------------------------------------------------ utilidades

    /** @return array{0:float,1:float,2:float} h(0-360), s(0-100), l(0-100) */
    public static function hsl(string $hex): array
    {
        [$r, $g, $b] = array_map(fn($c) => hexdec($c) / 255, str_split(ltrim($hex, '#'), 2));
        $max = max($r, $g, $b);
        $min = min($r, $g, $b);
        $l = ($max + $min) / 2;
        if ($max === $min) {
            return [0.0, 0.0, $l * 100];
        }
        $d = $max - $min;
        $s = $l > 0.5 ? $d / (2 - $max - $min) : $d / ($max + $min);
        $h = match ($max) {
            $r => ($g - $b) / $d + ($g < $b ? 6 : 0),
            $g => ($b - $r) / $d + 2,
            default => ($r - $g) / $d + 4,
        };
        return [$h * 60, $s * 100, $l * 100];
    }

    public static function hex(float $h, float $s, float $l): string
    {
        $s /= 100;
        $l /= 100;
        $c = (1 - abs(2 * $l - 1)) * $s;
        $x = $c * (1 - abs(fmod($h / 60, 2) - 1));
        $m = $l - $c / 2;
        [$r, $g, $b] = match (true) {
            $h < 60 => [$c, $x, 0], $h < 120 => [$x, $c, 0], $h < 180 => [0, $c, $x],
            $h < 240 => [0, $x, $c], $h < 300 => [$x, 0, $c], default => [$c, 0, $x],
        };
        return sprintf('#%02x%02x%02x', round(($r + $m) * 255), round(($g + $m) * 255), round(($b + $m) * 255));
    }

    private static function shift(string $hex, float $dl): string
    {
        [$h, $s, $l] = self::hsl($hex);
        return self::hex($h, $s, max(0, min(100, $l + $dl)));
    }

    public static function luminance(string $hex): float
    {
        $c = array_map(function ($v) {
            $v = hexdec($v) / 255;
            return $v <= 0.03928 ? $v / 12.92 : (($v + 0.055) / 1.055) ** 2.4;
        }, str_split(ltrim($hex, '#'), 2));
        return 0.2126 * $c[0] + 0.7152 * $c[1] + 0.0722 * $c[2];
    }

    public static function contrast(string $a, string $b): float
    {
        $la = self::luminance($a);
        $lb = self::luminance($b);
        return (max($la, $lb) + 0.05) / (min($la, $lb) + 0.05);
    }

    /** Mueve la luminosidad en la dirección dada hasta alcanzar el contraste mínimo contra $bg. */
    private static function tune(string $hex, string $bg, float $min, int $dir): string
    {
        [$h, $s, $l] = self::hsl($hex);
        for ($i = 0; $i < 100 && self::contrast(self::hex($h, $s, $l), $bg) < $min; $i++) {
            $l = max(2, min(96, $l + $dir));
        }
        return self::hex($h, $s, $l);
    }
}
