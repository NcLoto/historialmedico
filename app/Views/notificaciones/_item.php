<?php
/** @var array $n @var bool $acciones @var string $volver */
use App\Domain\Notificaciones;

$noLeida = $n['leida_en'] === null;
$ts = strtotime($n['creado_en']);
$hace = time() - $ts;
$cuando = $hace < 60 ? 'Ahora' : ($hace < 3600 ? 'Hace ' . (int) floor($hace / 60) . ' min' : ($hace < 86400 ? 'Hace ' . (int) floor($hace / 3600) . ' h' : fecha_hora($n['creado_en'])));
?>
<li class="notif notif-<?= e($n['nivel']) ?><?= $noLeida ? ' is-unread' : '' ?>">
    <span class="notif-dot" aria-hidden="true"></span>
    <a class="notif-body" href="<?= e(url('notificaciones/' . $n['id'])) ?>">
        <span class="notif-title"><?php if ($noLeida): ?><span class="sr-only">Sin leer: </span><?php endif; ?><?= e($n['titulo']) ?></span>
        <?php if ($n['mensaje']): ?><span class="notif-text"><?= e($n['mensaje']) ?></span><?php endif; ?>
        <span class="notif-meta"><?= e(Notificaciones::TIPOS[$n['tipo']][0] ?? 'Notificación') ?><span class="sep"></span><time datetime="<?= e(date('c', $ts)) ?>" title="<?= e(fecha_hora($n['creado_en'])) ?>"><?= e($cuando) ?></time></span>
    </a>
    <?php if (!empty($acciones)): ?>
        <div class="notif-actions">
            <form method="post" action="<?= e(url('notificaciones/' . $n['id'] . '/estado')) ?>"><?= csrf_field() ?><input type="hidden" name="volver" value="<?= e($volver) ?>">
                <button type="submit" class="icon-btn" aria-label="<?= $noLeida ? 'Marcar como leída' : 'Marcar como no leída' ?>" title="<?= $noLeida ? 'Marcar como leída' : 'Marcar como no leída' ?>"><?= icon($noLeida ? 'check' : 'circle', 16) ?></button></form>
            <form method="post" action="<?= e(url('notificaciones/' . $n['id'] . '/eliminar')) ?>"><?= csrf_field() ?><input type="hidden" name="volver" value="<?= e($volver) ?>">
                <button type="submit" class="icon-btn link-danger" aria-label="Eliminar notificación" title="Eliminar"><?= icon('trash', 16) ?></button></form>
        </div>
    <?php endif; ?>
</li>
