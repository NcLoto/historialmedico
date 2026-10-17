<?php
$u = $yo;
$nombre = nombre_completo($u);
$p = $paciente;
$fotoUrl = App\Domain\Avatar::url($u);
$tabs = [
    'datos'          => ['perfil', 'Datos personales', 'user', 'Foto, nombre y contacto'],
    'seguridad'      => ['perfil/seguridad', 'Seguridad', 'shield', 'Contraseña y accesos'],
    'notificaciones' => ['perfil/notificaciones', 'Notificaciones', 'bell', 'Avisos en el sistema y por correo'],
    'apariencia'     => ['perfil/apariencia', 'Apariencia', 'eye', 'Tema claro u oscuro'],
];
$telefono = $p ? $p['telefono'] : ($medico ? $medico['telefono'] : $cuenta['telefono']);
$estadoCivil = ['soltero' => 'Soltero(a)', 'casado' => 'Casado(a)', 'unido' => 'Unido(a)', 'divorciado' => 'Divorciado(a)', 'viudo' => 'Viudo(a)'];
$v = fn($x) => ($x === null || $x === '') ? '—' : e($x);
$campo = function (string $k, string $label, ?string $valor, string $tipo = 'text', string $extra = '', string $col = 'col-6') use ($errors) { ?>
    <div class="field <?= $col ?><?= isset($errors[$k]) ? ' has-error' : '' ?>">
        <label for="<?= e($k) ?>"><?= e($label) ?></label>
        <input type="<?= e($tipo) ?>" id="<?= e($k) ?>" name="<?= e($k) ?>" value="<?= e(old($k, (string) $valor)) ?>"<?= $extra ?>>
        <?= field_error($errors, $k) ?>
    </div>
<?php };
?>
<div class="page-head"><div><h1>Mi perfil</h1></div></div>

<section class="profile-head panel" aria-label="Resumen de la cuenta">
    <?php App\Core\View::partial('partials/avatar', ['u' => $u, 'clase' => 'xl', 'alt' => 'Foto de perfil de ' . $nombre]); ?>
    <div class="profile-id">
        <h2><?= e(($medico ? 'Dr(a). ' : '') . $nombre) ?></h2>
        <p><?= e($medico ? $medico['especialidad'] : $u['rol_nombre']) ?></p>
        <p class="muted"><?= e($u['email']) ?></p>
    </div>
    <dl class="profile-meta">
        <?php if ($p): ?><div><dt>Código de paciente</dt><dd class="num"><?= e($p['codigo']) ?></dd></div><?php endif; ?>
        <?php if ($medico): ?><div><dt>Colegiado</dt><dd class="num"><?= e($medico['colegiado']) ?></dd></div><?php endif; ?>
        <div><dt>Miembro desde</dt><dd><?= e(fecha($cuenta['creado_en'])) ?></dd></div>
        <div><dt>Último acceso</dt><dd><?= e($cuenta['ultimo_acceso'] ? fecha_hora($cuenta['ultimo_acceso']) : '—') ?></dd></div>
    </dl>
</section>

<div class="settings">
    <nav class="settings-nav" aria-label="Secciones del perfil">
        <?php foreach ($tabs as $k => [$href, $label, $ic, $desc]): ?>
            <a href="<?= e(url($href)) ?>" class="<?= $k === $seccion ? 'is-active' : '' ?>"<?= $k === $seccion ? ' aria-current="page"' : '' ?>>
                <?= icon($ic, 18) ?><span><strong><?= e($label) ?></strong><small><?= e($desc) ?></small></span>
            </a>
        <?php endforeach; ?>
    </nav>
    <div class="settings-body">

<?php if ($seccion === 'datos'): ?>
    <section class="form-section" id="foto">
        <h2>Foto de perfil</h2>
        <p class="help">Se muestra en la barra superior y en el menú de su cuenta.</p>
        <div class="avatar-edit" data-upload-scope>
            <div class="avatar-box" data-preview-box>
                <?php App\Core\View::partial('partials/avatar', ['u' => $u, 'clase' => 'xl', 'alt' => 'Foto de perfil actual']); ?>
            </div>
            <div class="stack grow">
                <form method="post" action="<?= e(url('perfil/foto')) ?>" enctype="multipart/form-data" class="upload-form" data-upload
                      data-ext="<?= e(implode(',', App\Domain\Avatar::EXT)) ?>" data-max-mb="<?= App\Domain\Avatar::MB ?>" data-formatos="JPG, PNG o WEBP" data-vacio="Ninguna foto elegida">
                    <?= csrf_field() ?><input type="hidden" name="accion" value="subir">
                    <label class="btn btn-secondary file-pick">
                        <?= icon('image', 16) ?> <?= $fotoUrl ? 'Cambiar foto' : 'Elegir foto' ?>
                        <input type="file" name="foto" class="sr-only" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" required>
                    </label>
                    <span class="file-name muted" data-file-name aria-live="polite">Ninguna foto elegida</span>
                    <button type="submit" class="btn btn-primary" data-upload-btn><?= icon('upload', 16) ?> Guardar foto</button>
                    <p class="field-error" data-upload-error role="alert" hidden></p>
                </form>
                <p class="help">JPG, PNG o WEBP, hasta <?= App\Domain\Avatar::MB ?> MB. Se recorta en cuadrado y se eliminan los datos de ubicación de la foto.</p>
                <?php if ($fotoUrl): ?>
                    <form method="post" action="<?= e(url('perfil/foto')) ?>" data-confirm="Se volverán a mostrar sus iniciales." data-confirm-title="Quitar foto de perfil" data-confirm-label="Quitar">
                        <?= csrf_field() ?><input type="hidden" name="accion" value="quitar">
                        <button type="submit" class="btn btn-ghost btn-sm"><?= icon('trash', 14) ?> Quitar foto</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <form method="post" action="<?= e(url('perfil/datos')) ?>" novalidate>
        <?= csrf_field() ?>
        <section class="form-section">
            <h2>Información personal</h2>
            <div class="form-grid">
                <?php if ($editaNombre): ?>
                    <?php $campo('nombres', 'Nombres', $u['nombres'], 'text', ' maxlength="80" autocomplete="given-name" required'); ?>
                    <?php $campo('apellidos', 'Apellidos', $u['apellidos'], 'text', ' maxlength="80" autocomplete="family-name" required'); ?>
                <?php else: ?>
                    <div class="field col-12">
                        <span class="field-label">Nombre completo</span>
                        <p class="field-static"><?= e($nombre) ?></p>
                        <p class="help"><?= $p ? 'Para corregirlo, acérquese a admisión con su documento de identidad.' : 'Para corregirlo, solicítelo a la administración.' ?></p>
                    </div>
                <?php endif; ?>
                <div class="field col-6">
                    <span class="field-label">Correo de acceso</span>
                    <p class="field-static"><?= e($u['email']) ?></p>
                    <p class="help">Es su usuario para ingresar; lo cambia la administración.</p>
                </div>
                <?php $campo('telefono', 'Teléfono', $telefono, 'tel', ' maxlength="25" autocomplete="tel" inputmode="tel"'); ?>
                <?php if ($p): ?>
                    <?php $campo('direccion', 'Dirección', $p['direccion'], 'text', ' maxlength="200" autocomplete="street-address"', 'col-12'); ?>
                <?php endif; ?>
            </div>
        </section>
        <?php if ($p): ?>
            <section class="form-section">
                <h2>Contacto de emergencia</h2>
                <p class="help">Persona a la que el hospital llamará si usted no puede comunicarse.</p>
                <div class="form-grid">
                    <?php $campo('contacto_nombre', 'Nombre', $p['contacto_nombre'], 'text', ' maxlength="120"'); ?>
                    <?php $campo('contacto_parentesco', 'Parentesco', $p['contacto_parentesco'], 'text', ' maxlength="40"', 'col-3'); ?>
                    <?php $campo('contacto_telefono', 'Teléfono', $p['contacto_telefono'], 'tel', ' maxlength="25" inputmode="tel"', 'col-3'); ?>
                </div>
            </section>
        <?php endif; ?>
        <div class="form-actions"><button type="submit" class="btn btn-primary">Guardar cambios</button></div>
    </form>

    <?php if ($p): ?>
        <section class="form-section spaced">
            <h2>Datos de identidad</h2>
            <p class="help">Constan en su expediente. Para corregir alguno, acérquese a admisión con su documento de identidad.</p>
            <dl class="kv kv-2">
                <dt>Documento</dt><dd class="num"><?= e($p['documento_identidad']) ?></dd>
                <dt>Fecha de nacimiento</dt><dd><?= e(fecha($p['fecha_nacimiento'])) ?> (<?= e(edad($p['fecha_nacimiento'])) ?>)</dd>
                <dt>Sexo</dt><dd><?= $p['sexo'] === 'F' ? 'Femenino' : 'Masculino' ?></dd>
                <dt>Tipo de sangre</dt><dd><?= $v($p['tipo_sangre']) ?></dd>
                <dt>Estado civil</dt><dd><?= $v($estadoCivil[$p['estado_civil']] ?? null) ?></dd>
                <dt>Ocupación</dt><dd><?= $v($p['ocupacion']) ?></dd>
            </dl>
        </section>
    <?php elseif ($medico): ?>
        <section class="form-section spaced">
            <h2>Datos profesionales</h2>
            <p class="help">Aparecen en recetas e informes. Para modificarlos, solicítelo a la administración.</p>
            <dl class="kv kv-2">
                <dt>Especialidad</dt><dd><?= e($medico['especialidad']) ?></dd>
                <dt>Colegiado</dt><dd class="num"><?= e($medico['colegiado']) ?></dd>
                <dt>Consultorio</dt><dd><?= $v($medico['consultorio']) ?></dd>
            </dl>
        </section>
    <?php endif; ?>

<?php elseif ($seccion === 'seguridad'): ?>
    <section class="form-section">
        <h2>Cambiar contraseña</h2>
        <p class="help"><?= $ultimoCambio ? 'Último cambio: ' . e(fecha_hora($ultimoCambio)) . '.' : 'No ha cambiado la contraseña desde que se creó la cuenta.' ?></p>
        <form method="post" action="<?= e(url('perfil/clave')) ?>" class="form-grid">
            <?= csrf_field() ?>
            <input type="text" name="username" value="<?= e($u['email']) ?>" autocomplete="username" hidden>
            <div class="field col-12<?= isset($errors['password_actual']) ? ' has-error' : '' ?>">
                <label for="password_actual">Contraseña actual</label>
                <input type="password" id="password_actual" name="password_actual" autocomplete="current-password" required>
                <?= field_error($errors, 'password_actual') ?>
            </div>
            <div class="field col-6<?= isset($errors['password_nueva']) ? ' has-error' : '' ?>">
                <label for="password_nueva">Nueva contraseña</label>
                <input type="password" id="password_nueva" name="password_nueva" autocomplete="new-password" minlength="10" required>
                <p class="help">Mínimo 10 caracteres, con letras y números.</p>
                <?= field_error($errors, 'password_nueva') ?>
            </div>
            <div class="field col-6<?= isset($errors['password_confirmar']) ? ' has-error' : '' ?>">
                <label for="password_confirmar">Confirmar nueva contraseña</label>
                <input type="password" id="password_confirmar" name="password_confirmar" autocomplete="new-password" required>
                <?= field_error($errors, 'password_confirmar') ?>
            </div>
            <div class="form-actions col-12"><button type="submit" class="btn btn-primary">Guardar contraseña</button></div>
        </form>
    </section>

    <section class="form-section">
        <h2>Actividad reciente</h2>
        <p class="help">Accesos y cambios de contraseña de su cuenta. Si ve uno que no reconoce, cambie la contraseña y avise a la administración.</p>
        <?php if (!$actividad): ?>
            <p class="muted">Sin actividad registrada.</p>
        <?php else: ?>
            <div class="table-wrap">
                <table class="table table-compact stackable">
                    <thead><tr><th scope="col">Fecha</th><th scope="col">Evento</th><th scope="col">Dirección IP</th></tr></thead>
                    <tbody>
                    <?php foreach ($actividad as $a): ?>
                        <tr<?= in_array($a['accion'], ['login_fallido', 'login_bloqueado'], true) ? ' class="is-warn"' : '' ?>>
                            <td data-label="Fecha" class="num nowrap"><?= e(date('d/m/Y H:i', strtotime($a['creado_en']))) ?></td>
                            <td data-label="Evento"><?= e($eventos[$a['accion']] ?? $a['descripcion']) ?></td>
                            <td data-label="Dirección IP" class="num"><?= in_array($a['ip'], ['::1', '127.0.0.1'], true) ? 'Equipo local' : $v($a['ip']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

<?php elseif ($seccion === 'notificaciones'): ?>
    <form method="post" action="<?= e(url('perfil/notificaciones')) ?>">
        <?= csrf_field() ?>
        <section class="form-section">
            <h2>Avisos</h2>
            <p class="help">Las notificaciones aparecen en la campana de la barra superior<?= $correoActivo ? '. Las marcadas en «Correo» llegan además a ' . e($u['email']) . ', sin información médica' : '' ?>. <a href="<?= e(url('notificaciones')) ?>">Ver mis notificaciones</a></p>
            <div class="table-wrap">
                <table class="table table-compact stackable pref-table">
                    <thead><tr><th scope="col">Aviso</th><th scope="col" class="right">En el sistema</th><?php if ($correoActivo): ?><th scope="col" class="right">Correo</th><?php endif; ?></tr></thead>
                    <tbody>
                    <?php foreach ($tiposNotif as $k => [$n, $d]): $off = in_array($k, $notifApagados, true); $conCorreo = $correoActivo && in_array($k, $tiposCorreo, true); ?>
                        <tr<?= $off ? ' class="is-disabled"' : '' ?>>
                            <td data-label="Aviso"><span class="cell-title"><?= e($n) ?></span><span class="cell-sub"><?= e($off ? 'Desactivado por la administración del hospital.' : $d) ?></span></td>
                            <td data-label="En el sistema" class="right"><input type="checkbox" class="switch" name="tipos[]" value="<?= e($k) ?>" aria-label="<?= e($n) ?> en el sistema" <?= $prefsNotif[$k] ? 'checked' : '' ?><?= $off ? ' disabled' : '' ?>></td>
                            <?php if ($correoActivo): ?><td data-label="Correo" class="right"><?php if ($conCorreo && !$off): ?><input type="checkbox" class="switch" name="correos[]" value="<?= e($k) ?>" aria-label="<?= e($n) ?> por correo" <?= $prefsCorreo[$k] ? 'checked' : '' ?>><?php else: ?><span class="muted small">Solo en el sistema</span><?php endif; ?></td><?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
        <div class="form-actions"><button type="submit" class="btn btn-primary">Guardar preferencias</button></div>
    </form>

<?php else: ?>
    <form method="post" action="<?= e(url('perfil/tema')) ?>">
        <?= csrf_field() ?>
        <section class="form-section">
            <h2>Tema</h2>
            <p class="help">Se aplica solo a su cuenta, en cualquier equipo donde ingrese.</p>
            <div class="choice-grid" role="radiogroup" aria-label="Tema">
                <?php foreach (['sistema' => ['Usar el del hospital', 'Tema definido por la administración.'], 'claro' => ['Claro', 'Fondo claro.'], 'oscuro' => ['Oscuro', 'Fondo oscuro, cómodo con poca luz.']] as $k => [$n, $d]): ?>
                    <label class="choice"><input type="radio" name="tema" value="<?= e($k) ?>" <?= ($u['tema'] ?? 'sistema') === $k ? 'checked' : '' ?>><span><strong><?= e($n) ?></strong><span class="help"><?= e($d) ?></span></span></label>
                <?php endforeach; ?>
            </div>
        </section>
        <div class="form-actions"><button type="submit" class="btn btn-primary">Guardar tema</button></div>
    </form>
<?php endif; ?>

    </div>
</div>
