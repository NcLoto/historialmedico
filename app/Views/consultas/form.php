<?php
/** @var array $p @var ?array $c @var array $diag @var array $trat @var array $categorias @var int $maxMb @var bool $puedeAdjuntar */
$editando = $c !== null;
$pid = (int) $p['id'];
$val = fn(string $k, string $def = '') => old($k, $editando ? (string) ($c[$k] ?? '') : $def);
$fecha = old('fecha_hora', $editando ? date('Y-m-d\TH:i', strtotime($c['fecha_hora'])) : date('Y-m-d\TH:i'));
$estado = old('estado', $editando ? ($c['estado'] === 'programada' ? 'realizada' : $c['estado']) : 'realizada');
$num = fn(string $k) => $editando && $c[$k] !== null ? rtrim(rtrim((string) $c[$k], '0'), '.') : '';
$campo = function (string $k, string $label, string $unidad, string $min, string $max, string $step = '1') use ($errors, $editando, $c, $num) {
    $v = old($k, $num($k));
    $err = isset($errors[$k]) ? ' has-error' : '';
    echo '<div class="field' . $err . '"><label for="' . $k . '">' . e($label) . ' <span class="opt">' . e($unidad) . '</span></label>';
    echo '<input type="number" id="' . $k . '" name="' . $k . '" value="' . e($v) . '" min="' . $min . '" max="' . $max . '" step="' . $step . '" inputmode="decimal">';
    echo field_error($errors, $k) . '</div>';
};
$tiposTrat = ['medicamento' => 'Medicamento', 'procedimiento' => 'Procedimiento', 'indicacion' => 'Indicación', 'referencia' => 'Referencia'];
$reabierto = is_array(\App\Core\Flash::oldValue('diag_desc', null));
$agregarMeds = $reabierto ? old('agregar_medicamentos') !== '' : true;
if (!$diag) { $diag = [['tipo' => 'principal', 'codigo_cie10' => '', 'descripcion' => '']]; }
if (!$trat) { $trat = [['tipo' => 'medicamento', 'descripcion' => '', 'dosis' => '', 'frecuencia' => '', 'duracion' => '']]; }

$filaDiag = function (array $d) {
    return '<div class="repeat-row"><div class="field"><select aria-label="Tipo de diagnóstico" name="diag_tipo[]">' . options(['principal' => 'Principal', 'secundario' => 'Secundario'], $d['tipo'] ?? 'principal', null) . '</select></div>'
        . '<div class="field"><input aria-label="Diagnóstico" type="text" name="diag_desc[]" value="' . e($d['descripcion'] ?? '') . '" maxlength="255" placeholder="Diagnóstico"></div>'
        . '<div class="field"><input aria-label="Código CIE-10" type="text" name="diag_codigo[]" value="' . e($d['codigo_cie10'] ?? '') . '" maxlength="10" placeholder="CIE-10"></div>'
        . '<button type="button" class="icon-btn" data-remove-row aria-label="Quitar diagnóstico">' . icon('x', 16) . '</button></div>';
};
$filaTrat = function (array $t) use ($tiposTrat) {
    return '<div class="repeat-row trat"><div class="field"><select aria-label="Tipo de indicación" name="trat_tipo[]">' . options($tiposTrat, $t['tipo'] ?? 'medicamento', null) . '</select></div>'
        . '<div class="field"><input aria-label="Medicamento o indicación" type="text" name="trat_desc[]" value="' . e($t['descripcion'] ?? '') . '" maxlength="255" placeholder="Medicamento o indicación"></div>'
        . '<div class="field"><input aria-label="Dosis" type="text" name="trat_dosis[]" value="' . e($t['dosis'] ?? '') . '" maxlength="80" placeholder="Dosis"></div>'
        . '<div class="field"><input aria-label="Frecuencia" type="text" name="trat_frec[]" value="' . e($t['frecuencia'] ?? '') . '" maxlength="80" placeholder="Frecuencia"></div>'
        . '<div class="field"><input aria-label="Duración" type="text" name="trat_dur[]" value="' . e($t['duracion'] ?? '') . '" maxlength="80" placeholder="Duración"></div>'
        . '<button type="button" class="icon-btn" data-remove-row aria-label="Quitar tratamiento">' . icon('x', 16) . '</button></div>';
};
?>
<div class="page-head">
    <div>
        <div class="breadcrumb">
            <a href="<?= e(url('pacientes')) ?>">Pacientes</a> <?= icon('chevron', 12) ?>
            <a href="<?= e(url("pacientes/$pid")) ?>"><?= e(nombre_completo($p)) ?></a> <?= icon('chevron', 12) ?> <?= $editando ? 'Editar consulta' : 'Nueva consulta' ?>
        </div>
        <h1><?= $editando ? ($c['estado'] === 'programada' ? 'Registrar atención' : 'Editar consulta') : 'Nueva consulta' ?></h1>
        <p><?= e(nombre_completo($p)) ?><span class="sep"></span><?= e($p['codigo']) ?><span class="sep"></span><?= e(edad($p['fecha_nacimiento'])) ?></p>
    </div>
</div>

<form method="post" action="<?= e(url($editando ? 'consultas/' . $c['id'] : "pacientes/$pid/consultas")) ?>"<?= $puedeAdjuntar ? ' enctype="multipart/form-data"' : '' ?> novalidate>
    <?= csrf_field() ?>

    <section class="form-section">
        <h2>Datos de la consulta</h2>
        <div class="form-grid">
            <div class="field col-4<?= isset($errors['fecha_hora']) ? ' has-error' : '' ?>">
                <label for="fecha_hora">Fecha y hora</label>
                <input type="datetime-local" id="fecha_hora" name="fecha_hora" value="<?= e($fecha) ?>" required>
                <?= field_error($errors, 'fecha_hora') ?>
            </div>
            <div class="field col-4<?= isset($errors['estado']) ? ' has-error' : '' ?>">
                <label for="estado">Estado</label>
                <select id="estado" name="estado"><?= options(['realizada' => 'Realizada', 'programada' => 'Programada (a futuro)', 'cancelada' => 'Cancelada'], $estado, null) ?></select>
                <p class="help">Una consulta realizada requiere al menos un diagnóstico.</p>
                <?= field_error($errors, 'estado') ?>
            </div>
            <div class="field col-12<?= isset($errors['motivo']) ? ' has-error' : '' ?>">
                <label for="motivo">Motivo de consulta</label>
                <input type="text" id="motivo" name="motivo" value="<?= e($val('motivo')) ?>" maxlength="255" required>
                <?= field_error($errors, 'motivo') ?>
            </div>
        </div>
    </section>

    <section class="form-section">
        <h2>Evaluación clínica</h2>
        <div class="form-grid">
            <div class="field col-12<?= isset($errors['enfermedad_actual']) ? ' has-error' : '' ?>">
                <label for="enfermedad_actual">Enfermedad actual <span class="opt">(opcional)</span></label>
                <textarea id="enfermedad_actual" name="enfermedad_actual" rows="3" maxlength="4000"><?= e($val('enfermedad_actual')) ?></textarea>
                <?= field_error($errors, 'enfermedad_actual') ?>
            </div>
            <div class="col-12">
                <div class="label"><strong>Signos vitales</strong> <span class="opt muted">(todos opcionales)</span></div>
                <div class="vitals-grid spaced">
                    <?php $campo('presion_sistolica', 'Sistólica', 'mmHg', '40', '300'); ?>
                    <?php $campo('presion_diastolica', 'Diastólica', 'mmHg', '20', '200'); ?>
                    <?php $campo('frecuencia_cardiaca', 'Frec. cardíaca', 'lpm', '20', '250'); ?>
                    <?php $campo('temperatura', 'Temperatura', '°C', '30', '45', '0.1'); ?>
                    <?php $campo('saturacion_o2', 'Sat. O₂', '%', '0', '100'); ?>
                    <?php $campo('peso_kg', 'Peso', 'kg', '0.5', '500', '0.1'); ?>
                    <?php $campo('talla_cm', 'Talla', 'cm', '20', '260', '0.1'); ?>
                </div>
            </div>
            <div class="field col-12<?= isset($errors['examen_fisico']) ? ' has-error' : '' ?>">
                <label for="examen_fisico">Examen físico <span class="opt">(opcional)</span></label>
                <textarea id="examen_fisico" name="examen_fisico" rows="3" maxlength="4000"><?= e($val('examen_fisico')) ?></textarea>
                <?= field_error($errors, 'examen_fisico') ?>
            </div>
        </div>
    </section>

    <section class="form-section<?= isset($errors['diagnosticos']) ? ' has-error' : '' ?>">
        <h2>Diagnósticos</h2>
        <p class="help">Indique el diagnóstico principal y, si corresponde, secundarios. El código CIE-10 es opcional.</p>
        <?= field_error($errors, 'diagnosticos') ?>
        <div id="lista-diag" class="spaced"><?php foreach ($diag as $d) { echo $filaDiag($d); } ?></div>
        <template id="tpl-diag"><?= $filaDiag([]) ?></template>
        <button type="button" class="btn btn-secondary btn-sm" data-add-row="#tpl-diag" data-target="#lista-diag"><?= icon('plus', 14) ?> Agregar diagnóstico</button>
    </section>

    <section class="form-section">
        <h2>Tratamiento e indicaciones</h2>
        <?= field_error($errors, 'tratamientos') ?>
        <div id="lista-trat" class="spaced"><?php foreach ($trat as $t) { echo $filaTrat($t); } ?></div>
        <template id="tpl-trat"><?= $filaTrat([]) ?></template>
        <button type="button" class="btn btn-secondary btn-sm" data-add-row="#tpl-trat" data-target="#lista-trat"><?= icon('plus', 14) ?> Agregar tratamiento</button>
        <?php if (!$editando): ?>
            <label class="check spaced"><input type="checkbox" name="agregar_medicamentos" value="1" <?= $agregarMeds ? 'checked' : '' ?>><span>Agregar los medicamentos indicados a la lista de medicamentos vigentes del paciente</span></label>
        <?php endif; ?>
    </section>

    <section class="form-section">
        <h2>Plan y notas</h2>
        <div class="form-grid">
            <div class="field col-12<?= isset($errors['plan']) ? ' has-error' : '' ?>">
                <label for="plan">Plan <span class="opt">(visible para el paciente)</span></label>
                <textarea id="plan" name="plan" rows="3" maxlength="4000"><?= e($val('plan')) ?></textarea>
                <?= field_error($errors, 'plan') ?>
            </div>
            <div class="field col-12<?= isset($errors['notas_internas']) ? ' has-error' : '' ?>">
                <label for="notas_internas">Notas internas <span class="opt">(solo personal médico)</span></label>
                <textarea id="notas_internas" name="notas_internas" rows="2" maxlength="4000"><?= e($val('notas_internas')) ?></textarea>
                <?= field_error($errors, 'notas_internas') ?>
            </div>
        </div>
    </section>

    <?php if ($puedeAdjuntar): ?>
        <section class="form-section">
            <h2>Adjuntar examen o imagen</h2>
            <p class="help">Opcional. <?= e(App\Core\Upload::etiquetaFormatos()) ?>; máximo <?= (int) $maxMb ?> MB. Puede agregar más documentos después desde el detalle de la consulta.</p>
            <div class="form-grid">
                <div class="field col-6<?= isset($errors['archivo']) ? ' has-error' : '' ?>">
                    <label for="archivo">Archivo</label>
                    <input type="file" id="archivo" name="archivo" accept="<?= e(App\Core\Upload::accept()) ?>" data-max-mb="<?= (int) $maxMb ?>">
                    <?= field_error($errors, 'archivo') ?>
                </div>
                <div class="field col-6<?= isset($errors['doc_categoria_id']) ? ' has-error' : '' ?>">
                    <label for="doc_categoria_id">Categoría</label>
                    <select id="doc_categoria_id" name="doc_categoria_id">
                        <option value="">Seleccione</option>
                        <?php foreach ($categorias as $grupo => $items): ?><optgroup label="<?= e($grupo) ?>"><?= options($items, old('doc_categoria_id'), null) ?></optgroup><?php endforeach; ?>
                    </select>
                    <?= field_error($errors, 'doc_categoria_id') ?>
                </div>
                <div class="field col-8<?= isset($errors['doc_titulo']) ? ' has-error' : '' ?>">
                    <label for="doc_titulo">Título del documento</label>
                    <input type="text" id="doc_titulo" name="doc_titulo" value="<?= e(old('doc_titulo')) ?>" maxlength="150">
                    <?= field_error($errors, 'doc_titulo') ?>
                </div>
                <div class="field col-4<?= isset($errors['doc_fecha_documento']) ? ' has-error' : '' ?>">
                    <label for="doc_fecha_documento">Fecha del documento <span class="opt">(opcional)</span></label>
                    <input type="date" id="doc_fecha_documento" name="doc_fecha_documento" value="<?= e(old('doc_fecha_documento')) ?>" max="<?= date('Y-m-d') ?>">
                    <?= field_error($errors, 'doc_fecha_documento') ?>
                </div>
            </div>
        </section>
    <?php endif; ?>

    <div class="form-actions">
        <a class="btn btn-secondary" href="<?= e(url($editando ? 'consultas/' . $c['id'] : "pacientes/$pid")) ?>">Cancelar</a>
        <button type="submit" class="btn btn-primary"><?= $editando ? 'Guardar cambios' : 'Guardar consulta' ?></button>
    </div>
</form>
