<?php
declare(strict_types=1);

namespace App\Domain;

use App\Core\Upload;

/**
 * Foto de perfil de las cuentas. Se recorta en cuadrado, se reduce a 400 px y se vuelve a codificar como JPG:
 * así se descartan los metadatos de la cámara (ubicación GPS, modelo, fecha) y nada del archivo original
 * llega al servidor web. Se guarda fuera de public/ y se entrega por /avatar/{id} con control de acceso.
 */
final class Avatar
{
    public const NOMBRE = '/^[0-9a-f-]{36}\.jpg$/';
    public const EXT = ['jpg', 'jpeg', 'jfif', 'png', 'webp'];
    public const MB = 8;
    private const LADO = 400;
    private const MIME = ['image/jpeg' => 'JPG', 'image/png' => 'PNG', 'image/webp' => 'WEBP'];

    public static function dir(): string
    {
        return dirname(__DIR__, 2) . '/storage/avatars';
    }

    /** URL de la foto (con versión para la caché del navegador) o null si la cuenta no tiene. */
    public static function url(?array $u): ?string
    {
        $f = (string) ($u['foto_archivo'] ?? '');
        return $f !== '' && preg_match(self::NOMBRE, $f) ? url('avatar/' . (int) $u['id']) . '?v=' . substr(md5($f), 0, 8) : null;
    }

    /** @return array{ok:bool, mensaje:string, nombre?:string} */
    public static function guardar(?array $f): array
    {
        if (!$f || ($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return ['ok' => false, 'mensaje' => 'Seleccione una foto.'];
        }
        if (in_array($f['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true) || $f['size'] > self::MB * 1048576) {
            return ['ok' => false, 'mensaje' => 'La foto pesa ' . bytes_legible((int) $f['size']) . '; el máximo es ' . self::MB . ' MB.'];
        }
        if ($f['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name'])) {
            return ['ok' => false, 'mensaje' => 'No se pudo recibir la foto. Intente de nuevo.'];
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
        if (!isset(self::MIME[$mime])) {
            return ['ok' => false, 'mensaje' => in_array($mime, ['image/heic', 'image/heif'], true)
                ? 'La foto está en formato HEIC (iPhone). Expórtela como JPG e intente de nuevo.'
                : 'El archivo no es una foto JPG, PNG o WEBP.'];
        }
        $info = @getimagesize($f['tmp_name']);
        if ($info === false || min($info[0], $info[1]) < 96) {
            return ['ok' => false, 'mensaje' => 'La foto es demasiado pequeña; use una de al menos 96 × 96 px.'];
        }
        if ($info[0] * $info[1] > 40_000_000) {
            return ['ok' => false, 'mensaje' => 'La foto tiene más de 40 megapíxeles. Redúzcala e intente de nuevo.'];
        }
        $img = @imagecreatefromstring((string) file_get_contents($f['tmp_name']));
        if (!$img) {
            return ['ok' => false, 'mensaje' => 'No se pudo leer la foto. Ábrala y guárdela de nuevo como JPG.'];
        }
        $img = self::orientar($img, $mime, $f['tmp_name']);
        $w = imagesx($img);
        $h = imagesy($img);
        $lado = min($w, $h);
        $out = imagecreatetruecolor(self::LADO, self::LADO);
        imagefill($out, 0, 0, imagecolorallocate($out, 255, 255, 255));   // fondo para PNG con transparencia
        // Recorte centrado; en fotos verticales se sube un poco el encuadre para no cortar la cabeza.
        $x = (int) (($w - $lado) / 2);
        $y = $h > $w ? (int) (($h - $lado) * 0.3) : 0;
        imagecopyresampled($out, $img, 0, 0, $x, $y, self::LADO, self::LADO, $lado, $lado);
        if (!is_dir(self::dir()) && !mkdir(self::dir(), 0775, true)) {
            throw new \RuntimeException('No se pudo crear el directorio de fotos de perfil.');
        }
        $nombre = Upload::uuid() . '.jpg';
        imagejpeg($out, self::dir() . '/' . $nombre, 86);
        return ['ok' => true, 'mensaje' => '', 'nombre' => $nombre];
    }

    public static function borrar(?string $nombre): void
    {
        if ($nombre && preg_match(self::NOMBRE, $nombre) && is_file(self::dir() . '/' . $nombre)) {
            unlink(self::dir() . '/' . $nombre);
        }
    }

    /** Las fotos de celular guardan la rotación en EXIF; sin aplicarla quedarían de lado. */
    private static function orientar(\GdImage $img, string $mime, string $ruta): \GdImage
    {
        if ($mime !== 'image/jpeg' || !function_exists('exif_read_data')) {
            return $img;
        }
        $o = (int) (@exif_read_data($ruta)['Orientation'] ?? 1);
        $r = match ($o) { 3 => imagerotate($img, 180, 0), 6 => imagerotate($img, -90, 0), 8 => imagerotate($img, 90, 0), default => $img };
        return $r ?: $img;
    }
}
