<?php
use App\Core\Auth;
?>
<div class="empty error-page">
    <?= icon($ic, 32) ?>
    <h1><?= e($heading) ?></h1>
    <p><?= e($text) ?></p>
    <p class="muted small">Código <?= (int) $code ?></p>
    <div class="row">
        <button type="button" class="btn btn-secondary" data-back><?= icon('arrow-left', 16) ?> Volver</button>
        <a class="btn btn-primary" href="<?= e(url(Auth::check() ? '/' : 'login')) ?>"><?= Auth::check() ? 'Ir al inicio' : 'Ir al inicio de sesión' ?></a>
    </div>
</div>
