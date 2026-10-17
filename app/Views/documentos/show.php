<?php
$esImagen = str_starts_with($d['tipo_mime'], 'image/');
$archivo = url('documentos/' . $d['id'] . '/archivo');
?>
<div class="page-head">
    <div>
        <div class="breadcrumb"><a href="<?= e(url('documentos')) ?>">Documentos</a> <?= icon('chevron', 12) ?> <span><?= e($d['titulo']) ?></span></div>
        <h1><?= e($d['titulo']) ?></h1>
        <p><?= e($d['categoria']) ?><span class="sep"></span><?= e(strtoupper($d['extension'])) ?><span class="sep"></span><?= e(bytes_legible((int) $d['tamano_bytes'])) ?></p>
    </div>
    <div class="page-actions">
        <?php if ($puedeVer): ?><a class="btn btn-primary" href="<?= e($archivo . '?descargar=1') ?>"><?= icon('download', 16) ?> Descargar</a><?php endif; ?>
        <?php if (can('documentos.eliminar')): ?>
            <form method="post" action="<?= e(url('documentos/' . $d['id'] . '/eliminar')) ?>" data-confirm="El archivo y sus datos se eliminarán de forma permanente. La acción queda registrada en la auditoría."
                  data-confirm-title="Eliminar documento" data-confirm-label="Eliminar">
                <?= csrf_field() ?><button type="submit" class="btn btn-danger"><?= icon('trash', 16) ?> Eliminar</button>
            </form>
        <?php endif; ?>
    </div>
</div>

<div class="cols-3">
    <section class="panel">
        <div class="panel-head"><h2>Vista previa</h2></div>
        <div class="panel-body">
            <?php if (!$puedeVer): ?>
                <div class="empty"><?= icon('lock', 28) ?><strong>Contenido no disponible para su rol</strong><p>Su cuenta puede ver los datos del documento, pero no su contenido clínico.</p></div>
            <?php elseif ($esImagen): ?>
                <div class="doc-preview"><img src="<?= e($archivo) ?>" alt="<?= e($d['titulo']) ?>"></div>
            <?php else: ?>
                <div class="doc-preview"><iframe src="<?= e($archivo) ?>" title="<?= e($d['titulo']) ?>"></iframe></div>
                <p class="help spaced">Si el visor no muestra el documento, use el botón Descargar.</p>
            <?php endif; ?>
        </div>
    </section>

    <section class="panel">
        <div class="panel-head"><h2>Datos del documento</h2></div>
        <dl class="kv panel-body">
            <dt>Paciente</dt><dd><?php if (!\App\Core\Auth::isPatient()): ?><a href="<?= e(url('pacientes/' . $d['paciente_id'])) ?>"><?= e($d['papellidos'] . ', ' . $d['pnombres']) ?></a><?php else: ?><?= e($d['pnombres'] . ' ' . $d['papellidos']) ?><?php endif; ?> <span class="muted"><?= e($d['codigo']) ?></span></dd>
            <dt>Fecha del documento</dt><dd><?= e(fecha($d['fecha_documento'])) ?></dd>
            <dt>Descripción</dt><dd><?= e($d['descripcion'] ?: '—') ?></dd>
            <?php if ($d['consulta_id'] && can('consultas.ver')): ?><dt>Consulta</dt><dd><a href="<?= e(url('consultas/' . $d['consulta_id'])) ?>">Ver consulta asociada</a></dd><?php endif; ?>
            <dt>Archivo original</dt><dd><?= e($d['nombre_original']) ?></dd>
            <dt>Cargado por</dt><dd><?= e(trim(($d['unombres'] ?? '') . ' ' . ($d['uapellidos'] ?? '')) ?: '—') ?></dd>
            <dt>Fecha de carga</dt><dd><?= e(fecha_hora($d['cargado_en'])) ?></dd>
            <dt>Huella SHA-256</dt><dd class="detail"><code><?= e(substr($d['sha256'], 0, 16)) ?>…</code></dd>
        </dl>
    </section>
</div>
