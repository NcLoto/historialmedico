<?php
$editable = can('configuracion.editar');
App\Core\View::partial('configuracion/_tabs', ['seccion' => $seccion]);
?>
<section class="panel">
    <div class="panel-head"><h2><?= e($c['asunto']) ?></h2><a class="small" href="<?= e(url('configuracion/correo')) ?>">Volver a la bandeja</a></div>
    <dl class="kv panel-body">
        <dt>Para</dt><dd><?= e(($c['nombre'] ? $c['nombre'] . ' ' : '') . '<' . $c['destinatario'] . '>') ?></dd>
        <dt>Aviso</dt><dd><?= e(App\Domain\Notificaciones::TIPOS[$c['tipo']][0] ?? ucfirst($c['tipo'])) ?></dd>
        <dt>Estado</dt><dd><?= e(rtrim($estados[$c['estado']], 's')) ?>, <?= cantidad((int) $c['intentos'], 'intento', 'intentos') ?></dd>
        <dt>Creado</dt><dd class="num"><?= e(fecha_hora($c['creado_en'])) ?></dd>
        <?php if ($c['enviado_en']): ?><dt>Enviado</dt><dd class="num"><?= e(fecha_hora($c['enviado_en'])) ?></dd><?php endif; ?>
        <?php if ($c['estado'] === 'pendiente'): ?><dt>Próximo intento</dt><dd class="num"><?= e(fecha_hora($c['siguiente_intento'])) ?></dd><?php endif; ?>
        <?php if ($c['ultimo_error']): ?><dt>Último error</dt><dd><?= e($c['ultimo_error']) ?></dd><?php endif; ?>
    </dl>
    <?php if ($editable && in_array($c['estado'], ['fallido', 'cancelado', 'pendiente'], true)): ?>
        <div class="form-actions panel-body">
            <?php if ($c['estado'] === 'pendiente'): ?>
                <form method="post" action="<?= e(url('configuracion/correo/' . $c['id'] . '/cancelar')) ?>"><?= csrf_field() ?><button type="submit" class="btn btn-secondary">Cancelar envío</button></form>
            <?php else: ?>
                <form method="post" action="<?= e(url('configuracion/correo/' . $c['id'] . '/reintentar')) ?>"><?= csrf_field() ?><button type="submit" class="btn btn-primary">Volver a enviar</button></form>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</section>

<section class="panel spaced">
    <div class="panel-head"><h2>Contenido</h2><span class="panel-meta">Versión de texto</span></div>
    <pre class="mail-preview"><?= e($c['cuerpo_texto']) ?></pre>
</section>
<?php App\Core\View::partial('configuracion/_fin'); ?>
