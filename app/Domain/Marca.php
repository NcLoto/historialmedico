<?php
declare(strict_types=1);

namespace App\Domain;

/**
 * Procesa el logo y el favicon institucionales. Acepta lo que entrega un diseñador (PNG, JPG, WEBP, SVG e ICO,
 * en cualquier tamaño razonable) y lo deja listo para la web: las imágenes grandes se reducen conservando la
 * transparencia y los SVG se revisan para que no contengan código ejecutable. La imagen de fondo del estilo
 * cristal se reduce a 2560 px y se guarda como JPG (una foto de fondo no necesita transparencia).
 */
final class Marca
{
    public const NOMBRE = '/^(logo|favicon|fondo)-[0-9a-f-]{36}\.(png|jpe?g|webp|ico|svg)$/';
    /** Formatos de imagen que GD sabe leer, identificados por su contenido (no por la extensión). */
    private const RASTER = ['image/png' => 'PNG', 'image/jpeg' => 'JPG', 'image/webp' => 'WEBP', 'image/avif' => 'AVIF', 'image/bmp' => 'BMP', 'image/x-ms-bmp' => 'BMP'];
    public const MIME = ['png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'webp' => 'image/webp', 'ico' => 'image/vnd.microsoft.icon', 'svg' => 'image/svg+xml'];
    public const LIMITES = [
        'logo'    => ['ext' => ['png', 'jpg', 'jpeg', 'jfif', 'webp', 'avif', 'svg'], 'mb' => 10, 'caja' => [1200, 400], 'mpx' => 40],
        'favicon' => ['ext' => ['png', 'jpg', 'jpeg', 'jfif', 'webp', 'avif', 'ico', 'svg'], 'mb' => 5, 'caja' => [256, 256], 'mpx' => 40],
        'fondo'   => ['ext' => ['png', 'jpg', 'jpeg', 'jfif', 'webp', 'avif', 'bmp'], 'mb' => 30, 'caja' => [2560, 1600], 'mpx' => 60],
    ];

    /** Texto para la ayuda del formulario: «PNG, JPG, WEBP o SVG». */
    public static function formatos(string $tipo): string
    {
        $f = array_values(array_unique(array_map(fn($e) => in_array($e, ['jpeg', 'jfif'], true) ? 'JPG' : strtoupper($e), self::LIMITES[$tipo]['ext'])));
        return implode(', ', array_slice($f, 0, -1)) . ' o ' . end($f);
    }

    public static function accept(string $tipo): string
    {
        return implode(',', array_map(fn($e) => '.' . $e, self::LIMITES[$tipo]['ext']));
    }

    /**
     * Valida y guarda el archivo subido en $dir. @return array{ok:bool, mensaje:string, nombre?:string, detalle?:string}
     */
    public static function guardar(string $tipo, ?array $f, string $dir): array
    {
        $lim = self::LIMITES[$tipo];
        if (!$f || ($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return ['ok' => false, 'mensaje' => 'Seleccione una imagen.'];
        }
        if (in_array($f['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true) || $f['size'] > $lim['mb'] * 1048576) {
            return ['ok' => false, 'mensaje' => 'La imagen pesa ' . bytes_legible((int) $f['size']) . '; el máximo es ' . $lim['mb'] . ' MB.'];
        }
        if ($f['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name'])) {
            return ['ok' => false, 'mensaje' => 'No se pudo recibir la imagen. Intente de nuevo.'];
        }
        $ext = strtolower(pathinfo((string) $f['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $lim['ext'], true)) {
            return ['ok' => false, 'mensaje' => 'Formato «.' . $ext . '» no admitido. Use ' . self::formatos($tipo) . '.'];
        }
        if (!is_dir($dir) && !mkdir($dir, 0775, true)) {
            throw new \RuntimeException('No se pudo crear el directorio de marca.');
        }
        $base = $tipo . '-' . \App\Core\Upload::uuid();

        if ($ext === 'svg') {
            $svg = (string) file_get_contents($f['tmp_name']);
            if ($error = self::svgInseguro($svg)) {
                return ['ok' => false, 'mensaje' => $error];
            }
            file_put_contents("$dir/$base.svg", $svg);
            return ['ok' => true, 'mensaje' => '', 'nombre' => "$base.svg", 'detalle' => 'SVG, ' . bytes_legible(strlen($svg))];
        }

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
        if ($ext === 'ico') {
            if (!in_array($mime, ['image/vnd.microsoft.icon', 'image/x-icon'], true)) {
                return ['ok' => false, 'mensaje' => 'El archivo no es un icono ICO válido.'];
            }
            move_uploaded_file($f['tmp_name'], "$dir/$base.ico");
            return ['ok' => true, 'mensaje' => '', 'nombre' => "$base.ico", 'detalle' => 'ICO, ' . bytes_legible((int) $f['size'])];
        }

        // Muchas imágenes descargadas traen una extensión que no corresponde (un «.jpg» que en realidad es WEBP o AVIF).
        // Se decide por el contenido: si es una imagen legible se acepta, y siempre se vuelve a codificar (PNG o JPG),
        // así que nada del archivo original llega tal cual al servidor web.
        if (!isset(self::RASTER[$mime])) {
            $que = match (true) {
                in_array($mime, ['image/heic', 'image/heif'], true) => 'El archivo es una foto HEIC de iPhone. Ábrala y expórtela como JPG.',
                $mime === 'image/gif' => 'El archivo es un GIF. Use una imagen PNG, JPG o WEBP.',
                str_starts_with($mime, 'image/') => 'El archivo es una imagen ' . strtoupper(substr($mime, 6)) . ', formato que no se admite. Use PNG, JPG o WEBP.',
                default => 'El archivo no es una imagen.',
            };
            return ['ok' => false, 'mensaje' => $que];
        }
        $formato = self::RASTER[$mime];
        $info = @getimagesize($f['tmp_name']);
        if ($info === false || $info[0] < 16 || $info[1] < 16) {
            return ['ok' => false, 'mensaje' => 'La imagen está dañada o es demasiado pequeña (mínimo 16 × 16 px).'];
        }
        if ($info[0] * $info[1] > $lim['mpx'] * 1_000_000) {
            return ['ok' => false, 'mensaje' => 'La imagen tiene ' . round($info[0] * $info[1] / 1e6) . ' megapíxeles (' . $info[0] . ' × ' . $info[1] . ' px); el máximo es ' . $lim['mpx'] . '. Redúzcala a menos de 6000 px por lado.'];
        }
        [$maxW, $maxH] = $lim['caja'];
        $img = @imagecreatefromstring((string) file_get_contents($f['tmp_name']));
        if (!$img) {
            return ['ok' => false, 'mensaje' => 'No se pudo leer la imagen (' . $formato . ' dañado o con una variante no admitida). Ábrala y guárdela de nuevo como JPG o PNG.'];
        }
        $w = imagesx($img);
        $h = imagesy($img);
        $k = min(1, $maxW / $w, $maxH / $h);
        if ($tipo === 'favicon') {
            // Cuadrado transparente con la imagen centrada: los navegadores esperan iconos cuadrados.
            $lado = (int) min(256, max($w, $h) * $k);
            $out = imagecreatetruecolor($lado, $lado);
            imagealphablending($out, false);
            imagesavealpha($out, true);
            imagefill($out, 0, 0, imagecolorallocatealpha($out, 0, 0, 0, 127));
            $s = $lado / max($w, $h);
            $nw = (int) round($w * $s);
            $nh = (int) round($h * $s);
            imagecopyresampled($out, $img, (int) (($lado - $nw) / 2), (int) (($lado - $nh) / 2), 0, 0, $nw, $nh, $w, $h);
        } elseif ($tipo === 'fondo') {
            $nw = (int) round($w * $k);
            $nh = (int) round($h * $k);
            $out = imagecreatetruecolor($nw, $nh);
            imagefill($out, 0, 0, imagecolorallocate($out, 255, 255, 255));
            imagecopyresampled($out, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
            imageinterlace($out, true);
            imagejpeg($out, "$dir/$base.jpg", 84);
            $px = imagecreatetruecolor(1, 1);
            imagecopyresampled($px, $out, 0, 0, 0, 0, 1, 1, $nw, $nh);
            $rgb = imagecolorat($px, 0, 0);
            $luma = \App\Core\Color::luminance(sprintf('#%06x', $rgb & 0xffffff));
            $detalle = $formato . ' ' . $w . '×' . $h . ' px' . ($nw !== $w ? ', ajustada a ' . $nw . '×' . $nh . ' px' : '') . ', ' . bytes_legible((int) filesize("$dir/$base.jpg"));
            return ['ok' => true, 'mensaje' => '', 'nombre' => "$base.jpg", 'detalle' => $detalle, 'luma' => round($luma, 4)];
        } else {
            $nw = (int) round($w * $k);
            $nh = (int) round($h * $k);
            $out = imagecreatetruecolor($nw, $nh);
            imagealphablending($out, false);
            imagesavealpha($out, true);
            imagefill($out, 0, 0, imagecolorallocatealpha($out, 0, 0, 0, 127));
            imagecopyresampled($out, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
        }
        imagepng($out, "$dir/$base.png", 8);
        $detalle = $formato . ' ' . $w . '×' . $h . ' px' . (imagesx($out) !== $w || imagesy($out) !== $h ? ', ajustado a ' . imagesx($out) . '×' . imagesy($out) . ' px' : '');
        return ['ok' => true, 'mensaje' => '', 'nombre' => "$base.png", 'detalle' => $detalle];
    }

    /** Valores del estilo cristal, acotados a rangos que mantienen el texto legible. */
    public static function cristal(array $cfg): array
    {
        $n = static fn(string $k, int $def, int $min, int $max): int => max($min, min($max, (int) ($cfg[$k] ?? $def)));
        return [
            'activo'        => ($cfg['estilo_interfaz'] ?? 'solido') === 'cristal',
            'opacidad'      => $n('cristal_opacidad', 74, 50, 95),
            'desenfoque'    => $n('cristal_desenfoque', 18, 0, 30),
            'intensidad'    => $n('cristal_intensidad', 60, 0, 100),
            'oscurecer'     => $n('fondo_oscurecer', 20, 0, 70),
            'transparencia' => $n('fondo_transparencia', 0, 0, 90),
            'fondo'         => !empty($cfg['fondo_archivo']),
            'luma'          => is_numeric($cfg['fondo_luma'] ?? null) ? (float) $cfg['fondo_luma'] : 0.2,
        ];
    }

    public const BARRA_MODOS = ['automatico' => 'Automática', 'claro' => 'Clara', 'personalizado' => 'Color personalizado'];

    /** Modo y color de la barra lateral, ya validados. @return array{modo:string,color:string} */
    public static function barra(array $cfg): array
    {
        $modo = $cfg['barra_modo'] ?? 'automatico';
        $color = strtolower((string) ($cfg['barra_color'] ?? ''));
        return [
            'modo' => isset(self::BARRA_MODOS[$modo]) ? $modo : 'automatico',
            'color' => \App\Core\Color::isHex($color) ? $color : '#1f2937',
        ];
    }

    /**
     * Variables de la barra lateral cuando no es automática. El texto se elige solo (claro u oscuro) según el
     * fondo, para que cualquier color elegido siga siendo legible. Va en /tema.css.
     */
    public static function cssBarra(array $cfg): string
    {
        $b = self::barra($cfg);
        if ($b['modo'] === 'automatico') {
            return '';
        }
        $bg = $b['modo'] === 'claro' ? '#f3f5f7' : $b['color'];
        $claro = \App\Core\Color::luminance($bg) > 0.4;
        $v = $claro
            ? ['--side-bg' => $bg, '--side-ink' => '#33444e', '--side-strong' => '#0f1b21', '--side-muted' => '#5b6d78',
               '--side-line' => 'rgba(15, 27, 33, .12)', '--side-hover' => 'rgba(15, 27, 33, .06)', '--side-active' => 'rgba(15, 27, 33, .1)',
               '--side-chip' => 'rgba(15, 27, 33, .1)', '--side-scroll' => 'rgba(15, 27, 33, .25)', '--side-accent' => 'var(--brand)']
            : ['--side-bg' => $bg, '--side-ink' => '#d6e1e6', '--side-strong' => '#ffffff', '--side-muted' => '#aebfc7',
               '--side-line' => 'rgba(255, 255, 255, .12)', '--side-hover' => 'rgba(255, 255, 255, .08)', '--side-active' => 'rgba(255, 255, 255, .14)',
               '--side-chip' => 'rgba(255, 255, 255, .16)', '--side-scroll' => 'rgba(255, 255, 255, .22)', '--side-accent' => '#ffffff'];
        $css = implode(' ', array_map(fn($k, $x) => "$k: $x;", array_keys($v), $v));
        // Especificidad alta a propósito: gana a las paletas por tema y por acento.
        return "/* Barra lateral: {$b['modo']} */\n:root:root:root[data-barra] { $css }\n"
            . ($claro ? ":root[data-barra] .sidebar { border-right: 1px solid var(--side-line); }\n" : '');
    }

    /** Variables CSS del estilo cristal (van en /tema.css: la política de seguridad no admite estilos en línea). */
    public static function cssCristal(array $cfg, string $urlFondo): string
    {
        $c = self::cristal($cfg);
        if (!$c['activo']) {
            return '';
        }
        $vars = '--glass-a: ' . ($c['opacidad'] / 100) . '; --glass-blur: ' . $c['desenfoque'] . 'px; --glass-i: ' . ($c['intensidad'] / 100)
              . '; --scrim-a: ' . ($c['oscurecer'] / 100) . '; --fondo-t: ' . ($c['transparencia'] / 100) . ';';
        $css = "/* Estilo cristal */\n:root[data-estilo=\"cristal\"] { $vars }\n";
        if ($c['fondo']) {
            $css = str_replace(' }', ' --fondo-img: url("' . $urlFondo . '"); }', $css);
            // El texto que queda directo sobre la foto toma el color que más contrasta con su brillo medio, ya mezclado
            // con el color base del tema (transparencia) y oscurecido. El texto dentro de los paneles conserva el tema.
            $sel = ':root[data-estilo="cristal"][data-fondo="1"]';
            foreach (['claro' => 0.79, 'oscuro' => 0.008] as $tema => $base) {
                $l = ($c['luma'] * (1 - $c['transparencia'] / 100) + $base * $c['transparencia'] / 100) * (1 - $c['oscurecer'] / 100);
                $blanco = (1.05 / ($l + 0.05)) >= (($l + 0.05) / 0.0632);
                $v = $blanco ? '--on-bg: #ffffff; --on-bg-2: rgb(255 255 255 / .84); --on-bg-shadow: 0 1px 3px rgb(0 0 0 / .45);'
                             : '--on-bg: #0f2329; --on-bg-2: #2c3f46; --on-bg-shadow: 0 1px 2px rgb(255 255 255 / .55);';
                $css .= "$sel" . "[data-theme=\"$tema\"] { $v }\n"
                      . '@media (prefers-color-scheme: ' . ($tema === 'claro' ? 'light' : 'dark') . ") { $sel" . "[data-theme=\"auto\"] { $v } }\n";
            }
        }
        return $css;
    }

    /** Un SVG de logo no necesita scripts, eventos, contenido HTML incrustado ni recursos externos. */
    public static function svgInseguro(string $svg): ?string
    {
        if (!preg_match('/<svg[\s>]/i', $svg)) {
            return 'El archivo no es un SVG válido.';
        }
        $peligros = ['/<script/i', '/\son[a-z]+\s*=/i', '/javascript\s*:/i', '/<foreignObject/i', '/<!ENTITY/i', '/<iframe|<embed|<object/i', '/(xlink:)?href\s*=\s*["\']\s*(https?:|\/\/|data:text)/i'];
        foreach ($peligros as $p) {
            if (preg_match($p, $svg)) {
                return 'El SVG contiene código o enlaces externos que no se permiten por seguridad. Expórtelo como «SVG simple» o use PNG.';
            }
        }
        return null;
    }
}
