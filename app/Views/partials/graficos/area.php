<?php
/**
 * Gráfico de área: curva suave con relleno que se desvanece hacia abajo, cuadrícula discreta y ejes.
 * @var string $titulo
 * @var list<string> $etiquetas
 * @var list<int> $datos
 * @var bool $ajustar   eje Y desde el rango de los datos (series acumuladas) en vez de desde 0
 * @var bool $parcial   el último periodo está en curso: ese tramo va punteado
 * @var string $vacio
 */
$ajustar ??= false; $parcial ??= false;
if (array_sum($datos) === 0): ?>
    <p class="muted chart-empty"><?= icon('activity', 16) ?> <?= e($vacio ?? 'Sin datos') ?>.</p>
<?php return; endif;
$W = 680; $H = 250; $padL = 36; $padR = 14; $padT = 14; $padB = 28;
$n = count($datos);
$hi = max($datos);
$lo = $ajustar ? min($datos) : 0;
// Paso de la cuadrícula «redondo» (1, 2, 5, 10, 20, 50…) para unas 4 líneas
$bruto = max(1, ($hi - $lo) / 4);
$mag = 10 ** floor(log10($bruto));
$paso = 10 * $mag;
foreach ([1, 2, 5] as $m) {
    if ($m * $mag >= $bruto) { $paso = $m * $mag; break; }
}
$paso = (int) max(1, $paso);
$min = $ajustar ? (int) (floor($lo / $paso) * $paso) : 0;
$max = (int) (ceil($hi / $paso) * $paso);
if ($max === $min) { $max = $min + $paso; }
$alto = $H - $padT - $padB;
$x = fn(int $i): float => round($padL + ($W - $padL - $padR) * ($n > 1 ? $i / ($n - 1) : 0.5), 1);
$y = fn(float $v): float => round($padT + $alto * (1 - ($v - $min) / ($max - $min)), 1);
$pts = array_map(fn($i) => [$x($i), $y($datos[$i])], array_keys($datos));
$firmes = $parcial && $n > 2 ? array_slice($pts, 0, -1) : $pts;
$curva = svg_curva($firmes);
$id = 'ga' . substr(md5($titulo . random_int(0, PHP_INT_MAX)), 0, 8);
?>
<figure class="chart area-chart">
    <svg viewBox="0 0 <?= $W ?> <?= $H ?>" class="chart-svg chart-tall" role="img" aria-label="<?= e($titulo) ?>">
        <defs><linearGradient id="<?= $id ?>" x1="0" y1="0" x2="0" y2="1"><stop offset="0" class="grad-top"/><stop offset="1" class="grad-bottom"/></linearGradient></defs>
        <?php for ($g = $min; $g <= $max; $g += $paso): ?>
            <line x1="<?= $padL ?>" x2="<?= $W - $padR ?>" y1="<?= $y($g) ?>" y2="<?= $y($g) ?>" class="chart-grid"/>
            <text x="<?= $padL - 8 ?>" y="<?= $y($g) + 4 ?>" class="chart-axis" text-anchor="end"><?= number_format($g, 0, ',', '.') ?></text>
        <?php endfor; ?>
        <?php foreach ($etiquetas as $i => $et): ?>
            <text x="<?= $x($i) ?>" y="<?= $H - 8 ?>" class="chart-axis" text-anchor="middle"><?= e($et) ?></text>
        <?php endforeach; ?>
        <path d="<?= $curva ?> L<?= end($firmes)[0] ?>,<?= $y($min) ?> L<?= $x(0) ?>,<?= $y($min) ?> Z" fill="url(#<?= $id ?>)"/>
        <path d="<?= $curva ?>" class="area-line"/>
        <?php if ($parcial && $n > 2): [$ax, $ay] = $pts[$n - 2]; [$bx, $by] = $pts[$n - 1]; ?>
            <path d="M<?= $ax ?>,<?= $ay ?> L<?= $bx ?>,<?= $by ?>" class="area-line line-partial"/>
        <?php endif; ?>
        <?php foreach ($pts as $i => [$px, $py]): ?>
            <circle cx="<?= $px ?>" cy="<?= $py ?>" r="9" class="area-hit"><title><?= e($etiquetas[$i] . ': ' . number_format($datos[$i], 0, ',', '.') . ($parcial && $i === $n - 1 ? ' (en curso)' : '')) ?></title></circle>
        <?php endforeach; ?>
    </svg>
    <table class="sr-only"><caption><?= e($titulo) ?></caption><tbody><?php foreach ($datos as $i => $v): ?><tr><th scope="row"><?= e($etiquetas[$i]) ?></th><td><?= (int) $v ?></td></tr><?php endforeach; ?></tbody></table>
</figure>
