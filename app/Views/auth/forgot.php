<h1>Recuperar contraseña</h1>
<p class="lead">Escriba el correo de su cuenta. Le enviaremos un enlace de un solo uso, válido por 60 minutos.</p>
<form method="post" action="<?= e(url('olvide-contrasena')) ?>" class="stack" data-enter-submit>
    <?= csrf_field() ?>
    <div class="field">
        <label for="email">Correo electrónico</label>
        <input type="email" id="email" name="email" autocomplete="username" required autofocus>
    </div>
    <button type="submit" class="btn btn-primary btn-block">Enviar enlace</button>
</form>
<p class="auth-links"><a href="<?= e(url('login')) ?>">Volver al inicio de sesión</a></p>
