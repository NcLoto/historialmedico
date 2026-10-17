<?php
/**
 * Gráfico de barras en SVG generado en el servidor (sin JavaScript ni estilos en línea).
 * @var string $titulo   descripción corta para lectores de pantalla
 * @var array  $series   lista de ['nombre' => string, 'clase' => 'a'|'b', 'datos' => [fecha(Y-m-d) => int]]
 * @var string $vacio    mensaje cuando todos los valores son cero
 */
$fechas = array_keys($series[0]['datos']);
$max = max(2, (int) (ceil(max(array_map(fn($s) => max($s['datos']), $series)) / 2) * 2));
$total = array_sum(array_map(fn($s) => array_sum($s['datos']), $series));
if ($total === 0): ?>
    <p class="muted chart-empty"><?= icon('activity', 16) ?> <?= e($vacio) ?>.</p>
<?php return; endif;

$W = 640; $H = 190; $padL = 28; $padB = 26; $padT = 16;
$n = count($fechas);
$slot = ($W - $padL) / $n;
$k = count($series);
$barW = max(4, min(26, ($slot - 8) / $k));
$alto = $H - $padB - $padT;
$meses = ['', 'ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
$paso = $n > 10 ? 2 : 1;
?>
<figure class="chart">
    <svg viewBox="0 0 <?= $W ?> <?= $H ?>" class="chart-svg" role="img" aria-label="<?= e($titulo) ?>" preserveAspectRatio="xMidYMid meet">
        <?php foreach ([0, 0.5, 1] as $g): $y = $padT + $alto * (1 - $g); ?>
            <line x1="<?= $padL ?>" x2="<?= $W ?>" y1="<?= $y ?>" y2="<?= $y ?>" class="chart-grid"/>
            <text x="<?= $padL - 6 ?>" y="<?= $y + 4 ?>" class="chart-axis" text-anchor="end"><?= (int) round($max * $g) ?></text>
        <?php endforeach; ?>
        <?php foreach ($fechas as $i => $f):
            $cx = $padL + $slot * $i + $slot / 2;
            foreach ($series as $j => $s):
                $v = $s['datos'][$f] ?? 0;
                $h = $alto * $v / $max;
                $x = $cx - ($barW * $k) / 2 + $barW * $j; ?>
                <rect x="<?= round($x, 1) ?>" y="<?= round($padT + $alto - $h, 1) ?>" width="<?= $barW - 2 ?>" height="<?= round($h, 1) ?>" rx="2" class="chart-bar chart-bar-<?= e($s['clase']) ?>"><title><?= e($s['nombre'] . ': ' . $v . ' el ' . date('d/m', strtotime($f))) ?></title></rect>
                <?php if ($v > 0 && $k === 1): ?><text x="<?= round($x + ($barW - 2) / 2, 1) ?>" y="<?= round($padT + $alto - $h - 4, 1) ?>" class="chart-value" text-anchor="middle"><?= $v ?></text><?php endif; ?>
            <?php endforeach;
            if ($i % $paso === 0 || $i === $n - 1): $t = strtotime($f); ?>
                <text x="<?= round($cx, 1) ?>" y="<?= $H - 8 ?>" class="chart-axis" text-anchor="middle"><?= date('j', $t) . ' ' . $meses[(int) date('n', $t)] ?></text>
            <?php endif; ?>
        <?php endforeach; ?>
    </svg>
    <?php if ($k > 1): ?>
        <figcaption class="chart-legend">
            <?php foreach ($series as $s): ?><span><i class="chart-key chart-bar-<?= e($s['clase']) ?>"></i><?= e($s['nombre']) ?> (<?= array_sum($s['datos']) ?>)</span><?php endforeach; ?>
        </figcaption>
    <?php endif; ?>
    <table class="sr-only">
        <caption><?= e($titulo) ?></caption>
        <thead><tr><th scope="col">Fecha</th><?php foreach ($series as $s): ?><th scope="col"><?= e($s['nombre']) ?></th><?php endforeach; ?></tr></thead>
        <tbody><?php foreach ($fechas as $f): ?><tr><th scope="row"><?= e(date('d/m/Y', strtotime($f))) ?></th><?php foreach ($series as $s): ?><td><?= (int) ($s['datos'][$f] ?? 0) ?></td><?php endforeach; ?></tr><?php endforeach; ?></tbody>
    </table>
</figure>
