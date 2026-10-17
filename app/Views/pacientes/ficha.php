<?php
use App\Core\Auth;

$esPaciente = Auth::isPatient();
$id = (int) $p['id'];
$nav = [['personal', 'Información personal', null]];
if ($clinico) {
    foreach ($secciones as $k => $s) {
        $nav[] = [$k, $s['titulo'], count($historial[$k])];
    }
}
if (isset($consultas)) { $nav[] = ['consultas', 'Consultas', $consultas_total]; }
if (isset($documentos)) { $nav[] = ['documentos', 'Documentos', $documentos_total]; }
if (isset($cambios)) { $nav[] = ['cambios', 'Historial de cambios', null]; }
?>
<?php if (!$esPaciente): ?>
<div class="breadcrumb"><a href="<?= e(url('pacientes')) ?>">Pacientes</a> <?= icon('chevron', 12) ?> <span><?= e(nombre_completo($p)) ?></span></div>
<?php endif; ?>

<header class="patient-head">
    <div class="patient-head-top">
        <div>
            <h1 class="patient-name"><?= e(nombre_completo($p)) ?> <?php if (!$p['activo']): ?><span class="badge">Inactivo</span><?php endif; ?></h1>
            <div class="patient-id">
                <span class="num"><?= e($p['codigo']) ?></span>
                <span><?= e(edad($p['fecha_nacimiento'])) ?><span class="sep"></span><?= $p['sexo'] === 'F' ? 'Femenino' : 'Masculino' ?></span>
                <span>Nacimiento <?= e(fecha($p['fecha_nacimiento'])) ?></span>
                <?php if ($p['tipo_sangre']): ?><span>Sangre <strong><?= e($p['tipo_sangre']) ?></strong></span><?php endif; ?>
                <span>DPI <span class="num"><?= e($p['documento_identidad']) ?></span></span>
            </div>
        </div>
        <div class="page-actions">
            <?php if ($clinico) { App\Core\View::partial('partials/menu_documentos', ['id' => 'docs-paciente', 'items' => [
                [url("pacientes/$id/exportar.pdf"), 'file', 'Resumen clínico', 'Historial completo según la configuración de documentos'],
                [url("pacientes/$id/vacunas.pdf"), 'syringe', 'Carné de vacunación', 'Vacunas aplicadas y dosis pendientes'],
            ]]); } ?>
            <?php if (!$esPaciente && can('consultas.crear') && $p['activo']): ?>
                <a class="btn btn-primary" href="<?= e(url("pacientes/$id/consultas/nueva")) ?>"><?= icon('stethoscope', 16) ?> Nueva consulta</a>
            <?php endif; ?>
            <?php if (!$esPaciente && can('documentos.crear') && $p['activo']): ?>
                <a class="btn btn-secondary" href="<?= e(url("pacientes/$id/documentos/nuevo")) ?>"><?= icon('upload', 16) ?> Adjuntar documento</a>
            <?php endif; ?>
            <?php if (!$esPaciente && can('pacientes.editar')): ?>
                <a class="btn btn-secondary" href="<?= e(url("pacientes/$id/editar")) ?>"><?= icon('edit', 16) ?> Editar datos</a>
            <?php endif; ?>
        </div>
    </div>
    <?php if ($clinico && !empty($vitales)): $v = $vitales; ?>
        <div class="vitals" aria-label="Últimos signos vitales">
            <div class="vital"><span class="k">Último control</span><span class="v"><?= e(fecha($v['fecha_hora'])) ?></span></div>
            <?php if ($v['presion_sistolica']): ?><div class="vital"><span class="k">Presión arterial</span><span class="v"><?= (int) $v['presion_sistolica'] ?>/<?= (int) $v['presion_diastolica'] ?> mmHg</span></div><?php endif; ?>
            <?php if ($v['frecuencia_cardiaca']): ?><div class="vital"><span class="k">Frec. cardíaca</span><span class="v"><?= (int) $v['frecuencia_cardiaca'] ?> lpm</span></div><?php endif; ?>
            <?php if ($v['saturacion_o2']): ?><div class="vital"><span class="k">Sat. O₂</span><span class="v"><?= (int) $v['saturacion_o2'] ?> %</span></div><?php endif; ?>
            <?php if ($v['peso_kg']): ?><div class="vital"><span class="k">Peso</span><span class="v"><?= e(rtrim(rtrim((string) $v['peso_kg'], '0'), '.')) ?> kg</span></div><?php endif; ?>
            <?php if ($v['talla_cm']): ?><div class="vital"><span class="k">Talla</span><span class="v"><?= e(rtrim(rtrim((string) $v['talla_cm'], '0'), '.')) ?> cm</span></div><?php endif; ?>
            <?php if ($v['peso_kg'] && $v['talla_cm']): ?><div class="vital"><span class="k">IMC</span><span class="v"><?= number_format((float) $v['peso_kg'] / (((float) $v['talla_cm'] / 100) ** 2), 1) ?></span></div><?php endif; ?>
        </div>
    <?php endif; ?>
</header>

<?php if ($clinico): App\Core\View::partial('partials/alertas_clinicas', ['resumen' => $resumen]); endif; ?>

<nav class="section-nav" aria-label="Secciones de la ficha">
    <?php foreach ($nav as $i => [$anchor, $label, $count]): ?>
        <a href="#<?= e($anchor) ?>"<?= $i === 0 ? ' class="is-active"' : '' ?>><?= e($label) ?><?php if ($count !== null): ?> <span class="count"><?= (int) $count ?></span><?php endif; ?></a>
    <?php endforeach; ?>
</nav>

<section class="panel ficha-section" id="personal">
    <div class="panel-head">
        <h2>Información personal</h2>
        <?php if (!$esPaciente && can('pacientes.eliminar')): ?>
            <form method="post" action="<?= e(url("pacientes/$id/estado")) ?>"
                  data-confirm="<?= $p['activo'] ? 'El paciente dejará de aparecer en las búsquedas activas. Su historial se conserva y puede reactivarlo cuando quiera.' : 'El paciente volverá a aparecer en las búsquedas activas.' ?>"
                  data-confirm-title="<?= $p['activo'] ? 'Inactivar paciente' : 'Reactivar paciente' ?>" data-confirm-label="<?= $p['activo'] ? 'Inactivar' : 'Reactivar' ?>">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-ghost btn-sm"><?= $p['activo'] ? 'Inactivar paciente' : 'Reactivar paciente' ?></button>
            </form>
        <?php endif; ?>
    </div>
    <?php App\Core\View::partial('pacientes/_datos', ['p' => $p]); ?>
</section>

<?php if (!$clinico): ?>
    <div class="alert alert-info"><?= icon('lock', 18) ?><span>Vista administrativa. El contenido clínico de la ficha (historial, consultas y contenido de los documentos) solo está disponible para el personal médico y el propio paciente.</span></div>
<?php else: foreach ($secciones as $key => $s):
    App\Core\View::partial('pacientes/_seccion', ['key' => $key, 's' => $s, 'rows' => $historial[$key], 'pid' => $id, 'esPaciente' => $esPaciente]);
endforeach; endif; ?>

<?php if (isset($consultas)): ?>
<section class="panel ficha-section" id="consultas">
    <div class="panel-head">
        <h2><?= icon('stethoscope', 16) ?> Consultas <span class="muted">(<?= (int) $consultas_total ?>)</span></h2>
        <div class="row">
            <?php if ($consultas_total > count($consultas)): ?><a class="small" href="<?= e(url('consultas?paciente=' . $id)) ?>">Ver todas</a><?php endif; ?>
            <?php if (!$esPaciente && can('consultas.crear') && $p['activo']): ?><a class="btn btn-secondary btn-sm" href="<?= e(url("pacientes/$id/consultas/nueva")) ?>"><?= icon('plus', 14) ?> Nueva consulta</a><?php endif; ?>
        </div>
    </div>
    <?php if (!$consultas): ?>
        <div class="empty"><strong>Sin consultas registradas</strong><p>Las consultas médicas de este paciente aparecerán aquí.</p></div>
    <?php else: ?>
        <ol class="timeline">
            <?php foreach ($consultas as $c): ?>
                <li>
                    <span class="date"><?= e(fecha($c['fecha_hora'])) ?><br><?= e(date('H:i', strtotime($c['fecha_hora']))) ?></span>
                    <div>
                        <a class="cell-title" href="<?= e(url('consultas/' . $c['id'])) ?>"><?= e($c['motivo']) ?></a>
                        <span class="cell-sub">Dr(a). <?= e(nombre_completo($c)) ?><?= $c['diagnostico'] ? '<span class="sep"></span>' . e($c['diagnostico']) : '' ?></span>
                        <?php App\Core\View::partial('partials/estado_consulta', ['estado' => $c['estado']]); ?>
                    </div>
                </li>
            <?php endforeach; ?>
        </ol>
    <?php endif; ?>
</section>
<?php endif; ?>

<?php if (isset($documentos)): ?>
<section class="panel ficha-section" id="documentos">
    <div class="panel-head">
        <h2><?= icon('folder', 16) ?> Documentos <span class="muted">(<?= (int) $documentos_total ?>)</span></h2>
        <div class="row">
            <?php if ($documentos_total > count($documentos)): ?><a class="small" href="<?= e(url('documentos?paciente=' . $id)) ?>">Ver todos</a><?php endif; ?>
            <?php if (!$esPaciente && can('documentos.crear') && $p['activo']): ?><a class="btn btn-secondary btn-sm" href="<?= e(url("pacientes/$id/documentos/nuevo")) ?>"><?= icon('upload', 14) ?> Adjuntar</a><?php endif; ?>
        </div>
    </div>
    <?php if (!$documentos): ?>
        <div class="empty"><strong>Sin documentos</strong><p>Exámenes de laboratorio, imágenes e informes adjuntos aparecerán aquí.</p></div>
    <?php else: ?>
        <ul class="list">
            <?php foreach ($documentos as $d): ?>
                <li>
                    <div>
                        <a class="cell-title" href="<?= e(url('documentos/' . $d['id'])) ?>"><?= e($d['titulo']) ?></a>
                        <span class="cell-sub"><?= e($d['categoria']) ?></span>
                    </div>
                    <span class="when"><?= e(fecha($d['fecha_documento'] ?: $d['cargado_en'])) ?></span>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>
<?php endif; ?>

<?php if (isset($cambios)): ?>
<section class="panel ficha-section" id="cambios">
    <div class="panel-head"><h2><?= icon('history', 16) ?> Historial de cambios</h2><span class="small muted">Registra quién modificó la ficha, no los valores</span></div>
    <?php if (!$cambios): ?>
        <div class="empty"><strong>Sin modificaciones registradas</strong></div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table stackable change-log">
                <thead><tr><th scope="col">Fecha</th><th scope="col">Usuario</th><th scope="col">Acción</th><th scope="col">Detalle</th></tr></thead>
                <tbody>
                <?php foreach ($cambios as $c): ?>
                    <tr>
                        <td data-label="Fecha" class="nowrap num"><?= e(fecha_hora($c['creado_en'])) ?></td>
                        <td data-label="Usuario"><?= e($c['usuario_nombre']) ?></td>
                        <td data-label="Acción"><?= e(etiqueta($c['accion'])) ?></td>
                        <td data-label="Detalle"><?= e($c['descripcion']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
<?php endif; ?>
