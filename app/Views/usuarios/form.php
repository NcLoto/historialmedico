<?php
$editando = $u !== null;
$val = fn(string $k) => old($k, (string) ($u[$k] ?? ''));
?>
<div class="page-head">
    <div>
        <div class="breadcrumb"><a href="<?= e(url('usuarios')) ?>">Usuarios</a> <?= icon('chevron', 12) ?> <?= $editando ? 'Editar' : 'Nuevo' ?></div>
        <h1><?= $editando ? 'Editar usuario' : 'Nuevo usuario' ?></h1>
        <p><?= $editando ? 'El rol se define al crear la cuenta. Los permisos del rol se ajustan en Roles y permisos.' : 'Se genera una contraseña temporal que el usuario deberá cambiar en su primer ingreso.' ?></p>
    </div>
</div>
<form method="post" action="<?= e(url($editando ? 'usuarios/' . $u['id'] : 'usuarios')) ?>" novalidate>
    <?= csrf_field() ?>
    <section class="form-section">
        <h2>Datos de la cuenta</h2>
        <div class="form-grid">
            <div class="field col-6<?= isset($errors['nombres']) ? ' has-error' : '' ?>"><label for="nombres">Nombres</label><input type="text" id="nombres" name="nombres" value="<?= e($val('nombres')) ?>" maxlength="80" required><?= field_error($errors, 'nombres') ?></div>
            <div class="field col-6<?= isset($errors['apellidos']) ? ' has-error' : '' ?>"><label for="apellidos">Apellidos</label><input type="text" id="apellidos" name="apellidos" value="<?= e($val('apellidos')) ?>" maxlength="80" required><?= field_error($errors, 'apellidos') ?></div>
            <div class="field col-8<?= isset($errors['email']) ? ' has-error' : '' ?>"><label for="email">Correo electrónico (usuario de acceso)</label><input type="email" id="email" name="email" value="<?= e($val('email')) ?>" maxlength="150" required><?= field_error($errors, 'email') ?></div>
        </div>
    </section>
    <section class="form-section">
        <h2>Rol y alcance</h2>
        <?php if ($editando): ?>
            <dl class="kv"><dt>Rol asignado</dt><dd><?= e($u['rol_nombre']) ?></dd></dl>
        <?php else: ?>
            <div class="form-grid">
                <div class="field col-4<?= isset($errors['rol_id']) ? ' has-error' : '' ?>">
                    <label for="rol_id">Rol</label>
                    <select id="rol_id" name="rol_id" required><?= options($roles, $val('rol_id'), 'Seleccione') ?></select>
                    <p class="help">Los médicos se registran desde el módulo Médicos.</p>
                    <?= field_error($errors, 'rol_id') ?>
                </div>
                <div class="field col-8<?= isset($errors['paciente_id']) ? ' has-error' : '' ?>">
                    <label for="paciente_id">Paciente vinculado <span class="opt">(solo para el rol Paciente)</span></label>
                    <select id="paciente_id" name="paciente_id"><?= options($pacientes, $val('paciente_id'), 'No aplica') ?></select>
                    <p class="help">La cuenta de un paciente solo puede ver la información de este paciente.</p>
                    <?= field_error($errors, 'paciente_id') ?>
                </div>
            </div>
        <?php endif; ?>
    </section>
    <div class="form-actions">
        <a class="btn btn-secondary" href="<?= e(url('usuarios')) ?>">Cancelar</a>
        <button type="submit" class="btn btn-primary"><?= $editando ? 'Guardar cambios' : 'Crear usuario' ?></button>
    </div>
</form>
