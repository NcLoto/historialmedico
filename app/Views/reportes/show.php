<?php
$filas = $rep['filas'];
$total = count($filas);
$conPeriodo = $rep['nota_periodo'] ?? true;
$link = fn(string $fmt) => url('reportes/' . $clave) . '?' . http_build_query($query + ['formato' => $fmt]);
?>
<div class="page-head">
    <div>
        <div class="breadcrumb"><a href="<?= e(url('reportes')) ?>">Reportes</a> <?= icon('chevron', 12) ?> <span><?= e($def[0]) ?></span></div>
        <h1><?= e($def[0]) ?></h1>
        <p><?= e($def[1]) ?></p>
    </div>
    <div class="page-actions">
        <a class="btn btn-secondary" href="<?= e($link('csv')) ?>"><?= icon('download', 16) ?> CSV</a>
        <a class="btn btn-primary" href="<?= e($link('pdf')) ?>"><?= icon('printer', 16) ?> Descargar PDF</a>
    </div>
</div>

<section class="panel">
    <form class="filters" method="get" action="<?= e(url('reportes/' . $clave)) ?>">
        <?php if ($conPeriodo): ?>
            <div class="field"><label for="desde">Desde</label><input type="date" id="desde" name="desde" value="<?= e($f['desde']) ?>" max="<?= date('Y-m-d') ?>"></div>
            <div class="field"><label for="hasta">Hasta</label><input type="date" id="hasta" name="hasta" value="<?= e($f['hasta']) ?>"></div>
        <?php endif; ?>
        <?php if ($clave === 'consultas'): ?>
            <div class="field"><label for="medico">Médico</label><select id="medico" name="medico"><option value="todos"<?= $f['medico'] === null ? ' selected' : '' ?>>Todos los médicos</option><?= options($medicos, $f['medico'] ?? '', null) ?></select></div>
            <div class="field"><label for="estado">Estado</label><select id="estado" name="estado"><?= options(['realizada' => 'Realizadas', 'programada' => 'Programadas', 'cancelada' => 'Canceladas'], $f['estado'], 'Todos') ?></select></div>
        <?php elseif ($clave === 'alergias'): ?>
            <div class="field"><label for="severidad">Severidad</label><select id="severidad" name="severidad"><?= options(['grave' => 'Grave', 'moderada' => 'Moderada', 'leve' => 'Leve'], $f['severidad'], 'Todas') ?></select></div>
        <?php elseif ($clave === 'vacunas'): ?>
            <div class="field"><label for="dias">Próximos</label><select id="dias" name="dias"><?= options([15 => '15 días', 30 => '30 días', 60 => '60 días', 90 => '90 días', 180 => '180 días'], $f['dias'], null) ?></select></div>
        <?php elseif ($clave === 'pacientes'): ?>
            <div class="field"><label for="estado">Estado</label><select id="estado" name="estado"><?= options(['activos' => 'Activos', 'inactivos' => 'Inactivos'], $f['estado'], 'Todos') ?></select></div>
        <?php elseif ($clave === 'documentos'): ?>
            <div class="field"><label for="grupo">Tipo</label><select id="grupo" name="grupo"><?= options(['examen' => 'Exámenes', 'imagen' => 'Imágenes', 'documento' => 'Documentos'], $f['grupo'], 'Todos') ?></select></div>
        <?php elseif ($clave === 'auditoria'): ?>
            <div class="field"><label for="accion">Acción</label><select id="accion" name="accion"><?= options(array_combine($rep['acciones'], array_map('accion_label', $rep['acciones'])), $f['accion'], 'Todas') ?></select></div>
        <?php endif; ?>
        <button type="submit" class="btn btn-secondary"><?= icon('filter', 16) ?> Actualizar</button>
        <?php if ($conPeriodo): ?>
            <div class="quick-ranges" role="group" aria-label="Periodos rápidos">
                <?php foreach (['Últimos 7 días' => '-7 days', 'Últimos 30 días' => '-30 days', 'Este año' => 'first day of january this year'] as $lbl => $rel): ?>
                    <a class="chip" href="<?= e(url('reportes/' . $clave) . '?' . http_build_query(['desde' => date('Y-m-d', strtotime($rel)), 'hasta' => date('Y-m-d')] + array_diff_key($query, ['desde' => 1, 'hasta' => 1]))) ?>"><?= e($lbl) ?></a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </form>

    <div class="report-summary">
        <p class="muted"><?= e($rep['subtitulo']) ?></p>
        <?php if (!empty($rep['resumen'])): ?>
            <dl class="stat-strip">
                <?php foreach ($rep['resumen'] as $k => $v): ?><div><dd class="num"><?= e($v) ?></dd><dt><?= e($k) ?></dt></div><?php endforeach; ?>
            </dl>
        <?php endif; ?>
    </div>

    <?php if (!$filas): ?>
        <div class="empty"><?= icon($def[3], 28) ?><strong>Sin registros</strong><p>No hay datos para los criterios seleccionados. Pruebe con otro periodo o filtro.</p></div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table stackable report-table">
                <thead><tr><?php foreach ($rep['columnas'] as $c): ?><th scope="col"><?= e($c) ?></th><?php endforeach; ?></tr></thead>
                <tbody>
                <?php foreach (array_slice($filas, 0, $previa) as $fila): ?>
                    <tr><?php foreach ($fila as $i => $celda): ?><td data-label="<?= e($rep['columnas'][$i]) ?>"><?= e($celda) ?></td><?php endforeach; ?></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <p class="panel-foot muted small"><?= $total > $previa ? 'Vista previa de ' . $previa . ' de ' . $total . ' filas. La descarga incluye todas.' : $total . ' fila(s).' ?></p>
    <?php endif; ?>
</section>
