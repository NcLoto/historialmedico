<?php
use App\Core\Auth;
use App\Core\Request;
use App\Domain\Notificaciones;

$user = Auth::user();
$currentPath = Request::capture()->path;
$hospital = brand()['nombre'];
$nombreUsuario = nombre_completo($user);
Notificaciones::sincronizar($user);
$noLeidas = Notificaciones::noLeidas((int) $user['id']);
$recientes = Notificaciones::recientes((int) $user['id'], 6);
$buscador = can('pacientes.ver') && !Auth::isPatient();
?>
<!doctype html>
<html lang="es"<?= ui_attrs() ?>>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="description" content="<?= e($hospital) ?>: sistema de historial médico electrónico.">
    <meta name="theme-color" content="#0b3a4a">
    <title><?= $noLeidas ? '(' . (int) $noLeidas . ') ' : '' ?><?= $title ? e($title) . ' - ' : '' ?><?= e($hospital) ?></title>
    <link rel="icon" href="<?= e(brand()['favicon']) ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@500;600;700&family=Open+Sans:wght@400;500;600;700&family=IBM+Plex+Sans:wght@400;500;600&display=swap">
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
    <?php if (brand()['tema_css']): ?><link rel="stylesheet" href="<?= e(brand()['tema_css']) ?>"><?php endif; ?>
    <script src="<?= e(asset('js/app.js')) ?>" defer></script>
</head>
<body data-base="<?= e(rtrim(url(''), '/')) ?>">
<a class="skip-link" href="#contenido">Saltar al contenido</a>
<div class="shell">
    <aside class="sidebar" id="sidebar">
        <a href="<?= e(url("")) ?>" class="brand<?= brand()['logo'] ? ' has-logo' : '' ?>" aria-label="Ir al inicio">
            <?php App\Core\View::partial('partials/logo'); ?>
            <?php if (!brand()['logo']): ?>
            <div class="brand-text">
                <strong><?= e($hospital) ?></strong>
                <span><?= e(brand()["lema"]) ?></span>
            </div>
            <?php endif; ?>
        </a>
        <?php App\Core\View::partial('partials/nav', compact('user', 'currentPath')); ?>
        <div class="sidebar-foot">
            <?php App\Core\View::partial('partials/avatar', ['u' => $user]); ?>
            <div class="user-meta"><strong><?= e($nombreUsuario) ?></strong><span><?= e($user['rol_nombre']) ?></span></div>
        </div>
    </aside>
    <div class="main">
        <header class="topbar">
            <button type="button" class="icon-btn menu-toggle" id="menu-toggle" aria-controls="sidebar" aria-expanded="false" aria-label="Abrir menú"><?= icon('menu', 22) ?></button>
            <a class="topbar-brand" href="<?= e(url("")) ?>"><?= e($hospital) ?></a>

            <?php if ($buscador): ?>
                <form class="global-search" method="get" action="<?= e(url('pacientes')) ?>" role="search" data-quick-search="<?= e(url('pacientes/sugerencias')) ?>">
                    <?= icon('search', 16) ?>
                    <label for="gq" class="sr-only">Buscar paciente</label>
                    <input type="search" id="gq" name="q" placeholder="Buscar paciente por nombre, DPI o código" autocomplete="off"
                           role="combobox" aria-expanded="false" aria-controls="gq-list" aria-autocomplete="list">
                    <kbd class="kbd" aria-hidden="true">/</kbd>
                    <ul class="qs-list" id="gq-list" role="listbox" aria-label="Pacientes encontrados" hidden></ul>
                </form>
            <?php endif; ?>

            <div class="topbar-actions">
                <button type="button" class="icon-btn bell" popovertarget="notif-pop" aria-label="Notificaciones<?= $noLeidas ? ', ' . (int) $noLeidas . ' sin leer' : '' ?>" data-bell data-count-url="<?= e(url('notificaciones/contador')) ?>">
                    <?= icon('bell', 20) ?><span class="bell-badge" data-bell-badge<?= $noLeidas ? '' : ' hidden' ?>><?= $noLeidas > 99 ? '99+' : (int) $noLeidas ?></span>
                </button>
                <button type="button" class="user-chip" popovertarget="user-pop" aria-label="Menú de <?= e($nombreUsuario) ?>">
                    <?php App\Core\View::partial('partials/avatar', ['u' => $user]); ?>
                    <span class="user-chip-name"><?= e($user['nombres']) ?></span><?= icon('chevron-down', 14) ?>
                </button>
            </div>
        </header>

        <div class="pop pop-notif" id="notif-pop" popover>
            <div class="pop-head">
                <strong>Notificaciones</strong>
                <?php if ($noLeidas): ?>
                    <form method="post" action="<?= e(url('notificaciones/leer')) ?>"><?= csrf_field() ?><input type="hidden" name="volver" value="<?= e($currentPath) ?>">
                        <button type="submit" class="link-btn small">Marcar todas como leídas</button></form>
                <?php endif; ?>
            </div>
            <?php if (!$recientes): ?>
                <div class="empty compact"><?= icon('bell', 22) ?><strong>Sin notificaciones</strong><p>Le avisaremos de consultas, documentos y recordatorios.</p></div>
            <?php else: ?>
                <ul class="notif-list compact"><?php foreach ($recientes as $n) { App\Core\View::partial('notificaciones/_item', ['n' => $n, 'acciones' => false, 'volver' => '']); } ?></ul>
            <?php endif; ?>
            <a class="pop-foot" href="<?= e(url('notificaciones')) ?>">Ver todas las notificaciones</a>
        </div>

        <div class="pop pop-user" id="user-pop" popover>
            <div class="pop-user-head">
                <?php App\Core\View::partial('partials/avatar', ['u' => $user, 'clase' => 'lg']); ?>
                <div class="user-meta"><strong><?= e($nombreUsuario) ?></strong><span><?= e($user['email']) ?></span><span><?= e($user['rol_nombre']) ?></span></div>
            </div>
            <nav class="pop-menu" aria-label="Cuenta">
                <a href="<?= e(url('perfil')) ?>"><?= icon('user', 16) ?> Mi perfil</a>
                <a href="<?= e(url('perfil/notificaciones')) ?>"><?= icon('bell', 16) ?> Notificaciones</a>
                <a href="<?= e(url('perfil/seguridad')) ?>"><?= icon('shield', 16) ?> Seguridad y contraseña</a>
                <a href="<?= e(url('perfil/apariencia')) ?>"><?= icon('eye', 16) ?> Apariencia</a>
                <?php if (can('configuracion.ver')): ?><a href="<?= e(url('configuracion')) ?>"><?= icon('settings', 16) ?> Configuración</a><?php endif; ?>
            </nav>
            <form method="post" action="<?= e(url('logout')) ?>" class="pop-logout">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-secondary btn-block"><?= icon('logout', 16) ?> Cerrar sesión</button>
            </form>
        </div>

        <main id="contenido" class="content" tabindex="-1">
            <?php App\Core\View::partial('partials/flash', ['flash' => $flash]); ?>
            <?= $content ?>
        </main>
        <footer class="app-footer"><?= icon('lock', 14) ?><span>Información médica confidencial. Cada consulta de datos queda registrada con su usuario, fecha y hora.</span></footer>
    </div>
</div>
<div class="scrim" id="scrim" hidden></div>
</body>
</html>
