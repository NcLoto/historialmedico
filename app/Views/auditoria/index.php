<?php
$tono = fn(string $a) => match (true) {
    in_array($a, ['login_fallido', 'login_bloqueado', 'acceso_denegado'], true) => 'badge-warning',
    in_array($a, ['eliminar', 'permisos'], true) => 'badge-critical',
    in_array($a, ['crear', 'cargar'], true) => 'badge-success',
    in_array($a, ['exportar', 'editar', 'estado', 'cambio_clave', 'recuperacion'], true) => 'badge-info',
    default => '',
};
?>
<div class="page-head">
    <div><h1>Auditoría</h1></div>
    <div class="page-actions"><?php App\Core\View::partial('partials/menu_exportar', ['id' => 'exp-auditoria', 'base' => 'auditoria/exportar', 'query' => ['q' => $q, 'accion' => $accion, 'modulo' => $modulo, 'desde' => $desde, 'hasta' => $hasta]]); ?></div>
</div>

<section class="panel">
    <form class="filters" method="get" action="<?= e(url('auditoria')) ?>">
        <div class="field grow"><label for="q">Buscar</label><input type="search" id="q" name="q" value="<?= e($q) ?>" placeholder="Usuario, descripción o código de paciente"></div>
        <div class="field"><label for="accion">Acción</label><select id="accion" name="accion"><?= options($acciones, $accion, 'Todas') ?></select></div>
        <div class="field"><label for="modulo">Módulo</label><select id="modulo" name="modulo"><?= options($modulos, $modulo, 'Todos') ?></select></div>
        <div class="field"><label for="desde">Desde</label><input type="date" id="desde" name="desde" value="<?= e($desde) ?>"></div>
        <div class="field"><label for="hasta">Hasta</label><input type="date" id="hasta" name="hasta" value="<?= e($hasta) ?>"></div>
        <button class="btn btn-secondary" type="submit">Filtrar</button>
        <?php if ($q !== '' || $accion !== '' || $modulo !== '' || $desde !== '' || $hasta !== ''): ?><a class="btn btn-ghost" href="<?= e(url('auditoria')) ?>">Limpiar</a><?php endif; ?>
    </form>
    <?php if (!$rows): ?>
        <div class="empty"><?= icon('clipboard', 28) ?><strong>Sin registros</strong><p>No hay eventos de auditoría con estos filtros.</p></div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table stackable">
                <thead><tr><th scope="col">Fecha y hora</th><th scope="col">Usuario</th><th scope="col">Acción</th><th scope="col">Módulo</th><th scope="col">Descripción</th><th scope="col">IP</th></tr></thead>
                <tbody>
                <?php foreach ($rows as $a):
                    $det = $a['detalle'] ? json_decode($a['detalle'], true) : null; ?>
                    <tr>
                        <td data-label="Fecha" class="num nowrap"><?= e(fecha_hora($a['creado_en'])) ?></td>
                        <td data-label="Usuario"><span class="cell-title"><?= e($a['usuario_nombre']) ?></span><?php if ($a['rol']): ?><span class="cell-sub"><?= e(etiqueta($a['rol'])) ?></span><?php endif; ?></td>
                        <td data-label="Acción"><span class="badge <?= $tono($a['accion']) ?>"><?= e(accion_label($a['accion'])) ?></span></td>
                        <td data-label="Módulo"><?= e(modulo_label($a['modulo'])) ?></td>
                        <td data-label="Descripción">
                            <?= e($a['descripcion']) ?>
                            <?php if ($a['paciente_codigo']): ?><span class="cell-sub">Paciente <?= e($a['paciente_codigo']) ?></span><?php endif; ?>
                            <?php if (!empty($det['agregados']) || !empty($det['quitados'])): ?>
                                <span class="detail">
                                    <?php if (!empty($det['agregados'])): ?>Agregados: <?php foreach ($det['agregados'] as $x): ?><code><?= e($x) ?></code> <?php endforeach; ?><?php endif; ?>
                                    <?php if (!empty($det['quitados'])): ?><br>Quitados: <?php foreach ($det['quitados'] as $x): ?><code><?= e($x) ?></code> <?php endforeach; ?><?php endif; ?>
                                </span>
                            <?php elseif (is_array($det) && !empty($det['cambios'])): ?>
                                <span class="detail"><?php foreach ($det['cambios'] as $campo => $c): ?><code><?= e($campo) ?></code>: <?= e($c['antes'] ?? '—') ?> → <?= e($c['despues'] ?? '—') ?> <?php endforeach; ?></span>
                            <?php endif; ?>
                        </td>
                        <td data-label="IP" class="num nowrap small"><?= e($a['ip'] ?: '—') ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php App\Core\View::partial('partials/pagination', ['pager' => $pager, 'query' => ['q' => $q, 'accion' => $accion, 'modulo' => $modulo, 'desde' => $desde, 'hasta' => $hasta], 'path' => 'auditoria']); ?>
    <?php endif; ?>
</section>
