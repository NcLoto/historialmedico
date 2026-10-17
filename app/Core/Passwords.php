<?php
declare(strict_types=1);

namespace App\Core;

/** Política de contraseñas y generación de contraseñas temporales. */
final class Passwords
{
    public const MIN = 10;

    /** @return string|null mensaje de error, o null si es válida */
    public static function validate(string $password): ?string
    {
        if (mb_strlen($password) < self::MIN) {
            return 'La contraseña debe tener al menos ' . self::MIN . ' caracteres.';
        }
        if (!preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) {
            return 'La contraseña debe combinar letras y números.';
        }
        return null;
    }

    public static function temporary(): string
    {
        $chars = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $out = '';
        for ($i = 0; $i < 12; $i++) {
            $out .= $chars[random_int(0, strlen($chars) - 1)];
        }
        return $out . random_int(2, 9);
    }

    public static function hash(string $password): string
    {
        return password_hash($password, PASSWORD_DEFAULT);
    }
}
