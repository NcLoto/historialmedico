<?php
$editable = can('configuracion.editar');
$tema = old('tema_defecto', $cfg['tema_defecto'] ?? 'claro');
$colorHex = App\Core\Color::toHex(old('color_acento', $cfg['color_acento'] ?? 'cian'));
$temas = ['claro' => ['Claro', 'Fondo claro. Recomendado para trabajo diurno y para proyectar.'], 'oscuro' => ['Oscuro', 'Fondo oscuro. Reduce el brillo en guardias nocturnas.'], 'auto' => ['Automático', 'Sigue la preferencia del sistema operativo de cada equipo.']];
$cristal = App\Domain\Marca::cristal($cfg);
$estilo = old('estilo_interfaz', $cristal['activo'] ? 'cristal' : 'solido');
$estilos = ['solido' => ['Sólido', 'Paneles opacos sobre un fondo liso. Máxima legibilidad.'], 'cristal' => ['Cristal', 'Paneles translúcidos y desenfocados sobre una imagen de fondo.']];
$grupos = [
    'Imagen de fondo' => [
        'fondo_transparencia' => ['Transparencia de la imagen', 0, 90, '%', 'Funde la foto con el color de fondo del tema. 0 % = foto completa.'],
        'fondo_oscurecer'     => ['Oscurecer la imagen', 0, 70, '%', 'Atenúa una foto muy clara o con mucho detalle.'],
    ],
    'Efecto vidrio' => [
        'cristal_intensidad' => ['Intensidad del efecto vidrio', 0, 100, '%', 'Brillo, borde luminoso y profundidad de los paneles.'],
        'cristal_opacidad'   => ['Opacidad de los paneles', 50, 95, '%', 'Más alto = más legible; más bajo = se ve más el fondo.'],
        'cristal_desenfoque' => ['Desenfoque detrás del vidrio', 0, 30, 'px', 'Cuánto se difumina la imagen detrás de cada panel.'],
    ],
];
$valores = ['cristal_opacidad' => $cristal['opacidad'], 'cristal_desenfoque' => $cristal['desenfoque'], 'fondo_oscurecer' => $cristal['oscurecer'],
    'cristal_intensidad' => $cristal['intensidad'], 'fondo_transparencia' => $cristal['transparencia']];
$resultadoMarca = $_SESSION['_marca'] ?? null;
unset($_SESSION['_marca']);
$subida = function (string $tipo, string $titulo, string $ayuda, ?string $url) use ($editable, $resultadoMarca) {
    $lim = App\Domain\Marca::LIMITES[$tipo];
    $res = ($resultadoMarca['tipo'] ?? '') === $tipo ? $resultadoMarca : null; ?>
    <section class="form-section" id="marca-<?= e($tipo) ?>">
        <h2><?= e($titulo) ?></h2>
        <p class="help"><?= e($ayuda) ?></p>
        <?php if ($res): ?>
            <div class="alert <?= $res['ok'] ? 'alert-success' : 'alert-error' ?>" role="<?= $res['ok'] ? 'status' : 'alert' ?>"><?= icon($res['ok'] ? 'check' : 'alert', 18) ?><span><?= e($res['msg']) ?></span></div>
        <?php endif; ?>
        <div class="brand-preview">
            <div class="brand-preview-box<?= ['favicon' => ' is-square', 'fondo' => ' is-wide'][$tipo] ?? '' ?>" data-preview-box>
                <?php if ($url): ?><img src="<?= e($url) ?>" alt="<?= e($titulo) ?> actual"><?php else: ?><span class="muted small"><?= $tipo === 'fondo' ? 'Sin imagen' : 'Predeterminado' ?></span><?php endif; ?>
            </div>
            <?php if ($editable): ?>
            <div class="stack grow">
                <form method="post" action="<?= e(url('configuracion/marca')) ?>" enctype="multipart/form-data" class="upload-form" data-upload
                      data-ext="<?= e(implode(',', $lim['ext'])) ?>" data-max-mb="<?= (int) $lim['mb'] ?>" data-formatos="<?= e(App\Domain\Marca::formatos($tipo)) ?>">
                    <?= csrf_field() ?><input type="hidden" name="accion" value="subir_<?= e($tipo) ?>">
                    <label class="btn btn-secondary file-pick">
                        <?= icon('image', 16) ?> Elegir imagen
                        <input type="file" name="archivo" class="sr-only" accept="<?= e(App\Domain\Marca::accept($tipo)) ?>" required>
                    </label>
                    <span class="file-name muted" data-file-name aria-live="polite">Ninguna imagen elegida</span>
                    <button type="submit" class="btn btn-primary" data-upload-btn><?= icon('upload', 16) ?> Subir y guardar</button>
                    <p class="field-error" data-upload-error role="alert" hidden></p>
                </form>
                <?php if ($url): ?>
                <form method="post" action="<?= e(url('configuracion/marca')) ?>" data-confirm="<?= $tipo === 'fondo' ? 'El estilo cristal usará un fondo del color de acento.' : 'Se volverá a usar la imagen predeterminada.' ?>" data-confirm-title="Quitar <?= e(mb_strtolower($titulo)) ?>" data-confirm-label="Quitar">
                    <?= csrf_field() ?><input type="hidden" name="accion" value="quitar_<?= e($tipo) ?>">
                    <button type="submit" class="btn btn-ghost btn-sm"><?= icon('trash', 14) ?> <?= $tipo === 'fondo' ? 'Quitar la imagen' : 'Quitar y usar el predeterminado' ?></button>
                </form>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </section>
<?php };
App\Core\View::partial('configuracion/_tabs', ['seccion' => $seccion]);
?>
<form method="post" action="<?= e(url('configuracion/apariencia')) ?>" novalidate>
    <?= csrf_field() ?>
    <section class="form-section">
        <h2>Tema por defecto</h2>
        <p class="help">Es el tema que ve cada usuario hasta que elija otro en su perfil.</p>
        <div class="choice-grid" role="radiogroup" aria-label="Tema por defecto">
            <?php foreach ($temas as $k => [$nombre, $desc]): ?>
                <label class="choice"><input type="radio" name="tema_defecto" value="<?= e($k) ?>" <?= $tema === $k ? 'checked' : '' ?> <?= $editable ? '' : 'disabled' ?>>
                    <span><strong><?= e($nombre) ?></strong><span class="help"><?= e($desc) ?></span></span></label>
            <?php endforeach; ?>
        </div>
        <?= field_error($errors, 'tema_defecto') ?>
    </section>
    <section class="form-section" id="estilo" data-estilo-form data-fondo-url="<?= e(brand()['fondo'] ?? '') ?>">
        <h2>Estilo de la interfaz</h2>
        <p class="help">Se aplica a todo el sistema y a la pantalla de acceso. Mientras ajusta, esta página muestra el resultado; se guarda al pulsar «Guardar apariencia».</p>
        <div class="choice-grid" role="radiogroup" aria-label="Estilo de la interfaz">
            <?php foreach ($estilos as $k => [$nombre, $desc]): ?>
                <label class="choice"><input type="radio" name="estilo_interfaz" value="<?= e($k) ?>" <?= $estilo === $k ? 'checked' : '' ?> <?= $editable ? '' : 'disabled' ?>>
                    <span><strong><?= e($nombre) ?></strong><span class="help"><?= e($desc) ?></span></span></label>
            <?php endforeach; ?>
        </div>
        <?= field_error($errors, 'estilo_interfaz') ?>
        <?php foreach ($grupos as $grupo => $deslizadores): ?>
        <h3 class="glass-group"><?= e($grupo) ?></h3>
        <div class="glass-controls" data-cristal-controls>
            <?php foreach ($deslizadores as $k => [$label, $min, $max, $unidad, $ayuda]): ?>
                <div class="field range-field">
                    <label for="<?= e($k) ?>"><?= e($label) ?> <output for="<?= e($k) ?>" data-unidad="<?= e($unidad) ?>"><?= (int) old($k, (string) $valores[$k]) . e($unidad) ?></output></label>
                    <input type="range" id="<?= e($k) ?>" name="<?= e($k) ?>" min="<?= $min ?>" max="<?= $max ?>" step="1" value="<?= (int) old($k, (string) $valores[$k]) ?>" <?= $editable ? '' : 'disabled' ?>>
                    <p class="help"><?= e($ayuda) ?></p>
                    <?= field_error($errors, $k) ?>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endforeach; ?>
        <p class="help glass-hint" data-cristal-hint<?= $estilo === 'cristal' ? ' hidden' : '' ?>>Estos controles se aplican con el estilo «Cristal».</p>
        <?php if (!brand()['fondo']): ?><p class="help">Sin imagen de fondo, el estilo cristal usa un fondo derivado del color de acento. Puede cargar una foto más abajo, en «Imagen de fondo».</p><?php endif; ?>
    </section>
    <section class="form-section">
        <h2>Color de acento</h2>
        <p class="help">Elija cualquier color. El sistema deriva el resto de la paleta (botones, enlaces, barra lateral) y ajusta el tono si hace falta para mantener el texto legible, en tema claro y oscuro.</p>
        <div class="accent-picker" data-accent-picker>
            <input type="color" id="color_acento" name="color_acento" value="<?= e($colorHex) ?>" aria-label="Selector de color" <?= $editable ? '' : 'disabled' ?>>
            <div class="field">
                <label for="color_hex">Valor hexadecimal</label>
                <input type="text" id="color_hex" value="<?= e($colorHex) ?>" maxlength="7" pattern="#[0-9a-fA-F]{6}" spellcheck="false" autocomplete="off" <?= $editable ? '' : 'disabled' ?>>
            </div>
            <div class="accent-presets" role="group" aria-label="Colores sugeridos">
                <?php foreach ($acentos as $k => $nombre): ?>
                    <button type="button" class="swatch-btn" data-color="<?= e(App\Core\Color::PRESETS[$k]) ?>" aria-label="<?= e($nombre) ?>" title="<?= e($nombre) ?>" <?= $editable ? '' : 'disabled' ?>><span class="swatch swatch-<?= e($k) ?>"></span></button>
                <?php endforeach; ?>
            </div>
            <div class="accent-preview" data-accent-preview aria-hidden="true">
                <span class="btn btn-primary btn-sm">Botón</span>
                <span class="badge badge-info">Estado</span>
                <span class="accent-link">Enlace</span>
            </div>
        </div>
        <?= field_error($errors, 'color_acento') ?>
    </section>
    <section class="form-section" id="barra" data-barra-form>
        <h2>Barra lateral del menú</h2>
        <p class="help">Color del menú de la izquierda. No cambia con el estilo cristal: conserva el color que elija aquí. El texto y los iconos se ajustan solos para leerse sobre cualquier color.</p>
        <?php $barra = App\Domain\Marca::barra(App\Core\Config::all()); ?>
        <div class="choice-grid" role="radiogroup" aria-label="Barra lateral">
            <?php foreach ([
                'automatico' => ['Automática', 'Oscura, con el matiz del color de acento.'],
                'claro' => ['Clara', 'Fondo claro con texto oscuro. Útil para logos oscuros.'],
                'personalizado' => ['Color personalizado', 'Elija el color exacto de la barra.'],
            ] as $k => [$n, $d]): ?>
                <label class="choice"><input type="radio" name="barra_modo" value="<?= e($k) ?>" <?= $barra['modo'] === $k ? 'checked' : '' ?> <?= $editable ? '' : 'disabled' ?>><span><strong><?= e($n) ?></strong><span class="help"><?= e($d) ?></span></span></label>
            <?php endforeach; ?>
        </div>
        <div class="accent-picker barra-color" data-barra-color <?= $barra['modo'] === 'personalizado' ? '' : 'hidden' ?>>
            <input type="color" id="barra_color" name="barra_color" value="<?= e($barra['color']) ?>" aria-label="Color de la barra lateral" <?= $editable ? '' : 'disabled' ?>>
            <div class="field">
                <label for="barra_hex">Valor hexadecimal</label>
                <input type="text" id="barra_hex" value="<?= e($barra['color']) ?>" maxlength="7" pattern="#[0-9a-fA-F]{6}" spellcheck="false" autocomplete="off" <?= $editable ? '' : 'disabled' ?>>
            </div>
        </div>
        <?= field_error($errors, 'barra_color') ?>
    </section>
    <?php if ($editable): ?><div class="form-actions"><button type="submit" class="btn btn-primary">Guardar apariencia</button></div><?php endif; ?>
</form>

<?php $subida('fondo', 'Imagen de fondo', App\Domain\Marca::formatos('fondo') . ', hasta ' . App\Domain\Marca::LIMITES['fondo']['mb'] . ' MB. Se usa con el estilo «Cristal». Una foto horizontal de al menos 1920 × 1080 px se ve mejor; se reduce sola a 2560 px.', brand()['fondo']); ?>
<?php $subida('logo', 'Logo', App\Domain\Marca::formatos('logo') . ', hasta ' . App\Domain\Marca::LIMITES['logo']['mb'] . ' MB. Las imágenes grandes se reducen solas y conservan la transparencia. Se muestra en la barra lateral, el acceso y los PDF (el SVG no se imprime en PDF: use PNG si lo necesita ahí).', brand()['logo']); ?>
<?php $subida('favicon', 'Favicon', App\Domain\Marca::formatos('favicon') . ', hasta ' . App\Domain\Marca::LIMITES['favicon']['mb'] . ' MB. Se convierte a un icono cuadrado; ideal una imagen cuadrada de al menos 64 × 64 px.', !empty($cfg['favicon_archivo']) ? brand()['favicon'] : null); ?>

<?php App\Core\View::partial('configuracion/_fin'); ?>
