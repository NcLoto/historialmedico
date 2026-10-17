<div class="page-head">
    <div>
        <div class="breadcrumb">
            <a href="<?= e(url('pacientes')) ?>">Pacientes</a> <?= icon('chevron', 12) ?>
            <a href="<?= e(url('pacientes/' . $p['id'] . '#documentos')) ?>"><?= e(nombre_completo($p)) ?></a> <?= icon('chevron', 12) ?> Adjuntar documento
        </div>
        <h1>Adjuntar documento</h1>
        <p>Paciente: <?= e(nombre_completo($p)) ?><span class="sep"></span><?= e($p['codigo']) ?></p>
    </div>
</div>

<form method="post" action="<?= e(url('pacientes/' . $p['id'] . '/documentos')) ?>" enctype="multipart/form-data" novalidate>
    <?= csrf_field() ?>
    <section class="form-section">
        <h2>Documento</h2>
        <p class="help">Exámenes de laboratorio, imágenes diagnósticas o informes. Formatos: <?= e(App\Core\Upload::etiquetaFormatos()) ?>; máximo <?= (int) $maxMb ?> MB.</p>
        <div class="form-grid">
            <div class="field col-8<?= isset($errors['archivo']) ? ' has-error' : '' ?>">
                <label for="archivo">Archivo</label>
                <input type="file" id="archivo" name="archivo" accept="<?= e(App\Core\Upload::accept()) ?>" data-max-mb="<?= (int) $maxMb ?>" required>
                <?= field_error($errors, 'archivo') ?>
            </div>
            <div class="field col-4<?= isset($errors['categoria_id']) ? ' has-error' : '' ?>">
                <label for="categoria_id">Categoría</label>
                <select id="categoria_id" name="categoria_id" required>
                    <option value="">Seleccione</option>
                    <?php foreach ($categorias as $grupo => $items): ?>
                        <optgroup label="<?= e($grupo) ?>"><?= options($items, old('categoria_id'), null) ?></optgroup>
                    <?php endforeach; ?>
                </select>
                <?= field_error($errors, 'categoria_id') ?>
            </div>
            <div class="field col-8<?= isset($errors['titulo']) ? ' has-error' : '' ?>">
                <label for="titulo">Título</label>
                <input type="text" id="titulo" name="titulo" value="<?= e(old('titulo')) ?>" maxlength="150" required placeholder="Ej.: Hemograma completo">
                <?= field_error($errors, 'titulo') ?>
            </div>
            <div class="field col-4<?= isset($errors['fecha_documento']) ? ' has-error' : '' ?>">
                <label for="fecha_documento">Fecha del documento <span class="opt">(opcional)</span></label>
                <input type="date" id="fecha_documento" name="fecha_documento" value="<?= e(old('fecha_documento')) ?>" max="<?= date('Y-m-d') ?>">
                <?= field_error($errors, 'fecha_documento') ?>
            </div>
            <div class="field col-12<?= isset($errors['descripcion']) ? ' has-error' : '' ?>">
                <label for="descripcion">Descripción <span class="opt">(opcional)</span></label>
                <input type="text" id="descripcion" name="descripcion" value="<?= e(old('descripcion')) ?>" maxlength="255">
                <?= field_error($errors, 'descripcion') ?>
            </div>
            <div class="field col-12<?= isset($errors['consulta_id']) ? ' has-error' : '' ?>">
                <label for="consulta_id">Asociar a una consulta <span class="opt">(opcional)</span></label>
                <select id="consulta_id" name="consulta_id"><?= options($consultas, $consultaId ?: '', 'Sin consulta asociada') ?></select>
                <?= field_error($errors, 'consulta_id') ?>
            </div>
        </div>
    </section>
    <div class="form-actions">
        <a class="btn btn-secondary" href="<?= e(url('pacientes/' . $p['id'] . '#documentos')) ?>">Cancelar</a>
        <button type="submit" class="btn btn-primary"><?= icon('upload', 16) ?> Adjuntar documento</button>
    </div>
</form>
