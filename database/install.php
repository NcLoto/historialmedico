<?php
declare(strict_types=1);

/**
 * Instalador desde cero.
 *   php database/install.php            crea la base (si no existe), tablas, datos de referencia y demo
 *   php database/install.php --fresh    elimina y recrea la base de datos
 *   php database/install.php --sin-demo solo datos de referencia + cuenta de administrador
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/../app/Core/Env.php';
require __DIR__ . '/../app/Core/App.php';
App\Core\App::bootCli();

use App\Core\Database;
use App\Core\Env;

Env::load(__DIR__ . '/../.env');
$fresh = in_array('--fresh', $argv, true);
$demo  = !in_array('--sin-demo', $argv, true);
$db    = Env::get('DB_DATABASE', 'sanitas_hme');
if (!preg_match('/^[A-Za-z0-9_]+$/', $db)) {
    fwrite(STDERR, "DB_DATABASE contiene caracteres no permitidos.\n");
    exit(1);
}

function say(string $m): void
{
    echo $m . PHP_EOL;
}

function randomPassword(): string
{
    $chars = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $out = '';
    for ($i = 0; $i < 14; $i++) {
        $out .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $out;
}

function uuid4(): string
{
    $d = random_bytes(16);
    $d[6] = chr(ord($d[6]) & 0x0f | 0x40);
    $d[8] = chr(ord($d[8]) & 0x3f | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($d), 4));
}

function runSqlFile(PDO $pdo, string $file): void
{
    $sql = file_get_contents($file);
    $sql = preg_replace('/^\s*--.*$/m', '', $sql);
    foreach (preg_split('/;\s*\n/', $sql) as $stmt) {
        $stmt = trim($stmt);
        if ($stmt !== '') {
            $pdo->exec($stmt);
        }
    }
}

try {
    $root = Database::connect(false);
} catch (PDOException $e) {
    fwrite(STDERR, "No se pudo conectar a MySQL: " . $e->getMessage() . "\n");
    exit(1);
}

if ($fresh) {
    $root->exec("DROP DATABASE IF EXISTS `$db`");
    say("Base de datos $db eliminada.");
    // Los archivos de la instalación anterior quedarían huérfanos (sin fila en la base).
    foreach (['documentos_medicos', 'branding'] as $dirName) {
        foreach (glob(__DIR__ . '/../storage/' . $dirName . '/*') ?: [] as $old) {
            if (is_file($old) && basename($old) !== '.gitkeep') {
                unlink($old);
            }
        }
    }
}
$root->exec("CREATE DATABASE IF NOT EXISTS `$db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
$pdo = Database::connect();

if ($pdo->query("SHOW TABLES LIKE 'usuarios'")->fetch()) {
    fwrite(STDERR, "La base $db ya tiene tablas. Use --fresh para recrearla.\n");
    exit(1);
}

runSqlFile($pdo, __DIR__ . '/schema.sql');
say('Esquema creado.');
runSqlFile($pdo, __DIR__ . '/seed.sql');
say('Roles, permisos, catálogos y configuración cargados.');

$pdo->exec("INSERT INTO migraciones (nombre) VALUES ('000_schema_base')");
foreach (glob(__DIR__ . '/migrations/*.sql') as $migracion) {
    runSqlFile($pdo, $migracion);
    $pdo->prepare('INSERT INTO migraciones (nombre) VALUES (?)')->execute([basename($migracion, '.sql')]);
    say('Migración aplicada: ' . basename($migracion));
}

// ------------------------------------------------------------ Cuentas
$adminEmail = Env::get('ADMIN_EMAIL') ?: 'admin@sanitas.local';
$adminPass  = Env::get('ADMIN_PASSWORD') ?: randomPassword();
$demoPass   = Env::get('DEMO_PASSWORD') ?: randomPassword();

$ins = $pdo->prepare('INSERT INTO usuarios (rol_id, email, password_hash, nombres, apellidos) VALUES (?,?,?,?,?)');
$ins->execute([1, $adminEmail, password_hash($adminPass, PASSWORD_DEFAULT), 'Verónica', 'Castellanos Aguilar']);
$cuentas = [['Administrador', $adminEmail, $adminPass]];

if ($demo) {
    $demoUsers = [
        [2, 'andres.ovalle@sanitas.local',   'Andrés',   'Ovalle Fuentes'],
        [2, 'lucia.merida@sanitas.local',    'Lucía',    'Mérida Barrios'],
        [2, 'rodrigo.pineda@sanitas.local',  'Rodrigo',  'Pineda Ajú'],
        [2, 'marta.estrada@sanitas.local',   'Marta',    'Estrada Lemus'],
        [3, 'carlos.ramirez@correo.test',    'Carlos Humberto', 'Ramírez Soto'],
        [3, 'mariajose.gomez@correo.test',   'María José', 'Gómez Archila'],
        [3, 'pedro.tzul@correo.test',        'Pedro Alejandro', 'Tzul Cojtí'],
        [3, 'ana.barrios@correo.test',       'Ana Lucrecia', 'Barrios de León'],
        [3, 'roberto.cifuentes@correo.test', 'Roberto Esteban', 'Cifuentes Morales'],
        [3, 'gabriela.lopez@correo.test',    'Gabriela Fernanda', 'López Mazariegos'],
    ];
    $hash = password_hash($demoPass, PASSWORD_DEFAULT);
    foreach ($demoUsers as [$rol, $email, $nom, $ape]) {
        $ins->execute([$rol, $email, $hash, $nom, $ape]);
    }
    runSqlFile($pdo, __DIR__ . '/seed_demo.sql');
    say('Datos de demostración (ficticios) cargados.');
    installDemoDocuments($pdo);
    installDemoNotifications($pdo);
    $cuentas[] = ['Médico (demo)', 'andres.ovalle@sanitas.local', $demoPass];
    $cuentas[] = ['Paciente (demo)', 'carlos.ramirez@correo.test', $demoPass];
}

say('');
say('Instalación completada. Cuentas iniciales (anótelas, no se vuelven a mostrar):');
foreach ($cuentas as [$rol, $email, $pass]) {
    say(sprintf('  %-16s %-34s %s', $rol, $email, $pass));
}
if ($demo) {
    say('  (todas las cuentas demo comparten la misma contraseña de demostración)');
}

// ------------------------------------------------------------ Notificaciones de demostración
function installDemoNotifications(PDO $pdo): void
{
    $uid = fn(string $e) => (int) $pdo->query('SELECT id FROM usuarios WHERE email = ' . $pdo->quote($e))->fetchColumn();
    $pid = fn(string $c) => (int) $pdo->query('SELECT id FROM pacientes WHERE codigo = ' . $pdo->quote($c))->fetchColumn();
    $doc = fn(int $p) => (int) $pdo->query('SELECT id FROM documentos_medicos WHERE paciente_id = ' . $p . ' ORDER BY id LIMIT 1')->fetchColumn();
    $con = fn(int $p, string $estado) => (int) $pdo->query('SELECT id FROM consultas WHERE paciente_id = ' . $p . ' AND estado = ' . $pdo->quote($estado) . ' ORDER BY fecha_hora LIMIT 1')->fetchColumn();
    $p1 = $pid('SAN-000101');
    $p7 = $pid('SAN-000107');
    $carlos = $uid('carlos.ramirez@correo.test');
    $andres = $uid('andres.ovalle@sanitas.local');
    $rodrigo = $uid('rodrigo.pineda@sanitas.local');
    $admin = (int) $pdo->query('SELECT id FROM usuarios WHERE rol_id = 1 ORDER BY id LIMIT 1')->fetchColumn();

    $rows = [
        // usuario, tipo, nivel, título, mensaje, enlace, paciente, hace (horas), leída
        [$carlos, 'documento_nuevo', 'info', 'Nuevo documento en su historial', 'Perfil metabólico y HbA1c', '/documentos/' . $doc($p1), $p1, 20 * 24, true],
        [$carlos, 'consulta_registrada', 'exito', 'Su consulta del ' . date('d/m/Y', strtotime('-20 days')) . ' está disponible', 'Revisión de resultados de laboratorio', '/consultas/' . $con($p1, 'realizada'), $p1, 20 * 24, false],
        [$carlos, 'consulta_programada', 'info', 'Consulta programada', 'Control trimestral de diabetes e hipertensión · Dr(a). Andrés Ovalle Fuentes', '/consultas/' . $con($p1, 'programada'), $p1, 19 * 24, false],
        [$carlos, 'aviso', 'info', 'Nuevo horario de atención en laboratorio', 'A partir del lunes, el laboratorio clínico atiende de 6:30 a 18:00 horas.', '/notificaciones', null, 3, false],
        [$andres, 'alergia_grave', 'critico', 'Alergia grave: Penicilina', 'Carlos Humberto Ramírez Soto · Urticaria generalizada y edema de labios', '/pacientes/' . $p1 . '#alergias', $p1, 6, false],
        [$andres, 'documento_nuevo', 'info', 'Documento nuevo de Carlos Humberto Ramírez Soto', 'Perfil metabólico y HbA1c', '/documentos/' . $doc($p1), $p1, 20 * 24, true],
        [$andres, 'aviso', 'info', 'Nuevo horario de atención en laboratorio', 'A partir del lunes, el laboratorio clínico atiende de 6:30 a 18:00 horas.', '/notificaciones', null, 3, false],
        [$rodrigo, 'alergia_grave', 'critico', 'Alergia grave: Aspirina', 'Roberto Esteban Cifuentes Morales · Sangrado gastrointestinal', '/pacientes/' . $p7 . '#alergias', $p7, 30, false],
        [$admin, 'cuentas', 'info', 'Instalación completada', 'El sistema se instaló con datos de demostración ficticios.', '/configuracion', null, 0, false],
    ];
    $ins = $pdo->prepare('INSERT INTO notificaciones (usuario_id, tipo, nivel, titulo, mensaje, enlace, paciente_id, creado_en, leida_en) VALUES (?,?,?,?,?,?,?,?,?)');
    foreach ($rows as [$u, $tipo, $nivel, $titulo, $msg, $link, $p, $horas, $leida]) {
        if ($u) {
            $cuando = date('Y-m-d H:i:s', time() - $horas * 3600);
            $ins->execute([$u, $tipo, $nivel, $titulo, $msg, $link, $p ?: null, $cuando, $leida ? $cuando : null]);
        }
    }
    say('Notificaciones de demostración generadas.');
}

// ------------------------------------------------------------ Documentos de demostración
function installDemoDocuments(PDO $pdo): void
{
    $dir = __DIR__ . '/../storage/documentos_medicos';
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    $cat = fn(string $n) => (int) $pdo->query('SELECT id FROM categorias_documentos WHERE nombre = ' . $pdo->quote($n))->fetchColumn();
    $pac = fn(string $c) => (int) $pdo->query('SELECT id FROM pacientes WHERE codigo = ' . $pdo->quote($c))->fetchColumn();
    $con = function (int $p, string $motivo) use ($pdo) {
        $id = $pdo->query('SELECT id FROM consultas WHERE paciente_id = ' . $p . ' AND motivo = ' . $pdo->quote($motivo))->fetchColumn();
        return $id ? (int) $id : null;
    };
    $usr = fn(string $e) => (int) $pdo->query('SELECT id FROM usuarios WHERE email = ' . $pdo->quote($e))->fetchColumn();

    $p1 = $pac('SAN-000101');
    $p2 = $pac('SAN-000102');
    $p3 = $pac('SAN-000103');
    $p7 = $pac('SAN-000107');
    $p8 = $pac('SAN-000108');
    $docs = [
        [$p1, $con($p1, 'Revisión de resultados de laboratorio'), 'Química sanguínea', 'Perfil metabólico y HbA1c', 'Glucosa en ayunas 148 mg/dL, HbA1c 7.4 %', 'pdf', 'andres.ovalle@sanitas.local', 21,
            ['Hemoglobina glicosilada (HbA1c)' => '7.4 %  (ref. < 5.7 %)', 'Glucosa en ayunas' => '148 mg/dL  (ref. 70-100)', 'Colesterol total' => '212 mg/dL', 'LDL' => '131 mg/dL', 'Creatinina' => '0.9 mg/dL']],
        [$p3, $con($p3, 'Disnea de esfuerzo progresiva'), 'Informe médico', 'Informe de espirometría', 'Patrón obstructivo moderado', 'pdf', 'andres.ovalle@sanitas.local', 85,
            ['FEV1' => '58 % del predicho', 'FVC' => '78 % del predicho', 'FEV1/FVC' => '0.61', 'Interpretación' => 'Obstrucción moderada, respuesta parcial al broncodilatador']],
        [$p8, $con($p8, 'Resultados de densitometría'), 'Informe médico', 'Densitometría ósea', 'T-score -2.7 en columna lumbar', 'pdf', 'andres.ovalle@sanitas.local', 40,
            ['Columna lumbar L1-L4' => 'T-score -2.7', 'Cuello femoral' => 'T-score -1.9', 'Conclusión' => 'Osteoporosis en columna lumbar']],
        [$p7, null, 'Electrocardiograma', 'Electrocardiograma de control', 'Ritmo sinusal, sin cambios isquémicos agudos', 'png', 'rodrigo.pineda@sanitas.local', 70, []],
        [$p2, $con($p2, 'Tos y sibilancias de tres días'), 'Radiografía', 'Radiografía de tórax PA', 'Sin infiltrados. Hiperinsuflación leve', 'png', 'andres.ovalle@sanitas.local', 45, []],
    ];

    $ins = $pdo->prepare('INSERT INTO documentos_medicos (paciente_id, consulta_id, categoria_id, titulo, descripcion, fecha_documento, nombre_original, nombre_interno, extension, tipo_mime, tamano_bytes, sha256, cargado_por, cargado_en) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
    foreach ($docs as [$p, $c, $categoria, $titulo, $desc, $ext, $email, $dias, $lineas]) {
        $pac = $pdo->query('SELECT nombres, apellidos, codigo FROM pacientes WHERE id = ' . (int) $p)->fetch(PDO::FETCH_ASSOC);
        $med = $pdo->query('SELECT nombres, apellidos FROM usuarios WHERE email = ' . $pdo->quote($email))->fetch(PDO::FETCH_ASSOC) ?: null;
        $bytes = $ext === 'pdf' ? \App\Domain\DocumentosDemo::pdf($titulo, $pac, $med, date('Y-m-d 10:00:00', strtotime("-$dias days"))) : demoPng($titulo);
        $name = uuid4();
        file_put_contents("$dir/$name", $bytes);
        $fecha = date('Y-m-d', strtotime("-$dias days"));
        $original = strtolower(trim(preg_replace('/\W+/', '_', iconv('UTF-8', 'ASCII//TRANSLIT', $titulo)), '_')) . ".$ext";
        $ins->execute([$p, $c, $cat($categoria), $titulo, $desc, $fecha, $original, $name, $ext,
            $ext === 'pdf' ? 'application/pdf' : 'image/png', strlen($bytes), hash('sha256', $bytes), $usr($email), "$fecha 10:00:00"]);
    }
    say('Documentos de demostración generados (' . count($docs) . ').');
}

function demoPng(string $titulo): string
{
    $img = imagecreatetruecolor(640, 420);
    $bg = imagecolorallocate($img, 24, 28, 32);
    $line = imagecolorallocate($img, 120, 190, 170);
    $txt = imagecolorallocate($img, 200, 210, 215);
    imagefill($img, 0, 0, $bg);
    for ($x = 0; $x < 640; $x += 2) {
        $y = 210 + (int) (60 * sin($x / 18) * (($x % 80) < 12 ? 2.2 : 0.35));
        imagesetpixel($img, $x, $y, $line);
        imagesetpixel($img, $x, $y + 1, $line);
    }
    imagestring($img, 4, 16, 14, 'IMAGEN DE DEMOSTRACION - ' . iconv('UTF-8', 'ASCII//TRANSLIT', $titulo), $txt);
    imagestring($img, 3, 16, 392, 'Hospital Sanitas  |  datos ficticios', $txt);
    ob_start();
    imagepng($img);
    return (string) ob_get_clean();
}
