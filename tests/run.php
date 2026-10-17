<?php
declare(strict_types=1);

/**
 * Pruebas funcionales y de seguridad contra el servidor local.
 *   php tests/run.php              reinstala la base (--fresh) y ejecuta todo
 *   php tests/run.php --sin-reinstalar
 * Requiere Apache y MySQL en ejecución y las contraseñas de demostración en .env.
 */

require __DIR__ . '/../app/Core/Env.php';
require __DIR__ . '/../app/Core/Database.php';
require __DIR__ . '/Http.php';
require __DIR__ . '/../app/Core/helpers.php';
// Carga automática de las clases del sistema (las suites usan dominio, correo y configuración directamente)
spl_autoload_register(function (string $class): void {
    if (str_starts_with($class, 'App\\')) {
        $file = __DIR__ . '/../app/' . str_replace('\\', '/', substr($class, 4)) . '.php';
        if (is_file($file)) {
            require $file;
        }
    }
});

use App\Core\Database;
use App\Core\Env;

Env::load(__DIR__ . '/../.env');
Http::$base = 'http://localhost' . rtrim(Env::get('APP_BASE_PATH', '/historialmedico'), '/');

/*
 * Las pruebas reinstalan la base. Antes se respaldan la base y los archivos subidos (documentos, logo, favicon)
 * y al terminar se restauran, aunque las pruebas fallen o se interrumpan. --sin-respaldo lo omite.
 */
$respaldo = null;
if (!in_array('--sin-reinstalar', $argv, true) && !in_array('--sin-respaldo', $argv, true)) {
    $respaldo = respaldar();
    register_shutdown_function(fn() => restaurar($respaldo));
}

function mysqlCmd(string $bin): string
{
    $pass = Env::get('DB_PASSWORD', '');
    return '"' . dirname(PHP_BINARY, 2) . '/mysql/bin/' . $bin . '.exe" -h' . escapeshellarg(Env::get('DB_HOST', '127.0.0.1')) . ' -P' . (int) Env::get('DB_PORT', '3306')
        . ' -u' . escapeshellarg(Env::get('DB_USERNAME', 'root')) . ($pass !== '' ? ' -p' . escapeshellarg($pass) : '');
}

function respaldar(): array
{
    $dir = __DIR__ . '/../storage/backups/pruebas-' . date('Ymd-His');
    @mkdir($dir, 0775, true);
    $db = Env::get('DB_DATABASE', 'sanitas_hme');
    exec(mysqlCmd('mysqldump') . ' --single-transaction --routines ' . escapeshellarg($db) . ' > ' . escapeshellarg("$dir/base.sql") . ' 2>NUL', $o, $code);
    if ($code !== 0 || filesize("$dir/base.sql") < 1000) {
        echo "No se pudo respaldar la base (código $code). Pruebas canceladas para no perder datos. Use --sin-respaldo si está seguro.\n";
        exit(1);
    }
    foreach (['documentos_medicos', 'branding', 'mail'] as $d) {
        @mkdir("$dir/$d", 0775, true);
        foreach (glob(__DIR__ . "/../storage/$d/*") ?: [] as $file) {
            copy($file, "$dir/$d/" . basename($file));
        }
    }
    echo "Respaldo de sus datos en storage/backups/" . basename($dir) . "\n";
    return ['dir' => $dir, 'db' => $db];
}

function restaurar(?array $r): void
{
    if (!$r) {
        return;
    }
    $db = $r['db'];
    exec(mysqlCmd('mysql') . ' -e ' . escapeshellarg("DROP DATABASE IF EXISTS `$db`; CREATE DATABASE `$db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;") . ' 2>NUL');
    exec(mysqlCmd('mysql') . ' ' . escapeshellarg($db) . ' < ' . escapeshellarg($r['dir'] . '/base.sql') . ' 2>NUL', $o, $code);
    foreach (['documentos_medicos', 'branding', 'mail'] as $d) {
        foreach (glob(__DIR__ . "/../storage/$d/*") ?: [] as $file) {
            if (basename($file) !== '.gitkeep') {
                unlink($file);
            }
        }
        foreach (glob($r['dir'] . "/$d/*") ?: [] as $file) {
            copy($file, __DIR__ . "/../storage/$d/" . basename($file));
        }
    }
    echo $code === 0 ? "\nSus datos se restauraron tal como estaban antes de las pruebas.\n" : "\nATENCIÓN: no se pudo restaurar la base automáticamente. El respaldo está en " . $r['dir'] . "\n";
}

if (!in_array('--sin-reinstalar', $argv, true)) {
    echo "Reinstalando base de datos…\n";
    exec('"' . PHP_BINARY . '" ' . escapeshellarg(__DIR__ . '/../database/install.php') . ' --fresh 2>&1', $out, $code);
    if ($code !== 0) {
        echo implode("\n", $out) . "\nNo se pudo reinstalar.\n";
        exit(1);
    }
}

final class T
{
    public static int $ok = 0;
    public static array $fail = [];
    public static string $group = '';

    public static function group(string $name): void
    {
        self::$group = $name;
        echo "\n== $name\n";
    }

    public static function ok(bool $cond, string $label, string $detail = ''): void
    {
        if ($cond) {
            self::$ok++;
            echo "  ok   $label\n";
        } else {
            self::$fail[] = self::$group . ': ' . $label;
            echo "  FALLA $label" . ($detail !== '' ? "  [$detail]" : '') . "\n";
        }
    }

    public static function status(array $r, int $expected, string $label): void
    {
        self::ok($r['status'] === $expected, $label, 'HTTP ' . $r['status'] . ', esperado ' . $expected);
    }

    public static function has(array $r, string $needle, string $label): void
    {
        self::ok(str_contains($r['body'], $needle), $label, 'no aparece «' . $needle . '»');
    }

    public static function hasNot(array $r, string $needle, string $label): void
    {
        self::ok(!str_contains($r['body'], $needle), $label, 'aparece «' . $needle . '»');
    }
}

function count_table(string $sql, array $p = []): int
{
    return (int) Database::value($sql, $p);
}

$ADMIN = ['admin@sanitas.local', Env::get('ADMIN_PASSWORD')];
$DEMO = Env::get('DEMO_PASSWORD');
$MEDICO = ['andres.ovalle@sanitas.local', $DEMO];
$MEDICO2 = ['rodrigo.pineda@sanitas.local', $DEMO];
$PACIENTE = ['carlos.ramirez@correo.test', $DEMO];     // paciente SAN-000101
$PACIENTE2 = ['mariajose.gomez@correo.test', $DEMO];   // paciente SAN-000102

function pid(string $codigo): int
{
    return (int) Database::value('SELECT id FROM pacientes WHERE codigo = ?', [$codigo]);
}

foreach (glob(__DIR__ . '/suites/*.php') as $suite) {
    require $suite;
}

echo "\n----------------------------------------\n";
echo 'Correctas: ' . T::$ok . '  Fallidas: ' . count(T::$fail) . "\n";
foreach (T::$fail as $f) {
    echo "  - $f\n";
}
exit(T::$fail ? 1 : 0);
