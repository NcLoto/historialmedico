<?php if (!$valid): ?>
    <h1>Enlace no válido</h1>
    <p class="lead">El enlace ya fue utilizado o venció. Solicite uno nuevo para continuar.</p>
    <a class="btn btn-primary" href="<?= e(url('olvide-contrasena')) ?>">Solicitar nuevo enlace</a>
<?php else: ?>
    <h1>Nueva contraseña</h1>
    <p class="lead">Mínimo 10 caracteres, combinando letras y números.</p>
    <form method="post" action="<?= e(url('restablecer/' . $token)) ?>" class="stack" data-enter-submit>
        <?= csrf_field() ?>
        <div class="field<?= isset($errors['password']) ? ' has-error' : '' ?>">
            <label for="password">Nueva contraseña</label>
            <input type="password" id="password" name="password" autocomplete="new-password" required autofocus>
            <?= field_error($errors, 'password') ?>
        </div>
        <div class="field">
            <label for="password_confirmar">Confirmar contraseña</label>
            <input type="password" id="password_confirmar" name="password_confirmar" autocomplete="new-password" required>
        </div>
        <button type="submit" class="btn btn-primary btn-block">Guardar contraseña</button>
    </form>
<?php endif; ?>
