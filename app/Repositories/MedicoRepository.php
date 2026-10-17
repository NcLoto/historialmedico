<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

final class MedicoRepository
{
    private const SELECT = "SELECT m.id, m.usuario_id, m.especialidad_id, m.colegiado, m.telefono, m.consultorio, m.activo,
            u.nombres, u.apellidos, u.email, u.activo AS usuario_activo, e.nombre AS especialidad
        FROM medicos m JOIN usuarios u ON u.id = m.usuario_id JOIN especialidades e ON e.id = m.especialidad_id";

    public static function find(int $id): ?array
    {
        return Database::one(self::SELECT . ' WHERE m.id = ?', [$id]);
    }

    public static function byUser(int $usuarioId): ?array
    {
        return Database::one(self::SELECT . ' WHERE m.usuario_id = ?', [$usuarioId]);
    }

    public static function all(string $q = '', ?int $especialidad = null): array
    {
        $where = ['1=1'];
        $params = [];
        if ($q !== '') {
            $like = '%' . addcslashes($q, '%_\\') . '%';
            $where[] = '(u.nombres LIKE ? OR u.apellidos LIKE ? OR m.colegiado LIKE ? OR u.email LIKE ?)';
            array_push($params, $like, $like, $like, $like);
        }
        if ($especialidad) {
            $where[] = 'm.especialidad_id = ?';
            $params[] = $especialidad;
        }
        return Database::all(self::SELECT . ' WHERE ' . implode(' AND ', $where) . ' ORDER BY u.apellidos, u.nombres', $params);
    }

    public static function especialidades(): array
    {
        return Database::all(
            'SELECT e.id, e.nombre, e.descripcion, (SELECT COUNT(*) FROM medicos m WHERE m.especialidad_id = e.id) AS medicos
               FROM especialidades e ORDER BY e.nombre'
        );
    }
}
