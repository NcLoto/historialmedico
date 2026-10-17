<?php
declare(strict_types=1);

/**
 * Aplica las migraciones pendientes de database/migrations/*.sql (orden alfabético).
 * Cada archivo se registra en la tabla `migraciones`. La instalación inicial (install.php)
 * ya registra 000_schema_base. Uso: php database/migrate.php
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require __DIR__ . '/../app/Core/Env.php';
require __DIR__ . '/../app/Core/Database.php';

use App\Core\Database;
use App\Core\Env;

Env::load(__DIR__ . '/../.env');
$pdo = Database::connect();
$hechas = $pdo->query('SELECT nombre FROM migraciones')->fetchAll(PDO::FETCH_COLUMN);
$pendientes = 0;
foreach (glob(__DIR__ . '/migrations/*.sql') as $file) {
    $nombre = basename($file, '.sql');
    if (in_array($nombre, $hechas, true)) {
        continue;
    }
    $sql = preg_replace('/^\s*--.*$/m', '', (string) file_get_contents($file));
    foreach (preg_split('/;\s*\n/', $sql) as $stmt) {
        if (trim($stmt) !== '') {
            $pdo->exec($stmt);
        }
    }
    $pdo->prepare('INSERT INTO migraciones (nombre) VALUES (?)')->execute([$nombre]);
    echo "Aplicada: $nombre\n";
    $pendientes++;
}
echo $pendientes ? "Listo.\n" : "No hay migraciones pendientes.\n";
