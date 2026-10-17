<?php
/**
 * Barras verticales finas con extremos redondeados (evolución por periodo). Se resalta el valor más alto y,
 * si el último periodo está en curso, su barra va atenuada.
 * @var string $titulo
 * @var list<string> $etiquetas
 * @var list<int> $datos
 * @var bool $parcial
 * @var string $vacio
 * @var bool   $etiquetar  cifra sobre cada barra (y todas del mismo color)
 * @var int    $ancho  ancho del lienzo (más pequeño = letra relativa más grande en tarjetas estrechas)
 */
$parcial ??= false; $ancho ??= 640; $etiquetar ??= false;
if (array_sum($datos) === 0): ?>
    <p class="muted chart-empty"><?= icon('activity', 16) ?> <?= e($vacio ?? 'Sin datos') ?>.</p>
<?php return; endif;
$W = $ancho; $H = (int) round($ancho * 0.36); $padL = 30; $padR = 8; $padT = 18; $padB = 24;
$n = count($datos);
$top = max($datos);
$paso = $top <= 4 ? 1 : (int) (ceil($top / 4 / 5) * 5);
$max = $paso * (int) ceil($top / $paso);
$alto = $H - $padT - $padB;
$slot = ($W - $padL - $padR) / $n;
$bw = min(28, $slot * 0.52);
$imax = array_search($top, $datos, true);
?>
<figure class="chart">
    <svg viewBox="0 0 <?= $W ?> <?= $H ?>" class="chart-svg chart-tall" role="img" aria-label="<?= e($titulo) ?>">
        <?php for ($g = 0; $g <= $max; $g += $paso): $yy = round($padT + $alto * (1 - $g / $max), 1); ?>
            <line x1="<?= $padL ?>" x2="<?= $W - $padR ?>" y1="<?= $yy ?>" y2="<?= $yy ?>" class="chart-grid"/>
            <text x="<?= $padL - 8 ?>" y="<?= $yy + 4 ?>" class="chart-axis" text-anchor="end"><?= $g ?></text>
        <?php endfor; ?>
        <?php foreach ($datos as $i => $v):
            $cx = $padL + $slot * ($i + 0.5);
            $h = max($v > 0 ? 3 : 0, $alto * $v / $max);
            $cls = $parcial && $i === $n - 1 ? 'is-partial' : (!$etiquetar && $i === $imax ? 'is-peak' : ''); ?>
            <rect x="<?= round($cx - $bw / 2, 1) ?>" y="<?= round($padT + $alto - $h, 1) ?>" width="<?= round($bw, 1) ?>" height="<?= round($h, 1) ?>" rx="2" class="vbar <?= $cls ?>"><title><?= e($etiquetas[$i] . ': ' . $v . ($cls === 'is-partial' ? ' (en curso)' : '')) ?></title></rect>
            <?php if ($etiquetar ? $v > 0 : $i === $imax): ?><text x="<?= round($cx, 1) ?>" y="<?= round($padT + $alto - $h - 6, 1) ?>" class="chart-value" text-anchor="middle"><?= $v ?></text><?php endif; ?>
            <text x="<?= round($cx, 1) ?>" y="<?= $H - 7 ?>" class="chart-axis" text-anchor="middle"><?= e($etiquetas[$i]) ?></text>
        <?php endforeach; ?>
    </svg>
    <table class="sr-only"><caption><?= e($titulo) ?></caption><tbody><?php foreach ($datos as $i => $v): ?><tr><th scope="row"><?= e($etiquetas[$i]) ?></th><td><?= (int) $v ?></td></tr><?php endforeach; ?></tbody></table>
</figure>
