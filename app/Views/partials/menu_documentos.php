<?php
/**
 * Menú desplegable de documentos imprimibles (details/summary nativo: funciona sin JavaScript y con teclado).
 * @var string $id    identificador único del menú
 * @var array  $items lista de [url, icono, título, descripción]
 */
if (!$items) {
    return;
}
?>
<details class="dropdown" id="<?= e($id) ?>">
    <summary class="btn btn-secondary"><?= icon('printer', 16) ?> Documentos <?= icon('chevron-down', 14) ?></summary>
    <div class="dropdown-panel">
        <span class="pop-label">Generar PDF</span>
        <nav class="pop-menu" aria-label="Documentos imprimibles">
            <?php foreach ($items as [$href, $ic, $titulo, $desc]): ?>
                <a href="<?= e($href) ?>"><?= icon($ic, 16) ?><span><strong><?= e($titulo) ?></strong><small><?= e($desc) ?></small></span></a>
            <?php endforeach; ?>
        </nav>
    </div>
</details>
