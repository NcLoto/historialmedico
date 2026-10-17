<?php
$editable = can('configuracion.editar');
$v = fn(string $k) => old($k, $cfg[$k] ?? '');
$campo = function (string $k, string $label, string $type, int $col, string $ayuda = '', string $extra = '') use ($v, $errors, $editable) {
    echo '<div class="field col-' . $col . (isset($errors[$k]) ? ' has-error' : '') . '"><label for="' . $k . '">' . e($label) . '</label>';
    echo '<input type="' . $type . '" id="' . $k . '" name="' . $k . '" value="' . e($v($k)) . '" ' . $extra . ($editable ? '' : ' disabled') . '>';
    if ($ayuda) { echo '<p class="help">' . e($ayuda) . '</p>'; }
    echo field_error($errors, $k) . '</div>';
};
App\Core\View::partial('configuracion/_tabs', ['seccion' => $seccion]);
?>
<form method="post" action="<?= e(url('configuracion')) ?>" novalidate>
    <?= csrf_field() ?>
    <section class="form-section">
        <h2>Identidad</h2>
        <p class="help">Aparece en la barra lateral, la pantalla de acceso y el título de cada página.</p>
        <div class="form-grid">
            <?php $campo('hospital_nombre', 'Nombre del hospital', 'text', 6, '', 'maxlength="100" required'); ?>
            <?php $campo('hospital_lema', 'Lema o descripción corta', 'text', 6, 'Texto bajo el nombre. Déjelo vacío para ocultarlo.', 'maxlength="80"'); ?>
        </div>
    </section>
    <section class="form-section">
        <h2>Contacto y horario</h2>
        <div class="form-grid">
            <?php $campo('hospital_telefono', 'Teléfono', 'tel', 4, '', 'maxlength="25"'); ?>
            <?php $campo('hospital_correo', 'Correo de contacto', 'email', 8, '', 'maxlength="150"'); ?>
            <?php $campo('hospital_direccion', 'Dirección', 'text', 12, '', 'maxlength="200"'); ?>
            <?php $campo('hospital_horario', 'Horario de atención', 'text', 12, 'Por ejemplo: Lunes a viernes de 7:00 a 19:00.', 'maxlength="150"'); ?>
        </div>
    </section>
    <?php if ($editable): ?><div class="form-actions"><button type="submit" class="btn btn-primary">Guardar institución</button></div><?php endif; ?>
</form>

<?php App\Core\View::partial('configuracion/_fin'); ?>
