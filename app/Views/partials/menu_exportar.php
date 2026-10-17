<?php
/**
 * Menú «Exportar» de un listado: PDF con el diseño institucional y CSV para hojas de cálculo.
 * Ambos respetan los filtros aplicados en pantalla.
 * @var string $id    identificador único del menú
 * @var string $base  ruta sin extensión, p. ej. 'pacientes/exportar'
 * @var array  $query filtros vigentes
 */
$qs = http_build_query(array_filter($query, fn($v) => $v !== null && $v !== ''));
$href = fn(string $ext) => url($base . '.' . $ext) . ($qs !== '' ? '?' . $qs : '');
?>
<details class="dropdown" id="<?= e($id) ?>">
    <summary class="btn btn-secondary"><?= icon('download', 16) ?> Exportar <?= icon('chevron-down', 14) ?></summary>
    <div class="dropdown-panel">
        <span class="pop-label">Con los filtros aplicados</span>
        <nav class="pop-menu" aria-label="Formatos de exportación">
            <a href="<?= e($href('pdf')) ?>"><?= icon('printer', 16) ?><span><strong>PDF</strong><small>Documento para imprimir o archivar, con membrete y código de verificación</small></span></a>
            <a href="<?= e($href('csv')) ?>"><?= icon('download', 16) ?><span><strong>CSV</strong><small>Tabla para abrir en Excel u otra hoja de cálculo</small></span></a>
        </nav>
    </div>
</details>
