<?php
declare(strict_types=1);

namespace App\Domain;

use App\Core\Audit;
use App\Core\Database;
use App\Core\Pdf;
use App\Core\Upload;

/**
 * Documentos de demostración (datos ficticios) con el diseño de Configuración → Documentos: resultados de
 * laboratorio e informes de imagen. Los usan el instalador y el historial de demostración, y regenerar()
 * actualiza los ya creados cuando cambia el diseño.
 *
 * Nunca toca documentos reales: solo reescribe PDF producidos por este sistema y marcados como ficticios.
 * Un documento cargado desde fuera (laboratorio, imagen, informe) es parte del expediente y no se modifica.
 */
final class DocumentosDemo
{
    public const MARCA = 'Documento de demostración con datos ficticios.';

    /** Resultados fijos de los documentos del instalador: título => [parámetro => resultado]. */
    public const INSTALACION = [
        'Perfil metabólico y HbA1c' => ['Hemoglobina glicosilada (HbA1c)' => ['7.4', '%', 'Menor de 5.7'], 'Glucosa en ayunas' => ['148', 'mg/dL', '70 – 100'], 'Colesterol total' => ['212', 'mg/dL', 'Menor de 200'], 'Colesterol LDL' => ['131', 'mg/dL', 'Menor de 100'], 'Creatinina' => ['0.90', 'mg/dL', '0.60 – 1.20']],
        'Informe de espirometría' => ['FEV1' => ['58', '% del predicho', 'Mayor de 80'], 'FVC' => ['78', '% del predicho', 'Mayor de 80'], 'FEV1/FVC' => ['0.61', '', 'Mayor de 0.70']],
        'Densitometría ósea' => ['Columna lumbar L1-L4' => ['-2.7', 'T-score', 'Mayor de -1.0'], 'Cuello femoral' => ['-1.9', 'T-score', 'Mayor de -1.0']],
    ];
    private const CONCLUSION = [
        'Informe de espirometría' => 'Patrón obstructivo moderado con respuesta parcial al broncodilatador.',
        'Densitometría ósea' => 'Osteoporosis en columna lumbar; osteopenia en cuello femoral.',
    ];
    private const INFORMES = [
        'Radiografía de tórax' => 'Campos pulmonares bien ventilados, sin consolidaciones ni derrame pleural. Silueta cardíaca de tamaño normal. Senos costofrénicos libres.',
        'Ultrasonido abdominal' => 'Hígado de tamaño y ecogenicidad normales. Vesícula sin litos. Riñones de forma y tamaño conservados.',
        'Electrocardiograma' => 'Ritmo sinusal, eje eléctrico normal, sin alteraciones agudas de la repolarización.',
        'Ultrasonido obstétrico' => 'Embarazo intrauterino único, vivo. Líquido amniótico normal. Placenta de inserción normal.',
    ];

    /**
     * PDF de un documento de demostración.
     * @param array{nombres:string,apellidos:string,codigo:string} $pac
     * @param array{nombres:string,apellidos:string,esp:string}|null $med
     * @param list<string> $perfil condiciones crónicas del paciente (dm2, dlp…) para resultados coherentes
     */
    public static function pdf(string $titulo, array $pac, ?array $med, string $fecha, array $perfil = [], ?int $semilla = null): string
    {
        $semilla !== null && mt_srand($semilla);
        $r = fn(int $a, int $b): int => mt_rand($a, $b);
        [$marca, $opt] = Impresos::disenio();
        $esImagen = isset(self::INFORMES[$titulo]);
        $tipo = $esImagen ? 'Informe de ' . mb_strtolower($titulo) : (isset(self::INSTALACION[$titulo]) && !str_starts_with($titulo, 'Perfil') ? $titulo : 'Resultado de laboratorio');
        $pdf = new Pdf($tipo, $marca, self::MARCA . ' No corresponde a un paciente real.', ['emitido' => date('d/m/Y H:i', strtotime($fecha))] + $opt);
        $pdf->title($tipo, $titulo !== $tipo ? $titulo : null);
        $pdf->pairs(array_filter([
            'Paciente' => $pac['nombres'] . ' ' . $pac['apellidos'], 'Código de paciente' => $pac['codigo'],
            'Fecha del estudio' => date('d/m/Y', strtotime($fecha)), 'Solicitado por' => $med ? 'Dr(a). ' . $med['nombres'] . ' ' . $med['apellidos'] : null,
        ]));
        if ($esImagen) {
            $texto = self::INFORMES[$titulo];
            if ($titulo === 'Electrocardiograma') {
                $texto = 'Ritmo sinusal, frecuencia de ' . $r(62, 88) . ' lpm, eje eléctrico normal, sin alteraciones agudas de la repolarización.';
            }
            $pdf->heading('Hallazgos');
            $pdf->para($texto);
            $pdf->heading('Conclusión');
            $pdf->para('Estudio dentro de límites normales para la edad.');
        } else {
            if (isset(self::INSTALACION[$titulo])) {
                $filas = array_map(fn($k, $v) => [$k, $v[0], $v[1], $v[2]], array_keys(self::INSTALACION[$titulo]), self::INSTALACION[$titulo]);
            } else {
                $dm = in_array('dm2', $perfil, true);
                $dl = in_array('dlp', $perfil, true);
                $filas = [
                    ['Glucosa en ayunas', (string) ($dm ? $r(118, 168) : $r(78, 99)), 'mg/dL', '70 – 100'],
                    ['Hemoglobina glicosilada (HbA1c)', $dm ? sprintf('%.1f', $r(62, 84) / 10) : sprintf('%.1f', $r(48, 56) / 10), '%', 'Menor de 5.7'],
                    ['Colesterol total', (string) ($dl ? $r(215, 262) : $r(150, 195)), 'mg/dL', 'Menor de 200'],
                    ['Triglicéridos', (string) ($dl ? $r(180, 290) : $r(80, 145)), 'mg/dL', 'Menor de 150'],
                    ['Creatinina', sprintf('%.2f', $r(65, 110) / 100), 'mg/dL', '0.60 – 1.20'],
                    ['Hemoglobina', sprintf('%.1f', $r(120, 158) / 10), 'g/dL', '12.0 – 16.0'],
                ];
            }
            $pdf->heading('Resultados');
            $pdf->table(['Análisis', 'Resultado', 'Unidad', 'Valor de referencia'], $filas, [5, 2, 2.2, 3]);
            if (isset(self::CONCLUSION[$titulo])) {
                $pdf->heading('Interpretación');
                $pdf->para(self::CONCLUSION[$titulo]);
            }
            $pdf->note('Resultados validados por el laboratorio clínico. Interpretar en conjunto con la evaluación médica.', 'Observación');
        }
        return $pdf->output();
    }

    /** ¿Es un PDF generado por este sistema con datos ficticios? (solo esos se pueden regenerar) */
    public static function esDemo(string $bytes): bool
    {
        if (!str_starts_with($bytes, '%PDF') || !str_contains($bytes, '/Producer (Historial M')) {
            return false;
        }
        preg_match_all('/stream\n(.*?)\nendstream/s', $bytes, $m);
        foreach ($m[1] as $s) {
            $d = @gzuncompress($s);
            if ($d !== false && str_contains($d, 'ficticios')) {
                return true;
            }
        }
        return false;
    }

    /**
     * Vuelve a generar con el diseño actual todos los PDF de demostración. Conserva el nombre interno del
     * archivo y actualiza tamaño y huella SHA-256. @return array{regenerados:int,omitidos:int}
     */
    public static function regenerar(): array
    {
        $res = ['regenerados' => 0, 'omitidos' => 0];
        $docs = Database::all(
            "SELECT d.id, d.titulo, d.fecha_documento, d.cargado_en, d.nombre_interno, p.id AS pid, p.nombres, p.apellidos, p.codigo,
                    u.nombres AS mn, u.apellidos AS ma
               FROM documentos_medicos d JOIN pacientes p ON p.id = d.paciente_id LEFT JOIN usuarios u ON u.id = d.cargado_por
              WHERE d.extension = 'pdf' ORDER BY d.id"
        );
        foreach ($docs as $d) {
            $ruta = Upload::path($d['nombre_interno']);
            $bytes = is_file($ruta) ? (string) file_get_contents($ruta) : '';
            if (!self::esDemo($bytes)) {
                $res['omitidos']++;
                continue;
            }
            $perfil = array_values(array_filter([
                Database::value("SELECT 1 FROM enfermedades WHERE paciente_id = ? AND codigo_cie10 = 'E11.9'", [$d['pid']]) ? 'dm2' : null,
                Database::value("SELECT 1 FROM enfermedades WHERE paciente_id = ? AND codigo_cie10 IN ('E78.2','E78.5')", [$d['pid']]) ? 'dlp' : null,
            ]));
            $titulo = $d['titulo'] === 'Electrocardiograma de control' ? 'Electrocardiograma' : ($d['titulo'] === 'Radiografía de tórax PA' ? 'Radiografía de tórax' : $d['titulo']);
            $nuevo = self::pdf($titulo, $d, $d['mn'] ? ['nombres' => $d['mn'], 'apellidos' => $d['ma'], 'esp' => ''] : null, ($d['fecha_documento'] ?: substr($d['cargado_en'], 0, 10)) . substr($d['cargado_en'], 10), $perfil, (int) $d['id']);
            file_put_contents($ruta, $nuevo);
            Database::run('UPDATE documentos_medicos SET tamano_bytes = ?, sha256 = ? WHERE id = ?', [strlen($nuevo), hash('sha256', $nuevo), $d['id']]);
            $res['regenerados']++;
        }
        if ($res['regenerados']) {
            Audit::log('editar', 'documentos', 'Documentos de demostración regenerados con el diseño actual (' . $res['regenerados'] . ')', null, null, null,
                ['id' => null, 'nombres' => 'Sistema', 'apellidos' => '', 'rol_codigo' => null]);
        }
        return $res;
    }
}
