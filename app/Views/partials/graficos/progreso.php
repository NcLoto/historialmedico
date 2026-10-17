<?php
/**
 * Lista con avance: iniciales o ícono en círculo, nombre, detalle, cifra y barra proporcional al mayor.
 * @var list<array{iniciales?:string,icono?:string,titulo:string,sub?:string,valor:int,extra?:string}> $filas
 * @var string $vacio
 */
$max = max(1, ...array_map(fn($f) => (int) $f['valor'], $filas ?: [['valor' => 0]]));
if (!$filas): ?>
    <p class="muted chart-empty"><?= e($vacio ?? 'Sin datos') ?>.</p>
<?php return; endif; ?>
<ul class="progress-list">
    <?php foreach ($filas as $i => $f): ?>
        <li class="seg-<?= $i % 5 + 1 ?>">
            <span class="pl-avatar" aria-hidden="true"><?= isset($f['icono']) ? icon($f['icono'], 16) : e($f['iniciales'] ?? '') ?></span>
            <span class="pl-body">
                <span class="pl-top"><span class="pl-title"><?= e($f['titulo']) ?></span><strong class="num"><?= (int) $f['valor'] ?></strong></span>
                <svg class="pl-bar" viewBox="0 0 100 6" preserveAspectRatio="none" aria-hidden="true"><rect width="100" height="6" rx="3" class="pl-track"/><?php if ($f['valor'] > 0): ?><rect width="<?= round(max(3, 100 * $f['valor'] / $max), 2) ?>" height="6" rx="3" class="pl-fill"/><?php endif; ?></svg>
                <?php if (!empty($f['sub']) || !empty($f['extra'])): ?><span class="pl-sub"><?= e($f['sub'] ?? '') ?><?= !empty($f['extra']) ? '<span class="sep"></span>' . e($f['extra']) : '' ?></span><?php endif; ?>
            </span>
        </li>
    <?php endforeach; ?>
</ul>
