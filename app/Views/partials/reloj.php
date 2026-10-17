<?php
// Fecha y hora actuales; app.js las mantiene al minuto (sin JavaScript queda la hora de carga).
$dias = ['domingo', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado'];
$meses = ['', 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
$t = time();
?>
<time class="clock" data-clock datetime="<?= e(date('c', $t)) ?>"><?= e(ucfirst($dias[(int) date('w', $t)]) . ', ' . date('j', $t) . ' de ' . $meses[(int) date('n', $t)] . ' de ' . date('Y', $t) . ', ' . date('H:i', $t)) ?></time>
