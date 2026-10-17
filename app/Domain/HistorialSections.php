<?php
declare(strict_types=1);

namespace App\Domain;

/**
 * Definición de las ocho secciones del historial clínico. Cada sección alimenta formulario,
 * validación y presentación; los nombres de tabla provienen solo de esta lista blanca.
 *
 * Campo: [etiqueta, tipo(text|textarea|select|date|bool|number), reglas, opciones?, columnas(1-12), ayuda?]
 */
final class HistorialSections
{
    public const SEVERIDAD = ['leve' => 'Leve', 'moderada' => 'Moderada', 'grave' => 'Grave'];

    public static function all(): array
    {
        return [
            'antecedentes' => [
                'table' => 'antecedentes_medicos', 'titulo' => 'Antecedentes personales', 'singular' => 'antecedente personal',
                'icon' => 'history', 'vacio' => 'No hay antecedentes personales registrados.',
                'order' => "FIELD(tipo,'patologico','traumatico','ginecoobstetrico','otro'), id DESC",
                'title' => 'descripcion',
                'meta' => ['tipo' => null, 'fecha_aproximada' => 'Fecha'],
                'fields' => [
                    'tipo'             => ['Tipo', 'select', 'required|in:patologico,traumatico,ginecoobstetrico,otro', ['patologico' => 'Patológico', 'traumatico' => 'Traumático', 'ginecoobstetrico' => 'Ginecoobstétrico', 'otro' => 'Otro'], 4],
                    'fecha_aproximada' => ['Fecha o año aproximado', 'text', 'max:40', null, 4, 'Por ejemplo: 2014 o marzo de 2019.'],
                    'descripcion'      => ['Descripción', 'text', 'required|max:255', null, 12],
                    'observaciones'    => ['Observaciones', 'textarea', 'max:2000', null, 12],
                ],
            ],
            'alergias' => [
                'table' => 'alergias', 'titulo' => 'Alergias', 'singular' => 'alergia', 'icon' => 'alert',
                'vacio' => 'No hay alergias registradas. Si el paciente no tiene alergias conocidas, no es necesario agregar nada.',
                'order' => "activa DESC, FIELD(severidad,'grave','moderada','leve'), agente",
                'title' => 'agente', 'inactive' => 'activa',
                'meta' => ['tipo' => 'Tipo', 'reaccion' => 'Reacción', 'fecha_deteccion' => 'Detectada'],
                'fields' => [
                    'agente'          => ['Agente (qué provoca la alergia)', 'text', 'required|max:120', null, 6],
                    'tipo'            => ['Tipo', 'select', 'required|in:medicamento,alimento,ambiental,otra', ['medicamento' => 'Medicamento', 'alimento' => 'Alimento', 'ambiental' => 'Ambiental', 'otra' => 'Otra'], 3],
                    'severidad'       => ['Severidad', 'select', 'required|in:leve,moderada,grave', self::SEVERIDAD, 3],
                    'reaccion'        => ['Reacción', 'text', 'max:255', null, 8, 'Síntomas que presenta el paciente.'],
                    'fecha_deteccion' => ['Fecha de detección', 'date', 'past', null, 4],
                    'activa'          => ['Alergia vigente', 'bool', 'bool', null, 12],
                    'observaciones'   => ['Observaciones', 'textarea', 'max:2000', null, 12],
                ],
                'defaults' => ['activa' => 1, 'severidad' => 'moderada'],
            ],
            'enfermedades' => [
                'table' => 'enfermedades', 'titulo' => 'Enfermedades', 'singular' => 'enfermedad', 'icon' => 'activity',
                'vacio' => 'No hay enfermedades registradas.',
                'order' => "FIELD(estado,'activa','controlada','resuelta'), cronica DESC, nombre",
                'title' => 'nombre',
                'meta' => ['codigo_cie10' => 'CIE-10', 'fecha_diagnostico' => 'Diagnóstico'],
                'fields' => [
                    'nombre'            => ['Enfermedad o condición', 'text', 'required|max:150', null, 8],
                    'codigo_cie10'      => ['Código CIE-10', 'text', 'max:10|regex:/^[A-Za-z][0-9]{2}(\.[0-9A-Za-z]{1,4})?$/', null, 4, 'Opcional. Ejemplo: E11.9'],
                    'fecha_diagnostico' => ['Fecha de diagnóstico', 'date', 'past', null, 4],
                    'estado'            => ['Estado', 'select', 'required|in:activa,controlada,resuelta', ['activa' => 'Activa', 'controlada' => 'Controlada', 'resuelta' => 'Resuelta'], 4],
                    'cronica'           => ['Condición crónica', 'bool', 'bool', null, 4],
                    'observaciones'     => ['Observaciones', 'textarea', 'max:2000', null, 12],
                ],
                'defaults' => ['estado' => 'activa'],
            ],
            'cirugias' => [
                'table' => 'cirugias', 'titulo' => 'Cirugías', 'singular' => 'cirugía', 'icon' => 'scalpel',
                'vacio' => 'No hay cirugías registradas.',
                'order' => 'fecha DESC, id DESC',
                'title' => 'procedimiento',
                'meta' => ['fecha' => 'Fecha', 'centro' => 'Centro', 'cirujano' => 'Cirujano', 'complicaciones' => 'Complicaciones'],
                'fields' => [
                    'procedimiento'  => ['Procedimiento', 'text', 'required|max:150', null, 8],
                    'fecha'          => ['Fecha', 'date', 'required|past', null, 4],
                    'centro'         => ['Hospital o centro', 'text', 'max:120', null, 6],
                    'cirujano'       => ['Cirujano', 'text', 'max:120', null, 6],
                    'complicaciones' => ['Complicaciones', 'text', 'max:255', null, 12],
                    'observaciones'  => ['Observaciones', 'textarea', 'max:2000', null, 12],
                ],
            ],
            'medicamentos' => [
                'table' => 'medicamentos', 'titulo' => 'Medicamentos', 'singular' => 'medicamento', 'icon' => 'pill',
                'vacio' => 'No hay medicamentos registrados.',
                'order' => 'activo DESC, nombre',
                'title' => 'nombre', 'inactive' => 'activo',
                'meta' => ['dosis' => null, 'via' => null, 'frecuencia' => null, 'motivo' => 'Para', 'fecha_inicio' => 'Desde', 'fecha_fin' => 'Hasta'],
                'fields' => [
                    'nombre'        => ['Medicamento', 'text', 'required|max:120', null, 6],
                    'dosis'         => ['Dosis', 'text', 'max:60', null, 3],
                    'via'           => ['Vía', 'select', 'required|in:oral,intravenosa,intramuscular,subcutanea,topica,inhalada,oftalmica,otra', ['oral' => 'Oral', 'intravenosa' => 'Intravenosa', 'intramuscular' => 'Intramuscular', 'subcutanea' => 'Subcutánea', 'topica' => 'Tópica', 'inhalada' => 'Inhalada', 'oftalmica' => 'Oftálmica', 'otra' => 'Otra'], 3],
                    'frecuencia'    => ['Frecuencia', 'text', 'max:80', null, 6, 'Por ejemplo: cada 12 horas con alimentos.'],
                    'motivo'        => ['Motivo o indicación', 'text', 'max:150', null, 6],
                    'fecha_inicio'  => ['Inicio', 'date', 'date', null, 4],
                    'fecha_fin'     => ['Fin', 'date', 'date', null, 4, 'Déjelo vacío si el tratamiento continúa.'],
                    'activo'        => ['Medicamento vigente', 'bool', 'bool', null, 4],
                    'observaciones' => ['Observaciones', 'textarea', 'max:2000', null, 12],
                ],
                'defaults' => ['activo' => 1, 'via' => 'oral'],
            ],
            'vacunas' => [
                'table' => 'vacunas', 'titulo' => 'Vacunas', 'singular' => 'vacuna', 'icon' => 'syringe',
                'vacio' => 'No hay vacunas registradas.',
                'order' => 'fecha_aplicacion DESC, id DESC',
                'title' => 'vacuna',
                'meta' => ['dosis' => null, 'fecha_aplicacion' => 'Aplicada', 'proxima_dosis' => 'Próxima dosis', 'lote' => 'Lote', 'centro' => 'Centro'],
                'fields' => [
                    'vacuna'           => ['Vacuna', 'text', 'required|max:120', null, 6],
                    'dosis'            => ['Dosis', 'text', 'max:40', null, 3, 'Ejemplo: 2 de 3, refuerzo.'],
                    'fecha_aplicacion' => ['Fecha de aplicación', 'date', 'required|past', null, 3],
                    'proxima_dosis'    => ['Próxima dosis', 'date', 'date', null, 4],
                    'lote'             => ['Lote', 'text', 'max:40', null, 4],
                    'centro'           => ['Centro de vacunación', 'text', 'max:120', null, 4],
                    'observaciones'    => ['Observaciones', 'textarea', 'max:2000', null, 12],
                ],
            ],
            'habitos' => [
                'table' => 'habitos_salud', 'titulo' => 'Hábitos de salud', 'singular' => 'hábito', 'icon' => 'dna',
                'vacio' => 'No hay hábitos registrados.',
                'order' => 'tipo, id',
                'title' => 'descripcion',
                'meta' => ['tipo' => null, 'frecuencia' => 'Frecuencia'],
                'fields' => [
                    'tipo'          => ['Tipo de hábito', 'select', 'required|in:tabaco,alcohol,ejercicio,alimentacion,sueno,otras_sustancias,otro', ['tabaco' => 'Tabaco', 'alcohol' => 'Alcohol', 'ejercicio' => 'Ejercicio', 'alimentacion' => 'Alimentación', 'sueno' => 'Sueño', 'otras_sustancias' => 'Otras sustancias', 'otro' => 'Otro'], 4],
                    'frecuencia'    => ['Frecuencia', 'text', 'max:80', null, 8],
                    'descripcion'   => ['Descripción', 'text', 'required|max:255', null, 12],
                    'observaciones' => ['Observaciones', 'textarea', 'max:2000', null, 12],
                ],
            ],
            'familiares' => [
                'table' => 'antecedentes_familiares', 'titulo' => 'Antecedentes familiares', 'singular' => 'antecedente familiar', 'icon' => 'users',
                'vacio' => 'No hay antecedentes familiares registrados.',
                'order' => 'parentesco, condicion',
                'title' => 'condicion',
                'meta' => ['parentesco' => 'Familiar', 'edad_inicio' => 'Edad de inicio', 'fallecido' => null],
                'fields' => [
                    'parentesco'    => ['Parentesco', 'select', 'required|in:madre,padre,hermano,hermana,abuelo_materno,abuela_materna,abuelo_paterno,abuela_paterna,tio,tia,hijo,hija,otro', [
                        'madre' => 'Madre', 'padre' => 'Padre', 'hermano' => 'Hermano', 'hermana' => 'Hermana', 'abuelo_materno' => 'Abuelo materno', 'abuela_materna' => 'Abuela materna',
                        'abuelo_paterno' => 'Abuelo paterno', 'abuela_paterna' => 'Abuela paterna', 'tio' => 'Tío', 'tia' => 'Tía', 'hijo' => 'Hijo', 'hija' => 'Hija', 'otro' => 'Otro'], 4],
                    'condicion'     => ['Condición', 'text', 'required|max:150', null, 8],
                    'edad_inicio'   => ['Edad de inicio', 'number', 'int|between:0,120', null, 4, 'En años. Opcional.'],
                    'fallecido'     => ['Familiar fallecido', 'bool', 'bool', null, 4],
                    'observaciones' => ['Observaciones', 'textarea', 'max:2000', null, 12],
                ],
            ],
        ];
    }

    public static function get(string $key): ?array
    {
        return self::all()[$key] ?? null;
    }

    /** Reglas de validación derivadas de los campos. */
    public static function rules(array $section): array
    {
        return array_map(fn($f) => $f[2], $section['fields']);
    }

    public static function labels(array $section): array
    {
        return array_map(fn($f) => $f[0], $section['fields']);
    }

    /** Texto legible de un valor para listados. */
    public static function display(array $section, string $field, mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }
        $def = $section['fields'][$field] ?? null;
        return match (true) {
            $def !== null && $def[1] === 'select' => (string) ($def[3][$value] ?? $value),
            $def !== null && $def[1] === 'date'   => fecha((string) $value),
            $field === 'edad_inicio'              => $value . ' años',
            $field === 'fallecido'                => $value ? 'Fallecido' : '',
            default                               => (string) $value,
        };
    }
}
