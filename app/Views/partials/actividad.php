<?php
/** @var array $filas @var bool $mostrarUsuario */
if (!$filas): ?>
    <div class="empty"><strong>Sin actividad registrada</strong></div>
<?php else: ?>
    <ul class="list">
        <?php foreach ($filas as $f): ?>
            <li>
                <div>
                    <span class="cell-title"><?= e($f['descripcion']) ?></span>
                    <span class="cell-sub"><?php if (!empty($mostrarUsuario)): ?><?= e($f['usuario_nombre']) ?><span class="sep"></span><?php endif; ?><?= $f["modulo"] === "autenticacion" ? "" : e(modulo_label($f["modulo"])) . '<span class="sep"></span>' ?><?= e(accion_label($f["accion"])) ?></span>
                </div>
                <span class="when"><?= e(fecha_hora($f['creado_en'])) ?></span>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>
