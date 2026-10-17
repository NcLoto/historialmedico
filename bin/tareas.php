<?php
declare(strict_types=1);

/**
 * Tarea programada del sistema. Ejecútela cada 5 minutos (Programador de tareas de Windows o cron):
 *   - genera los recordatorios de consultas para todos los pacientes y médicos (entren o no al sistema);
 *   - envía la cola de correos pendientes (con reintentos y límite por hora);
 *   - depura correos antiguos de la bandeja de salida.
 * Uso: php bin/tareas.php            (todo)
 *      php bin/tareas.php --solo-correo
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require __DIR__ . '/../app/Core/Env.php';
require __DIR__ . '/../app/Core/App.php';
App\Core\App::bootCli();

use App\Core\Database;
use App\Core\Logger;
use App\Domain\Correo;
use App\Domain\Notificaciones;

// Un solo proceso a la vez: si la ejecución anterior sigue enviando, esta termina sin hacer nada
$lock = fopen(__DIR__ . '/../storage/tareas.lock', 'c');
if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
    echo "Otra ejecución está en curso.\n";
    exit(0);
}
$inicio = microtime(true);
try {
    $revisadas = in_array('--solo-correo', $argv, true) ? 0 : Notificaciones::recordatoriosProgramados();
    $r = Correo::procesar(100);
    $borrados = Correo::depurar();
    Database::run("INSERT INTO configuracion (clave, valor) VALUES ('tareas_ultima_ejecucion', ?) ON DUPLICATE KEY UPDATE valor = VALUES(valor)", [date('Y-m-d H:i:s')]);
    printf("[%s] Recordatorios revisados: %d · Correos enviados: %d · Fallidos: %d · Pendientes: %d%s · Depurados: %d · %.1f s\n",
        date('Y-m-d H:i:s'), $revisadas, $r['enviados'], $r['fallidos'], $r['pendientes'], $r['limite'] ? ' (límite por hora alcanzado)' : '', $borrados, microtime(true) - $inicio);
} catch (Throwable $e) {
    Logger::error('Fallo en la tarea programada', $e);
    fwrite(STDERR, 'Error: ' . $e->getMessage() . "\n");
    exit(1);
} finally {
    flock($lock, LOCK_UN);
}
