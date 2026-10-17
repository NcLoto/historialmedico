<?php
$editable = can('configuracion.editar');
$v = fn(string $k) => old($k, $cfg[$k] ?? '');
$campo = function (string $k, string $label, int $min, int $max, string $ayuda) use ($v, $errors, $editable) {
    echo '<div class="field col-3' . (isset($errors[$k]) ? ' has-error' : '') . '"><label for="' . $k . '">' . e($label) . '</label>';
    echo '<input type="number" id="' . $k . '" name="' . $k . '" value="' . e($v($k)) . '" min="' . $min . '" max="' . $max . '" required' . ($editable ? '' : ' disabled') . '>';
    echo '<p class="help">' . e($ayuda) . '</p>' . field_error($errors, $k) . '</div>';
};
App\Core\View::partial('configuracion/_tabs', ['seccion' => $seccion]);
?>
<form method="post" action="<?= e(url('configuracion/seguridad')) ?>" novalidate
      data-confirm="Los parámetros de seguridad y sesión se aplican de inmediato a todo el sistema." data-confirm-title="Guardar seguridad" data-confirm-label="Guardar">
    <?= csrf_field() ?>
    <section class="form-section">
        <h2>Sesiones y acceso</h2>
        <div class="form-grid">
            <?php $campo('sesion_minutos', 'Cierre por inactividad (min)', 5, 480, 'Entre 5 y 480.'); ?>
            <?php $campo('intentos_max', 'Intentos fallidos permitidos', 3, 10, 'Entre 3 y 10.'); ?>
            <?php $campo('bloqueo_minutos', 'Bloqueo de cuenta (min)', 1, 120, 'Entre 1 y 120.'); ?>
        </div>
    </section>
    <p class="help">El tamaño máximo y los formatos de los documentos adjuntos se configuran en <a href="<?= e(url('configuracion/documentos#archivos')) ?>">Documentos</a>.</p>
    <?php if ($editable): ?><div class="form-actions"><button type="submit" class="btn btn-primary">Guardar seguridad</button></div><?php endif; ?>
</form>

<?php App\Core\View::partial('configuracion/_fin'); ?>
