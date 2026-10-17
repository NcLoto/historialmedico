<?php
/**
 * Lista de verificación del sistema: cada punto dice si está en orden o necesita atención, con su acción.
 * Los íconos de estado son informativos (no casillas que se puedan marcar).
 * @var list<array{ok:bool,texto:string,detalle?:string,href?:string,accion?:string}> $items
 */
?>
<ul class="checklist">
    <?php foreach ($items as $it): ?>
        <li class="<?= $it['ok'] ? 'is-ok' : 'is-pending' ?>">
            <span class="cl-state" aria-hidden="true"><?= icon($it['ok'] ? 'check' : 'alert', 14) ?></span>
            <span class="cl-text"><span class="sr-only"><?= $it['ok'] ? 'En orden: ' : 'Pendiente: ' ?></span><?= e($it['texto']) ?><?php if (!empty($it['detalle'])): ?><small><?= e($it['detalle']) ?></small><?php endif; ?></span>
            <?php if (!empty($it['href'])): ?><a class="btn btn-ghost btn-sm" href="<?= e($it['href']) ?>"><?= e($it['accion'] ?? 'Ver') ?></a><?php endif; ?>
        </li>
    <?php endforeach; ?>
</ul>
