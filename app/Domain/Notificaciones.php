<?php
declare(strict_types=1);

namespace App\Domain;

use App\Core\Auth;
use App\Core\Config;
use App\Core\Database;
use App\Core\Logger;

/**
 * Notificaciones internas. Un evento del sistema llama a enviar() (o a los atajos para paciente, médicos o
 * administradores); el servicio respeta la configuración global, las preferencias de cada usuario y nunca
 * notifica a quien realizó la acción. Los recordatorios se generan bajo demanda, sin tareas programadas.
 */
final class Notificaciones
{
    /** tipo => [nombre, descripción, roles a los que aplica] */
    public const TIPOS = [
        'consulta_programada'   => ['Consultas programadas', 'Cuando se programa una consulta con usted.', ['paciente', 'medico']],
        'consulta_registrada'   => ['Consultas registradas', 'Cuando el médico registra la atención de su consulta.', ['paciente']],
        'consulta_cancelada'    => ['Consultas canceladas', 'Cuando se cancela una consulta programada.', ['paciente', 'medico']],
        'recordatorio_consulta' => ['Recordatorios de consulta', 'Aviso previo a cada consulta programada.', ['paciente', 'medico']],
        'documento_nuevo'       => ['Documentos nuevos', 'Cuando se adjunta un examen, imagen o informe.', ['paciente', 'medico']],
        'alergia_grave'         => ['Alergias graves', 'Cuando se registra una alergia grave a un paciente que usted atenderá.', ['medico']],
        'vacuna_pendiente'      => ['Vacunas pendientes', 'Cuando una dosis está próxima a vencer o vencida.', ['paciente']],
        'seguridad'             => ['Alertas de seguridad', 'Cuentas bloqueadas por intentos fallidos y cambios de permisos.', ['administrador']],
        'cuentas'               => ['Cuentas de usuario', 'Altas de usuarios y médicos realizadas por otros administradores.', ['administrador']],
        'aviso'                 => ['Avisos institucionales', 'Comunicados enviados por la administración del hospital.', ['paciente', 'medico', 'administrador']],
    ];
    public const NIVELES = ['info' => 'Información', 'aviso' => 'Aviso', 'critico' => 'Importante', 'exito' => 'Confirmación'];

    /** Tipos que aplican a un rol. @return array<string,array> */
    public static function tiposDe(string $rol): array
    {
        return array_filter(self::TIPOS, fn($t) => in_array($rol, $t[2], true));
    }

    public static function enviar(
        int $usuarioId,
        string $tipo,
        string $titulo,
        ?string $mensaje = null,
        ?string $enlace = null,
        string $nivel = 'info',
        ?int $pacienteId = null,
        ?string $clave = null,
        bool $incluirActor = false,
        ?string $correoDetalle = null,
    ): bool {
        try {
            if (!$incluirActor && (int) (Auth::user()['id'] ?? 0) === $usuarioId) {
                return false;
            }
            if (Config::get('notif_activas', '1') !== '1' || in_array($tipo, self::tiposApagados(), true)) {
                return false;
            }
            if (Database::value('SELECT activo FROM notificacion_preferencias WHERE usuario_id = ? AND tipo = ?', [$usuarioId, $tipo]) === 0) {
                return false;
            }
            if (!Database::value('SELECT activo FROM usuarios WHERE id = ?', [$usuarioId])) {
                return false;
            }
            $enlace = ($enlace !== null && str_starts_with($enlace, '/') && !str_starts_with($enlace, '//')) ? $enlace : null;
            $nueva = Database::run(
                'INSERT IGNORE INTO notificaciones (usuario_id, tipo, nivel, titulo, mensaje, enlace, paciente_id, clave) VALUES (?,?,?,?,?,?,?,?)',
                [$usuarioId, $tipo, isset(self::NIVELES[$nivel]) ? $nivel : 'info', mb_substr($titulo, 0, 160), $mensaje !== null ? mb_substr($mensaje, 0, 255) : null, $enlace, $pacienteId, $clave]
            ) > 0;
            // Una notificación repetida (misma clave) no se crea de nuevo: tampoco genera otro correo
            if ($nueva) {
                Correo::deNotificacion($usuarioId, $tipo, $titulo, $mensaje, $enlace, $correoDetalle, $clave, $pacienteId);
            }
            return $nueva;
        } catch (\Throwable $e) {
            Logger::error('No se pudo registrar una notificación', $e);
            return false;
        }
    }

    /** A la cuenta del paciente; si no tiene cuenta, por correo cuando lo autorizó en su ficha. */
    public static function aPaciente(int $pacienteId, string $tipo, string $titulo, ?string $mensaje, ?string $enlace, string $nivel = 'info', ?string $clave = null, ?string $correoDetalle = null): void
    {
        $uid = Database::value('SELECT usuario_id FROM pacientes WHERE id = ?', [$pacienteId]);
        if ($uid) {
            self::enviar((int) $uid, $tipo, $titulo, $mensaje, $enlace, $nivel, $pacienteId, $clave, false, $correoDetalle);
        } elseif (Config::get('notif_activas', '1') === '1' && !in_array($tipo, self::tiposApagados(), true)) {
            Correo::aPacienteSinCuenta($pacienteId, $tipo, $correoDetalle, $clave);
        }
    }

    /** A los médicos que tienen consultas programadas con el paciente (los que lo atenderán). */
    public static function aMedicosDe(int $pacienteId, string $tipo, string $titulo, ?string $mensaje, ?string $enlace, string $nivel = 'info'): void
    {
        $ids = Database::all(
            "SELECT DISTINCT m.usuario_id FROM consultas c JOIN medicos m ON m.id = c.medico_id
              WHERE c.paciente_id = ? AND c.estado = 'programada' AND m.activo = 1",
            [$pacienteId]
        );
        foreach (array_column($ids, 'usuario_id') as $uid) {
            self::enviar((int) $uid, $tipo, $titulo, $mensaje, $enlace, $nivel, $pacienteId);
        }
    }

    public static function aAdministradores(string $tipo, string $titulo, ?string $mensaje, ?string $enlace, string $nivel = 'info'): void
    {
        $ids = Database::all("SELECT u.id FROM usuarios u JOIN roles r ON r.id = u.rol_id WHERE r.codigo = 'administrador' AND u.activo = 1");
        foreach (array_column($ids, 'id') as $uid) {
            self::enviar((int) $uid, $tipo, $titulo, $mensaje, $enlace, $nivel);
        }
    }

    /** Aviso institucional a todos los usuarios activos de los roles indicados. Devuelve cuántos lo recibieron. */
    public static function aviso(array $roles, string $titulo, ?string $mensaje, string $nivel): int
    {
        if (!$roles) {
            return 0;
        }
        $in = implode(',', array_fill(0, count($roles), '?'));
        $ids = Database::all("SELECT u.id FROM usuarios u JOIN roles r ON r.id = u.rol_id WHERE u.activo = 1 AND r.codigo IN ($in)", array_values($roles));
        $n = 0;
        foreach (array_column($ids, 'id') as $uid) {
            $n += self::enviar((int) $uid, 'aviso', $titulo, $mensaje, '/notificaciones', $nivel, null, null, true) ? 1 : 0;
        }
        return $n;
    }

    // ------------------------------------------------------------ Consulta y estado

    public static function noLeidas(int $usuarioId): int
    {
        return (int) Database::value('SELECT COUNT(*) FROM notificaciones WHERE usuario_id = ? AND leida_en IS NULL', [$usuarioId]);
    }

    public static function recientes(int $usuarioId, int $n = 6): array
    {
        return Database::all('SELECT * FROM notificaciones WHERE usuario_id = ? ORDER BY leida_en IS NOT NULL, id DESC LIMIT ' . max(1, $n), [$usuarioId]);
    }

    /** @return list<string> */
    public static function tiposApagados(): array
    {
        return array_values(array_filter(explode(',', Config::get('notif_tipos_off'))));
    }

    /** Preferencias efectivas del usuario: tipo => activo. */
    public static function preferencias(int $usuarioId, string $rol): array
    {
        $guardadas = array_column(Database::all('SELECT tipo, activo FROM notificacion_preferencias WHERE usuario_id = ?', [$usuarioId]), 'activo', 'tipo');
        $out = [];
        foreach (array_keys(self::tiposDe($rol)) as $t) {
            $out[$t] = (int) ($guardadas[$t] ?? 1);
        }
        return $out;
    }

    /**
     * Recordatorios de consultas próximas para todos los pacientes y médicos (tarea programada). La clave única
     * por consulta evita duplicados, así que puede ejecutarse cada pocos minutos. Devuelve cuántas consultas revisó.
     */
    public static function recordatoriosProgramados(): int
    {
        if (Config::get('notif_activas', '1') !== '1' || in_array('recordatorio_consulta', self::tiposApagados(), true)) {
            return 0;
        }
        $horas = max(1, Config::int('notif_recordatorio_horas', 48));
        $rows = Database::all(
            "SELECT c.id, c.fecha_hora, c.motivo, p.id AS pid, p.usuario_id AS paciente_uid, p.nombres, p.apellidos, m.usuario_id AS medico_uid
               FROM consultas c JOIN pacientes p ON p.id = c.paciente_id JOIN medicos m ON m.id = c.medico_id
              WHERE c.estado = 'programada' AND c.fecha_hora BETWEEN NOW() AND (NOW() + INTERVAL ? HOUR) AND p.activo = 1",
            [$horas]
        );
        foreach ($rows as $c) {
            $cuando = fecha_larga($c['fecha_hora']);
            if ($c['paciente_uid']) {
                self::enviar((int) $c['paciente_uid'], 'recordatorio_consulta', 'Consulta el ' . fecha_hora($c['fecha_hora']), $c['motivo'], '/consultas/' . $c['id'], 'aviso', (int) $c['pid'], 'rec-c' . $c['id'], true, 'del ' . $cuando);
            } else {
                Correo::aPacienteSinCuenta((int) $c['pid'], 'recordatorio_consulta', 'del ' . $cuando, 'rec-c' . $c['id']);
            }
            self::enviar((int) $c['medico_uid'], 'recordatorio_consulta', nombre_completo($c) . ', ' . fecha_hora($c['fecha_hora']), $c['motivo'], '/consultas/' . $c['id'], 'aviso', (int) $c['pid'], 'rec-c' . $c['id'], true, 'programada en su agenda para el ' . $cuando);
        }
        return count($rows);
    }

    /**
     * Genera los recordatorios pendientes del usuario (consultas próximas y vacunas) y depura las leídas antiguas.
     * Se ejecuta como máximo cada 5 minutos por sesión; la clave única evita duplicados.
     */
    public static function sincronizar(array $u): void
    {
        if (time() - (int) ($_SESSION['_notif_sync'] ?? 0) < 300) {
            return;
        }
        $_SESSION['_notif_sync'] = time();
        try {
            $horas = max(1, Config::int('notif_recordatorio_horas', 48));
            $uid = (int) $u['id'];
            if ($u['rol_codigo'] === 'paciente') {
                $pid = Database::value('SELECT id FROM pacientes WHERE usuario_id = ?', [$uid]);
                if ($pid) {
                    foreach (Database::all("SELECT c.id, c.fecha_hora, c.motivo FROM consultas c WHERE c.paciente_id = ? AND c.estado = 'programada' AND c.fecha_hora BETWEEN NOW() AND (NOW() + INTERVAL ? HOUR)", [$pid, $horas]) as $c) {
                        self::enviar($uid, 'recordatorio_consulta', 'Consulta el ' . fecha_hora($c['fecha_hora']), $c['motivo'], '/consultas/' . $c['id'], 'aviso', (int) $pid, 'rec-c' . $c['id'], true, 'del ' . fecha_larga($c['fecha_hora']));
                    }
                    foreach (Database::all('SELECT id, vacuna, dosis, proxima_dosis FROM vacunas WHERE paciente_id = ? AND proxima_dosis IS NOT NULL AND proxima_dosis <= (CURDATE() + INTERVAL 15 DAY)', [$pid]) as $v) {
                        $vencida = $v['proxima_dosis'] < date('Y-m-d');
                        self::enviar($uid, 'vacuna_pendiente', ($vencida ? 'Dosis vencida: ' : 'Próxima dosis: ') . $v['vacuna'], ($v['dosis'] ? $v['dosis'] . ', ' : '') . 'Fecha indicada ' . fecha($v['proxima_dosis']), '/mi-historial#vacunas', $vencida ? 'aviso' : 'info', (int) $pid, 'vac-' . $v['id'] . '-' . $v['proxima_dosis'], true);
                    }
                }
            } elseif ($u['rol_codigo'] === 'medico') {
                $rows = Database::all(
                    "SELECT c.id, c.fecha_hora, c.motivo, p.id AS pid, p.nombres, p.apellidos FROM consultas c JOIN medicos m ON m.id = c.medico_id JOIN pacientes p ON p.id = c.paciente_id
                      WHERE m.usuario_id = ? AND c.estado = 'programada' AND c.fecha_hora BETWEEN NOW() AND (NOW() + INTERVAL ? HOUR)",
                    [$uid, $horas]
                );
                foreach ($rows as $c) {
                    self::enviar($uid, 'recordatorio_consulta', nombre_completo($c) . ', ' . fecha_hora($c['fecha_hora']), $c['motivo'], '/consultas/' . $c['id'], 'aviso', (int) $c['pid'], 'rec-c' . $c['id'], true, 'programada en su agenda para el ' . fecha_larga($c['fecha_hora']));
                }
            }
            Database::run('DELETE FROM notificaciones WHERE usuario_id = ? AND leida_en IS NOT NULL AND leida_en < (NOW() - INTERVAL ? DAY)', [$uid, max(1, Config::int('notif_retencion_dias', 90))]);
        } catch (\Throwable $e) {
            Logger::error('Fallo al sincronizar notificaciones', $e);
        }
    }
}
