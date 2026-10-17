<?php
use App\Core\Config;

try {
    $hospital = brand()['nombre'];
} catch (Throwable) {
    $hospital = 'Hospital Sanitas';
}
?>
<!doctype html>
<html lang="es"<?= ui_attrs() ?>>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="description" content="<?= e($hospital) ?>: sistema de historial médico electrónico.">
    <meta name="theme-color" content="#0b3a4a">
    <title><?= $title ? e($title) . ' - ' : '' ?><?= e($hospital) ?></title>
    <link rel="icon" href="<?= e(brand()['favicon']) ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@500;600;700&family=Open+Sans:wght@400;500;600;700&family=IBM+Plex+Sans:wght@400;500;600&display=swap">
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
    <?php if (brand()['tema_css']): ?><link rel="stylesheet" href="<?= e(brand()['tema_css']) ?>"><?php endif; ?>
    <script src="<?= e(asset('js/app.js')) ?>" defer></script>
</head>
<body class="auth-body">
<div class="auth-shell">
    <main class="auth-panel">
        <div class="auth-card">
            <div class="auth-head<?= brand()['logo'] ? ' has-logo' : '' ?>">
                <?php App\Core\View::partial('partials/logo'); ?>
                <?php if (!brand()['logo']): ?><strong><?= e($hospital) ?></strong><?php endif; ?>
            </div>
            <?php App\Core\View::partial('partials/flash', ['flash' => $flash]); ?>
            <?= $content ?>
        </div>
        <footer class="auth-foot">
            <?php $contacto = array_filter([Config::get('hospital_direccion'), Config::get('hospital_telefono') ? 'Tel. ' . Config::get('hospital_telefono') : null]); ?>
            <?php if ($contacto): ?><p><?= e(implode(', ', $contacto)) ?></p><?php endif; ?>
            <p>&copy; <?= date('Y') ?> <?= e($hospital) ?>. Uso exclusivo del personal autorizado y de pacientes registrados.</p>
        </footer>
    </main>
</div>
</body>
</html>
