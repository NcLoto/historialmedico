<?php
use App\Domain\Notificaciones;

$volver = '/notificaciones?' . http_build_query(array_filter(['ver' => $filtro !== 'todas' ? $filtro : null, 'tipo' => $tipo]));
?>
<div class="page-head">
    <div>
        <h1>Notificaciones</h1>
        <?php if ($noLeidas): ?><p><?= cantidad((int) $noLeidas, 'notificación sin leer', 'notificaciones sin leer') ?></p><?php endif; ?>
    </div>
    <div class="page-actions">
        <a class="btn btn-secondary" href="<?= e(url('perfil/notificaciones')) ?>"><?= icon('settings', 16) ?> Preferencias</a>
        <?php if ($noLeidas): ?>
            <form method="post" action="<?= e(url('notificaciones/leer')) ?>"><?= csrf_field() ?><input type="hidden" name="volver" value="<?= e($volver) ?>">
                <button type="submit" class="btn btn-primary"><?= icon('check', 16) ?> Marcar todas como leídas</button></form>
        <?php endif; ?>
    </div>
</div>

<section class="panel">
    <form class="filters" method="get" action="<?= e(url('notificaciones')) ?>">
        <div class="segmented" role="group" aria-label="Mostrar">
            <a href="<?= e(url('notificaciones' . ($tipo ? '?tipo=' . $tipo : ''))) ?>" class="<?= $filtro === 'todas' ? 'is-active' : '' ?>"<?= $filtro === 'todas' ? ' aria-current="true"' : '' ?>>Todas</a>
            <a href="<?= e(url('notificaciones?ver=no_leidas' . ($tipo ? '&tipo=' . $tipo : ''))) ?>" class="<?= $filtro === 'no_leidas' ? 'is-active' : '' ?>"<?= $filtro === 'no_leidas' ? ' aria-current="true"' : '' ?>>Sin leer <?php if ($noLeidas): ?><span class="count"><?= (int) $noLeidas ?></span><?php endif; ?></a>
        </div>
        <div class="field">
            <label for="tipo" class="sr-only">Tipo</label>
            <select id="tipo" name="tipo" data-autosubmit><?= options(array_map(fn($t) => $t[0], $tipos), $tipo, 'Todos los tipos') ?></select>
        </div>
        <?php if ($filtro !== 'todas'): ?><input type="hidden" name="ver" value="<?= e($filtro) ?>"><?php endif; ?>
        <noscript><button class="btn btn-secondary" type="submit">Filtrar</button></noscript>
    </form>

    <?php if (!$rows): ?>
        <div class="empty">
            <?= icon('bell', 28) ?>
            <strong><?= $filtro === 'no_leidas' ? 'No tiene notificaciones sin leer' : 'No hay notificaciones' ?></strong>
            <p>Aquí verá avisos de consultas, documentos nuevos, recordatorios y comunicados del hospital.</p>
        </div>
    <?php else: ?>
        <ul class="notif-list">
            <?php foreach ($rows as $n): App\Core\View::partial('notificaciones/_item', ['n' => $n, 'acciones' => true, 'volver' => $volver]); endforeach; ?>
        </ul>
        <?php App\Core\View::partial('partials/pagination', ['pager' => $pager, 'query' => ['ver' => $filtro !== 'todas' ? $filtro : '', 'tipo' => $tipo], 'path' => 'notificaciones']); ?>
        <div class="panel-foot">
            <form method="post" action="<?= e(url('notificaciones/limpiar')) ?>" data-confirm="Se eliminarán todas las notificaciones que ya leyó." data-confirm-title="Eliminar leídas" data-confirm-label="Eliminar">
                <?= csrf_field() ?><button type="submit" class="btn btn-ghost btn-sm"><?= icon('trash', 14) ?> Eliminar las leídas</button>
            </form>
        </div>
    <?php endif; ?>
</section>
