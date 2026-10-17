<div class="page-head">
    <div>
        <h1><?= $esPaciente ? 'Mis documentos' : 'Documentos' ?></h1>
        <?php if ($filtrado): ?><p>Paciente: <?= e(nombre_completo($filtrado)) ?> (<?= e($filtrado['codigo']) ?>)</p><?php endif; ?>
    </div>
    <div class="page-actions">
        <?php App\Core\View::partial('partials/menu_exportar', ['id' => 'exp-documentos', 'base' => 'documentos/exportar', 'query' => ['paciente' => $filtrado['id'] ?? null, 'q' => $q, 'grupo' => $grupo]]); ?>
        <?php if ($filtrado && can('documentos.crear')): ?><a class="btn btn-primary" href="<?= e(url('pacientes/' . $filtrado['id'] . '/documentos/nuevo')) ?>"><?= icon('upload', 16) ?> Adjuntar documento</a><?php endif; ?>
    </div>
</div>

<section class="panel">
    <form class="filters" method="get" action="<?= e(url('documentos')) ?>">
        <?php if ($filtrado): ?><input type="hidden" name="paciente" value="<?= (int) $filtrado['id'] ?>"><?php endif; ?>
        <div class="field grow">
            <label for="q">Buscar</label>
            <input type="search" id="q" name="q" value="<?= e($q) ?>" placeholder="<?= $esPaciente ? 'Título del documento' : 'Título, paciente o código' ?>">
        </div>
        <div class="field">
            <label for="grupo">Tipo</label>
            <select id="grupo" name="grupo"><?= options(['examen' => 'Exámenes', 'imagen' => 'Imágenes', 'documento' => 'Documentos'], $grupo, 'Todos') ?></select>
        </div>
        <button type="submit" class="btn btn-secondary">Filtrar</button>
        <?php if ($q !== '' || $grupo !== ''): ?><a class="btn btn-ghost" href="<?= e(url('documentos' . ($filtrado ? '?paciente=' . $filtrado['id'] : ''))) ?>">Limpiar</a><?php endif; ?>
    </form>

    <?php if (!$rows): ?>
        <div class="empty">
            <?= icon('folder', 28) ?>
            <?php if ($q !== '' || $grupo !== ''): ?>
                <strong>Sin resultados</strong><p>Ningún documento coincide con los filtros aplicados.</p>
            <?php else: ?>
                <strong>No hay documentos</strong>
                <p><?= $esPaciente ? 'Cuando el equipo médico adjunte exámenes o informes a su historial, los verá aquí.' : 'Adjunte exámenes, imágenes o informes desde la ficha de un paciente.' ?></p>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table stackable">
                <thead><tr><th scope="col">Documento</th><?php if (!$esPaciente): ?><th scope="col">Paciente</th><?php endif; ?><th scope="col">Categoría</th><th scope="col">Fecha</th><th scope="col">Archivo</th></tr></thead>
                <tbody>
                <?php foreach ($rows as $d): ?>
                    <tr data-href="<?= e(url('documentos/' . $d['id'])) ?>">
                        <td data-label="Documento">
                            <a class="cell-title" href="<?= e(url('documentos/' . $d['id'])) ?>"><?= e($d['titulo']) ?></a>
                            <?php if ($d['descripcion']): ?><span class="cell-sub"><?= e($d['descripcion']) ?></span><?php endif; ?>
                        </td>
                        <?php if (!$esPaciente): ?><td data-label="Paciente"><span class="cell-title"><?= e($d['papellidos'] . ', ' . $d['pnombres']) ?></span><span class="cell-sub"><?= e($d['codigo']) ?></span></td><?php endif; ?>
                        <td data-label="Categoría"><?= e($d['categoria']) ?></td>
                        <td data-label="Fecha" class="num nowrap"><?= e(fecha($d['fecha_documento'] ?: $d['cargado_en'])) ?></td>
                        <td data-label="Archivo" class="nowrap"><?= e(strtoupper($d['extension'])) ?>, <?= e(bytes_legible((int) $d['tamano_bytes'])) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php App\Core\View::partial('partials/pagination', ['pager' => $pager, 'query' => ['q' => $q, 'grupo' => $grupo, 'paciente' => $filtrado['id'] ?? ''], 'path' => 'documentos']); ?>
    <?php endif; ?>
</section>
