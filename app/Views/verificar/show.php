<?php
$enmascarar = function (?string $nombres, ?string $apellidos): string {
    $partes = array_filter(preg_split('/\s+/', trim(($nombres ?? '') . ' ' . ($apellidos ?? ''))));
    return implode(' ', array_map(fn($p) => mb_substr($p, 0, 1) . '.', $partes));
};
?>
<?php if ($d): ?>
    <div class="verify verify-ok" role="status">
        <?= icon('verified', 28) ?>
        <div><h1>Documento auténtico</h1><p>Emitido por el sistema de historial médico del hospital.</p></div>
    </div>
    <dl class="kv verify-data">
        <dt>Código</dt><dd class="num"><?= e($codigo) ?></dd>
        <dt>Tipo</dt><dd><?= e($tipo) ?></dd>
        <dt>Fecha de emisión</dt><dd class="num"><?= e(fecha_hora($d['creado_en'])) ?></dd>
        <dt>Emitido por</dt><dd><?= e($d['emitido_por_nombre']) ?></dd>
        <?php if ($d['pcodigo']): ?><dt>Paciente</dt><dd><?= e($enmascarar($d['nombres'], $d['apellidos'])) ?><span class="sep"></span><?= e(substr($d['pcodigo'], 0, 4) . '•••' . substr($d['pcodigo'], -2)) ?></dd><?php endif; ?>
    </dl>
    <p class="help">Por privacidad no se muestra el contenido del documento. Compare estos datos con el impreso que tiene en sus manos.</p>
<?php else: ?>
    <div class="verify verify-bad" role="alert">
        <?= icon('alert', 28) ?>
        <div><h1>Código no encontrado</h1><p>No existe un documento emitido con el código <strong class="num"><?= e($codigo) ?></strong>.</p></div>
    </div>
    <p class="help">Revise que el código coincida con el pie del documento. Si persiste, el documento podría no ser auténtico: comuníquese con el hospital.</p>
<?php endif; ?>
<p class="auth-links"><a href="<?= e(url('verificar')) ?>">Verificar otro documento</a></p>
