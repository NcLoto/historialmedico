<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Domain\HistorialSections;

final class HistorialRepository
{
    /** @return array<string,array<int,array>> filas de todas las secciones, indexadas por clave de sección */
    public static function allFor(int $pacienteId): array
    {
        $out = [];
        foreach (HistorialSections::all() as $key => $s) {
            $out[$key] = Database::all("SELECT * FROM `{$s['table']}` WHERE paciente_id = ? ORDER BY {$s['order']}", [$pacienteId]);
        }
        return $out;
    }

    public static function find(array $section, int $pacienteId, int $id): ?array
    {
        return Database::one("SELECT * FROM `{$section['table']}` WHERE id = ? AND paciente_id = ?", [$id, $pacienteId]);
    }

    public static function insert(array $section, int $pacienteId, array $data, int $userId): int
    {
        return Database::insert($section['table'], $data + ['paciente_id' => $pacienteId, 'registrado_por' => $userId]);
    }

    public static function update(array $section, int $pacienteId, int $id, array $data): void
    {
        Database::update($section['table'], $data, 'id = ? AND paciente_id = ?', [$id, $pacienteId]);
    }

    public static function delete(array $section, int $pacienteId, int $id): void
    {
        Database::run("DELETE FROM `{$section['table']}` WHERE id = ? AND paciente_id = ?", [$id, $pacienteId]);
    }
}
