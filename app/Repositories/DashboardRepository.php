<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

/** Todas las cifras del dashboard provienen de consultas SQL directas; no hay datos de relleno. */
final class DashboardRepository
{
    /** Serie diaria continua de N días terminando hoy: [[fecha => etiqueta], ...] con valores por serie. */
    private static function days(int $n): array
    {
        $out = [];
        for ($i = $n - 1; $i >= 0; $i--) {
            $out[date('Y-m-d', strtotime("-$i day"))] = 0;
        }
        return $out;
    }

    // ------------------------------------------------------------ Series y comparaciones

    /** Conteo diario de los últimos N días (lista de enteros, del más antiguo a hoy). */
    private static function diario(string $tabla, string $col, string $where, array $params, int $n): array
    {
        $serie = self::days($n);
        foreach (Database::all("SELECT DATE($col) AS d, COUNT(*) AS n FROM $tabla WHERE $where AND $col >= ? AND $col <= NOW() GROUP BY DATE($col)", [...$params, array_key_first($serie)]) as $r) {
            if (isset($serie[$r['d']])) {
                $serie[$r['d']] = (int) $r['n'];
            }
        }
        return array_values($serie);
    }

    /** Suma de 7 en 7 de una serie diaria (semanas completas terminando hoy). */
    private static function semanal(array $diaria): array
    {
        return array_map('array_sum', array_chunk($diaria, 7));
    }

    /** [actual, anterior]: conteo de los últimos N días y de los N días previos. */
    private static function periodo(string $tabla, string $col, string $where, array $params, int $n): array
    {
        $r = Database::one(
            "SELECT COALESCE(SUM($col >= (NOW() - INTERVAL $n DAY)), 0) AS a, COALESCE(SUM($col < (NOW() - INTERVAL $n DAY)), 0) AS b
               FROM $tabla WHERE $where AND $col >= (NOW() - INTERVAL " . (2 * $n) . " DAY) AND $col <= NOW()",
            $params
        );
        return [(int) $r['a'], (int) $r['b']];
    }

    /** Conteo de los últimos 12 meses: ['Y-m' => n]. */
    private static function mensual(string $tabla, string $col, string $where, array $params, int $meses = 12): array
    {
        $out = [];
        for ($i = $meses - 1; $i >= 0; $i--) {
            $out[date('Y-m', strtotime(date('Y-m-01') . " -$i month"))] = 0;
        }
        foreach (Database::all("SELECT DATE_FORMAT($col, '%Y-%m') AS m, COUNT(*) AS n FROM $tabla WHERE $where AND $col >= ? AND $col <= NOW() GROUP BY m", [...$params, array_key_first($out) . '-01']) as $r) {
            if (isset($out[$r['m']])) {
                $out[$r['m']] = (int) $r['n'];
            }
        }
        return $out;
    }

    /** Etiquetas cortas de mes («oct», y el año cuando cambia: «ene 26»). */
    public static function etiquetasMes(array $claves): array
    {
        $m = ['', 'ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
        return array_map(fn($k) => $m[(int) substr($k, 5, 2)] . (substr($k, 5, 2) === '01' ? ' ' . substr($k, 2, 2) : ''), $claves);
    }

    /** Pacientes activos acumulados al cierre de cada uno de los últimos 12 meses (el último, a hoy). */
    private static function pacientesAcumulados(): array
    {
        $out = [];
        for ($i = 11; $i >= 0; $i--) {
            $fin = $i === 0 ? date('Y-m-d H:i:s') : date('Y-m-t 23:59:59', strtotime(date('Y-m-01') . " -$i month"));
            $out[] = (int) Database::value('SELECT COUNT(*) FROM pacientes WHERE activo = 1 AND creado_en <= ?', [$fin]);
        }
        return $out;
    }

    /** Consultas realizadas y canceladas por semana (12 semanas completas que terminan hoy). */
    private static function semanasEstado(?int $medicoId = null): array
    {
        $w = $medicoId ? ' AND medico_id = ?' : '';
        $par = $medicoId ? [$medicoId] : [];
        return [
            'realizadas' => self::semanal(self::diario('consultas', 'fecha_hora', "estado = 'realizada'$w", $par, 84)),
            'canceladas' => self::semanal(self::diario('consultas', 'fecha_hora', "estado = 'cancelada'$w", $par, 84)),
            'inicio' => array_map(fn($i) => date('d/m', strtotime('-' . (83 - 7 * $i) . ' day')), range(0, 11)),
        ];
    }

    /** Consultas realizadas por día de la semana en los últimos 90 días (lunes a sábado). */
    private static function diasSemana(?int $medicoId = null): array
    {
        $nombres = [2 => 'Lun', 3 => 'Mar', 4 => 'Mié', 5 => 'Jue', 6 => 'Vie', 7 => 'Sáb'];
        $out = array_fill_keys(array_values($nombres), 0);
        $w = $medicoId ? ' AND medico_id = ?' : '';
        foreach (Database::all("SELECT DAYOFWEEK(fecha_hora) AS d, COUNT(*) AS n FROM consultas WHERE estado = 'realizada' AND fecha_hora >= (NOW() - INTERVAL 90 DAY) AND fecha_hora <= NOW()$w GROUP BY d", $medicoId ? [$medicoId] : []) as $r) {
            if (isset($nombres[(int) $r['d']])) {
                $out[$nombres[(int) $r['d']]] = (int) $r['n'];
            }
        }
        if ($out['Sáb'] === 0) {
            unset($out['Sáb']);
        }
        return $out;
    }

    private static function pct(int $parte, int $total): ?float
    {
        return $total > 0 ? round($parte / $total * 100, 1) : null;
    }

    public static function paciente(int $pid): array
    {
        return [
            'vitales' => array_reverse(Database::all(
                "SELECT fecha_hora, presion_sistolica, presion_diastolica, frecuencia_cardiaca, peso_kg
                   FROM consultas WHERE paciente_id = ? AND estado = 'realizada' AND (presion_sistolica IS NOT NULL OR peso_kg IS NOT NULL)
                  ORDER BY fecha_hora DESC LIMIT 8",
                [$pid]
            )),
            'proximas' => Database::all(
                "SELECT c.id, c.fecha_hora, c.motivo, u.nombres, u.apellidos, e.nombre AS especialidad, m.consultorio
                   FROM consultas c JOIN medicos m ON m.id = c.medico_id JOIN usuarios u ON u.id = m.usuario_id JOIN especialidades e ON e.id = m.especialidad_id
                  WHERE c.paciente_id = ? AND c.estado = 'programada' AND c.fecha_hora >= NOW() ORDER BY c.fecha_hora LIMIT 3",
                [$pid]
            ),
            'ultima' => Database::one(
                "SELECT c.id, c.fecha_hora, c.motivo, c.plan, u.nombres, u.apellidos
                   FROM consultas c JOIN medicos m ON m.id = c.medico_id JOIN usuarios u ON u.id = m.usuario_id
                  WHERE c.paciente_id = ? AND c.estado = 'realizada' ORDER BY c.fecha_hora DESC LIMIT 1",
                [$pid]
            ),
            'conteos' => Database::one(
                "SELECT (SELECT COUNT(*) FROM consultas WHERE paciente_id = :a AND estado = 'realizada') AS consultas,
                        (SELECT COUNT(*) FROM medicamentos WHERE paciente_id = :b AND activo = 1) AS medicamentos,
                        (SELECT COUNT(*) FROM documentos_medicos WHERE paciente_id = :c) AS documentos,
                        (SELECT COUNT(*) FROM vacunas WHERE paciente_id = :d AND proxima_dosis IS NOT NULL AND proxima_dosis <= (CURDATE() + INTERVAL 60 DAY)) AS vacunas_pendientes",
                ['a' => $pid, 'b' => $pid, 'c' => $pid, 'd' => $pid]
            ),
            'documentos' => Database::all(
                "SELECT d.id, d.titulo, d.fecha_documento, d.cargado_en, c.nombre AS categoria, c.grupo
                   FROM documentos_medicos d JOIN categorias_documentos c ON c.id = d.categoria_id
                  WHERE d.paciente_id = ? ORDER BY d.cargado_en DESC, d.id DESC LIMIT 5",
                [$pid]
            ),
            'vacunas' => Database::all(
                "SELECT vacuna, dosis, proxima_dosis FROM vacunas WHERE paciente_id = ? AND proxima_dosis IS NOT NULL AND proxima_dosis <= (CURDATE() + INTERVAL 60 DAY) ORDER BY proxima_dosis",
                [$pid]
            ),
        ];
    }

    public static function medico(int $medicoId, int $usuarioId): array
    {
        return [
            'conteos' => Database::one(
                "SELECT (SELECT COUNT(*) FROM consultas WHERE medico_id = :a AND estado = 'programada' AND DATE(fecha_hora) = CURDATE()) AS hoy,
                        (SELECT COUNT(*) FROM consultas WHERE medico_id = :b AND estado = 'programada') AS pendientes,
                        (SELECT COUNT(DISTINCT paciente_id) FROM consultas WHERE medico_id = :c AND estado = 'realizada' AND fecha_hora >= (NOW() - INTERVAL 30 DAY)) AS pacientes_30d,
                        (SELECT COUNT(*) FROM documentos_medicos WHERE cargado_por = :d AND cargado_en >= (NOW() - INTERVAL 30 DAY)) AS documentos_30d",
                ['a' => $medicoId, 'b' => $medicoId, 'c' => $medicoId, 'd' => $usuarioId]
            ),
            'pendientes' => Database::all(
                "SELECT c.id, c.fecha_hora, c.motivo, p.id AS paciente_id, p.codigo, p.nombres, p.apellidos
                   FROM consultas c JOIN pacientes p ON p.id = c.paciente_id
                  WHERE c.medico_id = ? AND c.estado = 'programada' ORDER BY c.fecha_hora LIMIT 20",
                [$medicoId]
            ),
            'recientes' => Database::all(
                "SELECT p.id, p.codigo, p.nombres, p.apellidos, p.fecha_nacimiento, MAX(c.fecha_hora) AS ultima
                   FROM consultas c JOIN pacientes p ON p.id = c.paciente_id
                  WHERE c.medico_id = ? AND c.estado = 'realizada' GROUP BY p.id, p.codigo, p.nombres, p.apellidos, p.fecha_nacimiento
                  ORDER BY ultima DESC LIMIT 6",
                [$medicoId]
            ),
            'alertas' => Database::all(
                "SELECT DISTINCT c.id AS consulta_id, c.fecha_hora, p.id AS paciente_id, p.nombres, p.apellidos, a.agente, a.reaccion
                   FROM consultas c JOIN pacientes p ON p.id = c.paciente_id
                   JOIN alergias a ON a.paciente_id = p.id AND a.activa = 1 AND a.severidad = 'grave'
                  WHERE c.medico_id = ? AND c.estado = 'programada' AND c.fecha_hora BETWEEN NOW() AND (NOW() + INTERVAL 14 DAY)
                  ORDER BY c.fecha_hora",
                [$medicoId]
            ),
            'actividad' => Database::all(
                'SELECT accion, modulo, descripcion, creado_en, paciente_id FROM auditoria WHERE usuario_id = ? ORDER BY id DESC LIMIT 6',
                [$usuarioId]
            ),
            'atrasadas' => (int) Database::value("SELECT COUNT(*) FROM consultas WHERE medico_id = ? AND estado = 'programada' AND fecha_hora < NOW()", [$medicoId]),
            'siguiente' => Database::one(
                "SELECT c.id, c.fecha_hora, c.motivo, p.id AS paciente_id, p.codigo, p.nombres, p.apellidos, p.fecha_nacimiento,
                        (SELECT GROUP_CONCAT(a.agente SEPARATOR ', ') FROM alergias a WHERE a.paciente_id = p.id AND a.activa = 1 AND a.severidad = 'grave') AS alergias_graves
                   FROM consultas c JOIN pacientes p ON p.id = c.paciente_id
                  WHERE c.medico_id = ? AND c.estado = 'programada' ORDER BY c.fecha_hora LIMIT 1",
                [$medicoId]
            ),
            'serie' => self::medicoSerie($medicoId),
            'kpi' => self::medicoKpi($medicoId, $usuarioId),
        ];
    }

    private static function medicoKpi(int $medicoId, int $usuarioId): array
    {
        $realizadas = "estado = 'realizada' AND medico_id = ?";
        $diaria = self::diario('consultas', 'fecha_hora', $realizadas, [$medicoId], 84);
        $pac = Database::one(
            "SELECT COUNT(DISTINCT CASE WHEN fecha_hora >= (NOW() - INTERVAL 30 DAY) THEN paciente_id END) AS a,
                    COUNT(DISTINCT CASE WHEN fecha_hora < (NOW() - INTERVAL 30 DAY) THEN paciente_id END) AS b
               FROM consultas WHERE $realizadas AND fecha_hora >= (NOW() - INTERVAL 60 DAY) AND fecha_hora <= NOW()",
            [$medicoId]
        );
        $c90 = Database::one(
            "SELECT COALESCE(SUM(estado = 'realizada'), 0) AS r, COALESCE(SUM(estado = 'cancelada'), 0) AS c,
                    COALESCE(SUM(estado = 'realizada' AND EXISTS (SELECT 1 FROM diagnosticos d WHERE d.consulta_id = consultas.id)), 0) AS con_dx
               FROM consultas WHERE medico_id = ? AND fecha_hora >= (NOW() - INTERVAL 90 DAY) AND fecha_hora <= NOW()",
            [$medicoId]
        );
        $edades = Database::one(
            "SELECT COALESCE(SUM(e < 18), 0) AS g1, COALESCE(SUM(e BETWEEN 18 AND 39), 0) AS g2, COALESCE(SUM(e BETWEEN 40 AND 64), 0) AS g3, COALESCE(SUM(e >= 65), 0) AS g4
               FROM (SELECT DISTINCT p.id, TIMESTAMPDIFF(YEAR, p.fecha_nacimiento, CURDATE()) AS e
                       FROM consultas c JOIN pacientes p ON p.id = c.paciente_id
                      WHERE c.medico_id = ? AND c.estado = 'realizada' AND c.fecha_hora >= (NOW() - INTERVAL 12 MONTH)) t",
            [$medicoId]
        );
        return [
            'consultas' => self::periodo('consultas', 'fecha_hora', $realizadas, [$medicoId], 30),
            'consultas_serie' => array_slice($diaria, -30),
            'estado_semanas' => self::semanasEstado($medicoId),
            'dias_semana' => self::diasSemana($medicoId),
            'semanas' => self::semanal($diaria),
            'pacientes' => [(int) $pac['a'], (int) $pac['b']],
            'documentos' => self::periodo('documentos_medicos', 'cargado_en', 'cargado_por = ?', [$usuarioId], 30),
            'documentos_serie' => self::diario('documentos_medicos', 'cargado_en', 'cargado_por = ?', [$usuarioId], 30),
            'cumplimiento' => [self::pct((int) $c90['r'], (int) $c90['r'] + (int) $c90['c']), (int) $c90['r'], (int) $c90['r'] + (int) $c90['c']],
            'diagnostico' => [self::pct((int) $c90['con_dx'], (int) $c90['r']), (int) $c90['con_dx'], (int) $c90['r']],
            'edades' => [['Menores de 18', (int) $edades['g1']], ['18 a 39 años', (int) $edades['g2']], ['40 a 64 años', (int) $edades['g3']], ['65 años o más', (int) $edades['g4']]],
            'diagnosticos' => array_map(fn($r) => [$r['descripcion'], (int) $r['n'], $r['cie']], Database::all(
                "SELECT d.descripcion, MAX(d.codigo_cie10) AS cie, COUNT(*) AS n FROM diagnosticos d JOIN consultas c ON c.id = d.consulta_id
                  WHERE c.medico_id = ? AND c.estado = 'realizada' AND c.fecha_hora >= (NOW() - INTERVAL 12 MONTH)
                  GROUP BY d.descripcion ORDER BY n DESC, d.descripcion LIMIT 6",
                [$medicoId]
            )),
            'canceladas' => self::periodo('consultas', 'fecha_hora', "estado = 'cancelada' AND medico_id = ?", [$medicoId], 30),
            'hoy' => Database::all(
                "SELECT c.id, c.fecha_hora, c.motivo, c.estado, p.id AS paciente_id, p.codigo, p.nombres, p.apellidos, p.fecha_nacimiento,
                        (SELECT GROUP_CONCAT(a.agente SEPARATOR ', ') FROM alergias a WHERE a.paciente_id = p.id AND a.activa = 1 AND a.severidad = 'grave') AS alergias_graves
                   FROM consultas c JOIN pacientes p ON p.id = c.paciente_id
                  WHERE c.medico_id = ? AND DATE(c.fecha_hora) = CURDATE() AND c.estado <> 'cancelada' ORDER BY c.fecha_hora",
                [$medicoId]
            ),
        ];
    }

    private static function medicoSerie(int $medicoId): array
    {
        $serie = self::days(14);
        foreach (Database::all("SELECT DATE(fecha_hora) AS d, COUNT(*) AS n FROM consultas WHERE medico_id = ? AND estado = 'realizada' AND fecha_hora >= (CURDATE() - INTERVAL 13 DAY) GROUP BY DATE(fecha_hora)", [$medicoId]) as $r) {
            $serie[$r['d']] = (int) $r['n'];
        }
        return $serie;
    }

    private static function accesos(): array
    {
        $ok = self::days(7);
        $mal = self::days(7);
        foreach (Database::all("SELECT DATE(creado_en) AS d, accion, COUNT(*) AS n FROM auditoria WHERE accion IN ('login', 'login_fallido') AND creado_en >= (CURDATE() - INTERVAL 6 DAY) GROUP BY DATE(creado_en), accion") as $r) {
            if ($r['accion'] === 'login') { $ok[$r['d']] = (int) $r['n']; } else { $mal[$r['d']] = (int) $r['n']; }
        }
        return ['ok' => $ok, 'fallidos' => $mal];
    }

    public static function admin(): array
    {
        return [
            'conteos' => Database::one(
                "SELECT (SELECT COUNT(*) FROM usuarios WHERE activo = 1) AS usuarios,
                        (SELECT COUNT(*) FROM pacientes WHERE activo = 1) AS pacientes,
                        (SELECT COUNT(*) FROM medicos WHERE activo = 1) AS medicos,
                        (SELECT COUNT(*) FROM consultas WHERE estado = 'realizada' AND fecha_hora >= (NOW() - INTERVAL 30 DAY)) AS consultas_30d"
            ),
            'seguridad' => Database::one(
                "SELECT COALESCE(SUM(accion = 'login'), 0) AS accesos,
                        COALESCE(SUM(accion = 'login_fallido'), 0) AS fallidos,
                        COALESCE(SUM(accion = 'acceso_denegado'), 0) AS denegados,
                        (SELECT COUNT(*) FROM usuarios WHERE bloqueado_hasta > NOW()) AS bloqueadas
                   FROM auditoria WHERE creado_en >= (NOW() - INTERVAL 24 HOUR)"
            ),
            'por_rol' => Database::all(
                'SELECT r.nombre, COUNT(u.id) AS total, COALESCE(SUM(u.activo), 0) AS activos
                   FROM roles r LEFT JOIN usuarios u ON u.rol_id = r.id GROUP BY r.id, r.nombre ORDER BY r.id'
            ),
            'accesos_7d' => self::accesos(),
            'pendientes_acceso' => (int) Database::value("SELECT COUNT(*) FROM usuarios WHERE activo = 1 AND debe_cambiar_clave = 1 AND ultimo_acceso IS NULL"),
            'pacientes_sin_cuenta' => (int) Database::value('SELECT COUNT(*) FROM pacientes WHERE usuario_id IS NULL AND activo = 1'),
            'actividad' => Database::all(
                'SELECT id, usuario_nombre, rol, accion, modulo, descripcion, creado_en FROM auditoria ORDER BY id DESC LIMIT 8'
            ),
            'kpi' => self::adminKpi(),
        ];
    }

    private static function adminKpi(): array
    {
        $realizadas = "estado = 'realizada'";
        $portal = Database::one('SELECT COUNT(*) AS t, COALESCE(SUM(usuario_id IS NOT NULL), 0) AS c FROM pacientes WHERE activo = 1');
        $log = Database::one("SELECT COALESCE(SUM(accion = 'login'), 0) AS ok, COALESCE(SUM(accion = 'login_fallido'), 0) AS mal FROM auditoria WHERE accion IN ('login', 'login_fallido') AND creado_en >= (NOW() - INTERVAL 30 DAY)");
        $c90 = Database::one("SELECT COALESCE(SUM(estado = 'realizada'), 0) AS r, COALESCE(SUM(estado = 'cancelada'), 0) AS c FROM consultas WHERE fecha_hora >= (NOW() - INTERVAL 90 DAY) AND fecha_hora <= NOW()");
        $meses = self::mensual('consultas', 'fecha_hora', $realizadas, [], 13);
        $mesActual = array_pop($meses);   // el mes en curso va aparte: la serie muestra 12 meses completos
        return [
            'pacientes' => self::periodo('pacientes', 'creado_en', '1=1', [], 30),
            'pacientes_serie' => self::semanal(self::diario('pacientes', 'creado_en', '1=1', [], 84)),
            'consultas' => self::periodo('consultas', 'fecha_hora', $realizadas, [], 30),
            'consultas_serie' => self::diario('consultas', 'fecha_hora', $realizadas, [], 30),
            'documentos' => self::periodo('documentos_medicos', 'cargado_en', '1=1', [], 30),
            'documentos_serie' => self::diario('documentos_medicos', 'cargado_en', '1=1', [], 30),
            'accesos' => self::periodo('auditoria', 'creado_en', "accion = 'login'", [], 7),
            'accesos_serie' => self::diario('auditoria', 'creado_en', "accion = 'login'", [], 14),
            'meses' => self::etiquetasMes(array_keys($meses)),
            'mensual_consultas' => array_values($meses),
            'mes_actual' => $mesActual,
            'mensual_documentos' => array_values(self::mensual('documentos_medicos', 'cargado_en', '1=1', [])),
            'portal' => [self::pct((int) $portal['c'], (int) $portal['t']), (int) $portal['c'], (int) $portal['t']],
            'login_ok' => [self::pct((int) $log['ok'], (int) $log['ok'] + (int) $log['mal']), (int) $log['ok'], (int) $log['ok'] + (int) $log['mal']],
            'asistencia' => [self::pct((int) $c90['r'], (int) $c90['r'] + (int) $c90['c']), (int) $c90['r'], (int) $c90['r'] + (int) $c90['c']],
            'anual' => Database::one(
                "SELECT COALESCE(SUM(estado = 'realizada'), 0) AS realizadas, COALESCE(SUM(estado = 'cancelada'), 0) AS canceladas,
                        COALESCE(SUM(estado = 'realizada' AND EXISTS (SELECT 1 FROM documentos_medicos d WHERE d.consulta_id = consultas.id)), 0) AS con_doc
                   FROM consultas WHERE fecha_hora >= (NOW() - INTERVAL 12 MONTH) AND fecha_hora <= NOW()"
            ),
            'documentos_12m' => (int) Database::value('SELECT COUNT(*) FROM documentos_medicos WHERE cargado_en >= (NOW() - INTERVAL 12 MONTH)'),
            'semanas' => self::semanasEstado(),
            'emitidos' => self::periodo('documentos_emitidos', 'creado_en', "tipo <> 'muestra'", [], 30),
            'emitidos_serie' => self::diario('documentos_emitidos', 'creado_en', "tipo <> 'muestra'", [], 30),
            'por_medico' => Database::all(
                "SELECT u.nombres, u.apellidos, e.nombre AS especialidad,
                        COALESCE(SUM(c.estado = 'realizada' AND c.fecha_hora >= (NOW() - INTERVAL 30 DAY) AND c.fecha_hora <= NOW()), 0) AS r30,
                        COALESCE(SUM(c.estado = 'realizada' AND c.fecha_hora >= (NOW() - INTERVAL 90 DAY) AND c.fecha_hora <= NOW()), 0) AS r90,
                        COALESCE(SUM(c.estado = 'cancelada' AND c.fecha_hora >= (NOW() - INTERVAL 90 DAY) AND c.fecha_hora <= NOW()), 0) AS c90,
                        COALESCE(SUM(c.estado = 'programada' AND c.fecha_hora >= NOW()), 0) AS agenda,
                        COALESCE(SUM(c.estado = 'programada' AND DATE(c.fecha_hora) = CURDATE()), 0) AS hoy
                   FROM medicos m JOIN usuarios u ON u.id = m.usuario_id JOIN especialidades e ON e.id = m.especialidad_id
                   LEFT JOIN consultas c ON c.medico_id = m.id
                  WHERE m.activo = 1 GROUP BY m.id, u.nombres, u.apellidos, e.nombre ORDER BY r30 DESC"
            ),
            'canceladas' => self::periodo('consultas', 'fecha_hora', "estado = 'cancelada'", [], 30),
            'acumulado' => self::pacientesAcumulados(),
            'edad_sexo' => Database::all(
                "SELECT CASE WHEN e < 18 THEN 'Menores de 18' WHEN e < 40 THEN '18 a 39 años' WHEN e < 65 THEN '40 a 64 años' ELSE '65 años o más' END AS grupo,
                        MIN(e) AS orden, SUM(sexo = 'F') AS f, SUM(sexo = 'M') AS m, COUNT(*) AS t
                   FROM (SELECT sexo, TIMESTAMPDIFF(YEAR, fecha_nacimiento, CURDATE()) AS e FROM pacientes WHERE activo = 1) t
                  GROUP BY grupo ORDER BY orden"
            ),
            'dias_semana' => self::diasSemana(),
            'edades' => Database::one(
                "SELECT COALESCE(SUM(e < 18), 0) AS g1, COALESCE(SUM(e BETWEEN 18 AND 39), 0) AS g2, COALESCE(SUM(e BETWEEN 40 AND 64), 0) AS g3, COALESCE(SUM(e >= 65), 0) AS g4
                   FROM (SELECT TIMESTAMPDIFF(YEAR, fecha_nacimiento, CURDATE()) AS e FROM pacientes WHERE activo = 1) t"
            ),
            'atrasadas' => (int) Database::value("SELECT COUNT(*) FROM consultas WHERE estado = 'programada' AND fecha_hora < NOW()"),
            'especialidades' => array_map(fn($r) => [$r['nombre'], (int) $r['n']], Database::all(
                "SELECT e.nombre, COUNT(c.id) AS n FROM especialidades e
                   JOIN medicos m ON m.especialidad_id = e.id
                   LEFT JOIN consultas c ON c.medico_id = m.id AND c.estado = 'realizada' AND c.fecha_hora >= (NOW() - INTERVAL 12 MONTH) AND c.fecha_hora <= NOW()
                  GROUP BY e.id, e.nombre ORDER BY n DESC, e.nombre LIMIT 6"
            )),
        ];
    }
}
