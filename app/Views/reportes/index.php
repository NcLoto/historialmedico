<?php
$area = ['consultas' => 'Clínico', 'alergias' => 'Clínico', 'vacunas' => 'Clínico', 'pacientes' => 'Administrativo', 'documentos' => 'Administrativo', 'auditoria' => 'Seguridad'];
?>
<div class="page-head">
    <div>
        <h1>Reportes</h1>
    </div>
</div>

<section class="panel">
    <?php if (!$reportes): ?>
        <div class="empty"><?= icon('chart', 28) ?><strong>No hay reportes disponibles para su rol</strong></div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table stackable">
                <thead><tr><th scope="col">Reporte</th><th scope="col">Área</th><th scope="col">Formatos</th><th scope="col"><span class="sr-only">Abrir</span></th></tr></thead>
                <tbody>
                <?php foreach ($reportes as $k => [$titulo, $desc]): ?>
                    <tr data-href="<?= e(url('reportes/' . $k)) ?>">
                        <td data-label="Reporte"><a class="cell-title" href="<?= e(url('reportes/' . $k)) ?>"><?= e($titulo) ?></a><span class="cell-sub"><?= e($desc) ?></span></td>
                        <td data-label="Área"><?= e($area[$k] ?? 'General') ?></td>
                        <td data-label="Formatos" class="nowrap">PDF, CSV</td>
                        <td class="col-actions"><?= icon('chevron', 16) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
