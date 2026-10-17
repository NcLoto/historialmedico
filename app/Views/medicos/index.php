<div class="page-head">
    <div><h1>Médicos</h1></div>
    <div class="page-actions">
        <a class="btn btn-secondary" href="<?= e(url('especialidades')) ?>">Especialidades</a>
        <?php App\Core\View::partial('partials/menu_exportar', ['id' => 'exp-medicos', 'base' => 'medicos/exportar', 'query' => ['q' => $q, 'especialidad' => $esp]]); ?>
        <?php if (can('medicos.crear')): ?><a class="btn btn-primary" href="<?= e(url('medicos/nuevo')) ?>"><?= icon('plus', 16) ?> Registrar médico</a><?php endif; ?>
    </div>
</div>

<section class="panel">
    <form class="filters" method="get" action="<?= e(url('medicos')) ?>">
        <div class="field grow"><label for="q">Buscar</label><input type="search" id="q" name="q" value="<?= e($q) ?>" placeholder="Nombre, colegiado o correo"></div>
        <div class="field"><label for="especialidad">Especialidad</label><select id="especialidad" name="especialidad"><?= options($especialidades, $esp, 'Todas') ?></select></div>
        <button class="btn btn-secondary" type="submit">Filtrar</button>
    </form>
    <?php if (!$rows): ?>
        <div class="empty"><?= icon('stethoscope', 28) ?><strong>No hay médicos para mostrar</strong><p>Pruebe con otros filtros o registre un médico nuevo.</p></div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table stackable">
                <thead><tr><th scope="col">Médico</th><th scope="col">Especialidad</th><th scope="col">Colegiado</th><th scope="col">Consultorio</th><th scope="col">Estado</th><th scope="col"><span class="sr-only">Acciones</span></th></tr></thead>
                <tbody>
                <?php foreach ($rows as $m): ?>
                    <tr>
                        <td data-label="Médico"><?php if (can('medicos.editar')): ?><a class="cell-title" href="<?= e(url('medicos/' . $m['id'] . '/editar')) ?>"><?= e($m['apellidos'] . ', ' . $m['nombres']) ?></a><?php else: ?><span class="cell-title"><?= e($m['apellidos'] . ', ' . $m['nombres']) ?></span><?php endif; ?><span class="cell-sub"><?= e($m['email']) ?></span></td>
                        <td data-label="Especialidad"><?= e($m['especialidad']) ?></td>
                        <td data-label="Colegiado" class="num"><?= e($m['colegiado']) ?></td>
                        <td data-label="Consultorio"><?= e($m['consultorio'] ?: '—') ?><?php if ($m['telefono']): ?><span class="cell-sub"><?= e($m['telefono']) ?></span><?php endif; ?></td>
                        <td data-label="Estado"><?= $m['activo'] ? '<span class="badge badge-success">Activo</span>' : '<span class="badge">Inactivo</span>' ?></td>
                        <td class="col-actions">
                            <?php if (can('medicos.editar') || can('medicos.eliminar')): ?>
                            <details class="dropdown row-menu">
                                <summary class="icon-btn" aria-label="Acciones para <?= e(nombre_completo($m)) ?>"><?= icon('more', 18) ?></summary>
                                <div class="dropdown-panel menu-list">
                                    <?php if (can('medicos.editar')): ?><a href="<?= e(url('medicos/' . $m['id'] . '/editar')) ?>"><?= icon('edit', 15) ?> Editar</a><?php endif; ?>
                                    <?php if (can('medicos.eliminar')): ?>
                                        <form method="post" action="<?= e(url('medicos/' . $m['id'] . '/estado')) ?>"
                                              data-confirm="<?= $m['activo'] ? 'La cuenta del médico dejará de tener acceso. Sus consultas y documentos se conservan.' : 'La cuenta del médico volverá a tener acceso.' ?>"
                                              data-confirm-title="<?= $m['activo'] ? 'Inactivar médico' : 'Reactivar médico' ?>" data-confirm-label="<?= $m['activo'] ? 'Inactivar' : 'Reactivar' ?>">
                                            <?= csrf_field() ?><button type="submit"<?= $m['activo'] ? ' class="is-danger"' : '' ?>><?= icon($m['activo'] ? 'lock' : 'check', 15) ?> <?= $m['activo'] ? 'Inactivar' : 'Reactivar' ?></button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </details>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
