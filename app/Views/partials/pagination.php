<?php
/** @var App\Core\Pager $pager */
/** @var array $query */
/** @var string $path */
if ($pager->pages() <= 1) {
    return;
}
$desde = $pager->offset() + 1;
$hasta = min($pager->total, $pager->offset() + $pager->perPage);
// Primera, última y las dos vecinas de la actual; los saltos se marcan con «…».
$n = $pager->pages();
$paginas = array_values(array_unique(array_filter([1, $pager->page - 1, $pager->page, $pager->page + 1, $n], fn($x) => $x >= 1 && $x <= $n)));
sort($paginas);
?>
<nav class="pagination" aria-label="Paginación">
    <span class="muted">Mostrando <?= $desde ?>–<?= $hasta ?> de <?= number_format($pager->total, 0, '.', ',') ?></span>
    <div class="pagination-links">
        <?php if ($pager->page > 1): ?>
            <a class="pg-btn" href="<?= e($pager->link($pager->page - 1, $query, $path)) ?>" aria-label="Página anterior"><?= icon('chevron-left', 14) ?></a>
        <?php else: ?><span class="pg-btn is-off" aria-hidden="true"><?= icon('chevron-left', 14) ?></span><?php endif; ?>
        <?php $prev = 0; foreach ($paginas as $i): ?>
            <?php if ($i - $prev > 1): ?><span class="pg-gap" aria-hidden="true">…</span><?php endif; ?>
            <?php if ($i === $pager->page): ?><span class="pg-btn is-current" aria-current="page"><?= $i ?></span>
            <?php else: ?><a class="pg-btn" href="<?= e($pager->link($i, $query, $path)) ?>" aria-label="Página <?= $i ?>"><?= $i ?></a><?php endif; ?>
        <?php $prev = $i; endforeach; ?>
        <?php if ($pager->page < $n): ?>
            <a class="pg-btn" href="<?= e($pager->link($pager->page + 1, $query, $path)) ?>" aria-label="Página siguiente"><?= icon('chevron', 14) ?></a>
        <?php else: ?><span class="pg-btn is-off" aria-hidden="true"><?= icon('chevron', 14) ?></span><?php endif; ?>
    </div>
</nav>
