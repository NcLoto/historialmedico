<?php
/**
 * Tarjeta de indicador: etiqueta, cifra, variación o proporción y un gráfico pegado al borde inferior
 * (curva de la evolución o barra de proporción).
 * @var string     $etiqueta
 * @var int|string $valor
 * @var ?array     $tendencia  ['pct' => float, 'sentido' => 'sube'|'baja'] (si crecer es bueno o malo)
 * @var ?string    $resalte    cifra corta resaltada antes del detalle (p. ej. «55 %»)
 * @var string     $detalle
 * @var ?array     $serie      lista de enteros → curva de área
 * @var ?float     $barra      0–100 → barra de proporción
 * @var int        $suavizar   días de media móvil para series diarias (0 = sin suavizar)
 * @var ?string    $href
 */
$tendencia ??= null; $resalte ??= null; $detalle ??= ''; $serie ??= null; $barra ??= null; $href ??= null;
$suavizar ??= 0;
// Media móvil para series diarias: muestra la tendencia sin el ruido de los días sin actividad (la cifra sigue siendo el total real)
if ($serie && $suavizar > 1) {
    $serie = array_map(fn($i) => array_sum(array_slice($serie, max(0, $i - $suavizar + 1), min($suavizar, $i + 1))) / min($suavizar, $i + 1), array_keys($serie));
}
$tag = $href ? 'a' : 'div';
$tono = 'neutral';
$dir = 'flat';
if ($tendencia && $tendencia['pct'] !== null) {
    $dir = $tendencia['pct'] > 0 ? 'up' : ($tendencia['pct'] < 0 ? 'down' : 'flat');
    $tono = $dir === 'flat' ? 'neutral' : (($dir === 'up') === (($tendencia['sentido'] ?? 'sube') === 'sube') ? 'good' : 'bad');
    $resalte = ($tendencia['pct'] > 0 ? '+' : ($tendencia['pct'] < 0 ? '−' : '')) . number_format(abs($tendencia['pct']), 1, ',', '.') . ' %';
}
$id = 'g' . substr(md5($etiqueta . random_int(0, PHP_INT_MAX)), 0, 8);
?>
<<?= $tag ?> class="mcard<?= $serie || $barra !== null ? ' has-chart' : '' ?>"<?= $href ? ' href="' . e($href) . '"' : '' ?>>
    <span class="mcard-label"><?= e($etiqueta) ?></span>
    <span class="mcard-value num"><?= e(is_int($valor) ? number_format($valor, 0, ',', '.') : (string) $valor) ?></span>
    <span class="mcard-sub">
        <?php if ($resalte !== null): ?><strong class="mcard-hl is-<?= $tono ?>"><?php if ($tendencia): ?><span class="mcard-arrow is-<?= $dir ?>" aria-hidden="true"></span><?php endif; ?><?= e($resalte) ?></strong><?php endif; ?>
        <?= e($detalle) ?>
    </span>
    <?php if ($serie && count($serie) > 1):
        $W = 300; $H = 56; $n = count($serie);
        $lo = min($serie); $hi = max($serie);
        $rng = max(1, $hi - $lo);
        $pts = [];
        foreach ($serie as $i => $v) { $pts[] = [round($i * $W / ($n - 1), 1), round($H - 4 - ($H - 14) * ($v - $lo) / $rng, 1)]; }
        $d = svg_curva($pts); ?>
        <svg class="mcard-chart" viewBox="0 0 <?= $W ?> <?= $H ?>" preserveAspectRatio="none" aria-hidden="true">
            <defs><linearGradient id="<?= $id ?>" x1="0" y1="0" x2="0" y2="1"><stop offset="0" class="grad-top"/><stop offset="1" class="grad-bottom"/></linearGradient></defs>
            <path d="<?= $d ?> L<?= $W ?>,<?= $H ?> L0,<?= $H ?> Z" fill="url(#<?= $id ?>)"/>
            <path d="<?= $d ?>" class="mcard-line"/>
        </svg>
    <?php elseif ($barra !== null): ?>
        <svg class="mcard-bar" viewBox="0 0 100 6" preserveAspectRatio="none" aria-hidden="true"><rect width="100" height="6" class="mcard-bar-track"/><rect width="<?= round(max(0, min(100, $barra)), 2) ?>" height="6" class="mcard-bar-fill"/></svg>
    <?php endif; ?>
</<?= $tag ?>>
