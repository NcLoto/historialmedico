<?php
$editando = $p !== null;
$val = fn(string $k) => old($k, $p[$k] ?? '');
$action = $editando ? 'pacientes/' . $p['id'] : 'pacientes';
$campo = function (string $name, string $label, string $type = 'text', int $col = 6, string $ayuda = '', bool $req = false, string $extra = '') use ($val, $errors) {
    $err = isset($errors[$name]) ? ' has-error' : '';
    echo '<div class="field col-' . $col . $err . '"><label for="' . $name . '">' . e($label) . ($req ? '' : ' <span class="opt">(opcional)</span>') . '</label>';
    echo '<input type="' . $type . '" id="' . $name . '" name="' . $name . '" value="' . e($val($name)) . '"' . ($req ? ' required' : '') . ' ' . $extra . '>';
    if ($ayuda) { echo '<p class="help">' . e($ayuda) . '</p>'; }
    echo field_error($errors, $name) . '</div>';
};
?>
<div class="page-head">
    <div>
        <div class="breadcrumb"><a href="<?= e(url('pacientes')) ?>">Pacientes</a> <?= icon('chevron', 12) ?>
            <?php if ($editando): ?><a href="<?= e(url('pacientes/' . $p['id'])) ?>"><?= e(nombre_completo($p)) ?></a> <?= icon('chevron', 12) ?> Editar<?php else: ?>Nuevo<?php endif; ?></div>
        <h1><?= $editando ? 'Editar datos del paciente' : 'Registrar paciente' ?></h1>
        <?php if ($editando): ?><p>Código <?= e($p['codigo']) ?>. El historial clínico se edita desde la ficha médica.</p><?php endif; ?>
    </div>
</div>

<form method="post" action="<?= e(url($action)) ?>" novalidate>
    <?= csrf_field() ?>

    <section class="form-section">
        <h2>Información personal</h2>
        <p class="help">Datos de identificación tal como aparecen en el documento del paciente.</p>
        <div class="form-grid">
            <?php $campo('nombres', 'Nombres', 'text', 6, '', true, 'maxlength="80" autocomplete="off"'); ?>
            <?php $campo('apellidos', 'Apellidos', 'text', 6, '', true, 'maxlength="80" autocomplete="off"'); ?>
            <?php $campo('documento_identidad', 'Documento de identidad (DPI)', 'text', 4, 'Entre 6 y 20 caracteres, sin espacios.', true, 'maxlength="20" inputmode="numeric" autocomplete="off"'); ?>
            <?php $campo('fecha_nacimiento', 'Fecha de nacimiento', 'date', 4, '', true, 'max="' . date('Y-m-d') . '"'); ?>
            <div class="field col-4<?= isset($errors['sexo']) ? ' has-error' : '' ?>">
                <label for="sexo">Sexo</label>
                <select id="sexo" name="sexo" required><?= options(['F' => 'Femenino', 'M' => 'Masculino'], $val('sexo'), 'Seleccione') ?></select>
                <?= field_error($errors, 'sexo') ?>
            </div>
        </div>
    </section>

    <section class="form-section">
        <h2>Información médica general</h2>
        <div class="form-grid">
            <div class="field col-4<?= isset($errors['tipo_sangre']) ? ' has-error' : '' ?>">
                <label for="tipo_sangre">Tipo de sangre <span class="opt">(opcional)</span></label>
                <select id="tipo_sangre" name="tipo_sangre"><?= options(array_combine(['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'], ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-']), $val('tipo_sangre'), 'No registrado') ?></select>
                <?= field_error($errors, 'tipo_sangre') ?>
            </div>
            <div class="field col-4<?= isset($errors['estado_civil']) ? ' has-error' : '' ?>">
                <label for="estado_civil">Estado civil <span class="opt">(opcional)</span></label>
                <select id="estado_civil" name="estado_civil"><?= options(['soltero' => 'Soltero(a)', 'casado' => 'Casado(a)', 'unido' => 'Unido(a)', 'divorciado' => 'Divorciado(a)', 'viudo' => 'Viudo(a)'], $val('estado_civil'), 'No registrado') ?></select>
                <?= field_error($errors, 'estado_civil') ?>
            </div>
            <?php $campo('ocupacion', 'Ocupación', 'text', 4, '', false, 'maxlength="80"'); ?>
        </div>
    </section>

    <section class="form-section">
        <h2>Contacto</h2>
        <div class="form-grid">
            <?php $campo('telefono', 'Teléfono', 'tel', 4, '', false, 'maxlength="25"'); ?>
            <?php $campo('email', 'Correo electrónico', 'email', 8, '', false, 'maxlength="150"'); ?>
            <div class="field col-12">
                <label class="check"><input type="checkbox" name="acepta_correo" value="1" <?= (string) old('acepta_correo', (string) ($p['acepta_correo'] ?? '0')) === '1' ? 'checked' : '' ?>>
                    <span>El paciente autoriza recibir avisos de sus citas por correo</span></label>
                <p class="help">Solo se usa si el paciente no tiene cuenta en el portal. Los avisos indican fecha y hora, nunca información médica.</p>
            </div>
            <?php $campo('direccion', 'Dirección', 'text', 12, '', false, 'maxlength="200"'); ?>
        </div>
    </section>

    <section class="form-section">
        <h2>Contacto de emergencia</h2>
        <div class="form-grid">
            <?php $campo('contacto_nombre', 'Nombre', 'text', 5, '', false, 'maxlength="120"'); ?>
            <?php $campo('contacto_parentesco', 'Parentesco', 'text', 3, '', false, 'maxlength="40"'); ?>
            <?php $campo('contacto_telefono', 'Teléfono', 'tel', 4, '', false, 'maxlength="25"'); ?>
        </div>
    </section>

    <?php if (!$editando && $puedeCuenta): ?>
        <section class="form-section">
            <h2>Cuenta de acceso</h2>
            <p class="help">Permite que el paciente consulte su propia información. Se usará el correo indicado arriba.</p>
            <label class="check">
                <input type="checkbox" name="crear_cuenta" value="1" <?= old('crear_cuenta') ? 'checked' : '' ?>>
                <span>Crear cuenta con contraseña temporal <span class="muted">(el paciente deberá cambiarla en su primer ingreso)</span></span>
            </label>
        </section>
    <?php endif; ?>

    <div class="form-actions">
        <a class="btn btn-secondary" href="<?= e(url($editando ? 'pacientes/' . $p['id'] : 'pacientes')) ?>">Cancelar</a>
        <button type="submit" class="btn btn-primary"><?= $editando ? 'Guardar cambios' : 'Registrar paciente' ?></button>
    </div>
</form>
