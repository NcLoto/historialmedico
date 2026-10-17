<?php
use App\Domain\HistorialSections;

/** @var string $key @var array $s @var array $rows @var int $pid @var bool $esPaciente */
$puedeAgregar = !$esPaciente && can('historial.crear');
$puedeEditar = !$esPaciente && can('historial.editar');
$puedeEliminar = !$esPaciente && can('historial.eliminar');
$hoy = date('Y-m-d');

$badges = function (array $r) use ($key, $hoy, $s): array {
    $b = [];
    switch ($key) {
        case 'alergias':
            $b[] = ['sev', $r['severidad']];
            if (!$r['activa']) { $b[] = ['', 'Inactiva']; }
            break;
        case 'enfermedades':
            $b[] = [['activa' => 'badge-warning', 'controlada' => 'badge-info', 'resuelta' => ''][$r['estado']], etiqueta($r['estado'])];
            if ($r['cronica']) { $b[] = ['cat', 'Crónica']; }
            break;
        case 'medicamentos':
            $b[] = $r['activo'] ? ['badge-success', 'Vigente'] : ['', 'Finalizado'];
            break;
        case 'vacunas':
            if ($r['proxima_dosis'] && $r['proxima_dosis'] < $hoy) { $b[] = ['badge-warning', 'Dosis pendiente']; }
            break;
        case 'antecedentes':
        case 'habitos':
            $b[] = ['cat', HistorialSections::display($s, 'tipo', $r['tipo'])];
            break;
        case 'familiares':
            $b[] = ['', ucfirst_mb(str_replace('_', ' ', $r['parentesco']))];
            if ($r['fallecido']) { $b[] = ['', 'Fallecido']; }
            break;
    }
    return $b;
};
$inactiveField = $s['inactive'] ?? null;
?>
<section class="panel ficha-section" id="<?= e($key) ?>">
    <div class="panel-head">
        <h2><?= icon($s['icon'], 16) ?> <?= e($s['titulo']) ?> <span class="muted">(<?= count($rows) ?>)</span></h2>
        <?php if ($puedeAgregar): ?>
            <a class="btn btn-secondary btn-sm" href="<?= e(url("pacientes/$pid/historial/$key/nuevo")) ?>"><?= icon('plus', 14) ?> Agregar</a>
        <?php endif; ?>
    </div>
    <?php if (!$rows): ?>
        <div class="empty">
            <strong><?= e($s['vacio']) ?></strong>
            <?php if ($puedeAgregar): ?><p>Agregue el primer registro de esta sección.</p><a class="btn btn-secondary btn-sm" href="<?= e(url("pacientes/$pid/historial/$key/nuevo")) ?>">Agregar <?= e($s['singular']) ?></a><?php endif; ?>
        </div>
    <?php else: foreach ($rows as $r):
        $inactivo = $inactiveField && !$r[$inactiveField]; ?>
        <article class="entry<?= $inactivo ? ' is-inactive' : '' ?>">
            <div class="entry-main">
                <div class="entry-title">
                    <?= e($r[$s['title']]) ?>
                    <?php foreach ($badges($r) as [$cls, $txt]): ?> <?= match ($cls) { 'sev' => severidad($txt), 'cat' => '<span class="cat">' . e($txt) . '</span>', default => '<span class="badge ' . $cls . '">' . e($txt) . '</span>' } ?><?php endforeach; ?>
                </div>
                <div class="entry-meta">
                    <?php foreach ($s['meta'] as $field => $label):
                        if (in_array($key, ['antecedentes', 'habitos'], true) && $field === 'tipo') { continue; }
                        if ($key === 'familiares' && in_array($field, ['parentesco', 'fallecido'], true)) { continue; }
                        $txt = HistorialSections::display($s, $field, $r[$field] ?? null);
                        if ($txt === '') { continue; } ?>
                        <span><?= $label ? e($label) . ': ' : '' ?><?= e($txt) ?></span>
                    <?php endforeach; ?>
                </div>
                <?php if (!empty($r['observaciones'])): ?><p class="entry-note pre"><?= e($r['observaciones']) ?></p><?php endif; ?>
            </div>
            <?php if ($puedeEditar || $puedeEliminar): ?>
                <div class="entry-actions">
                    <?php if ($puedeEditar): ?>
                        <a class="icon-btn" href="<?= e(url("pacientes/$pid/historial/$key/{$r['id']}/editar")) ?>" aria-label="Editar <?= e($s['singular']) ?>" title="Editar"><?= icon('edit', 16) ?></a>
                    <?php endif; ?>
                    <?php if ($puedeEliminar): ?>
                        <form method="post" action="<?= e(url("pacientes/$pid/historial/$key/{$r['id']}/eliminar")) ?>"
                              data-confirm="Se eliminará este registro del historial. Esta acción queda registrada en la auditoría y no se puede deshacer."
                              data-confirm-title="Eliminar <?= e($s['singular']) ?>" data-confirm-label="Eliminar">
                            <?= csrf_field() ?>
                            <button type="submit" class="icon-btn link-danger" aria-label="Eliminar <?= e($s['singular']) ?>" title="Eliminar"><?= icon('trash', 16) ?></button>
                        </form>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </article>
    <?php endforeach; endif; ?>
</section>
