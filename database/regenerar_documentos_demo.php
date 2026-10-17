<?php
declare(strict_types=1);

/**
 * Vuelve a generar los PDF de demostración (datos ficticios) con el diseño actual de Configuración → Documentos.
 * El sistema lo hace solo al cambiar el diseño; este comando sirve tras actualizar o restaurar una copia.
 * Los documentos cargados desde fuera nunca se modifican.
 * Uso: php database/regenerar_documentos_demo.php
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require __DIR__ . '/../app/Core/Env.php';
require __DIR__ . '/../app/Core/App.php';
App\Core\App::bootCli();

$r = App\Domain\DocumentosDemo::regenerar();
echo "Regenerados: {$r['regenerados']}. Sin cambios (documentos reales o no PDF del sistema): {$r['omitidos']}.\n";
