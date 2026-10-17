<?php
// Abre el diseño de Configuración: navegación lateral + cuerpo. Cada vista lo cierra con partial('configuracion/_fin').
$tabs = [
    'institucion'    => ['configuracion', 'Institución', 'building', 'Nombre, contacto y horario'],
    'apariencia'     => ['configuracion/apariencia', 'Apariencia', 'eye', 'Tema, estilo, fondo y logo'],
    'documentos'     => ['configuracion/documentos', 'Documentos', 'printer', 'Diseño de impresos y archivos'],
    'notificaciones' => ['configuracion/notificaciones', 'Notificaciones', 'bell', 'Eventos, recordatorios y avisos'],
    'correo'         => ['configuracion/correo', 'Correo', 'mail', 'Envío, bandeja de salida y pruebas'],
    'seguridad'      => ['configuracion/seguridad', 'Seguridad', 'shield', 'Sesiones y bloqueo de cuentas'],
];
$actual = $tabs[$seccion];
?>
<div class="page-head">
    <div>
        <div class="breadcrumb"><a href="<?= e(url('configuracion')) ?>">Configuración</a> <?= icon('chevron', 12) ?> <span><?= e($actual[1]) ?></span></div>
        <h1><?= e($actual[1]) ?></h1>
    </div>
</div>
<div class="settings">
    <nav class="settings-nav" aria-label="Secciones de configuración">
        <?php foreach ($tabs as $k => [$href, $label, $ic, $desc]): ?>
            <a href="<?= e(url($href)) ?>" class="<?= $k === $seccion ? 'is-active' : '' ?>"<?= $k === $seccion ? ' aria-current="page"' : '' ?>>
                <?= icon($ic, 18) ?><span><strong><?= e($label) ?></strong><small><?= e($desc) ?></small></span>
            </a>
        <?php endforeach; ?>
    </nav>
    <div class="settings-body">
