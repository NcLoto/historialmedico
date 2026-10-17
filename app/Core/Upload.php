<?php
declare(strict_types=1);

namespace App\Core;

/** Validación y almacenamiento de documentos médicos fuera del directorio público. */
final class Upload
{
    /** Extensión permitida => tipos MIME reales aceptados. */
    public const TYPES = [
        'pdf'  => ['application/pdf'],
        'jpg'  => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png'  => ['image/png'],
        'webp' => ['image/webp'],
    ];

    /** Formatos aceptados en texto, para mensajes y ayudas: «PDF, JPG y PNG». */
    public static function etiquetaFormatos(): string
    {
        $f = array_values(array_unique(array_map(fn($e) => $e === 'jpeg' ? 'JPG' : strtoupper($e), \App\Domain\Impresos::formatosPermitidos())));
        return count($f) > 1 ? implode(', ', array_slice($f, 0, -1)) . ' y ' . end($f) : ($f[0] ?? 'PDF');
    }

    /** Atributo accept para el campo de archivo. */
    public static function accept(): string
    {
        return implode(',', array_map(fn($e) => '.' . $e, \App\Domain\Impresos::formatosPermitidos()));
    }

    public static function dir(): string
    {
        return dirname(__DIR__, 2) . '/storage/documentos_medicos';
    }

    public static function uuid(): string
    {
        $d = random_bytes(16);
        $d[6] = chr(ord($d[6]) & 0x0f | 0x40);
        $d[8] = chr(ord($d[8]) & 0x3f | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($d), 4));
    }

    /**
     * Valida el archivo subido.
     * @return array{error:?string, ext?:string, mime?:string, size?:int, original?:string}
     */
    public static function validate(?array $file): array
    {
        if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return ['error' => 'Seleccione un archivo.'];
        }
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return ['error' => match ($file['error']) {
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'El archivo supera el tamaño máximo permitido.',
                UPLOAD_ERR_PARTIAL => 'La carga se interrumpió. Intente de nuevo.',
                default => 'No se pudo recibir el archivo.',
            }];
        }
        $maxMb = Config::int('archivo_max_mb', 15);
        if ($file['size'] > $maxMb * 1048576) {
            return ['error' => "El archivo supera el máximo de $maxMb MB."];
        }
        if ($file['size'] === 0 || !is_uploaded_file($file['tmp_name'])) {
            return ['error' => 'El archivo está vacío o no es válido.'];
        }
        $original = basename(str_replace('\\', '/', (string) $file['name']));
        $ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));
        $permitidos = \App\Domain\Impresos::formatosPermitidos();
        if (!isset(self::TYPES[$ext]) || !in_array($ext, $permitidos, true)) {
            return ['error' => 'Tipo de archivo no permitido. Formatos aceptados: ' . self::etiquetaFormatos() . '.'];
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
        if (!in_array($mime, self::TYPES[$ext], true)) {
            return ['error' => 'El contenido del archivo no coincide con su extensión.'];
        }
        if (str_starts_with($mime, 'image/') && @getimagesize($file['tmp_name']) === false) {
            return ['error' => 'La imagen está dañada o no es válida.'];
        }
        return ['error' => null, 'ext' => $ext, 'mime' => $mime, 'size' => (int) $file['size'], 'original' => mb_substr($original, 0, 255)];
    }

    /** Mueve el archivo validado al almacenamiento privado. @return array<string,mixed> metadatos para la BD */
    public static function store(array $file, array $validated): array
    {
        $dir = self::dir();
        if (!is_dir($dir) && !mkdir($dir, 0775, true)) {
            throw new \RuntimeException('No se pudo crear el directorio de documentos.');
        }
        $name = self::uuid();
        if (!move_uploaded_file($file['tmp_name'], "$dir/$name")) {
            throw new \RuntimeException('No se pudo guardar el documento.');
        }
        return [
            'nombre_original' => $validated['original'],
            'nombre_interno'  => $name,
            'extension'       => $validated['ext'],
            'tipo_mime'       => $validated['mime'],
            'tamano_bytes'    => $validated['size'],
            'sha256'          => hash_file('sha256', "$dir/$name"),
        ];
    }

    public static function path(string $internalName): string
    {
        return self::dir() . '/' . $internalName;
    }
}
