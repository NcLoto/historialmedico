<?php
declare(strict_types=1);

namespace App\Core;

/** Parámetros de la tabla configuracion, cacheados por solicitud. */
final class Config
{
    private static ?array $cache = null;

    public static function all(): array
    {
        return self::$cache ??= array_column(Database::all('SELECT clave, valor FROM configuracion'), 'valor', 'clave');
    }

    public static function get(string $key, string $default = ''): string
    {
        return self::all()[$key] ?? $default;
    }

    public static function int(string $key, int $default = 0): int
    {
        return (int) self::get($key, (string) $default);
    }

    public static function reset(): void
    {
        self::$cache = null;
    }
}
