<?php
$pid = (int) $c['paciente_id'];
$id = (int) $c['id'];
$tiene = fn($k) => $c[$k] !== null && $c[$k] !== '';
$num = fn($k) => rtrim(rtrim((string) $c[$k], '0'), '.');
?>
<div class="page-head">
    <div>
        <div class="breadcrumb">
            <?php if (!$esPaciente): ?><a href="<?= e(url('pacientes/' . $pid)) ?>"><?= e($c['papellidos'] . ', ' . $c['pnombres']) ?></a> <?= icon('chevron', 12) ?><?php endif; ?>
            <a href="<?= e(url('consultas' . (!$esPaciente ? '?paciente=' . $pid : ''))) ?>">Consultas</a> <?= icon('chevron', 12) ?> <span><?= e(fecha($c['fecha_hora'])) ?></span>
        </div>
        <h1><?= e($c['motivo']) ?></h1>
        <p><?= e(fecha_hora($c['fecha_hora'])) ?><span class="sep"></span>Dr(a). <?= e(nombre_completo($c, 'mnombres', 'mapellidos')) ?></p>
    </div>
    <div class="page-actions">
        <?php
        $tieneMeds = (bool) array_filter($tratamientos, fn($t) => $t['tipo'] === 'medicamento');
        $docs = [[url("consultas/$id/exportar.pdf"), 'file', 'Informe de consulta', 'Evaluación, diagnósticos, tratamiento y plan']];
        if ($tieneMeds) { $docs[] = [url("consultas/$id/receta.pdf"), 'receipt', 'Receta médica', 'Medicamentos indicados, con firma']; }
        if ($c['estado'] === 'realizada') { $docs[] = [url("consultas/$id/constancia.pdf"), 'verified', 'Constancia de atención', 'Sin información diagnóstica']; }
        App\Core\View::partial('partials/menu_documentos', ['id' => 'docs-consulta', 'items' => $docs]);
        ?>
        <?php if (!$esPaciente && can('consultas.editar')): ?>
            <a class="btn btn-secondary" href="<?= e(url("consultas/$id/editar")) ?>"><?= icon('edit', 16) ?> <?= $c['estado'] === 'programada' ? 'Registrar atención' : 'Editar' ?></a>
            <?php if ($c['estado'] === 'programada'): ?>
                <form method="post" action="<?= e(url("consultas/$id/estado")) ?>" data-confirm="La consulta quedará marcada como cancelada." data-confirm-title="Cancelar consulta" data-confirm-label="Cancelar consulta">
                    <?= csrf_field() ?><button type="submit" class="btn btn-danger">Cancelar consulta</button>
                </form>
            <?php endif; ?>
        <?php endif; ?>
        <?php if (!$esPaciente && can('documentos.crear') && $c['paciente_activo']): ?>
            <a class="btn btn-secondary" href="<?= e(url("pacientes/$pid/documentos/nuevo?consulta=$id")) ?>"><?= icon('upload', 16) ?> Adjuntar examen</a>
        <?php endif; ?>
    </div>
</div>

<div class="cols-3">
    <div class="stack">
        <section class="panel">
            <div class="panel-head"><h2>Evaluación</h2><?php App\Core\View::partial('partials/estado_consulta', ['estado' => $c['estado']]); ?></div>
            <div class="panel-body stack">
                <?php if ($tiene('enfermedad_actual')): ?><div><h3>Enfermedad actual</h3><p class="pre"><?= e($c['enfermedad_actual']) ?></p></div><?php endif; ?>
                <?php if ($c['presion_sistolica'] || $c['frecuencia_cardiaca'] || $c['temperatura'] || $c['saturacion_o2'] || $c['peso_kg'] || $c['talla_cm']): ?>
                    <div>
                        <h3>Signos vitales</h3>
                        <div class="vitals-grid spaced">
                            <?php if ($c['presion_sistolica']): ?><div class="vital"><span class="k">Presión arterial</span><span class="v"><?= (int) $c['presion_sistolica'] ?>/<?= (int) $c['presion_diastolica'] ?> mmHg</span></div><?php endif; ?>
                            <?php if ($c['frecuencia_cardiaca']): ?><div class="vital"><span class="k">Frec. cardíaca</span><span class="v"><?= (int) $c['frecuencia_cardiaca'] ?> lpm</span></div><?php endif; ?>
                            <?php if ($c['temperatura']): ?><div class="vital"><span class="k">Temperatura</span><span class="v"><?= e($num('temperatura')) ?> °C</span></div><?php endif; ?>
                            <?php if ($c['saturacion_o2']): ?><div class="vital"><span class="k">Sat. O₂</span><span class="v"><?= (int) $c['saturacion_o2'] ?> %</span></div><?php endif; ?>
                            <?php if ($c['peso_kg']): ?><div class="vital"><span class="k">Peso</span><span class="v"><?= e($num('peso_kg')) ?> kg</span></div><?php endif; ?>
                            <?php if ($c['talla_cm']): ?><div class="vital"><span class="k">Talla</span><span class="v"><?= e($num('talla_cm')) ?> cm</span></div><?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
                <?php if ($tiene('examen_fisico')): ?><div><h3>Examen físico</h3><p class="pre"><?= e($c['examen_fisico']) ?></p></div><?php endif; ?>
                <?php if (!$tiene('enfermedad_actual') && !$tiene('examen_fisico') && !$c['presion_sistolica'] && $c['estado'] === 'programada'): ?>
                    <p class="muted">Consulta programada. La evaluación se registrará durante la atención.</p>
                <?php endif; ?>
            </div>
        </section>

        <?php if ($diagnosticos): ?>
        <section class="panel">
            <div class="panel-head"><h2>Diagnósticos</h2></div>
            <ul class="list">
                <?php foreach ($diagnosticos as $d): ?>
                    <li><div><span class="cell-title"><?= e($d['descripcion']) ?></span><?php if ($d['codigo_cie10']): ?><span class="cell-sub">CIE-10 <?= e($d['codigo_cie10']) ?></span><?php endif; ?></div>
                        <span class="cat<?= $d['tipo'] === 'principal' ? ' is-strong' : '' ?>"><?= e(etiqueta($d['tipo'])) ?></span></li>
                <?php endforeach; ?>
            </ul>
        </section>
        <?php endif; ?>

        <?php if ($tratamientos): ?>
        <section class="panel">
            <div class="panel-head"><h2>Tratamiento e indicaciones</h2></div>
            <ul class="list">
                <?php foreach ($tratamientos as $t): ?>
                    <li>
                        <div>
                            <span class="cell-title"><?= e($t['descripcion']) ?></span>
                            <span class="cell-sub"><?= e(implode(', ', array_filter([$t['dosis'], $t['frecuencia'], $t['duracion'] ? 'Duración: ' . $t['duracion'] : null]))) ?></span>
                        </div>
                        <span class="cat"><?= e(etiqueta($t['tipo'])) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>
        <?php endif; ?>

        <?php if ($tiene('plan')): ?>
        <section class="panel"><div class="panel-head"><h2>Plan</h2></div><div class="panel-body"><p class="pre"><?= e($c['plan']) ?></p></div></section>
        <?php endif; ?>

        <?php if (!$esPaciente && $tiene('notas_internas')): ?>
        <section class="panel"><div class="panel-head"><h2>Notas internas</h2><span class="small muted">No visibles para el paciente</span></div><div class="panel-body"><p class="pre"><?= e($c['notas_internas']) ?></p></div></section>
        <?php endif; ?>
    </div>

    <aside class="stack">
        <section class="panel">
            <div class="panel-head"><h2>Datos</h2></div>
            <dl class="kv panel-body">
                <dt>Paciente</dt><dd><a href="<?= e($esPaciente ? url('mi-historial') : url('pacientes/' . $c['paciente_id'])) ?>"><?= e(nombre_completo($c, 'pnombres', 'papellidos')) ?></a><span class="cell-sub"><?= e($c['codigo']) ?></span></dd>
                <dt>Edad</dt><dd><?= e(edad($c['fecha_nacimiento'])) ?></dd>
                <dt>Médico</dt><dd><?= e(nombre_completo($c, 'mnombres', 'mapellidos')) ?></dd>
                <dt>Especialidad</dt><dd><?= e($c['especialidad']) ?></dd>
                <?php if ($c['consultorio']): ?><dt>Consultorio</dt><dd><?= e($c['consultorio']) ?></dd><?php endif; ?>
                <dt>Registrada</dt><dd><?= e(fecha_hora($c['creado_en'])) ?></dd>
            </dl>
        </section>
        <?php if (can('documentos.ver')): ?>
        <section class="panel">
            <div class="panel-head"><h2>Documentos adjuntos</h2></div>
            <?php if (!$documentos): ?>
                <div class="empty"><strong>Sin documentos</strong><p>No hay exámenes ni imágenes asociados a esta consulta.</p></div>
            <?php else: ?>
                <ul class="list">
                    <?php foreach ($documentos as $d): ?>
                        <li><div><a class="cell-title" href="<?= e(url('documentos/' . $d['id'])) ?>"><?= e($d['titulo']) ?></a><span class="cell-sub"><?= e($d['categoria']) ?></span></div><span class="cat"><?= e(strtoupper($d['extension'])) ?></span></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
        <?php endif; ?>
    </aside>
</div>
