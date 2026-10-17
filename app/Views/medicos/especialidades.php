<div class="page-head">
    <div>
        <div class="breadcrumb"><a href="<?= e(url('medicos')) ?>">Médicos</a> <?= icon('chevron', 12) ?> Especialidades</div>
        <h1>Especialidades</h1>
    </div>
</div>

<div class="cols-3">
    <section class="panel">
        <div class="table-wrap">
            <table class="table stackable">
                <thead><tr><th scope="col">Especialidad</th><th scope="col">Descripción</th><th scope="col" class="right">Médicos</th><?php if (can('medicos.editar')): ?><th scope="col"><span class="sr-only">Acciones</span></th><?php endif; ?></tr></thead>
                <tbody>
                <?php foreach ($rows as $e_): ?>
                    <tr>
                        <?php if (can('medicos.editar')): ?>
                            <td colspan="3">
                                <form method="post" action="<?= e(url('especialidades/' . $e_['id'])) ?>" class="input-group">
                                    <?= csrf_field() ?>
                                    <input type="text" name="nombre" value="<?= e($e_['nombre']) ?>" maxlength="80" required aria-label="Nombre de la especialidad">
                                    <input type="text" name="descripcion" value="<?= e($e_['descripcion']) ?>" maxlength="255" aria-label="Descripción">
                                    <span class="small muted nowrap"><?= (int) $e_['medicos'] ?> médico(s)</span>
                                    <button type="submit" class="btn btn-secondary btn-sm">Guardar</button>
                                </form>
                            </td>
                        <?php else: ?>
                            <td><?= e($e_['nombre']) ?></td><td><?= e($e_['descripcion']) ?></td><td class="right num"><?= (int) $e_['medicos'] ?></td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <?php if (can('medicos.crear')): ?>
    <section class="panel">
        <div class="panel-head"><h2>Nueva especialidad</h2></div>
        <form method="post" action="<?= e(url('especialidades')) ?>" class="panel-body stack">
            <?= csrf_field() ?>
            <div class="field<?= isset($errors['nombre']) ? ' has-error' : '' ?>"><label for="nombre">Nombre</label><input type="text" id="nombre" name="nombre" value="<?= e(old('nombre')) ?>" maxlength="80" required><?= field_error($errors, 'nombre') ?></div>
            <div class="field"><label for="descripcion">Descripción <span class="opt">(opcional)</span></label><input type="text" id="descripcion" name="descripcion" value="<?= e(old('descripcion')) ?>" maxlength="255"></div>
            <div class="form-actions"><button type="submit" class="btn btn-primary">Agregar</button></div>
        </form>
    </section>
    <?php endif; ?>
</div>
