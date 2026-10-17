<?php
/**
 * Línea de tiempo de la actividad (bitácora): punto por evento, hace cuánto, descripción, quién y dónde.
 * @var array $filas  filas de auditoría (accion, modulo, descripcion, creado_en, usuario_nombre?)
 * @var bool  $mostrarUsuario
 */
$hace = function (string $f): string {
    $s = time() - strtotime($f);
    return match (true) {
        $s < 60 => 'Hace un momento',
        $s < 3600 => 'Hace ' . intdiv($s, 60) . ' min',
        $s < 86400 => 'Hace ' . intdiv($s, 3600) . ' h',
        $s < 172800 => 'Ayer',
        default => 'Hace ' . intdiv($s, 86400) . ' días',
    };
};
$ico = ['autenticacion' => 'key', 'pacientes' => 'user', 'consultas' => 'stethoscope', 'documentos' => 'file', 'historial' => 'heart',
    'usuarios' => 'users', 'configuracion' => 'settings', 'auditoria' => 'clipboard', 'reportes' => 'chart', 'medicos' => 'stethoscope'];
if (!$filas): ?>
    <div class="empty"><strong>Sin actividad registrada</strong></div>
<?php return; endif; ?>
<ol class="activity-tl">
    <?php foreach ($filas as $a): $pdf = str_contains($a['descripcion'], 'PDF'); ?>
        <li class="tl-item<?= in_array($a['accion'], ['login_fallido', 'acceso_denegado', 'login_bloqueado'], true) ? ' is-alert' : '' ?>">
            <span class="tl-dot" aria-hidden="true"><?= icon($ico[$a['modulo']] ?? 'circle', 13) ?></span>
            <div class="tl-body">
                <div class="tl-head"><strong><?= e($a['descripcion']) ?></strong><time class="tl-time" datetime="<?= e(date('c', strtotime($a['creado_en']))) ?>" title="<?= e(fecha_hora($a['creado_en'])) ?>"><?= e($hace($a['creado_en'])) ?></time></div>
                <span class="tl-meta"><?= !empty($mostrarUsuario) ? e($a['usuario_nombre']) . '<span class="sep"></span>' : '' ?><?= e(modulo_label($a['modulo'])) ?><span class="sep"></span><?= e(accion_label($a['accion'])) ?></span>
                <?php if ($pdf): ?><span class="tl-file"><?= icon('file', 13) ?> Documento PDF emitido</span><?php endif; ?>
            </div>
        </li>
    <?php endforeach; ?>
</ol>
