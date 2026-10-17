<?php
/**
 * Gráfico de líneas con área para la primera serie (tendencia en el tiempo). Valores reales en cada punto
 * (título emergente) y tabla equivalente para lectores de pantalla.
 * @var string $titulo
 * @var list<string> $etiquetas  eje X (p. ej. meses)
 * @var list<array{nombre:string,datos:list<int>}> $series  la primera se dibuja con área
 * @var string $vacio
 * @var bool   $ajustar  eje Y desde el rango de los datos (mediciones como presión o peso) en vez de desde 0
 * @var bool   $totales  muestra el total de cada serie en la leyenda (solo tiene sentido para conteos)
 * @var int    $ancho    ancho del lienzo; más pequeño = letra relativa más grande en columnas estrechas
 * @var bool   $parcial  el último periodo está en curso (se dibuja punteado)
 * @var ?string $nota    aclaración bajo la leyenda
 */
$ajustar ??= false; $totales ??= !$ajustar; $ancho ??= 640; $parcial ??= false; $nota ??= null;
$total = array_sum(array_map(fn($s) => array_sum($s['datos']), $series));
if ($total === 0): ?>
    <p class="muted chart-empty"><?= icon('activity', 16) ?> <?= e($vacio ?? 'Sin datos') ?>.</p>
<?php return; endif;
$W = $ancho; $H = (int) round($ancho * 0.33); $padL = 30; $padR = 24; $padT = 14; $padB = 26;
$n = count($etiquetas);
$vals = array_merge(...array_map(fn($s) => array_values(array_filter($s['datos'], fn($v) => $v > 0 || !$ajustar)), $series)) ?: [0];
$top = max(1, ...$vals);
$bajo = $ajustar ? min($vals) : 0;
$rango = max(1, $top - $bajo);
$paso = $rango <= 4 ? 1 : (int) ceil($rango / 4 / ($rango > 20 ? 5 : 1)) * ($rango > 20 ? 5 : 1);
$min = $ajustar ? max(0, $paso * (int) floor(($bajo - $paso / 2) / $paso)) : 0;
$max = $min + $paso * (int) max(1, ceil(($top + ($ajustar ? $paso / 2 : 0) - $min) / $paso));
$alto = $H - $padT - $padB;
$x = fn(int $i): float => round($padL + ($W - $padL - $padR) * ($n > 1 ? $i / ($n - 1) : 0.5), 1);
$y = fn(int $v): float => round($padT + $alto * (1 - (max($min, $v) - $min) / ($max - $min)), 1);
?>
<figure class="chart">
    <svg viewBox="0 0 <?= $W ?> <?= $H ?>" class="chart-svg chart-tall" role="img" aria-label="<?= e($titulo) ?>" preserveAspectRatio="xMidYMid meet">
        <?php for ($g = $min; $g <= $max; $g += $paso): ?>
            <line x1="<?= $padL ?>" x2="<?= $W - $padR ?>" y1="<?= $y($g) ?>" y2="<?= $y($g) ?>" class="chart-grid"/>
            <text x="<?= $padL - 7 ?>" y="<?= $y($g) + 4 ?>" class="chart-axis" text-anchor="end"><?= $g ?></text>
        <?php endfor; ?>
        <?php foreach ($etiquetas as $i => $et): ?>
            <text x="<?= $x($i) ?>" y="<?= $H - 7 ?>" class="chart-axis" text-anchor="middle"><?= e($et) ?></text>
        <?php endforeach; ?>
        <?php foreach ($series as $j => $s):
            $pts = array_map(fn($i) => [$x($i), $y((int) ($s['datos'][$i] ?? 0))], array_keys($etiquetas));
            // Periodo en curso (p. ej. el mes actual): el último tramo va punteado para no leerse como una caída real
            $firmes = $parcial && $n > 2 ? array_slice($pts, 0, -1) : $pts;
            $curva = svg_curva($firmes);
            if ($j === 0): ?><path class="line-area" d="<?= $curva ?> L<?= end($firmes)[0] ?>,<?= $y($min) ?> L<?= $x(0) ?>,<?= $y($min) ?> Z"/><?php endif; ?>
            <path class="line-path seg-<?= $j + 1 ?>" d="<?= $curva ?>"/>
            <?php if ($parcial && $n > 2): [$ax, $ay] = $pts[$n - 2]; [$bx, $by] = $pts[$n - 1]; ?>
                <path class="line-path line-partial seg-<?= $j + 1 ?>" d="M<?= $ax ?>,<?= $ay ?> L<?= $bx ?>,<?= $by ?>"/>
            <?php endif; ?>
            <?php foreach ($etiquetas as $i => $et): $v = (int) ($s['datos'][$i] ?? 0); ?>
                <circle cx="<?= $x($i) ?>" cy="<?= $y($v) ?>" r="<?= $n > 14 ? 2.2 : 3 ?>" class="line-dot seg-<?= $j + 1 ?><?= $parcial && $i === $n - 1 ? ' is-partial' : '' ?>"><title><?= e($s['nombre'] . ' · ' . $et . ': ' . $v . ($parcial && $i === $n - 1 ? ' (en curso)' : '')) ?></title></circle>
            <?php endforeach; ?>
        <?php endforeach; ?>
    </svg>
    <figcaption class="chart-legend">
        <?php if ($parcial): ?><span class="legend-partial"><i class="key-partial"></i>Periodo en curso</span><?php endif; ?>
        <?php foreach ($series as $j => $s): ?><span><i class="chart-key key seg-<?= $j + 1 ?>"></i><?= e($s['nombre']) ?><?= $totales ? ' (' . array_sum($s['datos']) . ')' : '' ?></span><?php endforeach; ?>
    </figcaption>
    <table class="sr-only">
        <caption><?= e($titulo) ?></caption>
        <thead><tr><th scope="col">Periodo</th><?php foreach ($series as $s): ?><th scope="col"><?= e($s['nombre']) ?></th><?php endforeach; ?></tr></thead>
        <tbody><?php foreach ($etiquetas as $i => $et): ?><tr><th scope="row"><?= e($et) ?></th><?php foreach ($series as $s): ?><td><?= (int) ($s['datos'][$i] ?? 0) ?></td><?php endforeach; ?></tr><?php endforeach; ?></tbody>
    </table>
</figure>
