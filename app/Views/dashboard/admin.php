<div class="dash-page">
<?php
use App\Core\View;

$c = $datos['conteos'];
$s = $datos['seguridad'];
$k = $datos['kpi'];
$n = fn(int|float $v, int $dec = 0): string => number_format($v, $dec, ',', '.');
$pct = fn(int $a, int $b): string => $b > 0 ? number_format($a / $b * 100, 1, ',', '.') . ' %' : '—';
[$canc, $cancAnt] = $k['canceladas'];
$tasaCanc = $pct($canc, $canc + $k['consultas'][0]);

// Solo lo que requiere acción; redacción breve
$pendientes = array_values(array_filter([
    (int) $s['bloqueadas'] ? [cantidad((int) $s['bloqueadas'], 'cuenta bloqueada', 'cuentas bloqueadas'), url('usuarios'), 'Usuarios'] : null,
    (int) $s['fallidos'] >= 5 ? [cantidad((int) $s['fallidos'], 'intento de acceso fallido', 'intentos de acceso fallidos') . ' (24 h)', url('auditoria?accion=login_fallido'), 'Auditoría'] : null,
    (int) $s['denegados'] ? [cantidad((int) $s['denegados'], 'acceso denegado', 'accesos denegados') . ' (24 h)', url('auditoria?accion=acceso_denegado'), 'Auditoría'] : null,
    $k['atrasadas'] ? [cantidad($k['atrasadas'], 'consulta vencida', 'consultas vencidas') . ' sin registrar', url('consultas?estado=programada'), 'Consultas'] : null,
    $datos['pendientes_acceso'] ? [cantidad($datos['pendientes_acceso'], 'cuenta', 'cuentas') . ' sin primer ingreso', url('usuarios'), 'Usuarios'] : null,
    $datos['pacientes_sin_cuenta'] ? [cantidad($datos['pacientes_sin_cuenta'], 'paciente', 'pacientes') . ' sin acceso al portal', url('usuarios/nuevo'), 'Crear cuenta'] : null,
]));
$totalEsp = array_sum(array_column($k['especialidades'], 1));
?>
<div class="page-head">
    <div>
        <h1>Dashboard</h1>
        <p><?php View::partial('partials/reloj'); ?></p>
    </div>
    <div class="page-actions">
        <?php if (can('usuarios.crear')): ?><a class="btn btn-secondary" href="<?= e(url('usuarios/nuevo')) ?>"><?= icon('plus', 16) ?> Nuevo usuario</a><?php endif; ?>
        <?php if (can('pacientes.crear')): ?><a class="btn btn-primary" href="<?= e(url('pacientes/nuevo')) ?>"><?= icon('plus', 16) ?> Registrar paciente</a><?php endif; ?>
    </div>
</div>

<?php
$acum = $k['acumulado'];
$crec = $acum[0] > 0 ? ($acum[11] - $acum[0]) / $acum[0] * 100 : null;
$varCons = $k['consultas'][1] >= 10 ? ($k['consultas'][0] - $k['consultas'][1]) / $k['consultas'][1] * 100 : null;
$portalPct = $k['portal'][2] ? $k['portal'][1] / $k['portal'][2] * 100 : 0;
$segEsp = array_slice($k['especialidades'], 0, 4);
if (count($k['especialidades']) > 4) { $segEsp[] = ['Otras', array_sum(array_column(array_slice($k['especialidades'], 4), 1))]; }
?>
<div class="mcard-grid">
    <?php View::partial('partials/graficos/tarjeta', ['etiqueta' => 'Pacientes activos', 'valor' => (int) $c['pacientes'], 'tendencia' => ['pct' => $crec, 'sentido' => 'sube'], 'detalle' => 'en 12 meses', 'serie' => $acum, 'href' => can('pacientes.ver') ? url('pacientes') : null]); ?>
    <?php View::partial('partials/graficos/tarjeta', ['etiqueta' => 'Con acceso al portal', 'valor' => $k['portal'][1], 'resalte' => number_format($portalPct, 0) . ' %', 'detalle' => 'de los pacientes activos', 'barra' => $portalPct]); ?>
    <?php View::partial('partials/graficos/tarjeta', ['etiqueta' => 'Consultas (30 días)', 'valor' => $k['consultas'][0], 'tendencia' => $varCons !== null ? ['pct' => $varCons, 'sentido' => 'sube'] : null, 'detalle' => 'frente a los 30 días previos', 'serie' => $k['consultas_serie'], 'suavizar' => 7]); ?>
    <?php View::partial('partials/graficos/tarjeta', ['etiqueta' => 'Exámenes cargados (30 días)', 'valor' => $k['documentos'][0], 'detalle' => $k['emitidos'][0] . ' documentos emitidos en el mismo periodo', 'serie' => $k['documentos_serie'], 'suavizar' => 7, 'href' => can('documentos.ver') ? url('documentos') : null]); ?>
</div>

<div class="dash-grid">
    <section class="panel span-8 chart-panel" aria-labelledby="mes-t">
        <div class="chart-head">
            <h2 id="mes-t">Consultas realizadas</h2>
            <p>Últimos 12 meses completos. <?= e(ucfirst(['', 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'][(int) date('n')])) ?> en curso: <?= (int) $k['mes_actual'] ?> consultas</p>
        </div>
        <?php View::partial('partials/graficos/area', ['titulo' => 'Consultas realizadas por mes, últimos 12 meses', 'etiquetas' => $k['meses'], 'datos' => $k['mensual_consultas'], 'vacio' => 'Sin consultas en los últimos 12 meses']); ?>
        <?php
        $serie = array_map('intval', $k['mensual_consultas']);
        $total12 = array_sum($serie);
        $pico = $serie ? array_search(max($serie), $serie, true) : null;
        if ($total12 > 0): ?>
        <dl class="chart-summary">
            <div><dt>Total, 12 meses</dt><dd><?= $n($total12) ?></dd></div>
            <div><dt>Promedio mensual</dt><dd><?= $n((int) round($total12 / max(1, count($serie)))) ?></dd></div>
            <div><dt>Mes con más consultas</dt><dd><?= e($k['meses'][$pico]) ?> (<?= $n($serie[$pico]) ?>)</dd></div>
        </dl>
        <?php endif; ?>
    </section>

    <section class="panel span-4 chart-panel" aria-labelledby="esp-t">
        <div class="chart-head">
            <h2 id="esp-t">Consultas por especialidad</h2>
            <p>Últimos 12 meses</p>
        </div>
        <?php View::partial('partials/graficos/dona', ['titulo' => 'Consultas por especialidad, últimos 12 meses', 'centro' => 'consultas', 'vacio' => 'Sin consultas', 'segmentos' => $segEsp]); ?>
    </section>
    <section class="panel span-8" aria-labelledby="med-t">
        <div class="panel-head"><h2 id="med-t">Médicos</h2><?php if (can('medicos.ver')): ?><a class="small" href="<?= e(url('medicos')) ?>">Ver todos</a><?php endif; ?></div>
        <div class="table-wrap">
            <table class="table table-compact stackable">
                <thead><tr><th scope="col">Médico</th><th scope="col">Especialidad</th><th scope="col" class="right">Atendidas (30 d)</th><th scope="col" class="right">Completadas (90 d)</th><th scope="col" class="right">Hoy</th><th scope="col" class="right">En agenda</th></tr></thead>
                <tbody>
                <?php foreach ($k['por_medico'] as $m): ?>
                    <tr>
                        <td data-label="Médico">Dr(a). <?= e(nombre_completo($m)) ?></td>
                        <td data-label="Especialidad"><?= e($m['especialidad']) ?></td>
                        <td data-label="Atendidas (30 d)" class="right num"><?= $n((int) $m['r30']) ?></td>
                        <td data-label="Completadas (90 d)" class="right num"><?= e($pct((int) $m['r90'], (int) $m['r90'] + (int) $m['c90'])) ?></td>
                        <td data-label="Hoy" class="right num"><?= $n((int) $m['hoy']) ?></td>
                        <td data-label="En agenda" class="right num"><?= $n((int) $m['agenda']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="panel span-4" aria-labelledby="pen-t">
        <div class="panel-head"><h2 id="pen-t">Pendientes</h2><?php if ($pendientes): ?><span class="count"><?= count($pendientes) ?></span><?php endif; ?></div>
        <?php if (!$pendientes): ?>
            <p class="panel-body muted">Sin pendientes.</p>
        <?php else: ?>
            <ul class="list pend-list">
                <?php foreach ($pendientes as [$texto, $href, $accion]): ?>
                    <li><span><?= e($texto) ?></span><a class="small" href="<?= e($href) ?>"><?= e($accion) ?></a></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>

    <section class="panel span-5" aria-labelledby="pac-t">
        <div class="panel-head"><h2 id="pac-t">Pacientes activos</h2><span class="panel-meta">Por edad y sexo</span></div>
        <div class="table-wrap">
            <table class="table table-compact">
                <thead><tr><th scope="col">Edad</th><th scope="col" class="right">Mujeres</th><th scope="col" class="right">Hombres</th><th scope="col" class="right">Total</th></tr></thead>
                <tbody>
                <?php $tf = $tm = 0; foreach ($k['edad_sexo'] as $g): $tf += (int) $g['f']; $tm += (int) $g['m']; ?>
                    <tr><td><?= e($g['grupo']) ?></td><td class="right num"><?= $n((int) $g['f']) ?></td><td class="right num"><?= $n((int) $g['m']) ?></td><td class="right num"><?= $n((int) $g['t']) ?></td></tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot><tr><th scope="row">Total</th><td class="right num"><?= $n($tf) ?></td><td class="right num"><?= $n($tm) ?></td><td class="right num"><?= $n($tf + $tm) ?></td></tr></tfoot>
            </table>
        </div>
        <dl class="kv panel-body kv-compact">
            <dt>Con acceso al portal</dt><dd class="num"><?= $n($k['portal'][1]) ?> (<?= e($pct($k['portal'][1], $k['portal'][2])) ?>)</dd>
            <dt>Usuarios activos</dt><dd class="num"><?= (int) $c['usuarios'] ?></dd>
            <dt>Médicos activos</dt><dd class="num"><?= (int) $c['medicos'] ?></dd>
        </dl>
    </section>

    <section class="panel span-7" aria-labelledby="rec-t">
        <div class="panel-head"><h2 id="rec-t">Actividad reciente</h2><?php if (can('auditoria.ver')): ?><a class="small" href="<?= e(url('auditoria')) ?>">Auditoría</a><?php endif; ?></div>
        <div class="table-wrap">
            <table class="table table-compact stackable">
                <thead><tr><th scope="col">Hora</th><th scope="col">Usuario</th><th scope="col">Acción</th></tr></thead>
                <tbody>
                <?php foreach (array_slice($datos['actividad'], 0, 8) as $a): ?>
                    <tr>
                        <td data-label="Hora" class="num nowrap"><?= e(date('d/m H:i', strtotime($a['creado_en']))) ?></td>
                        <td data-label="Usuario"><?= e($a['usuario_nombre']) ?></td>
                        <td data-label="Acción"><?= e($a['descripcion']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>
</div>
