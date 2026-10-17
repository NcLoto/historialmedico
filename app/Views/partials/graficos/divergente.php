<?php
/**
 * Barras divergentes: hacia arriba lo que sí ocurrió (consultas realizadas), hacia abajo lo que no (canceladas).
 * @var string $titulo
 * @var list<string> $etiquetas
 * @var array{0:string,1:list<int>} $arriba   [nombre, datos]
 * @var array{0:string,1:list<int>} $abajo
 */
[$nA, $dA] = $arriba;
[$nB, $dB] = $abajo;
if (array_sum($dA) + array_sum($dB) === 0): ?>
    <p class="muted chart-empty"><?= icon('activity', 16) ?> Sin consultas en el periodo.</p>
<?php return; endif;
$W = 320; $H = 150; $n = count($dA);
$mA = max(1, max($dA));
$mB = max(1, max($dB));
$cero = round(14 + ($H - 40) * $mA / ($mA + $mB * 1.6), 1);   // las canceladas ocupan menos alto: son menos
$escA = ($cero - 14) / $mA;
$escB = ($H - 26 - $cero) / max($mB, $mA / 1.6);
$slot = $W / $n;
$bw = min(7, $slot * 0.42);
?>
<figure class="chart">
    <svg viewBox="0 0 <?= $W ?> <?= $H ?>" class="chart-svg" role="img" aria-label="<?= e($titulo) ?>">
        <line x1="0" x2="<?= $W ?>" y1="<?= $cero ?>" y2="<?= $cero ?>" class="chart-zero"/>
        <?php foreach ($dA as $i => $a): $b = $dB[$i] ?? 0; $cx = round($slot * ($i + 0.5), 1); ?>
            <?php if ($a > 0): ?><rect x="<?= $cx - $bw / 2 ?>" y="<?= round($cero - $a * $escA - 1, 1) ?>" width="<?= $bw ?>" height="<?= round($a * $escA, 1) ?>" rx="<?= $bw / 2 ?>" class="vbar"><title><?= e($nA . ' · semana del ' . $etiquetas[$i] . ': ' . $a) ?></title></rect><?php endif; ?>
            <?php if ($b > 0): ?><rect x="<?= $cx - $bw / 2 ?>" y="<?= $cero + 1 ?>" width="<?= $bw ?>" height="<?= round(max(3, $b * $escB), 1) ?>" rx="<?= $bw / 2 ?>" class="vbar is-down"><title><?= e($nB . ' · semana del ' . $etiquetas[$i] . ': ' . $b) ?></title></rect><?php endif; ?>
            <?php if ($i % 3 === 0): ?><text x="<?= $cx ?>" y="<?= $H - 6 ?>" class="chart-axis" text-anchor="middle"><?= e($etiquetas[$i]) ?></text><?php endif; ?>
        <?php endforeach; ?>
    </svg>
    <figcaption class="chart-legend"><span><i class="key seg-1"></i><?= e($nA) ?> (<?= array_sum($dA) ?>)</span><span><i class="key seg-2"></i><?= e($nB) ?> (<?= array_sum($dB) ?>)</span></figcaption>
    <table class="sr-only"><caption><?= e($titulo) ?></caption><thead><tr><th scope="col">Semana</th><th scope="col"><?= e($nA) ?></th><th scope="col"><?= e($nB) ?></th></tr></thead><tbody><?php foreach ($dA as $i => $a): ?><tr><th scope="row"><?= e($etiquetas[$i]) ?></th><td><?= (int) $a ?></td><td><?= (int) ($dB[$i] ?? 0) ?></td></tr><?php endforeach; ?></tbody></table>
</figure>
