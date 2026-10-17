<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

final class PacienteRepository
{
    public static function find(int $id): ?array
    {
        return Database::one('SELECT * FROM pacientes WHERE id = ?', [$id]);
    }

    /** Búsqueda por nombre, apellido, documento o código. @return array{rows:array,total:int} */
    public static function search(string $q, string $estado, int $limit, int $offset, bool $clinico = true): array
    {
        [$where, $params] = self::filters($q, $estado);
        $total = (int) Database::value("SELECT COUNT(*) FROM pacientes p WHERE $where", $params);
        $extra = $clinico
            ? "(SELECT MAX(c.fecha_hora) FROM consultas c WHERE c.paciente_id = p.id AND c.estado = 'realizada') AS ultima_consulta,
               (SELECT COUNT(*) FROM alergias a WHERE a.paciente_id = p.id AND a.activa = 1 AND a.severidad = 'grave') AS alergias_graves"
            : "NULL AS ultima_consulta, 0 AS alergias_graves";
        $rows = Database::all(
            "SELECT p.id, p.codigo, p.nombres, p.apellidos, p.fecha_nacimiento, p.sexo, p.documento_identidad, p.telefono, p.activo, p.usuario_id,
                    $extra
               FROM pacientes p WHERE $where ORDER BY p.apellidos, p.nombres LIMIT $limit OFFSET $offset",
            $params
        );
        return ['rows' => $rows, 'total' => $total];
    }

    /** Datos personales para exportar (sin información clínica). */
    public static function exportRows(string $q, string $estado): array
    {
        [$where, $params] = self::filters($q, $estado);
        return Database::all("SELECT p.codigo, p.nombres, p.apellidos, p.documento_identidad, p.fecha_nacimiento, p.sexo, p.tipo_sangre, p.telefono, p.email, p.direccion, p.activo, (p.usuario_id IS NOT NULL) AS con_cuenta, p.creado_en FROM pacientes p WHERE $where ORDER BY p.apellidos, p.nombres LIMIT 20000", $params);
    }

    private static function filters(string $q, string $estado): array
    {
        $where = ['1=1'];
        $params = [];
        if ($estado === 'activos') {
            $where[] = 'p.activo = 1';
        } elseif ($estado === 'inactivos') {
            $where[] = 'p.activo = 0';
        }
        foreach (array_filter(preg_split('/\s+/', trim($q))) as $term) {
            $like = '%' . addcslashes($term, '%_\\') . '%';
            $where[] = "(p.nombres LIKE ? OR p.apellidos LIKE ? OR p.documento_identidad LIKE ? OR p.codigo LIKE ? OR p.telefono LIKE ?)";
            array_push($params, $like, $like, $like, $like, $like);
        }
        return [implode(' AND ', $where), $params];
    }

    public static function nextCode(): string
    {
        $max = (int) Database::value("SELECT MAX(CAST(SUBSTRING(codigo, 5) AS UNSIGNED)) FROM pacientes");
        return sprintf('SAN-%06d', max($max, 100) + 1);
    }

    /** Resumen clínico para alertas: alergias activas (graves primero), enfermedades vigentes y medicación activa. */
    public static function clinicalSummary(int $pacienteId): array
    {
        return [
            'alergias' => Database::all(
                "SELECT id, agente, tipo, reaccion, severidad FROM alergias WHERE paciente_id = ? AND activa = 1
                  ORDER BY FIELD(severidad, 'grave', 'moderada', 'leve'), agente",
                [$pacienteId]
            ),
            'enfermedades' => Database::all(
                "SELECT id, nombre, estado, cronica FROM enfermedades WHERE paciente_id = ? AND (cronica = 1 OR estado = 'activa') AND estado <> 'resuelta'
                  ORDER BY cronica DESC, nombre",
                [$pacienteId]
            ),
            'medicamentos' => Database::all(
                "SELECT id, nombre, dosis, frecuencia FROM medicamentos WHERE paciente_id = ? AND activo = 1 ORDER BY nombre",
                [$pacienteId]
            ),
        ];
    }

    public static function lastVitals(int $pacienteId): ?array
    {
        return Database::one(
            "SELECT fecha_hora, presion_sistolica, presion_diastolica, frecuencia_cardiaca, temperatura, saturacion_o2, peso_kg, talla_cm
               FROM consultas WHERE paciente_id = ? AND estado = 'realizada'
                AND (presion_sistolica IS NOT NULL OR peso_kg IS NOT NULL OR frecuencia_cardiaca IS NOT NULL)
              ORDER BY fecha_hora DESC LIMIT 1",
            [$pacienteId]
        );
    }
}
