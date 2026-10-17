<?php
declare(strict_types=1);

namespace App\Domain;

use App\Controllers\MarcaController;
use App\Core\Auth;
use App\Core\Color;
use App\Core\Config;
use App\Core\Database;
use App\Core\Pdf;

/**
 * Fábrica de documentos imprimibles: aplica el diseño definido en Configuración → Documentos
 * y registra cada emisión con un código de verificación consultable en /verificar.
 */
final class Impresos
{
    public const TIPOS = [
        'resumen'    => 'Resumen clínico',
        'consulta'   => 'Informe de consulta',
        'receta'     => 'Receta médica',
        'constancia' => 'Constancia de atención',
        'vacunas'    => 'Carné de vacunación',
        'reporte'    => 'Reporte',
        'listado'    => 'Listado',
        'muestra'    => 'Documento de muestra',
    ];
    public const SECCIONES = [
        'vitales' => 'Último control (signos vitales)', 'alertas' => 'Alertas clínicas (alergias, condiciones y medicación)',
        'antecedentes' => 'Antecedentes personales', 'enfermedades' => 'Enfermedades', 'cirugias' => 'Cirugías', 'vacunas' => 'Vacunas',
        'habitos' => 'Hábitos de salud', 'familiares' => 'Antecedentes familiares', 'consultas' => 'Consultas recientes',
    ];
    public const FORMATOS = ['pdf' => 'PDF', 'jpg' => 'JPG', 'jpeg' => 'JPEG', 'png' => 'PNG', 'webp' => 'WEBP'];
    private const ALFABETO = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    /** @return list<string> */
    public static function secciones(): array
    {
        return array_values(array_intersect(array_keys(self::SECCIONES), explode(',', Config::get('doc_secciones'))));
    }

    /** @return list<string> extensiones aceptadas al adjuntar documentos */
    public static function formatosPermitidos(): array
    {
        $f = array_values(array_intersect(array_keys(self::FORMATOS), explode(',', Config::get('doc_tipos_permitidos', 'pdf,jpg,jpeg,png,webp'))));
        return $f ?: ['pdf'];
    }

    public static function codigo(): string
    {
        $out = '';
        for ($i = 0; $i < 12; $i++) {
            $out .= self::ALFABETO[random_int(0, strlen(self::ALFABETO) - 1)];
        }
        return $out;
    }

    public static function codigoLegible(string $c): string
    {
        return implode('-', str_split($c, 4));
    }

    /**
     * Crea el documento con el diseño configurado. Si la verificación está activa y $registrar es verdadero,
     * guarda la emisión y añade el código al pie. $opciones permite, p. ej., ['orientacion' => 'horizontal'].
     * @return array{0:Pdf,1:?string}
     */
    public static function crear(string $tipo, string $titulo, ?int $pacienteId = null, ?int $consultaId = null, bool $registrar = true, ?string $codigoFijo = null, array $opciones = []): array
    {
        $c = Config::all();
        $u = Auth::user();
        $codigo = $codigoFijo;
        if ($codigo === null && $registrar && ($c['doc_verificacion'] ?? '1') === '1') {
            $codigo = self::codigo();
            Database::insert('documentos_emitidos', [
                'codigo' => $codigo, 'tipo' => $tipo, 'titulo' => mb_substr($titulo, 0, 150), 'paciente_id' => $pacienteId,
                'consulta_id' => $consultaId, 'emitido_por' => $u['id'] ?? null, 'emitido_por_nombre' => $u ? nombre_completo($u) . ' (' . $u['rol_nombre'] . ')' : 'Sistema',
            ]);
        }
        $pie = trim((string) ($c['doc_pie'] ?? ''));
        if (($c['doc_generado_por'] ?? '1') === '1' && $u) {
            $pie .= ($pie !== '' ? ' ' : '') . 'Generado por ' . nombre_completo($u) . ' (' . $u['rol_nombre'] . ') el ' . date('d/m/Y H:i') . '.';
        }
        $host = ($_SERVER['HTTP_HOST'] ?? '') !== '' ? ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . url('verificar') : '';
        [$marca, $opt] = self::disenio();
        $pdf = new Pdf($titulo, $marca, $pie, ['verificacion' => $codigo ? self::codigoLegible($codigo) : null, 'verificacion_url' => $host] + $opciones + $opt);
        return [$pdf, $codigo];
    }

    /**
     * Diseño de Configuración → Documentos (membrete, color, papel, letra, marca de agua, logo y contacto).
     * Todo PDF que produce el sistema lo usa, para que todos los usuarios vean el mismo diseño.
     * @return array{0:array,1:array} [marca, opciones de Pdf]
     */
    public static function disenio(): array
    {
        $c = Config::all();
        $logo = !empty($c['logo_archivo']) ? MarcaController::dir() . '/' . $c['logo_archivo'] : null;
        return [[
            'nombre' => $c['hospital_nombre'] ?? 'Hospital Sanitas',
            'lema' => $c['hospital_lema'] ?? '',
            'contacto' => implode(' | ', array_filter([$c['hospital_direccion'] ?? '', $c['hospital_telefono'] ?? '', $c['hospital_correo'] ?? ''])),
            'logo' => $logo,
            'color' => Color::isHex($c['doc_color'] ?? '') ? Color::brand($c['doc_color']) : Color::brand($c['color_acento'] ?? 'cian'),
        ], [
            'papel' => $c['doc_papel'] ?? 'a4',
            'encabezado' => $c['doc_encabezado'] ?? 'barra',
            'escala' => ($c['doc_letra'] ?? 'normal') === 'grande' ? 1.1 : 1.0,
            'marca_agua' => ($c['doc_marca_agua'] ?? '0') === '1' ? ($c['doc_marca_agua_texto'] ?: 'CONFIDENCIAL') : null,
            'mostrar_logo' => ($c['doc_logo'] ?? '1') === '1',
            'mostrar_contacto' => ($c['doc_contacto'] ?? '1') === '1',
        ]];
    }

    public static function enviar(Pdf $pdf, string $nombre, bool $descargar = true): never
    {
        $bytes = $pdf->output();
        header('Content-Type: application/pdf');
        header('Content-Length: ' . strlen($bytes));
        header('Content-Disposition: ' . ($descargar ? 'attachment' : 'inline') . '; filename="' . (preg_replace('/[^A-Za-z0-9._-]+/', '_', $nombre) ?: 'documento.pdf') . '"');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, no-store');
        echo $bytes;
        exit;
    }
}
