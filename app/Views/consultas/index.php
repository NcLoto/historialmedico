<div class="page-head">
    <div>
        <h1><?= $esPaciente ? 'Mis consultas' : 'Consultas' ?></h1>
        <?php if ($filtrado): ?><p>Paciente: <?= e(nombre_completo($filtrado)) ?> (<?= e($filtrado['codigo']) ?>)</p><?php endif; ?>
    </div>
    <?php if (!$esPaciente): ?>
        <div class="page-actions"><a class="btn btn-primary" href="<?= e(url('pacientes')) ?>"><?= icon('search', 16) ?> Buscar paciente</a></div>
    <?php endif; ?>
</div>

<section class="panel">
    <form class="filters" method="get" action="<?= e(url('consultas')) ?>">
        <?php if ($filtrado): ?><input type="hidden" name="paciente" value="<?= (int) $filtrado['id'] ?>"><?php endif; ?>
        <div class="field grow">
            <label for="q">Buscar</label>
            <input type="search" id="q" name="q" value="<?= e($q) ?>" placeholder="<?= $esPaciente ? 'Motivo de la consulta' : 'Paciente, código o motivo' ?>">
        </div>
        <div class="field">
            <label for="estado">Estado</label>
            <select id="estado" name="estado"><?= options(['programada' => 'Programadas', 'realizada' => 'Realizadas', 'cancelada' => 'Canceladas'], $estado, 'Todas') ?></select>
        </div>
        <?php if ($tieneMedico): ?>
            <label class="check"><input type="hidden" name="mias" value="0"><input type="checkbox" name="mias" value="1" <?= $soloMias ? 'checked' : '' ?>><span>Solo mis consultas</span></label>
        <?php endif; ?>
        <button type="submit" class="btn btn-secondary">Filtrar</button>
    </form>

    <?php if (!$rows): ?>
        <div class="empty">
            <?= icon('stethoscope', 28) ?>
            <strong>No hay consultas para mostrar</strong>
            <p><?= ($q !== '' || $estado !== '') ? 'Ninguna consulta coincide con los filtros aplicados.' : ($esPaciente ? 'Cuando tenga consultas registradas o programadas, aparecerán aquí.' : 'Todavía no hay consultas registradas con este criterio.') ?></p>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table stackable">
                <thead><tr><th scope="col">Fecha</th><?php if (!$esPaciente): ?><th scope="col">Paciente</th><?php endif; ?><th scope="col">Motivo y diagnóstico</th><?php if (!$soloMias): ?><th scope="col">Médico</th><?php endif; ?><th scope="col">Estado</th></tr></thead>
                <tbody>
                <?php foreach ($rows as $c): ?>
                    <tr data-href="<?= e(url('consultas/' . $c['id'])) ?>">
                        <td data-label="Fecha" class="num nowrap"><?= e(fecha_hora($c['fecha_hora'])) ?></td>
                        <?php if (!$esPaciente): ?>
                            <td data-label="Paciente"><a class="cell-title" href="<?= e(url('pacientes/' . $c['paciente_id'])) ?>"><?= e($c['papellidos'] . ', ' . $c['pnombres']) ?></a><span class="cell-sub"><?= e($c['codigo']) ?></span></td>
                        <?php endif; ?>
                        <td data-label="Motivo"><a class="cell-title" href="<?= e(url('consultas/' . $c['id'])) ?>"><?= e($c['motivo']) ?></a><?php if (!empty($diag[$c['id']])): ?><span class="cell-sub"><?= e($diag[$c['id']]) ?></span><?php endif; ?></td>
                        <?php if (!$soloMias): ?><td data-label="Médico"><?= e(nombre_completo($c, 'mnombres', 'mapellidos')) ?><span class="cell-sub"><?= e($c['especialidad']) ?></span></td><?php endif; ?>
                        <td data-label="Estado"><?php App\Core\View::partial('partials/estado_consulta', ['estado' => $c['estado']]); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php App\Core\View::partial('partials/pagination', ['pager' => $pager, 'query' => ['q' => $q, 'estado' => $estado, 'paciente' => $filtrado['id'] ?? '', 'mias' => $soloMias ? '1' : '0'], 'path' => 'consultas']); ?>
    <?php endif; ?>
</section>
