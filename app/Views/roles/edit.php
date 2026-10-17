<?php $editable = can('roles.editar'); ?>
<div class="page-head">
    <div>
        <div class="breadcrumb"><a href="<?= e(url('roles')) ?>">Roles y permisos</a> <?= icon('chevron', 12) ?> <?= e($rol['nombre']) ?></div>
        <h1>Permisos del rol <?= e($rol['nombre']) ?></h1>
        <p><?= e($rol['descripcion']) ?></p>
    </div>
</div>

<?php if ($rol['codigo'] === 'paciente'): ?>
    <div class="alert alert-info"><?= icon('info', 18) ?><span>Las cuentas de pacientes siempre quedan limitadas a su propia información, sin importar los permisos marcados, y no pueden modificar datos clínicos.</span></div>
<?php endif; ?>

<form method="post" action="<?= e(url('roles/' . $rol['id'])) ?>" data-confirm="Los cambios de permisos se aplican de inmediato a todos los usuarios de este rol y quedan en la auditoría." data-confirm-title="Guardar permisos" data-confirm-label="Guardar">
    <?= csrf_field() ?>
    <section class="panel">
        <div class="table-wrap">
            <table class="table matrix">
                <thead><tr><th scope="col">Módulo</th><?php foreach ($acciones as $a => $label): ?><th scope="col"><?= e($label) ?></th><?php endforeach; ?></tr></thead>
                <tbody>
                <?php foreach ($orden as $mod): ?>
                    <tr>
                        <td><span class="cell-title"><?= e($modulos[$mod]) ?></span></td>
                        <?php foreach ($acciones as $a => $label): $perm = $matriz[$mod][$a] ?? null; ?>
                            <td>
                                <?php if ($perm): ?>
                                    <input type="checkbox" name="permisos[]" value="<?= (int) $perm['id'] ?>" <?= isset($asignados[$perm['id']]) ? 'checked' : '' ?> <?= $editable ? '' : 'disabled' ?>
                                           title="<?= e($perm['descripcion']) ?>" aria-label="<?= e($label . ', ' . $modulos[$mod]) ?>">
                                <?php else: ?><span class="muted" aria-hidden="true">—</span><?php endif; ?>
                            </td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
    <p class="help spaced">«Eliminar» en Pacientes, Médicos y Usuarios significa inactivar (no se borran registros). Los cambios se aplican a todos los usuarios del rol y quedan en la auditoría.</p>
    <div class="form-actions">
        <a class="btn btn-secondary" href="<?= e(url('roles')) ?>"><?= $editable ? 'Cancelar' : 'Volver' ?></a>
        <?php if ($editable): ?><button type="submit" class="btn btn-primary">Guardar permisos</button><?php endif; ?>
    </div>
</form>
