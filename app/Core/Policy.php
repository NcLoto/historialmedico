<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Autorización por registro. Los permisos (Auth::can) dicen QUÉ módulo se puede usar;
 * la política dice SOBRE QUÉ registros: un paciente solo accede a lo suyo.
 */
final class Policy
{
    /** Lanza 403 si el usuario no puede acceder al paciente; 404 si no existe. */
    public static function patient(int $id): array
    {
        if (Auth::isPatient() && Auth::patientId() !== $id) {
            throw new HttpException(403);
        }
        $p = Database::one('SELECT * FROM pacientes WHERE id = ?', [$id]);
        if (!$p) {
            throw new HttpException(404);
        }
        return $p;
    }

    /** Solo personal (médico/administrador): bloquea a pacientes aunque tengan el permiso del módulo. */
    public static function staffOnly(): void
    {
        if (Auth::isPatient()) {
            throw new HttpException(403);
        }
    }

    /** Verifica pertenencia de un registro clínico/consulta/documento al paciente accesible. */
    public static function ownsRecord(int $pacienteId): void
    {
        if (Auth::isPatient() && Auth::patientId() !== $pacienteId) {
            throw new HttpException(403);
        }
    }
}
