<?php
/**
 * Medidor de segmentos (marcas radiales en un arco de 240°): se encienden hasta el valor; la marca de la meta
 * es más larga. La cifra y su explicación van al centro y debajo.
 * @var string $etiqueta
 * @var ?float $pct
 * @var ?float $meta
 * @var string $detalle
 */
$meta ??= null; $detalle ??= '';
$p = $pct === null ? 0 : max(0, min(100, $pct));
$N = 44;
$cx = 80; $cy = 78;
?>
<figure class="seg-gauge">
    <svg viewBox="0 0 160 136" class="seg-gauge-svg" role="img" aria-label="<?= e($etiqueta . ': ' . ($pct === null ? 'sin datos' : round($p) . ' %') . ($meta !== null ? ', meta ' . round($meta) . ' %' : '')) ?>">
        <?php for ($i = 0; $i < $N; $i++):
            $v = $i / ($N - 1) * 100;
            $a = deg2rad(210 - 240 * $i / ($N - 1));
            $esMeta = $meta !== null && abs($v - $meta) < 100 / ($N - 1) / 2 + 0.01;
            [$r1, $r2] = $esMeta ? [50, 70] : [56, 68];
            $on = $pct !== null && $v <= $p + 0.001; ?>
            <line x1="<?= round($cx + $r1 * cos($a), 2) ?>" y1="<?= round($cy - $r1 * sin($a), 2) ?>" x2="<?= round($cx + $r2 * cos($a), 2) ?>" y2="<?= round($cy - $r2 * sin($a), 2) ?>" class="tick<?= $on ? ' is-on' : '' ?><?= $esMeta ? ' is-goal' : '' ?>"/>
        <?php endfor; ?>
        <text x="<?= $cx ?>" y="<?= $cy + 4 ?>" class="seg-gauge-pct" text-anchor="middle"><?= $pct === null ? '—' : round($p) . ' %' ?></text>
        <text x="<?= $cx ?>" y="<?= $cy + 20 ?>" class="seg-gauge-cap" text-anchor="middle"><?= e($meta !== null ? 'Meta ' . round($meta) . ' %' : '') ?></text>
    </svg>
    <figcaption><strong><?= e($etiqueta) ?></strong><?php if ($detalle): ?><span><?= e($detalle) ?></span><?php endif; ?></figcaption>
</figure>
