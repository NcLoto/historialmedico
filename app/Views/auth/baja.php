<h1>Avisos por correo</h1>
<?php if (!$valido): ?>
    <p class="lead">El enlace no es válido. Puede administrar sus avisos desde su perfil después de iniciar sesión.</p>
<?php elseif ($hecho): ?>
    <div class="alert alert-success"><?= icon('check', 18) ?><span>Listo: ya no recibirá por correo <?= e($tipoNombre) ?>. Las notificaciones dentro del sistema siguen activas.</span></div>
    <p class="help">Puede volver a activarlos cuando quiera en su perfil, sección Notificaciones.</p>
<?php else: ?>
    <p class="lead">¿Quiere dejar de recibir por correo <?= e($tipoNombre) ?>? Seguirá viéndolos en la campana de notificaciones del sistema.</p>
    <form method="post" action="<?= e(url('correo/baja/' . $token)) ?>" class="stack">
        <?= csrf_field() ?>
        <button type="submit" class="btn btn-primary btn-block">Dejar de recibir estos correos</button>
        <button type="submit" name="alcance" value="todos" class="btn btn-secondary btn-block">Dejar de recibir todos los correos</button>
    </form>
<?php endif; ?>
<p class="auth-links"><a href="<?= e(url('login')) ?>">Ir al inicio de sesión</a></p>
