<?php
/** @var array $p paciente @var array $s sección @var string $key @var ?array $registro */
$editando = $registro !== null;
$pid = (int) $p['id'];
$action = $editando ? "pacientes/$pid/historial/$key/{$registro['id']}" : "pacientes/$pid/historial/$key";
$valor = function (string $campo) use ($registro, $s, $editando) {
    if ($editando) {
        return old($campo, (string) ($registro[$campo] ?? ''));
    }
    return old($campo, (string) ($s['defaults'][$campo] ?? ''));
};
?>
<div class="page-head">
    <div>
        <div class="breadcrumb">
            <a href="<?= e(url('pacientes')) ?>">Pacientes</a> <?= icon('chevron', 12) ?>
            <a href="<?= e(url("pacientes/$pid#$key")) ?>"><?= e(nombre_completo($p)) ?></a> <?= icon('chevron', 12) ?> <?= e($s['titulo']) ?>
        </div>
        <h1><?= $editando ? 'Editar' : 'Agregar' ?> <?= e($s['singular']) ?></h1>
        <p>Paciente: <?= e(nombre_completo($p)) ?><span class="sep"></span><?= e($p['codigo']) ?></p>
    </div>
</div>

<form method="post" action="<?= e(url($action)) ?>" novalidate>
    <?= csrf_field() ?>
    <section class="form-section">
        <div class="form-grid">
            <?php foreach ($s['fields'] as $name => $def):
                [$label, $type, $rules, $opts, $cols, $ayuda] = array_pad($def, 6, '');
                $req = str_contains($rules, 'required');
                $err = isset($errors[$name]) ? ' has-error' : '';
                $v = $valor($name); ?>
                <?php if ($type === 'bool'): ?>
                    <div class="field col-<?= (int) $cols ?>">
                        <label class="check"><input type="checkbox" name="<?= e($name) ?>" value="1" <?= ($v !== '' && $v !== '0') ? 'checked' : '' ?>><span><?= e($label) ?></span></label>
                    </div>
                <?php else: ?>
                    <div class="field col-<?= (int) $cols ?><?= $err ?>">
                        <label for="f_<?= e($name) ?>"><?= e($label) ?><?= $req ? '' : ' <span class="opt">(opcional)</span>' ?></label>
                        <?php if ($type === 'select'): ?>
                            <select id="f_<?= e($name) ?>" name="<?= e($name) ?>"<?= $req ? ' required' : '' ?>><?= options($opts, $v, 'Seleccione') ?></select>
                        <?php elseif ($type === 'textarea'): ?>
                            <textarea id="f_<?= e($name) ?>" name="<?= e($name) ?>" maxlength="2000" rows="3"><?= e($v) ?></textarea>
                        <?php elseif ($type === 'date'): ?>
                            <input type="date" id="f_<?= e($name) ?>" name="<?= e($name) ?>" value="<?= e($v) ?>"<?= str_contains($rules, 'past') ? ' max="' . date('Y-m-d') . '"' : '' ?>>
                        <?php elseif ($type === 'number'): ?>
                            <input type="number" id="f_<?= e($name) ?>" name="<?= e($name) ?>" value="<?= e($v) ?>" min="0" max="120" inputmode="numeric">
                        <?php else: ?>
                            <input type="text" id="f_<?= e($name) ?>" name="<?= e($name) ?>" value="<?= e($v) ?>" maxlength="255"<?= $req ? ' required' : '' ?> autocomplete="off">
                        <?php endif; ?>
                        <?php if ($ayuda): ?><p class="help"><?= e($ayuda) ?></p><?php endif; ?>
                        <?= field_error($errors, $name) ?>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    </section>
    <div class="form-actions">
        <a class="btn btn-secondary" href="<?= e(url("pacientes/$pid#$key")) ?>">Cancelar</a>
        <button type="submit" class="btn btn-primary"><?= $editando ? 'Guardar cambios' : 'Guardar ' . e($s['singular']) ?></button>
    </div>
</form>
