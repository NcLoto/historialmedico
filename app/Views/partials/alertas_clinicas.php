<?php
/** @var array $resumen alergias, enfermedades, medicamentos */
$alergias = $resumen['alergias'];
$hayGrave = (bool) array_filter($alergias, fn($a) => $a['severidad'] === 'grave');
?>
<section class="clinical-alerts<?= $hayGrave ? ' has-grave' : '' ?>" aria-label="Alertas clínicas">
    <div class="clinical-alerts-head"><?= icon('alert', 15) ?> Alertas clínicas</div>
    <div class="alert-rows">
        <div class="alert-row">
            <span class="k">Alergias</span>
            <span class="v">
                <?php if (!$alergias): ?>
                    <span class="muted">Sin alergias registradas</span>
                <?php else: foreach ($alergias as $a): ?>
                    <span class="tag<?= $a['severidad'] === 'grave' ? ' sev-grave' : '' ?>"><?= e($a['agente']) ?> <?= severidad($a['severidad']) ?></span>
                <?php endforeach; endif; ?>
            </span>
        </div>
        <div class="alert-row">
            <span class="k">Condiciones vigentes</span>
            <span class="v">
                <?php if (!$resumen['enfermedades']): ?>
                    <span class="muted">Sin condiciones vigentes registradas</span>
                <?php else: foreach ($resumen['enfermedades'] as $en): ?>
                    <span class="tag"><?= e($en['nombre']) ?></span>
                <?php endforeach; endif; ?>
            </span>
        </div>
        <div class="alert-row">
            <span class="k">Medicación vigente</span>
            <span class="v">
                <?php if (!$resumen['medicamentos']): ?>
                    <span class="muted">Sin medicación vigente registrada</span>
                <?php else: foreach ($resumen['medicamentos'] as $m): ?>
                    <span class="tag"><?= e($m['nombre']) ?><?= $m['dosis'] ? ' <span class="muted">' . e($m['dosis']) . '</span>' : '' ?></span>
                <?php endforeach; endif; ?>
            </span>
        </div>
    </div>
</section>
