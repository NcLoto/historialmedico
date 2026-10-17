<?php
/**
 * Medidor semicircular de un porcentaje con meta: arco de avance, marca de la meta y aguja en el valor actual.
 * @var string  $etiqueta
 * @var ?float  $pct       0–100; null = sin datos suficientes
 * @var ?float  $meta      0–100
 * @var string  $detalle   de dónde sale la cifra (p. ej. «41 de 50 pacientes»)
 */
$meta ??= null; $detalle ??= '';
$p = $pct === null ? 0 : max(0, min(100, $pct));
$ok = $pct !== null && ($meta === null || $pct >= $meta);
$punto = function (float $v, float $r): array {
    $a = deg2rad(180 - 180 * $v / 100);
    return [round(70 + $r * cos($a), 2), round(66 - $r * sin($a), 2)];
};
?>
<figure class="gauge">
    <figcaption class="gauge-label"><?= e($etiqueta) ?></figcaption>
    <svg viewBox="0 0 140 84" class="gauge-svg" role="img" aria-label="<?= e($etiqueta . ': ' . ($pct === null ? 'sin datos' : round($p) . ' %') . ($meta !== null ? ', meta ' . round($meta) . ' %' : '')) ?>">
        <path d="M 14 66 A 56 56 0 0 1 126 66" class="gauge-track" pathLength="100"/>
        <?php if ($pct !== null && $p > 0): ?>
            <path d="M 14 66 A 56 56 0 0 1 126 66" class="gauge-value <?= $ok ? 'is-ok' : 'is-low' ?>" pathLength="100" stroke-dasharray="<?= round($p, 2) ?> 100"/>
        <?php endif; ?>
        <?php if ($meta !== null): [$x1, $y1] = $punto($meta, 46); [$x2, $y2] = $punto($meta, 66); ?>
            <line x1="<?= $x1 ?>" y1="<?= $y1 ?>" x2="<?= $x2 ?>" y2="<?= $y2 ?>" class="gauge-goal"/>
        <?php endif; ?>
        <?php if ($pct !== null): ?>
            <line x1="70" y1="66" x2="70" y2="24" class="gauge-needle" transform="rotate(<?= round(-90 + 180 * $p / 100, 2) ?> 70 66)"/>
            <circle cx="70" cy="66" r="4.5" class="gauge-hub"/>
        <?php endif; ?>
        <text x="14" y="81" class="chart-axis" text-anchor="middle">0</text>
        <text x="126" y="81" class="chart-axis" text-anchor="middle">100</text>
    </svg>
    <div class="gauge-figure">
        <span class="gauge-pct num"><?= $pct === null ? '—' : e(number_format($p, 0, ',', '.')) . ' %' ?></span>
        <?php if ($meta !== null): ?><span class="gauge-meta">Meta <?= e((string) round($meta)) ?> %</span><?php endif; ?>
    </div>
    <?php if ($detalle): ?><p class="gauge-detail"><?= e($detalle) ?></p><?php endif; ?>
</figure>
