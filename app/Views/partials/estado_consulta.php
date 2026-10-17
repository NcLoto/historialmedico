<?php
$map = ['programada' => ['badge-info', 'Programada'], 'realizada' => ['badge-success', 'Realizada'], 'cancelada' => ['', 'Cancelada']];
[$cls, $txt] = $map[$estado] ?? ['', etiqueta((string) $estado)];
?>
<span class="badge <?= $cls ?>"><?= e($txt) ?></span>
