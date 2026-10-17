<?php
declare(strict_types=1);

namespace App\Core;

/** Bitácora: quién hizo qué, cuándo y sobre qué información. */
final class Audit
{
    public static function log(
        string $accion,
        string $modulo,
        string $descripcion,
        ?int $registroId = null,
        ?int $pacienteId = null,
        ?array $detalle = null,
        ?array $actor = null,
    ): void {
        $u = $actor ?? Auth::user();
        try {
            Database::insert('auditoria', [
                'usuario_id'     => $u['id'] ?? null,
                'usuario_nombre' => $u ? nombre_completo($u) : 'Anónimo',
                'rol'            => $u['rol_codigo'] ?? null,
                'accion'         => $accion,
                'modulo'         => $modulo,
                'registro_id'    => $registroId,
                'paciente_id'    => $pacienteId,
                'descripcion'    => mb_substr($descripcion, 0, 255),
                'detalle'        => $detalle ? json_encode($detalle, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
                'ip'             => $_SERVER['REMOTE_ADDR'] ?? null,
            ]);
        } catch (\Throwable $e) {
            Logger::error('Fallo al escribir auditoría', $e);
        }
    }

    /**
     * Nombres de los campos modificados, sin sus valores. Se usa con información clínica y personal:
     * la bitácora indica QUÉ cambió y quién lo hizo sin duplicar datos sensibles.
     * @return list<string>
     */
    public static function changedFields(array $antes, array $despues): array
    {
        return array_keys(self::diff($antes, $despues));
    }

    /** Diferencias campo a campo entre dos versiones de un registro (solo para datos no clínicos). */
    public static function diff(array $antes, array $despues): array
    {
        $out = [];
        foreach ($despues as $k => $nuevo) {
            $viejo = $antes[$k] ?? null;
            if ((string) $viejo !== (string) $nuevo) {
                $out[$k] = ['antes' => $viejo, 'despues' => $nuevo];
            }
        }
        return $out;
    }
}
