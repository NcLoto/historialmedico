<?php
declare(strict_types=1);

namespace App\Core;

/** Mensajes de una sola vez, errores de validación y valores de formulario previos. */
final class Flash
{
    private static ?array $old = null;

    public static function add(string $type, string $message): void
    {
        $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
    }

    public static function pull(): array
    {
        $m = $_SESSION['_flash'] ?? [];
        unset($_SESSION['_flash']);
        return $m;
    }

    public static function keepInput(array $input): void
    {
        unset($input['_csrf'], $input['password'], $input['password_actual'], $input['password_nueva'], $input['password_confirmar']);
        $_SESSION['_old'] = $input;
    }

    public static function oldValue(string $key, mixed $default = ''): mixed
    {
        if (self::$old === null) {
            self::$old = $_SESSION['_old'] ?? [];
            unset($_SESSION['_old']);
        }
        return self::$old[$key] ?? $default;
    }

    public static function errors(): array
    {
        $e = $_SESSION['_errors'] ?? [];
        unset($_SESSION['_errors']);
        return $e;
    }

    public static function keepErrors(array $errors): void
    {
        $_SESSION['_errors'] = $errors;
    }
}
