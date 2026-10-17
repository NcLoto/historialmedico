<?php
declare(strict_types=1);

namespace App\Core;

/** Registro técnico privado (storage/logs). Nunca se muestra al usuario. */
final class Logger
{
    public static function error(string $message, ?\Throwable $e = null): void
    {
        $detail = $e ? ' :: ' . get_class($e) . ': ' . $e->getMessage() . ' @ ' . basename($e->getFile()) . ':' . $e->getLine() : '';
        @file_put_contents(__DIR__ . '/../../storage/logs/app.log', sprintf("[%s] %s%s\n", date('c'), $message, $detail), FILE_APPEND | LOCK_EX);
    }
}
