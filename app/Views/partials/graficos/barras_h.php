<?php
/**
 * Clasificación en barras horizontales: etiqueta, barra proporcional al mayor valor y cifra.
 * @var list<array{0:string,1:int}> $filas
 * @var string $vacio
 */
$max = max(1, ...array_column($filas ?: [['', 0]], 1));
if (!$filas || array_sum(array_column($filas, 1)) === 0): ?>
    <p class="muted chart-empty"><?= icon('activity', 16) ?> <?= e($vacio ?? 'Sin datos') ?>.</p>
<?php return; endif; ?>
<ul class="hbars">
    <?php foreach ($filas as [$et, $v]): ?>
        <li>
            <span class="hbar-label"><?= e($et) ?></span>
            <svg class="hbar" viewBox="0 0 100 10" preserveAspectRatio="none" aria-hidden="true">
                <rect x="0" y="0" width="100" height="10" rx="2" class="hbar-track"/>
                <?php if ($v > 0): ?><rect x="0" y="0" width="<?= round(max(2, 100 * $v / $max), 2) ?>" height="10" rx="2" class="hbar-fill"/><?php endif; ?>
            </svg>
            <strong class="hbar-value num"><?= (int) $v ?></strong>
        </li>
    <?php endforeach; ?>
</ul>
