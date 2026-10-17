<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Generador de PDF sin dependencias (Helvetica estándar), con el aspecto de un documento clínico oficial:
 * membrete institucional con número y fecha de emisión, título centrado, secciones con filete, datos en
 * cuadrícula de formulario, tablas con filetes (sin rellenos de color), avisos en recuadro y bloque de firma.
 * Casi todo en negro y grises; el color institucional solo marca el membrete.
 * Pie con numeración y código de verificación, marca de agua opcional. Texto en UTF-8 → WinAnsi (español).
 *
 * Opciones: papel (a4|carta), orientacion (vertical|horizontal), encabezado (barra|linea|minimo), escala (1 o 1.1), marca_agua (?string),
 * verificacion (?string código), verificacion_url (string), mostrar_logo (bool), mostrar_contacto (bool), emitido (string).
 */
final class Pdf
{
    /** 'carne': A6 vertical (105 × 148 mm), el tamaño de un carné o cartilla que se guarda en el bolsillo. */
    private const PAPEL = ['a4' => [595.28, 841.89], 'carta' => [612.0, 792.0], 'carne' => [297.64, 419.53]];
    private const INK = [22, 22, 22];        // títulos y valores
    private const BODY = [38, 38, 38];       // texto corrido
    private const MUTED = [98, 98, 98];      // etiquetas y pie
    private const RULE = [40, 40, 40];       // filetes estructurales
    private const HAIR = [196, 196, 196];    // separadores finos
    private const BOX = [150, 150, 150];     // bordes de cuadrícula y recuadros

    private const REG = [278, 278, 355, 556, 556, 889, 667, 191, 333, 333, 389, 584, 278, 333, 278, 278, 556, 556, 556, 556, 556, 556, 556, 556, 556, 556, 278, 278, 584, 584, 584, 556, 1015, 667, 667, 722, 722, 667, 611, 778, 722, 278, 500, 667, 556, 833, 722, 778, 667, 778, 722, 667, 611, 722, 667, 944, 667, 667, 611, 278, 278, 278, 469, 556, 333, 556, 556, 500, 556, 556, 278, 556, 556, 222, 222, 500, 222, 833, 556, 556, 556, 556, 333, 500, 278, 556, 500, 722, 500, 500, 500, 334, 260, 334, 584];
    private const BOLD = [278, 333, 474, 556, 556, 889, 722, 238, 333, 333, 389, 584, 278, 333, 278, 278, 556, 556, 556, 556, 556, 556, 556, 556, 556, 556, 333, 333, 584, 584, 584, 611, 975, 722, 722, 722, 722, 667, 611, 778, 722, 278, 556, 722, 611, 833, 722, 778, 667, 778, 722, 667, 611, 722, 667, 944, 667, 667, 611, 333, 278, 333, 584, 556, 333, 556, 611, 556, 611, 556, 333, 611, 611, 278, 278, 556, 278, 889, 611, 611, 611, 611, 389, 556, 333, 611, 556, 778, 556, 556, 500, 389, 280, 389, 584];

    private float $m = 50.0;
    private bool $carne = false;
    private float $W;
    private float $H;
    private float $top;
    private float $bottom;
    private float $k;
    /** @var list<string> */
    private array $pages = [];
    private string $cur = '';
    private float $y = 0;
    private ?array $logo = null;
    private array $rgb;
    private array $opt;

    /** @param array{nombre:string,lema?:string,contacto?:string,logo?:?string,color?:string} $brand */
    public function __construct(private string $title, private array $brand, private string $footer, array $opt = [])
    {
        $this->opt = $opt + ['papel' => 'a4', 'encabezado' => 'linea', 'escala' => 1.0, 'marca_agua' => null, 'verificacion' => null,
            'verificacion_url' => '', 'mostrar_logo' => true, 'mostrar_contacto' => true, 'emitido' => date('d/m/Y H:i'), 'orientacion' => 'vertical'];
        [$this->W, $this->H] = self::PAPEL[$this->opt['papel']] ?? self::PAPEL['a4'];
        if ($this->opt['orientacion'] === 'horizontal') {
            [$this->W, $this->H] = [$this->H, $this->W];
        }
        $this->k = (float) $this->opt['escala'];
        $this->carne = $this->opt['papel'] === 'carne';
        if ($this->carne) {
            $this->m = 22.0;
        }
        $this->top = $this->carne ? 70.0 : ($this->opt['encabezado'] === 'minimo' ? 96.0 : 124.0);
        $this->bottom = $this->carne ? ($this->opt['verificacion'] ? 62.0 : 50.0) : ($this->opt['verificacion'] ? 74.0 : 62.0);
        $this->rgb = self::hexToRgb($brand['color'] ?? '#0e7490');
        if ($this->opt['mostrar_logo'] && !empty($brand['logo']) && is_file($brand['logo'])) {
            $this->logo = self::jpegFromImage($brand['logo']);
        }
        $this->newPage();
    }

    public function contentWidth(): float
    {
        return $this->W - 2 * $this->m;
    }

    // ------------------------------------------------------------ Contenido

    /** Título del documento: centrado, en mayúsculas espaciadas, como en los formularios oficiales. */
    public function title(string $text, ?string $sub = null): void
    {
        $this->need(60);
        $size = 13 * $this->k;
        foreach ($this->wrap(mb_strtoupper($text), $this->contentWidth() - 40, $size, true, 1.2) as $ln) {
            $this->textCenter($this->y + $size, $ln, $size, true, self::INK, 1.2);
            $this->y += $size + 5;
        }
        if ($sub) {
            $ss = 9 * $this->k;
            foreach ($this->wrap($sub, $this->contentWidth() - 60, $ss, false) as $ln) {
                $this->textCenter($this->y + $ss + 1, $ln, $ss, false, self::MUTED);
                $this->y += $ss + 4;
            }
        }
        $this->y += 14;
    }

    /** Encabezado de sección: mayúsculas pequeñas espaciadas con un filete debajo. */
    public function heading(string $text): void
    {
        $this->need(96);   // el título nunca queda solo al pie: se reserva espacio para su primer contenido
        $this->y += 10;
        $size = 8.5 * $this->k;
        $this->text($this->m, $this->y + $size, mb_strtoupper($text), $size, true, self::INK, 0.8);
        $this->y += $size + 5;
        $this->line($this->m, $this->y, $this->W - $this->m, $this->y, self::RULE, 0.75);
        $this->y += 9;
    }

    public function para(string $text, float $size = 9.5, bool $bold = false, ?array $color = null): void
    {
        $size *= $this->k;
        foreach ($this->wrap($text, $this->contentWidth(), $size, $bold) as $ln) {
            $this->need($size + 5);
            $this->text($this->m, $this->y + $size, $ln, $size, $bold, $color ?? self::BODY);
            $this->y += $size + 4.5;
        }
        $this->y += 3;
    }

    public function space(float $h = 6): void
    {
        $this->y += $h;
    }

    /**
     * Datos en cuadrícula de formulario: dos columnas, etiqueta pequeña arriba y valor debajo, con bordes finos.
     * Si queda un dato impar al final, ocupa todo el ancho. @param array<string,string> $pairs
     */
    public function pairs(array $pairs): void
    {
        $items = array_values(array_filter(array_map(null, array_keys($pairs), array_values($pairs)), fn($p) => $p[1] !== null && $p[1] !== ''));
        if (!$items) {
            return;
        }
        $total = $this->contentWidth();
        $ls = 6.8 * $this->k;
        $vs = 9.5 * $this->k;
        $pad = 6.0;
        $this->need(40);
        for ($i = 0; $i < count($items); $i += 2) {
            $row = array_slice($items, $i, 2);
            $colW = count($row) === 2 ? $total / 2 : $total;
            $cells = [];
            $h = 0;
            foreach ($row as [$label, $value]) {
                $lines = $this->wrap((string) $value, $colW - 2 * $pad, $vs, false);
                $cells[] = [$label, $lines];
                $h = max($h, $pad + $ls + 5 + count($lines) * ($vs + 3) + $pad - 3);
            }
            if ($this->y + $h > $this->H - $this->bottom) {
                $this->newPage();
            }
            foreach ($cells as $j => [$label, $lines]) {
                $x = $this->m + $j * $colW;
                $this->strokeRect($x, $this->y, $colW, $h, self::BOX, 0.5);
                $this->text($x + $pad, $this->y + $pad + $ls, mb_strtoupper((string) $label), $ls, false, self::MUTED, 0.4);
                foreach ($lines as $n => $ln) {
                    $this->text($x + $pad, $this->y + $pad + $ls + 5 + $vs + $n * ($vs + 3), $ln, $vs, false, self::INK);
                }
            }
            $this->y += $h;
        }
        $this->y += 10;
    }

    /**
     * Tabla con filetes: fuerte arriba y abajo de la cabecera y al cierre, finos entre filas; sin rellenos.
     * La cabecera se repite al partirse. $widths son proporciones.
     * @param list<string> $headers @param list<list<string>> $rows @param list<float> $widths
     */
    public function table(array $headers, array $rows, array $widths, float $size = 8.5): void
    {
        $size *= $this->k;
        $hs = 7 * $this->k;
        $total = $this->contentWidth();
        $sum = array_sum($widths);
        $cw = array_map(fn($w) => $total * $w / $sum, $widths);
        $hh = $hs + 11;
        $header = function () use ($headers, $cw, $hs, $hh): void {
            $this->line($this->m, $this->y, $this->W - $this->m, $this->y, self::RULE, 0.9);
            $x = $this->m;
            foreach ($headers as $i => $h) {
                $this->text($x + 4, $this->y + $hh - 5.5, mb_strtoupper($h), $hs, true, self::MUTED, 0.4);
                $x += $cw[$i];
            }
            $this->y += $hh;
            $this->line($this->m, $this->y, $this->W - $this->m, $this->y, self::RULE, 0.5);
        };
        $this->need(60);
        $header();
        $ultima = count($rows) - 1;
        foreach ($rows as $n => $row) {
            $wrapped = [];
            $lines = 1;
            foreach ($row as $i => $cell) {
                $wrapped[$i] = $this->wrap((string) $cell, $cw[$i] - 8, $size, $i === 0);
                $lines = max($lines, count($wrapped[$i]));
            }
            $h = $lines * ($size + 3.5) + 9;
            if ($this->y + $h > $this->H - $this->bottom) {
                $this->line($this->m, $this->y, $this->W - $this->m, $this->y, self::RULE, 0.9);
                $this->newPage();
                $header();
            }
            $x = $this->m;
            foreach ($wrapped as $i => $ls) {
                foreach ($ls as $k => $ln) {
                    // La primera columna (lo que se describe) va en negrita: guía la lectura de cada fila.
                    $this->text($x + 4, $this->y + 5.5 + $size + $k * ($size + 3.5), $ln, $size, $i === 0, $i === 0 ? self::INK : self::BODY);
                }
                $x += $cw[$i];
            }
            $this->y += $h;
            $fin = $n === $ultima;
            $this->line($this->m, $this->y, $this->W - $this->m, $this->y, $fin ? self::RULE : self::HAIR, $fin ? 0.9 : 0.4);
        }
        $this->y += 12;
    }

    /** Recuadro de aviso (indicaciones, advertencias): borde fino, sin relleno, etiqueta en mayúsculas pequeñas. */
    public function note(string $text, ?string $label = null): void
    {
        $size = 9 * $this->k;
        $ls = 7 * $this->k;
        $lines = $this->wrap($text, $this->contentWidth() - 24, $size, false);
        $h = count($lines) * ($size + 4) + 18 + ($label ? $ls + 6 : 0);
        $this->need($h + 6);
        $this->strokeRect($this->m, $this->y, $this->contentWidth(), $h, self::BOX, 0.6);
        $yy = $this->y + 10;
        if ($label) {
            $this->text($this->m + 12, $yy + $ls, mb_strtoupper($label), $ls, true, self::INK, 0.6);
            $yy += $ls + 6;
        }
        foreach ($lines as $ln) {
            $this->text($this->m + 12, $yy + $size, $ln, $size, false, self::BODY);
            $yy += $size + 4;
        }
        $this->y += $h + 12;
    }

    /** Bloque de firma a la derecha: espacio para firmar, línea, y nombre, datos y leyenda centrados bajo ella. */
    public function signature(string $nombre, string $detalle, string $etiqueta): void
    {
        $this->need(110);
        $this->y += 56;
        $w = 220.0;
        $x = $this->W - $this->m - $w;
        $cx = $x + $w / 2;
        $this->line($x, $this->y, $x + $w, $this->y, self::RULE, 0.6);
        foreach ([[13, $nombre, 9.5, true, self::INK], [25, $detalle, 8.5, false, self::BODY], [36, $etiqueta, 7.5, false, self::MUTED]] as [$dy, $t, $sz, $b, $rgb]) {
            $sz *= $this->k;
            $this->text($cx - $this->width($t, $sz, $b) / 2, $this->y + $dy * $this->k, $t, $sz, $b, $rgb);
        }
        $this->y += 46 * $this->k;
    }

    public function output(): string
    {
        $this->pages[] = $this->cur;
        $n = count($this->pages);
        $objs = [];
        $objs[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $objs[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
        $objs[4] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';
        $next = 5;
        $xobj = '';
        if ($this->logo) {
            $objs[$next] = '<< /Type /XObject /Subtype /Image /Width ' . $this->logo['w'] . ' /Height ' . $this->logo['h'] . ' /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length ' . strlen($this->logo['data']) . " >>\nstream\n" . $this->logo['data'] . "\nendstream";
            $xobj = ' /XObject << /Im1 ' . $next . ' 0 R >>';
            $next++;
        }
        $box = sprintf('[0 0 %.2f %.2f]', $this->W, $this->H);
        $kids = [];
        foreach ($this->pages as $i => $content) {
            $content .= $this->footerFor($i + 1, $n);
            $z = gzcompress($content, 6);
            $cid = $next++;
            $pid = $next++;
            $objs[$cid] = '<< /Length ' . strlen($z) . " /Filter /FlateDecode >>\nstream\n" . $z . "\nendstream";
            $objs[$pid] = '<< /Type /Page /Parent 2 0 R /MediaBox ' . $box . ' /Contents ' . $cid . ' 0 R /Resources << /Font << /F1 3 0 R /F2 4 0 R >>' . $xobj . ' >> >>';
            $kids[] = $pid . ' 0 R';
        }
        $objs[2] = '<< /Type /Pages /Kids [' . implode(' ', $kids) . '] /Count ' . $n . ' >>';
        $info = $next;
        $objs[$info] = '<< /Title (' . self::esc($this->title) . ') /Author (' . self::esc($this->brand['nombre']) . ') /Producer (Historial Médico Electrónico) /CreationDate (D:' . date('YmdHis') . ') >>';

        ksort($objs);
        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $off = [];
        foreach ($objs as $id => $body) {
            $off[$id] = strlen($pdf);
            $pdf .= $id . " 0 obj\n" . $body . "\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= "xref\n0 " . (count($objs) + 1) . "\n0000000000 65535 f \n";
        foreach ($off as $o) {
            $pdf .= sprintf("%010d 00000 n \n", $o);
        }
        return $pdf . 'trailer << /Size ' . (count($objs) + 1) . ' /Root 1 0 R /Info ' . $info . " 0 R >>\nstartxref\n" . $xref . "\n%%EOF";
    }

    // ------------------------------------------------------------ Páginas

    private function need(float $h): void
    {
        if ($this->y + $h > $this->H - $this->bottom) {
            $this->newPage();
        }
    }

    private function newPage(): void
    {
        if ($this->cur !== '') {
            $this->pages[] = $this->cur;
        }
        $this->cur = '';
        if ($this->opt['marca_agua']) {
            $this->watermark((string) $this->opt['marca_agua']);
        }
        $this->drawHeader();
        $this->y = $this->top;
    }

    private function watermark(string $text): void
    {
        $size = 62;
        $w = $this->width($text, $size, true);
        $c = cos(M_PI / 4);
        $s = sin(M_PI / 4);
        $cx = $this->W / 2 - $c * $w / 2;
        $cy = $this->H / 2 - $s * $w / 2;
        $this->cur .= sprintf("BT %s rg /F2 %d Tf %.4f %.4f %.4f %.4f %.2f %.2f Tm (%s) Tj ET\n", self::rgbOp([238, 238, 238]), $size, $c, $s, -$s, $c, $cx, $cy, self::esc($text));
    }

    /**
     * Membrete: logo, nombre y contacto a la izquierda; número y fecha de emisión a la derecha; filete abajo.
     * «barra» añade una franja fina del color institucional en el borde superior de la hoja; «linea» usa ese
     * color en el filete del membrete; «minimo» deja solo el nombre y el contacto en una línea.
     */
    /** Encabezado compacto del carné: logo y nombre a la izquierda, fecha a la derecha y un solo filete. */
    private function drawHeaderCarne(): void
    {
        $right = $this->W - $this->m;
        if ($this->opt['encabezado'] !== 'minimo') {
            $this->rect(0, 0, $this->W, 5, $this->rgb);
        }
        $top = 16.0;
        $x = $this->m;
        if ($this->logo) {
            $esc = min(86 / $this->logo['w'], 26 / $this->logo['h']);
            $w = $this->logo['w'] * $esc;
            $h = $this->logo['h'] * $esc;
            $this->cur .= sprintf("q %.2f 0 0 %.2f %.2f %.2f cm /Im1 Do Q\n", $w, $h, $this->m, $this->H - $top - $h);
            $x += $w + 8;
            $fondo = $top + $h;
        } else {
            $this->rect($this->m + 6, $top + 1, 5, 16, self::INK);
            $this->rect($this->m + 1, $top + 6.5, 15, 5, self::INK);
            $x += 24;
            $fondo = $top + 18;
        }
        $emitido = (string) $this->opt['emitido'];
        $emitido = substr($emitido, 0, 10);
        $ew = $this->width($emitido, 7, true);
        $disp = $right - $ew - 10 - $x;
        $ns = 10.5;
        while ($ns > 7.5 && $this->width($this->brand['nombre'], $ns, true) > $disp) {
            $ns -= 0.5;
        }
        $this->text($x, $top + 9, $this->brand['nombre'], $ns, true, self::INK);
        $yy = $top + 9;
        if (!empty($this->brand['lema'])) {
            $yy += 9;
            $this->text($x, $yy, (string) $this->brand['lema'], 6.5, false, self::MUTED);
        }
        $this->text($right - 36, $top + 6, 'EMITIDO', 5.5, false, self::MUTED, 0.4);
        $this->text($right - $ew, $top + 15, $emitido, 7, true, self::INK);
        $ry = max($fondo, $yy + 2) + 7;
        $this->rect($this->m, $ry - 0.6, $right - $this->m, 1.2, $this->rgb);
        $this->top = $ry + 16;
    }

    private function drawHeader(): void
    {
        if ($this->carne) {
            $this->drawHeaderCarne();
            return;
        }
        $estilo = $this->opt['encabezado'];
        $nombre = $this->brand['nombre'];
        $lema = $this->brand['lema'] ?? '';
        $contacto = $this->opt['mostrar_contacto'] ? ($this->brand['contacto'] ?? '') : '';
        $right = $this->W - $this->m;

        // Datos del documento, alineados a la derecha
        $meta = [['Emitido', (string) $this->opt['emitido']]];
        if ($this->opt['verificacion']) {
            array_unshift($meta, ['Documento N.º', (string) $this->opt['verificacion']]);
        }
        $metaW = 0.0;
        foreach ($meta as [$l, $v]) {
            $metaW = max($metaW, $this->trackedWidth(mb_strtoupper($l), 6.5, false, 0.4), $this->width($v, 8.5, true));
        }
        $drawMeta = function (float $y0) use ($meta, $right): void {
            foreach ($meta as $i => [$l, $v]) {
                $yy = $y0 + $i * 22;
                $lu = mb_strtoupper($l);
                $this->text($right - $this->trackedWidth($lu, 6.5, false, 0.4), $yy, $lu, 6.5, false, self::MUTED, 0.4);
                $this->text($right - $this->width($v, 8.5, true), $yy + 10, $v, 8.5, true, self::INK);
            }
        };

        if ($estilo === 'minimo') {
            $this->text($this->m, 46, $nombre, 11, true, self::INK);
            if ($contacto) {
                $this->text($this->m, 58, $contacto, 7.5, false, self::MUTED);
            }
            $drawMeta(38);
            $ry = count($meta) > 1 ? 82.0 : 66.0;
            $this->line($this->m, $ry, $right, $ry, self::RULE, 0.75);
            $this->top = $ry + 24;
            return;
        }

        if ($estilo === 'barra') {
            $this->rect(0, 0, $this->W, 6, $this->rgb);
        }
        $top = 34.0;
        $x = $this->m;
        if ($this->logo) {
            $esc = min(130 / $this->logo['w'], 44 / $this->logo['h']);
            $w = $this->logo['w'] * $esc;
            $h = $this->logo['h'] * $esc;
            $this->cur .= sprintf("q %.2f 0 0 %.2f %.2f %.2f cm /Im1 Do Q\n", $w, $h, $this->m, $this->H - $top - $h);
            $x += $w + 14;
            $fondo = $top + $h;
        } else {
            // Cruz institucional, sólida y sin recuadro de color
            $this->rect($this->m + 11, $top + 2, 8, 26, self::INK);
            $this->rect($this->m + 2, $top + 11, 26, 8, self::INK);
            $x += 40;
            $fondo = $top + 28;
        }
        $disp = $right - $metaW - 20 - $x;
        $ns = 13.0;
        while ($ns > 9.5 && $this->width($nombre, $ns, true) > $disp) {
            $ns -= 0.5;
        }
        $this->text($x, $top + 13, $nombre, $ns, true, self::INK);
        $yy = $top + 13;
        if ($lema) {
            $yy += 12;
            $this->text($x, $yy, $lema, 8.5, false, self::BODY);
        }
        if ($contacto) {
            foreach (array_slice($this->wrap($contacto, $disp, 7.5, false), 0, 2) as $ln) {
                $yy += 10.5;
                $this->text($x, $yy, $ln, 7.5, false, self::MUTED);
            }
        }
        $drawMeta($top + 6);
        // El filete va justo debajo de lo más bajo del membrete (logo, contacto o datos del documento).
        $ry = max($fondo, $yy + 3, $top + 6 + (count($meta) - 1) * 22 + 13) + 12;
        $this->top = $ry + 26;
        if ($estilo === 'linea') {
            $this->rect($this->m, $ry - 0.7, $right - $this->m, 1.4, $this->rgb);
        } else {
            $this->line($this->m, $ry, $right, $ry, self::RULE, 0.75);
        }
    }

    private function footerFor(int $page, int $total): string
    {
        $save = $this->cur;
        $this->cur = '';
        $base = $this->H - $this->bottom + 16;
        $this->line($this->m, $base, $this->W - $this->m, $base, self::HAIR, 0.5);
        $label = "Página $page de $total";
        $lw = $this->width($label, 7, false);
        $lines = $this->wrap($this->footer, $this->contentWidth() - $lw - 24, 7, false);
        foreach (array_slice($lines, 0, 2) as $i => $ln) {
            $this->text($this->m, $base + 12 + $i * 9.5, $ln, 7, false, self::MUTED);
        }
        $this->text($this->W - $this->m - $lw, $base + 12, $label, 7, false, self::MUTED);
        if ($this->opt['verificacion']) {
            $yy = $base + 12 + max(1, min(2, count($lines))) * 9.5 + 3;
            $pre = 'Verificación: ';
            $codigo = (string) $this->opt['verificacion'];
            $this->text($this->m, $yy, $pre, 7, false, self::MUTED);
            $x = $this->m + $this->width($pre, 7, false);
            $this->text($x, $yy, $codigo, 7, true, self::INK);
            if ($this->opt['verificacion_url']) {
                if ($this->carne) {
                    $this->text($this->m, $yy + 8.5, 'Compruebe este carné en ' . preg_replace('#^https?://#', '', (string) $this->opt['verificacion_url']), 6, false, self::MUTED);
                } else {
                    $x += $this->width($codigo, 7, true);
                    $this->text($x, $yy, '. Compruebe la autenticidad de este documento en ' . $this->opt['verificacion_url'], 7, false, self::MUTED);
                }
            }
        }
        $out = $this->cur;
        $this->cur = $save;
        return $out;
    }

    // ------------------------------------------------------------ Primitivas

    private function text(float $x, float $y, string $s, float $size, bool $bold, array $rgb, float $tracking = 0.0): void
    {
        $tc = sprintf('%.2f Tc ', $tracking);
        $this->cur .= sprintf("BT %s rg /%s %.2f Tf %s%.2f %.2f Td (%s) Tj ET\n", self::rgbOp($rgb), $bold ? 'F2' : 'F1', $size, $tc, $x, $this->H - $y, self::esc($s));
    }

    private function textCenter(float $y, string $s, float $size, bool $bold, array $rgb, float $tracking = 0.0): void
    {
        $this->text(($this->W - $this->trackedWidth($s, $size, $bold, $tracking)) / 2, $y, $s, $size, $bold, $rgb, $tracking);
    }

    private function rect(float $x, float $y, float $w, float $h, array $rgb): void
    {
        $this->cur .= sprintf("%s rg %.2f %.2f %.2f %.2f re f\n", self::rgbOp($rgb), $x, $this->H - $y - $h, $w, $h);
    }

    private function strokeRect(float $x, float $y, float $w, float $h, array $rgb, float $width = 0.5): void
    {
        $this->cur .= sprintf("%s RG %.2f w %.2f %.2f %.2f %.2f re S\n", self::rgbOp($rgb), $width, $x, $this->H - $y - $h, $w, $h);
    }

    private function line(float $x1, float $y1, float $x2, float $y2, array $rgb, float $width = 0.6): void
    {
        $this->cur .= sprintf("%s RG %.2f w %.2f %.2f m %.2f %.2f l S\n", self::rgbOp($rgb), $width, $x1, $this->H - $y1, $x2, $this->H - $y2);
    }

    // ------------------------------------------------------------ Texto

    /** @return list<string> */
    private function wrap(string $text, float $width, float $size, bool $bold, float $tracking = 0.0): array
    {
        $w = fn(string $t): float => $this->trackedWidth($t, $size, $bold, $tracking);
        $out = [];
        foreach (preg_split('/\R/u', trim($text)) as $para) {
            $line = '';
            foreach (preg_split('/\s+/u', $para, -1, PREG_SPLIT_NO_EMPTY) ?: [''] as $word) {
                while ($w($word) > $width && mb_strlen($word) > 1) {
                    $cut = mb_strlen($word);
                    while ($cut > 1 && $w(mb_substr($word, 0, $cut)) > $width) {
                        $cut--;
                    }
                    if ($line !== '') {
                        $out[] = $line;
                        $line = '';
                    }
                    $out[] = mb_substr($word, 0, $cut);
                    $word = mb_substr($word, $cut);
                }
                $try = $line === '' ? $word : $line . ' ' . $word;
                if ($w($try) <= $width) {
                    $line = $try;
                } else {
                    $out[] = $line;
                    $line = $word;
                }
            }
            $out[] = $line;
        }
        return $out ?: [''];
    }

    private function trackedWidth(string $s, float $size, bool $bold, float $tracking): float
    {
        return $this->width($s, $size, $bold) + $tracking * max(0, mb_strlen($s) - 1);
    }

    private function width(string $s, float $size, bool $bold): float
    {
        $table = $bold ? self::BOLD : self::REG;
        $w = 0;
        foreach (mb_str_split($s) as $ch) {
            $b = $ch;
            if (ord($ch[0]) > 127) {
                $t = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $ch);
                $b = ($t !== false && $t !== '' && ctype_alpha($t[0])) ? $t[0] : 'a';
            }
            $o = ord($b[0]);
            $w += ($o >= 32 && $o <= 126) ? $table[$o - 32] : 556;
        }
        return $w * $size / 1000;
    }

    private static function esc(string $s): string
    {
        $s = mb_convert_encoding($s, 'Windows-1252', 'UTF-8');
        return str_replace(['\\', '(', ')', "\r"], ['\\\\', '\\(', '\\)', ''], $s);
    }

    private static function rgbOp(array $c): string
    {
        return sprintf('%.3f %.3f %.3f', $c[0] / 255, $c[1] / 255, $c[2] / 255);
    }

    /** @return array{0:int,1:int,2:int} */
    public static function hexToRgb(string $hex): array
    {
        $hex = ltrim($hex, '#');
        return strlen($hex) === 6 ? [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))] : [14, 116, 144];
    }

    /** Convierte PNG/JPG/WEBP a JPEG sobre fondo blanco para incrustarlo. @return array{data:string,w:int,h:int}|null */
    private static function jpegFromImage(string $path): ?array
    {
        $raw = @file_get_contents($path);
        $img = $raw === false ? false : @imagecreatefromstring($raw);
        if (!$img) {
            return null;
        }
        $w = imagesx($img);
        $h = imagesy($img);
        $canvas = imagecreatetruecolor($w, $h);
        imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255));
        imagecopy($canvas, $img, 0, 0, 0, 0, $w, $h);
        ob_start();
        imagejpeg($canvas, null, 88);
        return ['data' => (string) ob_get_clean(), 'w' => $w, 'h' => $h];
    }
}
