<?php
/**
 * Cifra con anillo de proporción (para la fila de métricas en la cabecera de una tarjeta).
 * @var string $etiqueta
 * @var string $icono
 * @var int|string $valor
 * @var ?float $pct       0–100 (proporción que muestra el anillo)
 * @var string $detalle   qué representa el anillo (p. ej. «55 % con acceso al portal»)
 * @var int    $tono      1–5 (color de la serie)
 */
$pct ??= null; $tono ??= 1;
$p = $pct === null ? 0 : max(0, min(100, $pct));
?>
<div class="ring-metric" title="<?= e($detalle) ?>">
    <span class="ring-label"><?= icon($icono, 14) ?> <?= e($etiqueta) ?></span>
    <span class="ring-row">
        <svg viewBox="0 0 36 36" class="ring-svg seg-<?= (int) $tono ?>" aria-hidden="true">
            <circle cx="18" cy="18" r="14" class="ring-track"/>
            <?php if ($p > 0): ?><circle cx="18" cy="18" r="14" class="ring-value" pathLength="100" stroke-dasharray="<?= round($p, 1) ?> 100" transform="rotate(-90 18 18)"/><?php endif; ?>
        </svg>
        <strong class="ring-value-text num"><?= e(is_int($valor) ? number_format($valor, 0, ',', '.') : (string) $valor) ?></strong>
    </span>
    <span class="ring-detail"><?= e($detalle) ?></span>
</div>
