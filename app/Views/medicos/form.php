<?php
$editando = $m !== null;
$val = fn(string $k) => old($k, (string) ($m[$k] ?? ''));
$campo = function (string $name, string $label, string $type, int $col, bool $req, string $extra = '') use ($val, $errors) {
    echo '<div class="field col-' . $col . (isset($errors[$name]) ? ' has-error' : '') . '"><label for="' . $name . '">' . e($label) . ($req ? '' : ' <span class="opt">(opcional)</span>') . '</label>';
    echo '<input type="' . $type . '" id="' . $name . '" name="' . $name . '" value="' . e($val($name)) . '"' . ($req ? ' required' : '') . ' ' . $extra . '>' . field_error($errors, $name) . '</div>';
};
?>
<div class="page-head">
    <div>
        <div class="breadcrumb"><a href="<?= e(url('medicos')) ?>">Médicos</a> <?= icon('chevron', 12) ?> <?= $editando ? 'Editar' : 'Nuevo' ?></div>
        <h1><?= $editando ? 'Editar médico' : 'Registrar médico' ?></h1>
        <?php if (!$editando): ?><p>Se crea también su cuenta de acceso con una contraseña temporal.</p><?php endif; ?>
    </div>
</div>
<form method="post" action="<?= e(url($editando ? 'medicos/' . $m['id'] : 'medicos')) ?>" novalidate>
    <?= csrf_field() ?>
    <section class="form-section">
        <h2>Datos personales y de acceso</h2>
        <div class="form-grid">
            <?php $campo('nombres', 'Nombres', 'text', 6, true, 'maxlength="80"'); ?>
            <?php $campo('apellidos', 'Apellidos', 'text', 6, true, 'maxlength="80"'); ?>
            <?php $campo('email', 'Correo electrónico (usuario de acceso)', 'email', 8, true, 'maxlength="150"'); ?>
        </div>
    </section>
    <section class="form-section">
        <h2>Datos profesionales</h2>
        <div class="form-grid">
            <div class="field col-4<?= isset($errors['especialidad_id']) ? ' has-error' : '' ?>">
                <label for="especialidad_id">Especialidad</label>
                <select id="especialidad_id" name="especialidad_id" required><?= options($especialidades, $val('especialidad_id'), 'Seleccione') ?></select>
                <?= field_error($errors, 'especialidad_id') ?>
            </div>
            <?php $campo('colegiado', 'Número de colegiado', 'text', 4, true, 'maxlength="20"'); ?>
            <?php $campo('consultorio', 'Consultorio', 'text', 4, false, 'maxlength="60"'); ?>
            <?php $campo('telefono', 'Teléfono', 'tel', 4, false, 'maxlength="25"'); ?>
        </div>
    </section>
    <div class="form-actions">
        <a class="btn btn-secondary" href="<?= e(url('medicos')) ?>">Cancelar</a>
        <button type="submit" class="btn btn-primary"><?= $editando ? 'Guardar cambios' : 'Registrar médico' ?></button>
    </div>
</form>
