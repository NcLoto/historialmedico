<?php
$editable = can('configuracion.editar');
$dis = $editable ? '' : ' disabled';
$v = fn(string $k, string $d = '') => old($k, $cfg[$k] ?? $d);
$rolNombres = ['paciente' => 'Pacientes', 'medico' => 'Médicos', 'administrador' => 'Administradores'];
App\Core\View::partial('configuracion/_tabs', ['seccion' => $seccion]);
?>
<div class="stat-strip">
    <div><span class="num"><?= (int) $stats['total'] ?></span><small>Notificaciones (30 días)</small></div>
    <div><span class="num"><?= (int) $stats['sin_leer'] ?></span><small>Sin leer</small></div>
    <div><span class="num"><?= (int) $stats['usuarios'] ?></span><small>Usuarios notificados</small></div>
</div>

<form method="post" action="<?= e(url('configuracion/notificaciones')) ?>" novalidate>
    <?= csrf_field() ?>
    <section class="form-section">
        <h2>Notificaciones del sistema</h2>
        <div class="switch-list">
            <label class="switch-row"><input type="checkbox" class="switch" name="notif_activas" value="1" <?= ($cfg['notif_activas'] ?? '1') === '1' ? 'checked' : '' ?><?= $dis ?>>
                <span><strong>Notificaciones habilitadas</strong><span class="help">Si se desactivan, ningún evento genera notificaciones nuevas. Las existentes se conservan.</span></span></label>
        </div>
        <div class="form-grid spaced">
            <div class="field col-4<?= isset($errors['notif_recordatorio_horas']) ? ' has-error' : '' ?>">
                <label for="notif_recordatorio_horas">Recordar consultas con (horas)</label>
                <input type="number" id="notif_recordatorio_horas" name="notif_recordatorio_horas" value="<?= e($v('notif_recordatorio_horas', '48')) ?>" min="1" max="168" required<?= $dis ?>>
                <p class="help">De anticipación. Entre 1 y 168 (una semana).</p>
                <?= field_error($errors, 'notif_recordatorio_horas') ?>
            </div>
            <div class="field col-4<?= isset($errors['notif_retencion_dias']) ? ' has-error' : '' ?>">
                <label for="notif_retencion_dias">Conservar las leídas (días)</label>
                <input type="number" id="notif_retencion_dias" name="notif_retencion_dias" value="<?= e($v('notif_retencion_dias', '90')) ?>" min="7" max="365" required<?= $dis ?>>
                <p class="help">Después se eliminan automáticamente. Entre 7 y 365.</p>
                <?= field_error($errors, 'notif_retencion_dias') ?>
            </div>
        </div>
    </section>

    <section class="form-section">
        <h2>Eventos que notifican</h2>
        <p class="help">Desactive un evento para todo el hospital. Cada usuario puede además silenciar los que no le interesen desde su perfil.</p>
        <div class="table-wrap">
            <table class="table stackable">
                <thead><tr><th scope="col">Evento</th><th scope="col">Lo reciben</th><th scope="col" class="right">Activo</th></tr></thead>
                <tbody>
                <?php foreach ($tipos as $k => [$nombre, $desc, $roles]): ?>
                    <tr>
                        <td data-label="Evento"><span class="cell-title"><?= e($nombre) ?></span><span class="cell-sub"><?= e($desc) ?></span></td>
                        <td data-label="Lo reciben"><?= e(implode(', ', array_map(fn($r) => $rolNombres[$r], $roles))) ?></td>
                        <td class="right"><input type="checkbox" class="switch" name="tipos_activos[]" value="<?= e($k) ?>" <?= in_array($k, $apagados, true) ? '' : 'checked' ?> aria-label="Activar <?= e($nombre) ?>"<?= $dis ?>></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
    <?php if ($editable): ?><div class="form-actions"><button type="submit" class="btn btn-primary">Guardar notificaciones</button></div><?php endif; ?>
</form>

<?php if ($editable): ?>
<section class="form-section spaced" id="aviso">
    <h2>Enviar aviso institucional</h2>
    <p class="help">Un comunicado para todos los usuarios activos de los roles elegidos: mantenimientos, cambios de horario o recordatorios generales. Queda registrado en la auditoría.</p>
    <form method="post" action="<?= e(url('notificaciones/aviso')) ?>" novalidate data-confirm="El aviso se enviará de inmediato a todos los usuarios activos de los roles seleccionados." data-confirm-title="Enviar aviso" data-confirm-label="Enviar">
        <?= csrf_field() ?>
        <div class="form-grid">
            <div class="field col-8<?= isset($errors['titulo']) ? ' has-error' : '' ?>">
                <label for="aviso_titulo">Título</label>
                <input type="text" id="aviso_titulo" name="titulo" value="<?= e(old('titulo')) ?>" maxlength="160" required placeholder="Ej.: Mantenimiento programado del sistema">
                <?= field_error($errors, 'titulo') ?>
            </div>
            <div class="field col-4">
                <label for="aviso_nivel">Tipo</label>
                <select id="aviso_nivel" name="nivel"><?= options(['info' => 'Información', 'aviso' => 'Aviso', 'critico' => 'Importante'], old('nivel', 'info'), null) ?></select>
            </div>
            <div class="field col-12<?= isset($errors['mensaje']) ? ' has-error' : '' ?>">
                <label for="aviso_mensaje">Mensaje <span class="opt">(opcional)</span></label>
                <input type="text" id="aviso_mensaje" name="mensaje" value="<?= e(old('mensaje')) ?>" maxlength="255">
                <?= field_error($errors, 'mensaje') ?>
            </div>
            <div class="field col-12<?= isset($errors['roles']) ? ' has-error' : '' ?>">
                <span class="label"><strong>Destinatarios</strong></span>
                <div class="row">
                    <?php foreach ($rolNombres as $k => $n): ?>
                        <label class="check"><input type="checkbox" name="roles[]" value="<?= e($k) ?>"><span><?= e($n) ?></span></label>
                    <?php endforeach; ?>
                </div>
                <?= field_error($errors, 'roles') ?>
            </div>
        </div>
        <div class="form-actions"><button type="submit" class="btn btn-primary"><?= icon('megaphone', 16) ?> Enviar aviso</button></div>
    </form>
</section>
<?php endif; ?>
<?php App\Core\View::partial('configuracion/_fin'); ?>
