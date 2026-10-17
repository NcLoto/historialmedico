<h1>Defina su contraseña</h1>
<p class="lead">La contraseña que usó es temporal. Para continuar, elija una contraseña nueva para <strong><?= e($cuenta['email']) ?></strong>.</p>
<form method="post" action="<?= e(url('login/cambiar-clave')) ?>" class="stack" novalidate autocomplete="off" data-enter-submit>
    <?= csrf_field() ?>
    <input type="text" name="username" value="<?= e($cuenta['email']) ?>" autocomplete="username" hidden>
    <div class="field<?= isset($errors['password_nueva']) ? ' has-error' : '' ?>">
        <label for="password_nueva">Nueva contraseña</label>
        <div class="input-pass">
            <input type="password" id="password_nueva" name="password_nueva" autocomplete="new-password" minlength="10" required autofocus>
            <button type="button" class="pass-toggle" data-pass-toggle aria-controls="password_nueva" aria-pressed="false">Mostrar</button>
        </div>
        <p class="help">Mínimo 10 caracteres, con letras y números.</p>
        <?= field_error($errors, 'password_nueva') ?>
    </div>
    <div class="field">
        <label for="password_confirmar">Confirmar contraseña</label>
        <input type="password" id="password_confirmar" name="password_confirmar" autocomplete="new-password" required>
    </div>
    <button type="submit" class="btn btn-primary btn-block">Guardar y continuar</button>
</form>
<form method="post" action="<?= e(url('login/cancelar')) ?>" class="auth-links"><?= csrf_field() ?><button type="submit" class="link-btn small">Cancelar y volver al inicio de sesión</button></form>
