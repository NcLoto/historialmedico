<?php
/**
 * Tarjeta compacta: ícono en círculo, cifra, etiqueta y minicurva de la evolución.
 * @var string $icono
 * @var int|string $valor
 * @var string $etiqueta
 * @var list<int> $serie
 * @var int $tono   1–5
 * @var ?string $href
 */
$serie ??= []; $tono ??= 1; $href ??= null;
$tag = $href ? 'a' : 'div';
?>
<<?= $tag ?> class="stat-card seg-<?= (int) $tono ?>"<?= $href ? ' href="' . e($href) . '"' : '' ?>>
    <span class="stat-icon" aria-hidden="true"><?= icon($icono, 18) ?></span>
    <span class="stat-text">
        <strong class="stat-value num"><?= e(is_int($valor) ? number_format($valor, 0, ',', '.') : (string) $valor) ?></strong>
        <span class="stat-label"><?= e($etiqueta) ?></span>
    </span>
    <?php if (count($serie) > 1):
        $W = 96; $H = 32; $max = max(1, max($serie)); $n = count($serie);
        $pts = [];
        foreach ($serie as $i => $v) { $pts[] = [round($i * $W / ($n - 1), 1), round($H - 4 - ($H - 8) * $v / $max, 1)]; } ?>
        <svg class="stat-wave" viewBox="0 0 <?= $W ?> <?= $H ?>" preserveAspectRatio="none" aria-hidden="true"><path d="<?= svg_curva($pts) ?>"/></svg>
    <?php endif; ?>
</<?= $tag ?>>
