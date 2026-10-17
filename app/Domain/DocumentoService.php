<?php
declare(strict_types=1);

namespace App\Domain;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Database;
use App\Core\Upload;
use App\Core\Validator;

/** Validación y registro de documentos médicos; lo usan la carga directa y el formulario de consulta. */
final class DocumentoService
{
    public const RULES = [
        'categoria_id'    => 'required|int',
        'titulo'          => 'required|max:150',
        'fecha_documento' => 'past',
        'descripcion'     => 'max:255',
    ];
    public const LABELS = ['categoria_id' => 'Categoría', 'titulo' => 'Título', 'fecha_documento' => 'Fecha del documento', 'descripcion' => 'Descripción'];

    /**
     * Valida los campos y el archivo. @return array{0:Validator,1:array} validador y resultado de Upload::validate
     */
    public static function validate(array $post, ?array $file, int $pacienteId, string $prefix = ''): array
    {
        $data = [];
        foreach (self::RULES as $k => $_) {
            $data[$k] = $post[$prefix . $k] ?? null;
        }
        $v = Validator::make($data, self::RULES, self::LABELS);
        if (!$v->errors() && !Database::value('SELECT id FROM categorias_documentos WHERE id = ?', [$v->clean()['categoria_id']])) {
            $v->addError('categoria_id', 'Seleccione una categoría válida.');
        }
        $up = Upload::validate($file);
        return [$v, $up];
    }

    /** Guarda archivo y metadatos. Devuelve el id del documento. */
    public static function store(int $pacienteId, ?int $consultaId, Validator $v, array $file, array $up): int
    {
        $meta = Upload::store($file, $up);
        $c = $v->clean();
        try {
            $id = Database::insert('documentos_medicos', $meta + [
                'paciente_id'     => $pacienteId,
                'consulta_id'     => $consultaId,
                'categoria_id'    => $c['categoria_id'],
                'titulo'          => $c['titulo'],
                'descripcion'     => $c['descripcion'],
                'fecha_documento' => $c['fecha_documento'],
                'cargado_por'     => Auth::user()['id'],
            ]);
        } catch (\Throwable $e) {
            @unlink(Upload::path($meta['nombre_interno']));
            throw $e;
        }
        Audit::log('cargar', 'documentos', 'Documento cargado: ' . mb_substr($c['titulo'], 0, 120) . ' (' . $up['ext'] . ', ' . bytes_legible($up['size']) . ')', $id, $pacienteId);
        \App\Domain\Notificaciones::aPaciente($pacienteId, 'documento_nuevo', 'Nuevo documento en su historial', $c['titulo'], '/documentos/' . $id, 'info');
        \App\Domain\Notificaciones::aMedicosDe($pacienteId, 'documento_nuevo', 'Documento nuevo de ' . (string) Database::value("SELECT CONCAT(nombres, ' ', apellidos) FROM pacientes WHERE id = ?", [$pacienteId]), $c['titulo'], '/documentos/' . $id, 'info');
        return $id;
    }

    /** Categorías agrupadas para <optgroup>. @return array<string,array<int,string>> */
    public static function categoriasAgrupadas(): array
    {
        $grupos = ['examen' => 'Exámenes de laboratorio', 'imagen' => 'Imágenes diagnósticas', 'documento' => 'Documentos médicos'];
        $out = [];
        foreach (Database::all('SELECT id, nombre, grupo FROM categorias_documentos ORDER BY FIELD(grupo, "examen", "imagen", "documento"), nombre') as $c) {
            $out[$grupos[$c['grupo']]][(int) $c['id']] = $c['nombre'];
        }
        return $out;
    }
}
