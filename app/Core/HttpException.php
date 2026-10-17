<?php
declare(strict_types=1);

namespace App\Core;

/** Error HTTP controlado (403, 404, 419...). Se muestra con mensaje genérico. */
final class HttpException extends \RuntimeException
{
    public function __construct(public readonly int $status, string $message = '')
    {
        parent::__construct($message);
    }
}
