<?php
use App\Core\Color;
use App\Domain\Impresos;

$editable = can('configuracion.editar');
$dis = $editable ? '' : ' disabled';
$v = fn(string $k, string $d = '') => old($k, $cfg[$k] ?? $d);
$check = function (string $k, string $label, string $help = '') use ($cfg, $dis) {
    $c = ($cfg[$k] ?? '0') === '1' ? ' checked' : '';
    echo '<label class="switch-row"><input type="checkbox" class="switch" name="' . $k . '" value="1"' . $c . $dis . '><span><strong>' . e($label) . '</strong>' . ($help ? '<span class="help">' . e($help) . '</span>' : '') . '</span></label>';
};
$secciones = explode(',', $cfg['doc_secciones'] ?? '');
$formatos = explode(',', $cfg['doc_tipos_permitidos'] ?? '');
$colorPropio = Color::isHex($cfg['doc_color'] ?? '');
$colorHex = $colorPropio ? $cfg['doc_color'] : Color::toHex($cfg['color_acento'] ?? 'cian');
$encabezados = [
    'barra' => ['Franja de color', 'Membrete sobre blanco con una franja fina del color institucional en el borde superior de la hoja.'],
    'linea' => ['Membrete clásico', 'Logo, nombre y contacto sobre blanco, con un filete del color institucional.'],
    'minimo' => ['Mínimo', 'Nombre y contacto en una línea, sin logo. Ahorra tinta y espacio.'],
];
$grupos = ['examen' => 'Exámenes de laboratorio', 'imagen' => 'Imágenes diagnósticas', 'documento' => 'Documentos médicos'];
App\Core\View::partial('configuracion/_tabs', ['seccion' => $seccion]);
?>
<div class="settings-intro">
    <p>Estos ajustes definen cómo se ven todos los impresos: resumen clínico, informe de consulta, receta, constancia, carné de vacunación y reportes. Se aplican de inmediato.</p>
    <a class="btn btn-secondary" href="<?= e(url('configuracion/documentos/muestra.pdf')) ?>" target="_blank" rel="noopener"><?= icon('eye', 16) ?> Ver PDF de muestra</a>
</div>

<form method="post" action="<?= e(url('configuracion/documentos')) ?>" novalidate>
    <?= csrf_field() ?>

    <section class="form-section">
        <h2>Diseño de los impresos</h2>
        <p class="help">Formato de página y apariencia del encabezado.</p>
        <div class="form-grid">
            <div class="field col-4">
                <label for="doc_papel">Tamaño de papel</label>
                <select id="doc_papel" name="doc_papel"<?= $dis ?>><?= options(['a4' => 'A4 (210 × 297 mm)', 'carta' => 'Carta (216 × 279 mm)'], $v('doc_papel', 'a4'), null) ?></select>
            </div>
            <div class="field col-4">
                <label for="doc_letra">Tamaño de letra</label>
                <select id="doc_letra" name="doc_letra"<?= $dis ?>><?= options(['normal' => 'Normal', 'grande' => 'Grande (más legible al imprimir)'], $v('doc_letra', 'normal'), null) ?></select>
            </div>
        </div>
        <div class="label spaced"><strong>Encabezado</strong></div>
        <div class="choice-grid spaced" role="radiogroup" aria-label="Estilo de encabezado">
            <?php foreach ($encabezados as $k => [$n, $d]): ?>
                <label class="choice choice-preview">
                    <input type="radio" name="doc_encabezado" value="<?= e($k) ?>" <?= $v('doc_encabezado', 'barra') === $k ? 'checked' : '' ?><?= $dis ?>>
                    <span class="sheet sheet-<?= e($k) ?>" aria-hidden="true"><i></i><b></b><b></b><b></b></span>
                    <span><strong><?= e($n) ?></strong><span class="help"><?= e($d) ?></span></span>
                </label>
            <?php endforeach; ?>
        </div>
        <div class="form-grid spaced">
            <div class="field col-12">
                <span class="label"><strong>Color de los documentos</strong></span>
                <div class="row">
                    <label class="check"><input type="radio" name="doc_color_modo" value="acento" <?= $colorPropio ? '' : 'checked' ?><?= $dis ?>><span>Usar el color de acento del sistema</span></label>
                    <label class="check"><input type="radio" name="doc_color_modo" value="propio" <?= $colorPropio ? 'checked' : '' ?><?= $dis ?>><span>Color propio</span></label>
                    <input type="color" name="doc_color" value="<?= e($colorHex) ?>" aria-label="Color propio de los documentos"<?= $dis ?>>
                </div>
                <p class="help">Se oscurece automáticamente si hace falta para que el texto sea legible al imprimir.</p>
                <?= field_error($errors, 'doc_color') ?>
            </div>
        </div>
        <div class="switch-list">
            <?php $check('doc_logo', 'Mostrar el logo', 'Usa el logo cargado en Apariencia; si no hay, la cruz institucional.'); ?>
            <?php $check('doc_contacto', 'Mostrar dirección, teléfono y correo en el encabezado'); ?>
        </div>
    </section>

    <section class="form-section">
        <h2>Contenido y seguridad</h2>
        <div class="form-grid">
            <div class="field col-12<?= isset($errors['doc_pie']) ? ' has-error' : '' ?>">
                <label for="doc_pie">Texto del pie de página</label>
                <input type="text" id="doc_pie" name="doc_pie" value="<?= e($v('doc_pie')) ?>" maxlength="220"<?= $dis ?>>
                <?= field_error($errors, 'doc_pie') ?>
            </div>
            <div class="field col-6<?= isset($errors['doc_firma_texto']) ? ' has-error' : '' ?>">
                <label for="doc_firma_texto">Texto bajo la firma</label>
                <input type="text" id="doc_firma_texto" name="doc_firma_texto" value="<?= e($v('doc_firma_texto')) ?>" maxlength="80"<?= $dis ?>>
                <?= field_error($errors, 'doc_firma_texto') ?>
            </div>
            <div class="field col-6<?= isset($errors['doc_marca_agua_texto']) ? ' has-error' : '' ?>">
                <label for="doc_marca_agua_texto">Texto de la marca de agua</label>
                <input type="text" id="doc_marca_agua_texto" name="doc_marca_agua_texto" value="<?= e($v('doc_marca_agua_texto')) ?>" maxlength="30"<?= $dis ?>>
                <?= field_error($errors, 'doc_marca_agua_texto') ?>
            </div>
            <div class="field col-12<?= isset($errors['doc_receta_nota']) ? ' has-error' : '' ?>">
                <label for="doc_receta_nota">Nota al pie de la receta médica <span class="opt">(opcional)</span></label>
                <input type="text" id="doc_receta_nota" name="doc_receta_nota" value="<?= e($v('doc_receta_nota')) ?>" maxlength="255"<?= $dis ?>>
                <?= field_error($errors, 'doc_receta_nota') ?>
            </div>
        </div>
        <div class="switch-list">
            <?php $check('doc_verificacion', 'Código de verificación', 'Cada PDF lleva un código único; cualquiera puede comprobar su autenticidad en /verificar sin ver el contenido. ' . (int) $emitidos . ' documentos emitidos.'); ?>
            <?php $check('doc_generado_por', 'Indicar quién generó el documento', 'Agrega al pie el nombre, el rol, la fecha y la hora.'); ?>
            <?php $check('doc_marca_agua', 'Imprimir marca de agua', 'Texto diagonal muy tenue en cada página.'); ?>
        </div>
    </section>

    <section class="form-section<?= isset($errors['doc_secciones']) ? ' has-error' : '' ?>">
        <h2>Resumen clínico</h2>
        <p class="help">Secciones que se incluyen al exportar el resumen de un paciente. Los datos personales siempre se incluyen.</p>
        <?= field_error($errors, 'doc_secciones') ?>
        <div class="check-grid">
            <?php foreach (Impresos::SECCIONES as $k => $label): ?>
                <label class="check"><input type="checkbox" name="doc_secciones[]" value="<?= e($k) ?>" <?= in_array($k, $secciones, true) ? 'checked' : '' ?><?= $dis ?>><span><?= e($label) ?></span></label>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="form-section" id="archivos">
        <h2>Documentos adjuntos</h2>
        <p class="help">Formatos y tamaño de los exámenes, imágenes e informes que se adjuntan a la historia clínica. El contenido real de cada archivo siempre se verifica.</p>
        <?= field_error($errors, 'doc_tipos_permitidos') ?>
        <div class="check-grid">
            <?php foreach (['pdf' => 'PDF', 'jpg' => 'JPG / JPEG', 'png' => 'PNG', 'webp' => 'WEBP'] as $k => $label): ?>
                <label class="check"><input type="checkbox" name="doc_tipos_permitidos[]" value="<?= e($k) ?>" <?= in_array($k, $formatos, true) ? 'checked' : '' ?><?= $dis ?>><span><?= e($label) ?></span></label>
            <?php endforeach; ?>
        </div>
        <div class="form-grid spaced">
            <div class="field col-4<?= isset($errors['archivo_max_mb']) ? ' has-error' : '' ?>">
                <label for="archivo_max_mb">Tamaño máximo por archivo (MB)</label>
                <input type="number" id="archivo_max_mb" name="archivo_max_mb" value="<?= e($v('archivo_max_mb', '15')) ?>" min="1" max="40" required<?= $dis ?>>
                <p class="help">Entre 1 y 40. El servidor admite hasta 40 MB.</p>
                <?= field_error($errors, 'archivo_max_mb') ?>
            </div>
        </div>
    </section>

    <?php if ($editable): ?><div class="form-actions"><button type="submit" class="btn btn-primary">Guardar documentos</button></div><?php endif; ?>
</form>

<section class="form-section spaced" id="categorias">
    <h2>Categorías de documentos</h2>
    <p class="help">Clasifican los adjuntos en exámenes, imágenes y documentos. Una categoría con documentos no puede eliminarse.</p>
    <div class="table-wrap">
        <table class="table stackable">
            <thead><tr><th scope="col">Categoría</th><th scope="col">Grupo</th><th scope="col" class="right">Documentos</th><th scope="col"><span class="sr-only">Acciones</span></th></tr></thead>
            <tbody>
            <?php foreach ($categorias as $c): ?>
                <tr>
                    <?php if ($editable): ?>
                        <td data-label="Categoría" colspan="2">
                            <form method="post" action="<?= e(url('configuracion/categorias/' . $c['id'])) ?>" class="input-group">
                                <?= csrf_field() ?>
                                <input type="text" name="nombre" value="<?= e($c['nombre']) ?>" maxlength="60" required aria-label="Nombre de la categoría">
                                <select name="grupo" aria-label="Grupo"><?= options($grupos, $c['grupo'], null) ?></select>
                                <button type="submit" class="btn btn-secondary btn-sm">Guardar</button>
                            </form>
                        </td>
                    <?php else: ?>
                        <td data-label="Categoría"><?= e($c['nombre']) ?></td><td data-label="Grupo"><?= e($grupos[$c['grupo']]) ?></td>
                    <?php endif; ?>
                    <td data-label="Documentos" class="right num"><?= (int) $c['usos'] ?></td>
                    <td class="col-actions">
                        <?php if ($editable && !$c['usos']): ?>
                            <form method="post" action="<?= e(url('configuracion/categorias/' . $c['id'] . '/eliminar')) ?>" data-confirm="Se eliminará la categoría «<?= e($c['nombre']) ?>»." data-confirm-title="Eliminar categoría" data-confirm-label="Eliminar">
                                <?= csrf_field() ?><button type="submit" class="icon-btn link-danger" aria-label="Eliminar <?= e($c['nombre']) ?>" title="Eliminar"><?= icon('trash', 16) ?></button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php if ($editable): ?>
        <form method="post" action="<?= e(url('configuracion/categorias')) ?>" class="input-group spaced">
            <?= csrf_field() ?>
            <div class="field grow<?= isset($errors['nombre']) ? ' has-error' : '' ?>"><label for="cat_nombre">Nueva categoría</label><input type="text" id="cat_nombre" name="nombre" maxlength="60" required><?= field_error($errors, 'nombre') ?></div>
            <div class="field"><label for="cat_grupo">Grupo</label><select id="cat_grupo" name="grupo"><?= options($grupos, 'examen', null) ?></select></div>
            <button type="submit" class="btn btn-secondary align-end"><?= icon('plus', 16) ?> Agregar</button>
        </form>
    <?php endif; ?>
</section>
<?php App\Core\View::partial('configuracion/_fin'); ?>
