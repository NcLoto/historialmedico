<?php
declare(strict_types=1);

namespace App\Core;

/** Descarga de tablas en CSV listas para Excel (UTF-8 con BOM, separador «;») y a prueba de inyección de fórmulas. */
final class Csv
{
    /** @param list<string> $headers @param iterable<list<scalar|null>> $rows */
    public static function send(string $filename, array $headers, iterable $rows): never
    {
        $name = preg_replace('/[^A-Za-z0-9._-]+/', '_', $filename) ?: 'exportacion.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $name . '"');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, no-store');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, $headers, ';');
        foreach ($rows as $row) {
            fputcsv($out, array_map([self::class, 'cell'], $row), ';');
        }
        fclose($out);
        exit;
    }

    /** Un texto que empiece con = + - @ se interpretaría como fórmula al abrirlo en Excel. */
    public static function cell(mixed $v): string
    {
        $s = (string) ($v ?? '');
        return ($s !== '' && strpbrk($s[0], "=+-@\t\r") !== false && !is_numeric($s)) ? "'" . $s : $s;
    }
}
