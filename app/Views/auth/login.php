<h1>Iniciar sesión</h1>
<form method="post" action="<?= e(url('login')) ?>" class="stack" novalidate data-enter-submit>
    <?= csrf_field() ?>
    <input type="hidden" name="next" value="<?= e($next) ?>">
    <div class="field">
        <label for="email">Correo electrónico</label>
        <input type="email" id="email" name="email" value="<?= e(old('email')) ?>" autocomplete="username" required autofocus>
    </div>
    <div class="field">
        <div class="label-row"><label for="password">Contraseña</label><a class="small" href="<?= e(url('olvide-contrasena')) ?>">¿Olvidó su contraseña?</a></div>
        <div class="input-pass">
            <input type="password" id="password" name="password" autocomplete="current-password" required>
            <button type="button" class="pass-toggle" data-pass-toggle aria-controls="password" aria-pressed="false">Mostrar</button>
        </div>
    </div>
    <button type="submit" class="btn btn-primary btn-block">Ingresar</button>
</form>
