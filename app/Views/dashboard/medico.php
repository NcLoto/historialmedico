<div class="dash-page">
<?php
use App\Core\View;

$c = $datos['conteos'];
$k = $datos['kpi'];
$n = fn(int $v): string => number_format($v, 0, ',', '.');
$ahora = date('Y-m-d H:i:s');
$hoy = date('Y-m-d');
$semanas = array_map(fn($i) => date('d/m', strtotime('-' . (83 - 7 * $i) . ' day')), array_keys($k['semanas']));
$proximas = array_values(array_filter($datos['pendientes'], fn($p) => substr($p['fecha_hora'], 0, 10) > $hoy));
[$canc] = $k['canceladas'];
?>
<div class="page-head">
    <div>
        <h1>Dr(a). <?= e(nombre_completo($medico)) ?></h1>
        <p><?= e($medico['especialidad']) ?><?= $medico['consultorio'] ? ', ' . e($medico['consultorio']) : '' ?><span class="sep"></span><?php View::partial('partials/reloj'); ?></p>
    </div>
    <div class="page-actions">
        <a class="btn btn-primary" href="<?= e(url('pacientes')) ?>"><?= icon('search', 16) ?> Buscar paciente</a>
    </div>
</div>

<section class="panel" aria-labelledby="hoy-t">
    <div class="panel-head"><h2 id="hoy-t">Agenda de hoy</h2><span class="panel-meta"><?= e(cantidad(count($k['hoy']), 'consulta', 'consultas')) ?></span></div>
    <?php if (!$k['hoy']): ?>
        <p class="panel-body muted">Sin consultas programadas para hoy.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table table-compact stackable">
                <thead><tr><th scope="col">Hora</th><th scope="col">Paciente</th><th scope="col">Motivo</th><th scope="col">Estado</th><th scope="col"><span class="sr-only">Acciones</span></th></tr></thead>
                <tbody>
                <?php foreach ($k['hoy'] as $p):
                    $hecha = $p['estado'] === 'realizada';
                    $tarde = !$hecha && $p['fecha_hora'] < $ahora; ?>
                    <tr>
                        <td data-label="Hora" class="num nowrap"><strong><?= e(date('H:i', strtotime($p['fecha_hora']))) ?></strong></td>
                        <td data-label="Paciente">
                            <a class="cell-title" href="<?= e(url('pacientes/' . $p['paciente_id'])) ?>"><?= e(nombre_completo($p)) ?></a>
                            <span class="cell-sub"><?= e($p['codigo']) ?>, <?= e(edad($p['fecha_nacimiento'])) ?><?php if ($p['alergias_graves']): ?><span class="sep"></span><span class="sev-grave">Alergia: <?= e($p['alergias_graves']) ?></span><?php endif; ?></span>
                        </td>
                        <td data-label="Motivo"><?= e($p['motivo']) ?></td>
                        <td data-label="Estado" class="nowrap"><?= $hecha ? '<span class="badge badge-success">Atendida</span>' : ($tarde ? '<span class="badge badge-warning">En espera</span>' : '<span class="badge">Programada</span>') ?></td>
                        <td class="col-actions"><?php if (!$hecha && can('consultas.editar')): ?><a class="btn btn-secondary btn-sm" href="<?= e(url('consultas/' . $p['id'] . '/editar')) ?>">Registrar</a><?php else: ?><a class="btn btn-ghost btn-sm" href="<?= e(url('consultas/' . $p['id'])) ?>">Ver</a><?php endif; ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<?php
$varCons = $k['consultas'][1] >= 10 ? ($k['consultas'][0] - $k['consultas'][1]) / $k['consultas'][1] * 100 : null;
$varPac = $k['pacientes'][1] >= 10 ? ($k['pacientes'][0] - $k['pacientes'][1]) / $k['pacientes'][1] * 100 : null;
?>
<div class="mcard-grid spaced">
    <?php View::partial('partials/graficos/tarjeta', ['etiqueta' => 'Consultas atendidas (30 días)', 'valor' => $k['consultas'][0], 'tendencia' => $varCons !== null ? ['pct' => $varCons, 'sentido' => 'sube'] : null, 'detalle' => 'frente a los 30 días previos', 'serie' => $k['consultas_serie'], 'suavizar' => 7]); ?>
    <?php View::partial('partials/graficos/tarjeta', ['etiqueta' => 'Pacientes atendidos (30 días)', 'valor' => $k['pacientes'][0], 'tendencia' => $varPac !== null ? ['pct' => $varPac, 'sentido' => 'sube'] : null, 'detalle' => 'frente a los 30 días previos', 'serie' => $k['semanas']]); ?>
    <?php View::partial('partials/graficos/tarjeta', ['etiqueta' => 'Completadas (90 días)', 'valor' => $k['cumplimiento'][0] === null ? '—' : number_format($k['cumplimiento'][0], 1, ',', '.') . ' %', 'resalte' => (string) $canc, 'detalle' => 'canceladas en los últimos 30 días', 'barra' => $k['cumplimiento'][0] ?? 0]); ?>
    <?php View::partial('partials/graficos/tarjeta', ['etiqueta' => 'En agenda', 'valor' => (int) $c['pendientes'], 'resalte' => (string) (int) $datos['atrasadas'], 'detalle' => (int) $datos['atrasadas'] === 1 ? 'consulta atrasada' : 'consultas atrasadas', 'href' => url('consultas?estado=programada')]); ?>
</div>
<div class="dash-grid">
    <section class="panel span-8 chart-panel" aria-labelledby="sem-t">
        <div class="chart-head">
            <h2 id="sem-t">Consultas atendidas</h2>
            <p>Por semana, últimas 12 semanas (fecha de inicio de cada semana)</p>
        </div>
        <?php View::partial('partials/graficos/area', ['titulo' => 'Consultas atendidas por semana, últimas 12 semanas', 'etiquetas' => $semanas, 'datos' => $k['semanas'], 'vacio' => 'Sin consultas en las últimas 12 semanas']); ?>
    </section>
    <section class="panel span-4" aria-labelledby="pro-t">
        <div class="panel-head"><h2 id="pro-t">Próximas consultas</h2><a class="small" href="<?= e(url('consultas?estado=programada')) ?>">Ver agenda</a></div>
        <?php if (!$proximas): ?>
            <p class="panel-body muted">Sin consultas en los próximos días.</p>
        <?php else: ?>
            <div class="table-wrap">
                <table class="table table-compact">
                    <tbody>
                    <?php foreach (array_slice($proximas, 0, 5) as $p): ?>
                        <tr>
                            <td class="num nowrap"><?= e(date('d/m H:i', strtotime($p['fecha_hora']))) ?></td>
                            <td><a href="<?= e(url('consultas/' . $p['id'])) ?>"><?= e(nombre_completo($p)) ?></a><span class="cell-sub"><?= e($p['motivo']) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <?php if ($datos['alertas']): ?>
    <section class="panel span-12" aria-labelledby="ale-t">
        <div class="panel-head"><h2 id="ale-t">Alergias graves en la agenda</h2><span class="panel-meta">Próximos 14 días</span></div>
        <div class="table-wrap">
            <table class="table table-compact">
                <thead><tr><th scope="col">Consulta</th><th scope="col">Paciente</th><th scope="col">Alergia</th><th scope="col">Reacción</th></tr></thead>
                <tbody>
                <?php foreach ($datos['alertas'] as $a): ?>
                    <tr><td class="num nowrap"><?= e(date('d/m H:i', strtotime($a['fecha_hora']))) ?></td><td><a href="<?= e(url('pacientes/' . $a['paciente_id'])) ?>"><?= e(nombre_completo($a)) ?></a></td><td class="sev-grave"><?= e($a['agente']) ?></td><td><?= e($a['reaccion'] ?? '') ?></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
    <?php endif; ?>

    <section class="panel span-7" aria-labelledby="dx-t">
        <div class="panel-head"><h2 id="dx-t">Diagnósticos más frecuentes</h2><span class="panel-meta">12 meses</span></div>
        <?php if (!$k['diagnosticos']): ?>
            <p class="panel-body muted">Sin diagnósticos registrados.</p>
        <?php else: ?>
            <div class="table-wrap">
                <table class="table table-compact">
                    <thead><tr><th scope="col">CIE-10</th><th scope="col">Diagnóstico</th><th scope="col" class="right">Casos</th></tr></thead>
                    <tbody>
                    <?php foreach ($k['diagnosticos'] as [$dx, $v, $cie]): ?>
                        <tr><td class="num nowrap"><?= e($cie ?? '—') ?></td><td><?= e($dx) ?></td><td class="right num"><?= $n($v) ?></td></tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <section class="panel span-5" aria-labelledby="pr-t">
        <div class="panel-head"><h2 id="pr-t">Pacientes recientes</h2></div>
        <?php if (!$datos['recientes']): ?>
            <p class="panel-body muted">Sin pacientes atendidos.</p>
        <?php else: ?>
            <div class="table-wrap">
                <table class="table table-compact">
                    <thead><tr><th scope="col">Paciente</th><th scope="col" class="right">Última consulta</th></tr></thead>
                    <tbody>
                    <?php foreach ($datos['recientes'] as $p): ?>
                        <tr><td><a href="<?= e(url('pacientes/' . $p['id'])) ?>"><?= e(nombre_completo($p)) ?></a><span class="cell-sub"><?= e($p['codigo']) ?>, <?= e(edad($p['fecha_nacimiento'])) ?></span></td><td class="right num nowrap"><?= e(fecha($p['ultima'])) ?></td></tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</div>
</div>
