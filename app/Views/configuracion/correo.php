<?php
$editable = can('configuracion.editar');
$dis = $editable ? '' : ' disabled';
$m = $estadoMail;
$modos = ['smtp' => 'SMTP', 'archivo' => 'Archivo (sin envío)', 'desactivado' => 'Desactivado'];
$tono = ['pendiente' => '', 'enviado' => 'badge-success', 'fallido' => 'badge-critical', 'cancelado' => ''];
$rolNombres = ['paciente' => 'Pacientes', 'medico' => 'Médicos', 'administrador' => 'Administradores'];
App\Core\View::partial('configuracion/_tabs', ['seccion' => $seccion]);
?>
<?php if ($m['driver'] === 'archivo'): ?>
    <div class="alert alert-info"><?= icon('info', 18) ?><span>Modo archivo: los correos se generan y quedan guardados en <code>storage/mail</code>, pero no salen a internet. Para enviarlos, configure el servidor SMTP en <code>.env</code> (MAIL_DRIVER=smtp).</span></div>
<?php elseif ($m['driver'] === 'desactivado'): ?>
    <div class="alert alert-warning"><?= icon('alert', 18) ?><span>El envío de correo está desactivado en <code>.env</code> (MAIL_DRIVER=desactivado).</span></div>
<?php elseif (!$m['listo']): ?>
    <div class="alert alert-error"><?= icon('alert', 18) ?><span>Falta configurar el servidor o el remitente en <code>.env</code> (MAIL_HOST, MAIL_FROM).</span></div>
<?php endif; ?>
<?php if ($tareaAtrasada): ?>
    <div class="alert alert-warning"><?= icon('clock', 18) ?><span>La tarea programada <?= $ultimaTarea ? 'no se ejecuta desde el ' . e(fecha_hora($ultimaTarea)) : 'aún no se ha ejecutado' ?>. Sin ella los correos esperan en la cola y los recordatorios solo se generan cuando el usuario entra al sistema. Use «Procesar ahora» o registre <code>bin/tareas.bat</code> en el Programador de tareas de Windows.</span></div>
<?php endif; ?>

<dl class="indicadores panel">
    <div><dt>Pendientes</dt><dd><?= (int) $stats['pendientes'] ?></dd><small>En la cola de salida</small></div>
    <div><dt>Enviados (24 h)</dt><dd><?= (int) $stats['enviados_24h'] ?></dd><small>Límite: <?= (int) ($cfg['correo_limite_hora'] ?? 300) ?> por hora</small></div>
    <div><dt>Fallidos</dt><dd><?= (int) $stats['fallidos'] ?></dd><small>Tras 4 intentos</small></div>
    <div><dt>Última ejecución</dt><dd class="dd-small"><?= $ultimaTarea ? e(date('d/m H:i', strtotime($ultimaTarea))) : '—' ?></dd><small>Tarea programada</small></div>
</dl>

<section class="form-section spaced">
    <h2>Servidor de correo</h2>
    <p class="help">Se configura en el archivo <code>.env</code> del servidor; la contraseña nunca se muestra ni se guarda en la base de datos.</p>
    <dl class="kv">
        <dt>Modo</dt><dd><?= e($modos[$m['driver']]) ?></dd>
        <?php if ($m['driver'] === 'smtp'): ?>
            <dt>Servidor</dt><dd><?= e($m['host'] ?: '—') ?>:<?= (int) $m['puerto'] ?> (<?= e(strtoupper($m['cifrado'])) ?>)</dd>
            <dt>Usuario</dt><dd><?= e($m['usuario'] ?: '—') ?>, contraseña <?= $m['clave'] ? 'configurada' : 'sin configurar' ?></dd>
        <?php endif; ?>
        <dt>Remitente</dt><dd><?= e($m['nombre']) ?> &lt;<?= e($m['remitente']) ?>&gt;</dd>
    </dl>
    <?php if ($editable): ?>
        <form method="post" action="<?= e(url('configuracion/correo/prueba')) ?>" class="row spaced" novalidate>
            <?= csrf_field() ?>
            <div class="field grow"><label for="para">Enviar correo de prueba a</label><input type="email" id="para" name="para" placeholder="<?= e(App\Core\Auth::user()['email']) ?>" autocomplete="email"></div>
            <button type="submit" class="btn btn-secondary"><?= icon('mail', 16) ?> Enviar prueba</button>
        </form>
    <?php endif; ?>
</section>

<form method="post" action="<?= e(url('configuracion/correo')) ?>" novalidate>
    <?= csrf_field() ?>
    <section class="form-section">
        <h2>Avisos por correo</h2>
        <div class="switch-list">
            <label class="switch-row"><input type="checkbox" class="switch" name="correo_activo" value="1" <?= ($cfg['correo_activo'] ?? '1') === '1' ? 'checked' : '' ?><?= $dis ?>>
                <span><strong>Enviar notificaciones también por correo</strong><span class="help">Cada usuario puede desactivar los correos que no quiera desde su perfil o desde el enlace del propio correo.</span></span></label>
        </div>
        <p class="help spaced">Por privacidad, los correos no incluyen información clínica (diagnósticos, motivos, títulos de documentos ni nombres de pacientes en correos a médicos): avisan y enlazan al sistema.</p>
        <div class="table-wrap">
            <table class="table stackable">
                <thead><tr><th scope="col">Aviso</th><th scope="col">Lo reciben</th><th scope="col" class="right">Por correo</th></tr></thead>
                <tbody>
                <?php foreach ($tipos as $k => [$nombre, $desc, $roles]): ?>
                    <tr>
                        <td data-label="Aviso"><span class="cell-title"><?= e($nombre) ?></span><span class="cell-sub">Asunto: <?= e(str_replace('{titulo}', 'el título del aviso', App\Domain\Correo::PLANTILLAS[$k][0] ?? '')) ?></span></td>
                        <td data-label="Lo reciben"><?= e(implode(', ', array_map(fn($r) => $rolNombres[$r], $roles))) ?><?= in_array($k, App\Domain\Correo::PARA_SIN_CUENTA, true) ? '<span class="cell-sub">También pacientes sin cuenta que lo autorizaron</span>' : '' ?></td>
                        <td class="right"><input type="checkbox" class="switch" name="correo_tipos[]" value="<?= e($k) ?>" <?= in_array($k, $tiposCorreo, true) ? 'checked' : '' ?> aria-label="Enviar por correo: <?= e($nombre) ?>"<?= $dis ?>></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="form-grid spaced">
            <div class="field col-4<?= isset($errors['correo_limite_hora']) ? ' has-error' : '' ?>">
                <label for="correo_limite_hora">Máximo de correos por hora</label>
                <input type="number" id="correo_limite_hora" name="correo_limite_hora" value="<?= e(old('correo_limite_hora', $cfg['correo_limite_hora'] ?? '300')) ?>" min="10" max="5000" required<?= $dis ?>>
                <p class="help">Respeta los límites del proveedor (p. ej. Gmail: 500 al día).</p>
                <?= field_error($errors, 'correo_limite_hora') ?>
            </div>
        </div>
    </section>
    <?php if ($editable): ?><div class="form-actions"><button type="submit" class="btn btn-primary">Guardar</button></div><?php endif; ?>
</form>

<section class="panel spaced" aria-labelledby="bandeja-t">
    <div class="panel-head">
        <h2 id="bandeja-t">Bandeja de salida</h2>
        <?php if ($editable): ?>
            <form method="post" action="<?= e(url('configuracion/correo/procesar')) ?>"><?= csrf_field() ?><button type="submit" class="btn btn-secondary btn-sm">Procesar ahora</button></form>
        <?php endif; ?>
    </div>
    <nav class="segmented panel-body" aria-label="Filtrar por estado">
        <a href="<?= e(url('configuracion/correo')) ?>"<?= $estado === '' ? ' class="is-active" aria-current="page"' : '' ?>>Todos</a>
        <?php foreach ($estados as $k => $n): ?><a href="<?= e(url('configuracion/correo?estado=' . $k)) ?>"<?= $estado === $k ? ' class="is-active" aria-current="page"' : '' ?>><?= e($n) ?></a><?php endforeach; ?>
    </nav>
    <?php if (!$filas): ?>
        <p class="panel-body muted">No hay correos<?= $estado ? ' ' . e(mb_strtolower($estados[$estado])) : '' ?>.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table table-compact stackable">
                <thead><tr><th scope="col">Fecha</th><th scope="col">Destinatario</th><th scope="col">Asunto</th><th scope="col">Estado</th><th scope="col"><span class="sr-only">Ver</span></th></tr></thead>
                <tbody>
                <?php foreach ($filas as $c): ?>
                    <tr>
                        <td data-label="Fecha" class="num nowrap"><?= e(date('d/m/Y H:i', strtotime($c['enviado_en'] ?? $c['creado_en']))) ?></td>
                        <td data-label="Destinatario"><span class="cell-title"><?= e($c['nombre'] ?: $c['destinatario']) ?></span><span class="cell-sub"><?= e($c['destinatario']) ?></span></td>
                        <td data-label="Asunto"><?= e($c['asunto']) ?><?php if ($c['ultimo_error']): ?><span class="cell-sub"><?= e(mb_substr($c['ultimo_error'], 0, 90)) ?></span><?php endif; ?></td>
                        <td data-label="Estado" class="nowrap"><span class="badge <?= $tono[$c['estado']] ?>"><?= e(rtrim($estados[$c['estado']], 's')) ?></span><?php if ($c['intentos'] > 1): ?><span class="cell-sub"><?= (int) $c['intentos'] ?> intentos</span><?php endif; ?></td>
                        <td class="col-actions"><a class="btn btn-ghost btn-sm" href="<?= e(url('configuracion/correo/' . $c['id'])) ?>">Ver</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php App\Core\View::partial('partials/pagination', ['pager' => $pager, 'query' => ['estado' => $estado], 'path' => 'configuracion/correo']); ?>
    <?php endif; ?>
</section>

<?php App\Core\View::partial('configuracion/_fin'); ?>
