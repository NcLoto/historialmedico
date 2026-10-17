<?php
/**
 * Gráfico de anillo con total al centro y leyenda con cifras y porcentajes (la leyenda lleva siempre el número:
 * el color nunca es la única forma de leerlo).
 * @var string $titulo
 * @var list<array{0:string,1:int}> $segmentos  [nombre, valor]; hasta 5 (el resto conviene agruparlo en «Otros»)
 * @var string $centro   texto bajo el total (p. ej. «cuentas»)
 * @var string $vacio
 */
$total = array_sum(array_column($segmentos, 1));
if ($total === 0): ?>
    <p class="muted chart-empty"><?= icon('activity', 16) ?> <?= e($vacio ?? 'Sin datos') ?>.</p>
<?php return; endif;
$acum = 0.0;
?>
<figure class="donut">
    <svg viewBox="0 0 42 42" class="donut-svg" role="img" aria-label="<?= e($titulo) ?>">
        <circle cx="21" cy="21" r="15.9155" class="donut-track"/>
        <?php foreach ($segmentos as $i => [$nombre, $v]): if ($v <= 0) { continue; }
            $pct = $v / $total * 100;
            // Separación de 0,6 entre segmentos para que se lean como partes distintas
            $largo = count(array_filter(array_column($segmentos, 1))) > 1 ? max(0.4, $pct - 0.6) : 100; ?>
            <circle cx="21" cy="21" r="15.9155" class="donut-seg seg-<?= $i + 1 ?>" stroke-dasharray="<?= round($largo, 3) ?> <?= round(100 - $largo, 3) ?>" stroke-dashoffset="<?= round(25 - $acum, 3) ?>"><title><?= e($nombre . ': ' . $v . ' (' . round($pct) . ' %)') ?></title></circle>
        <?php $acum += $pct; endforeach; ?>
        <text x="21" y="21" class="donut-total" text-anchor="middle"><?= (int) $total ?></text>
        <text x="21" y="26.5" class="donut-caption" text-anchor="middle"><?= e($centro ?? '') ?></text>
    </svg>
    <figcaption>
        <ul class="donut-legend">
            <?php foreach ($segmentos as $i => [$nombre, $v]): ?>
                <li><i class="key seg-<?= $i + 1 ?>" aria-hidden="true"></i><span class="grow"><?= e($nombre) ?></span><strong class="num"><?= (int) $v ?></strong><span class="num muted"><?= round($v / $total * 100) ?> %</span></li>
            <?php endforeach; ?>
        </ul>
    </figcaption>
</figure>
