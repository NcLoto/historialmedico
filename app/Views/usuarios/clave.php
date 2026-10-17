<?php
/** @var array $u @var bool $esYo */
$action = e(url('usuarios/' . $u['id'] . '/clave'));
$rol = $u['rol_nombre'];
?>
<div class="page-head">
    <div>
        <div class="breadcrumb"><a href="<?= e(url('usuarios')) ?>">Usuarios</a> <?= icon('chevron', 12) ?> <span>Contraseña</span></div>
        <h1>Contraseña de la cuenta</h1>
    </div>
</div>

<section class="form-section">
    <h2>Cuenta</h2>
    <dl class="kv">
        <dt>Nombre</dt><dd><?= e(nombre_completo($u)) ?></dd>
        <dt>Usuario</dt><dd><?= e($u['email']) ?></dd>
        <dt>Rol</dt><dd><?= e($rol) ?></dd>
        <dt>Estado</dt><dd><?= $u['activo'] ? 'Activa' : 'Inactiva' ?></dd>
    </dl>
</section>

<section class="form-section">
    <h2>Generar una contraseña temporal</h2>
    <p class="help">El sistema crea una contraseña aleatoria y se la muestra una sola vez. También desbloquea la cuenta si estaba bloqueada por intentos fallidos.</p>
    <form method="post" action="<?= $action ?>" data-confirm="Se reemplazará la contraseña actual por una temporal y se cerrará cualquier bloqueo." data-confirm-title="Generar contraseña temporal" data-confirm-label="Generar">
        <?= csrf_field() ?><input type="hidden" name="modo" value="temporal">
        <button type="submit" class="btn btn-secondary"><?= icon('key', 16) ?> Generar contraseña temporal</button>
    </form>
</section>

<form method="post" action="<?= $action ?>" novalidate autocomplete="off">
    <?= csrf_field() ?><input type="hidden" name="modo" value="definir">
    <section class="form-section">
        <h2>Definir una contraseña</h2>
        <p class="help">Escriba la contraseña que el usuario recibirá. Por seguridad, deberá cambiarla en su primer ingreso.</p>
        <div class="form-grid">
            <div class="field col-6<?= isset($errors['password_nueva']) ? ' has-error' : '' ?>">
                <label for="password_nueva">Nueva contraseña</label>
                <div class="input-pass">
                    <input type="password" id="password_nueva" name="password_nueva" autocomplete="new-password" minlength="10" required>
                    <button type="button" class="pass-toggle" data-pass-toggle aria-controls="password_nueva" aria-pressed="false">Mostrar</button>
                </div>
                <p class="help">Mínimo 10 caracteres, con letras y números.</p>
                <?= field_error($errors, 'password_nueva') ?>
            </div>
            <div class="field col-6">
                <label for="password_confirmar">Confirmar contraseña</label>
                <input type="password" id="password_confirmar" name="password_confirmar" autocomplete="new-password" required>
            </div>
        </div>
    </section>
    <div class="form-actions">
        <a class="btn btn-ghost" href="<?= e(url('usuarios')) ?>">Cancelar</a>
        <button type="submit" class="btn btn-primary">Guardar contraseña</button>
    </div>
</form>
