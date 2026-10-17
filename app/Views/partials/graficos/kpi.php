<?php
/**
 * Indicador con tendencia: cifra principal, variación frente al periodo anterior y minigráfico de la evolución.
 * @var string      $etiqueta
 * @var int|string  $valor     cifra mostrada
 * @var ?int        $actual    para calcular la variación (si se omite, no se muestra)
 * @var ?int        $anterior
 * @var string      $periodo   p. ej. «vs. 30 días anteriores»
 * @var list<int>   $serie     evolución (minigráfico); vacío = sin minigráfico
 * @var string      $sentido   'sube' si crecer es bueno, 'baja' si crecer es malo, 'neutro'
 * @var ?string     $detalle   línea secundaria
 * @var ?string     $href
 */
$actual ??= null; $anterior ??= null; $serie ??= []; $sentido ??= 'sube'; $detalle ??= null; $href ??= null; $periodo ??= '';
$delta = null;
if ($actual !== null && $anterior !== null) {
    $dif = $actual - $anterior;
    // Con una base pequeña el porcentaje distorsiona (de 1 a 14 = +1300 %): solo se muestra con 10 o más registros previos
    $pct = $anterior >= 10 ? round($dif / $anterior * 100) : null;
    $dir = $dif > 0 ? 'up' : ($dif < 0 ? 'down' : 'flat');
    $tono = $dir === 'flat' || $sentido === 'neutro' ? 'neutral' : (($dir === 'up') === ($sentido === 'sube') ? 'good' : 'bad');
    $texto = $dir === 'flat' ? 'Sin cambio' : (($dif > 0 ? '+' : '−') . abs($dif) . ($pct !== null ? ' (' . ($dif > 0 ? '+' : '−') . abs($pct) . ' %)' : ''));
    $delta = [$dir, $tono, $texto];
}
$tag = $href ? 'a' : 'div';
?>
<<?= $tag ?> class="kpi"<?= $href ? ' href="' . e($href) . '"' : '' ?>>
    <span class="kpi-label"><?= e($etiqueta) ?></span>
    <span class="kpi-row">
        <span class="kpi-value num"><?= e((string) $valor) ?></span>
        <?php if (count($serie) > 1):
            $W = 112; $H = 34; $max = max(1, max($serie)); $n = count($serie);
            $pts = [];
            foreach ($serie as $i => $v) { $pts[] = [round($i * $W / ($n - 1), 1), round($H - 3 - ($H - 8) * $v / $max, 1)]; }
            $curva = svg_curva($pts); ?>
            <svg class="spark" viewBox="0 0 <?= $W ?> <?= $H ?>" preserveAspectRatio="none" aria-hidden="true">
                <path class="spark-area" d="<?= $curva ?> L<?= $W ?>,<?= $H ?> L0,<?= $H ?> Z"/>
                <path class="spark-line" d="<?= $curva ?>"/>
                <circle class="spark-end" cx="<?= end($pts)[0] ?>" cy="<?= end($pts)[1] ?>" r="2.2"/>
            </svg>
        <?php endif; ?>
    </span>
    <?php if ($delta): [$dir, $tono, $texto] = $delta; ?>
        <span class="kpi-delta is-<?= $tono ?>"><span class="kpi-arrow is-<?= $dir ?>" aria-hidden="true"></span><?= e($texto) ?> <span class="kpi-period"><?= e($periodo) ?></span></span>
    <?php elseif ($detalle): ?>
        <span class="kpi-delta"><span class="kpi-period"><?= e($detalle) ?></span></span>
    <?php endif; ?>
</<?= $tag ?>>
