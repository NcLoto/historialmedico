<div class="dash-page">
<?php
$prox = $datos['proximas'];
$primera = $prox[0] ?? null;
$dias = $primera ? (int) floor((strtotime(substr($primera['fecha_hora'], 0, 10)) - strtotime(date('Y-m-d'))) / 86400) : null;
$cuando = $dias === null ? '' : ($dias === 0 ? 'Hoy' : ($dias === 1 ? 'Mañana' : 'En ' . $dias . ' días'));
?>
<div class="page-head">
    <div>
        <h1><?= e(nombre_completo($paciente)) ?></h1>
        <p>Código de paciente <?= e($paciente['codigo']) ?><span class="sep"></span><?= e(edad($paciente['fecha_nacimiento'])) ?><span class="sep"></span><?php App\Core\View::partial('partials/reloj'); ?></p>
    </div>
    <div class="page-actions">
        <a class="btn btn-primary" href="<?= e(url('mi-historial')) ?>"><?= icon('heart', 16) ?> Ver mi historial médico</a>
    </div>
</div>

<?php if ($primera): ?>
<section class="panel next-up" aria-labelledby="prox-t">
    <div class="panel-head"><h2 id="prox-t">Próxima consulta</h2><span class="panel-meta"><?= e($cuando) ?></span></div>
    <div class="next-body">
        <div class="next-when">
            <span class="next-day"><?= e(fecha($primera['fecha_hora'])) ?></span>
            <span class="next-time num"><?= e(date('H:i', strtotime($primera['fecha_hora']))) ?></span>
        </div>
        <div class="next-who">
            <a class="next-name" href="<?= e(url('consultas/' . $primera['id'])) ?>"><?= e($primera['motivo']) ?></a>
            <span class="muted">Dr(a). <?= e(nombre_completo($primera)) ?><span class="sep"></span><?= e($primera['especialidad']) ?><?= $primera['consultorio'] ? '<span class="sep"></span>' . e($primera['consultorio']) : '' ?></span>
        </div>
    </div>
</section>
<?php endif; ?>

<?php App\Core\View::partial('partials/alertas_clinicas', ['resumen' => $resumen]); ?>

<section class="panel spaced" aria-label="Resumen">
    <dl class="indicadores">
        <div><dt>Consultas realizadas</dt><dd><?= (int) $datos['conteos']['consultas'] ?></dd><small><?= $datos['ultima'] ? 'Última: ' . e(fecha($datos['ultima']['fecha_hora'])) : 'Sin consultas' ?></small></div>
        <div><dt>Medicamentos vigentes</dt><dd><?= (int) $datos['conteos']['medicamentos'] ?></dd><small><a href="<?= e(url('mi-historial')) ?>">Ver historial</a></small></div>
        <div><dt>Documentos</dt><dd><?= (int) $datos['conteos']['documentos'] ?></dd><small><a href="<?= e(url('documentos')) ?>">Ver documentos</a></small></div>
        <div><dt>Vacunas pendientes</dt><dd><?= (int) $datos['conteos']['vacunas_pendientes'] ?></dd><small>Próximos 60 días</small></div>
    </dl>
</section>

<?php $vit = $datos['vitales'];
if ($vit):
    $fechas = array_map(fn($v) => date('d/m/y', strtotime($v['fecha_hora'])), $vit);
    $ult = end($vit); ?>
<section class="panel spaced" aria-labelledby="vit-t">
    <div class="panel-head"><h2 id="vit-t">Evolución de mis signos vitales</h2><span class="panel-meta">Últimos <?= count($vit) ?> controles</span></div>
    <div class="vitals-trend">
        <div class="vt-item">
            <span class="vt-label">Presión arterial</span>
            <span class="vt-value num"><?= $ult['presion_sistolica'] ? (int) $ult['presion_sistolica'] . '/' . (int) $ult['presion_diastolica'] : '—' ?> <small>mmHg</small></span>
            <?php if (count($vit) > 1) { App\Core\View::partial('partials/graficos/linea', ['titulo' => 'Presión arterial por control', 'etiquetas' => $fechas, 'vacio' => 'Sin registros de presión', 'ajustar' => true, 'ancho' => 380,
                'series' => [['nombre' => 'Sistólica', 'datos' => array_map(fn($v) => (int) $v['presion_sistolica'], $vit)], ['nombre' => 'Diastólica', 'datos' => array_map(fn($v) => (int) $v['presion_diastolica'], $vit)]]]); } ?>
        </div>
        <div class="vt-item">
            <span class="vt-label">Frecuencia cardíaca</span>
            <span class="vt-value num"><?= $ult['frecuencia_cardiaca'] ? (int) $ult['frecuencia_cardiaca'] : '—' ?> <small>lpm</small></span>
            <?php if (count($vit) > 1) { App\Core\View::partial('partials/graficos/linea', ['titulo' => 'Frecuencia cardíaca por control', 'etiquetas' => $fechas, 'vacio' => 'Sin registros', 'ajustar' => true, 'ancho' => 380,
                'series' => [['nombre' => 'Frecuencia cardíaca', 'datos' => array_map(fn($v) => (int) $v['frecuencia_cardiaca'], $vit)]]]); } ?>
        </div>
        <div class="vt-item">
            <span class="vt-label">Peso</span>
            <span class="vt-value num"><?= $ult['peso_kg'] !== null ? e((string) ($ult['peso_kg'] + 0)) : '—' ?> <small>kg</small></span>
            <?php if (count($vit) > 1) { App\Core\View::partial('partials/graficos/linea', ['titulo' => 'Peso por control', 'etiquetas' => $fechas, 'vacio' => 'Sin registros de peso', 'ajustar' => true, 'ancho' => 380,
                'series' => [['nombre' => 'Peso (kg)', 'datos' => array_map(fn($v) => (int) round((float) $v['peso_kg']), $vit)]]]); } ?>
        </div>
    </div>
    <?php if (count($vit) < 2): ?><p class="help panel-body">La evolución se mostrará a partir de su segundo control con signos vitales.</p><?php endif; ?>
</section>
<?php endif; ?>

<div class="cols-2 spaced">
    <section class="panel">
        <div class="panel-head"><h2>Mis medicamentos</h2></div>
        <?php if (!$resumen['medicamentos']): ?>
            <div class="empty"><?= icon('pill', 28) ?><strong>Sin medicamentos vigentes</strong><p>Los medicamentos que le indique su médico aparecerán aquí.</p></div>
        <?php else: ?>
            <ul class="list">
                <?php foreach ($resumen['medicamentos'] as $m): ?>
                    <li><div><span class="cell-title"><?= e($m['nombre']) ?></span><span class="cell-sub"><?= e(implode(', ', array_filter([$m['dosis'], $m['frecuencia']]))) ?></span></div></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>

    <section class="panel">
        <div class="panel-head"><h2>Documentos recientes</h2><a class="small" href="<?= e(url('documentos')) ?>">Ver todos</a></div>
        <?php if (!$datos['documentos']): ?>
            <div class="empty"><?= icon('folder', 28) ?><strong>Aún no hay documentos</strong><p>Los exámenes e informes que cargue el equipo médico aparecerán aquí.</p></div>
        <?php else: ?>
            <ul class="list">
                <?php foreach ($datos['documentos'] as $d): ?>
                    <li>
                        <div><a class="cell-title" href="<?= e(url('documentos/' . $d['id'])) ?>"><?= e($d['titulo']) ?></a><span class="cell-sub"><?= e($d['categoria']) ?></span></div>
                        <span class="when"><?= e(fecha($d['fecha_documento'] ?: $d['cargado_en'])) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
</div>

<div class="cols-2 spaced">
    <section class="panel">
        <div class="panel-head"><h2>Próximas consultas</h2><a class="small" href="<?= e(url('consultas')) ?>">Ver todas</a></div>
        <?php if (!$prox): ?>
            <div class="empty"><?= icon('calendar', 28) ?><strong>No tiene consultas programadas</strong><p>Cuando el hospital programe una cita, aparecerá aquí.</p></div>
        <?php else: ?>
            <ul class="list">
                <?php foreach ($prox as $c): ?>
                    <li>
                        <div>
                            <a class="cell-title" href="<?= e(url('consultas/' . $c['id'])) ?>"><?= e($c['motivo']) ?></a>
                            <span class="cell-sub">Dr(a). <?= e(nombre_completo($c)) ?><span class="sep"></span><?= e($c['especialidad']) ?></span>
                        </div>
                        <span class="when"><?= e(fecha_hora($c['fecha_hora'])) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>

    <section class="panel">
        <div class="panel-head"><h2>Última consulta</h2></div>
        <?php if (!$datos['ultima']): ?>
            <div class="empty"><strong>Sin consultas realizadas</strong></div>
        <?php else: $u = $datos['ultima']; ?>
            <div class="panel-body stack">
                <div>
                    <a class="cell-title" href="<?= e(url('consultas/' . $u['id'])) ?>"><?= e($u['motivo']) ?></a>
                    <span class="cell-sub num"><?= e(fecha_hora($u['fecha_hora'])) ?><span class="sep"></span>Dr(a). <?= e(nombre_completo($u)) ?></span>
                </div>
                <?php if ($u['plan']): ?><div><h3>Plan indicado</h3><p class="pre"><?= e($u['plan']) ?></p></div><?php endif; ?>
            </div>
        <?php endif; ?>
    </section>
</div>

<?php if ($datos['vacunas']): ?>
    <section class="panel spaced">
        <div class="panel-head"><h2>Vacunas pendientes</h2></div>
        <ul class="list">
            <?php foreach ($datos['vacunas'] as $v): ?>
                <li>
                    <div><span class="cell-title"><?= e($v['vacuna']) ?></span><span class="cell-sub"><?= e($v['dosis'] ?? '') ?></span></div>
                    <span class="when"><?= $v['proxima_dosis'] < date('Y-m-d') ? '<span class="badge badge-warning">Vencida</span> ' : '' ?><?= e(fecha($v['proxima_dosis'])) ?></span>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>
<?php endif; ?>
</div>
