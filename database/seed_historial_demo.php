<?php
declare(strict_types=1);

/**
 * Historial de demostración de 12 meses (datos ficticios) para que dashboards, reportes y listados se vean como
 * en un hospital en funcionamiento. Agrega pacientes, consultas con signos vitales coherentes, diagnósticos CIE-10,
 * tratamientos, condiciones crónicas, alergias, medicación, vacunas y documentos (PDF reales generados).
 *
 * No modifica pacientes ni usuarios existentes. No inventa registros de auditoría (solo deja constancia de la carga).
 * Uso: php database/seed_historial_demo.php [--pacientes=120] [--forzar]
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
$root = dirname(__DIR__);
require $root . '/app/Core/Env.php';
require $root . '/app/Core/App.php';
App\Core\App::bootCli();

use App\Core\Database;
use App\Core\Env;
use App\Domain\DocumentosDemo;
use App\Core\Upload;

Env::load($root . '/.env');
$pdo = Database::connect();
$opt = getopt('', ['pacientes::', 'forzar']);
$nPac = max(10, min(400, (int) ($opt['pacientes'] ?? 120)));
$ya = $pdo->query("SELECT valor FROM configuracion WHERE clave = 'demo_historial'")->fetchColumn();
if ($ya && !isset($opt['forzar'])) {
    exit("El historial de demostración ya se cargó el $ya. Use --forzar para agregar otro lote.\n");
}
mt_srand(20261010);
$r = fn(int $a, int $b): int => mt_rand($a, $b);
$p = fn(float $prob): bool => mt_rand() / mt_getrandmax() < $prob;
$pick = fn(array $a) => $a[array_rand($a)];
$hoy = new DateTimeImmutable('today');
$ahora = new DateTimeImmutable();

// ------------------------------------------------------------ Catálogos (ficticios, verosímiles)
$nombresF = ['María José', 'Ana Lucía', 'Sofía', 'Valeria', 'Camila', 'Andrea', 'Gabriela', 'Lucía', 'Fernanda', 'Daniela', 'Paola', 'Karla', 'Mónica', 'Claudia', 'Silvia', 'Rosa María', 'Elena', 'Patricia', 'Marta Julia', 'Ingrid', 'Alejandra', 'Isabel', 'Carmen', 'Luisa Fernanda', 'Mariela', 'Jimena', 'Emily', 'Valentina', 'Regina', 'Ximena'];
$nombresM = ['José Luis', 'Carlos', 'Juan Pablo', 'Luis Fernando', 'Diego', 'Andrés', 'Jorge Mario', 'Kevin', 'Pablo', 'Mario Roberto', 'Óscar', 'Erick', 'Byron', 'Edgar', 'Francisco', 'Héctor', 'Julio César', 'Marvin', 'Sergio', 'Rodrigo', 'Gustavo', 'Javier', 'Santiago', 'Mateo', 'Sebastián', 'Daniel', 'Ángel', 'Emilio', 'Nicolás', 'Alejandro'];
$apellidos = ['García', 'López', 'Hernández', 'Pérez', 'Rodríguez', 'Morales', 'Castillo', 'Méndez', 'Juárez', 'Ramírez', 'Rivera', 'Gómez', 'Orellana', 'Barrios', 'Estrada', 'Cifuentes', 'Monzón', 'Tzul', 'Chávez', 'Cojtí', 'Ajú', 'Sandoval', 'Velásquez', 'Alvarado', 'Recinos', 'Fuentes', 'Arriaza', 'Batres', 'Solórzano', 'De León', 'Quiñónez', 'Marroquín', 'Paiz', 'Girón', 'Cabrera', 'Ochoa', 'Samayoa', 'Lemus', 'Mazariegos', 'Ixcot'];
$zonas = ['zona 1', 'zona 2', 'zona 5', 'zona 7', 'zona 10', 'zona 11', 'zona 12', 'zona 16', 'zona 18'];
$municipios = ['Ciudad de Guatemala', 'Mixco', 'Villa Nueva', 'San Miguel Petapa', 'Santa Catarina Pinula', 'Chinautla'];
$ocupaciones = ['Maestra', 'Contador', 'Comerciante', 'Ingeniero', 'Enfermera', 'Estudiante', 'Ama de casa', 'Abogada', 'Piloto', 'Albañil', 'Secretaria', 'Agricultor', 'Mecánico', 'Jubilado', 'Administrador', 'Vendedora'];
$sangre = ['O+' => 52, 'A+' => 24, 'B+' => 9, 'O-' => 5, 'AB+' => 3, 'A-' => 3, 'B-' => 2, 'AB-' => 2];

// Condiciones crónicas: [cie, nombre, especialidad, medicamento, dosis, frecuencia, efecto en signos]
$cronicas = [
    'hta'   => ['I10', 'Hipertensión arterial esencial', 1, 'Losartán', '50 mg', 'Una vez al día', ['pas' => 18]],
    'dm2'   => ['E11.9', 'Diabetes mellitus tipo 2 sin complicaciones', 1, 'Metformina', '850 mg', 'Cada 12 horas con alimentos', ['peso' => 9]],
    'dlp'   => ['E78.2', 'Dislipidemia mixta', 1, 'Atorvastatina', '20 mg', 'Una tableta por la noche', []],
    'hipot' => ['E03.9', 'Hipotiroidismo', 1, 'Levotiroxina', '50 mcg', 'En ayunas', []],
    'gast'  => ['K29.5', 'Gastritis crónica', 1, 'Omeprazol', '20 mg', 'En ayunas', []],
    'asma'  => ['J45.9', 'Asma bronquial', 2, 'Salbutamol inhalador', '100 mcg', 'Dos inhalaciones si hay dificultad para respirar', ['sat' => -2]],
    'fa'    => ['I48.9', 'Fibrilación auricular', 3, 'Apixabán', '5 mg', 'Cada 12 horas', ['fc' => 14]],
    'ic'    => ['I50.9', 'Insuficiencia cardíaca', 3, 'Furosemida', '40 mg', 'Una vez al día por la mañana', ['sat' => -2]],
    'sop'   => ['E28.2', 'Síndrome de ovario poliquístico', 4, 'Metformina', '500 mg', 'Cada 12 horas', ['peso' => 6]],
];
// Motivos de consulta por especialidad: [cie, diagnóstico, motivo, tratamientos [[tipo, desc, dosis, frecuencia, duración]], fiebre]
$agudos = [
    1 => [
        ['J06.9', 'Infección aguda de las vías respiratorias superiores', 'Tos, congestión nasal y malestar general', [['medicamento', 'Paracetamol', '500 mg', 'Cada 8 horas si hay fiebre o dolor', '3 días'], ['indicacion', 'Abundantes líquidos y reposo relativo', null, null, null]], true],
        ['N39.0', 'Infección de vías urinarias', 'Ardor al orinar y aumento de la frecuencia urinaria', [['medicamento', 'Nitrofurantoína', '100 mg', 'Cada 12 horas', '5 días'], ['procedimiento', 'Urocultivo de control', null, null, 'Al terminar el tratamiento']], false],
        ['A09', 'Gastroenteritis aguda', 'Diarrea y dolor abdominal desde hace dos días', [['medicamento', 'Sales de rehidratación oral', '1 sobre en 1 litro de agua', 'A libre demanda', '3 días']], true],
        ['M54.5', 'Lumbalgia', 'Dolor en la parte baja de la espalda al levantar peso', [['medicamento', 'Ibuprofeno', '400 mg', 'Cada 8 horas con alimentos', '5 días'], ['indicacion', 'Calor local y evitar cargar peso', null, null, '1 semana']], false],
        ['R51', 'Cefalea', 'Dolor de cabeza frecuente por las tardes', [['medicamento', 'Acetaminofén', '500 mg', 'Cada 8 horas si hay dolor', '3 días']], false],
        ['Z00.0', 'Examen médico general', 'Evaluación médica general y exámenes de rutina', [['procedimiento', 'Hematología, glucosa y perfil lipídico', null, null, 'Antes del próximo control']], false],
    ],
    2 => [
        ['Z00.1', 'Control de niño sano', 'Control de crecimiento y desarrollo', [['indicacion', 'Continuar esquema de vacunación', null, null, null]], false],
        ['J06.9', 'Infección aguda de las vías respiratorias superiores', 'Tos y secreción nasal desde hace tres días', [['medicamento', 'Acetaminofén suspensión', '160 mg/5 mL', 'Cada 6 horas si hay fiebre', '3 días']], true],
        ['H66.9', 'Otitis media aguda', 'Dolor de oído y fiebre', [['medicamento', 'Amoxicilina suspensión', '250 mg/5 mL', 'Cada 8 horas', '7 días']], true],
        ['A09', 'Gastroenteritis aguda', 'Vómitos y evacuaciones líquidas', [['medicamento', 'Suero oral', '50 mL por cada evacuación', 'Después de cada evacuación', '3 días']], true],
        ['L20.9', 'Dermatitis atópica', 'Resequedad y comezón en la piel', [['medicamento', 'Crema emoliente', 'Aplicación local', 'Dos veces al día', '2 semanas']], false],
        ['J21.9', 'Bronquiolitis aguda', 'Dificultad respiratoria leve y tos', [['indicacion', 'Lavados nasales y control en 48 horas', null, null, null]], true],
    ],
    3 => [
        ['R07.4', 'Dolor torácico no especificado', 'Molestia en el pecho al hacer esfuerzo', [['procedimiento', 'Electrocardiograma y prueba de esfuerzo', null, null, 'Esta semana']], false],
        ['R00.2', 'Palpitaciones', 'Sensación de latidos rápidos', [['procedimiento', 'Holter de 24 horas', null, null, 'Próxima semana']], false],
        ['I25.1', 'Cardiopatía isquémica crónica', 'Control cardiológico', [['medicamento', 'Ácido acetilsalicílico', '100 mg', 'Una vez al día', 'Continuo']], false],
        ['Z01.8', 'Evaluación cardiovascular preoperatoria', 'Evaluación antes de cirugía programada', [['indicacion', 'Riesgo quirúrgico bajo; puede continuar con su cirugía', null, null, null]], false],
    ],
    4 => [
        ['Z34.9', 'Control prenatal', 'Control prenatal', [['medicamento', 'Ácido fólico', '1 mg', 'Una vez al día', 'Hasta el parto'], ['procedimiento', 'Ultrasonido obstétrico', null, null, 'Próximo control']], false],
        ['Z01.4', 'Examen ginecológico de rutina', 'Chequeo ginecológico anual', [['procedimiento', 'Citología cervical (Papanicolaou)', null, null, 'Resultado en 15 días']], false],
        ['N76.0', 'Vaginitis aguda', 'Flujo vaginal y molestias', [['medicamento', 'Metronidazol', '500 mg', 'Cada 12 horas', '7 días']], false],
        ['N94.6', 'Dismenorrea', 'Dolor menstrual intenso', [['medicamento', 'Naproxeno', '550 mg', 'Cada 12 horas durante la menstruación', '3 días']], false],
    ],
];
$alergenos = [['Penicilina', 'medicamento', 'Urticaria generalizada'], ['Sulfas', 'medicamento', 'Erupción cutánea'], ['Ibuprofeno', 'medicamento', 'Broncoespasmo'], ['Mariscos', 'alimento', 'Inflamación de labios'], ['Maní', 'alimento', 'Urticaria'], ['Polvo', 'ambiental', 'Rinitis y estornudos'], ['Látex', 'otra', 'Dermatitis de contacto'], ['Huevo', 'alimento', 'Vómitos']];
$horario = ['07:30', '08:00', '08:20', '08:40', '09:00', '09:20', '09:40', '10:00', '10:20', '10:40', '11:00', '11:20', '11:40', '13:00', '13:20', '13:40', '14:00', '14:20', '14:40', '15:00', '15:20', '15:40', '16:00'];

// Médicos existentes por especialidad (1 Medicina interna, 2 Pediatría, 3 Cardiología, 4 Ginecología)
$medicos = [];
foreach ($pdo->query('SELECT m.id, m.especialidad_id, m.usuario_id, m.consultorio, u.nombres, u.apellidos, m.colegiado, e.nombre AS esp FROM medicos m JOIN usuarios u ON u.id = m.usuario_id JOIN especialidades e ON e.id = m.especialidad_id WHERE m.activo = 1') as $m) {
    // Índice interno por especialidad (el id de la tabla puede variar): 1 interna, 2 pediatría, 3 cardiología, 4 ginecología
    $clave = match (true) {
        str_starts_with($m['esp'], 'Medicina interna') => 1, str_starts_with($m['esp'], 'Pediatr') => 2,
        str_starts_with($m['esp'], 'Cardiolog') => 3, str_starts_with($m['esp'], 'Ginecolog') => 4, default => 0,
    };
    if ($clave) {
        $medicos[$clave] ??= $m;
    }
}
if (count($medicos) < 4) {
    exit("Se necesitan los cuatro médicos de demostración (una especialidad cada uno).\n");
}
$conf = $pdo->query("SELECT clave, valor FROM configuracion")->fetchAll(PDO::FETCH_KEY_PAIR);
$marca = ['nombre' => $conf['hospital_nombre'] ?? 'Hospital Sanitas', 'lema' => $conf['hospital_lema'] ?? '', 'contacto' => implode(' · ', array_filter([$conf['hospital_direccion'] ?? '', $conf['hospital_telefono'] ?? ''])), 'color' => '#0e7490'];

$dpis = array_flip($pdo->query('SELECT documento_identidad FROM pacientes')->fetchAll(PDO::FETCH_COLUMN));
$correos = array_flip($pdo->query('SELECT email FROM usuarios')->fetchAll(PDO::FETCH_COLUMN));
$sig = (int) substr((string) $pdo->query('SELECT MAX(codigo) FROM pacientes')->fetchColumn(), 4) + 1;
$hashDemo = password_hash(Env::get('DEMO_PASSWORD', bin2hex(random_bytes(12))), PASSWORD_DEFAULT);
$rolPaciente = (int) $pdo->query("SELECT id FROM roles WHERE codigo = 'paciente'")->fetchColumn();
$sinTilde = fn(string $s): string => strtolower(preg_replace('/[^a-z]/i', '', iconv('UTF-8', 'ASCII//TRANSLIT', $s)));
$pesoSangre = function () use ($sangre, $r): string {
    $t = $r(1, 100);
    foreach ($sangre as $k => $w) { if (($t -= $w) <= 0) { return $k; } }
    return 'O+';
};
/** Fecha y hora hábil aleatoria entre dos fechas (lunes a viernes, horario de consulta). */
$slot = function (DateTimeImmutable $desde, DateTimeImmutable $hasta) use ($r, $horario): DateTimeImmutable {
    $dias = max(0, (int) $desde->diff($hasta)->days);
    for ($i = 0; $i < 20; $i++) {
        $d = $desde->modify('+' . $r(0, $dias) . ' day');
        if ((int) $d->format('N') <= 5) {
            break;
        }
    }
    [$h, $mi] = explode(':', $horario[$r(0, count($horario) - 1)]);
    return $d->setTime((int) $h, (int) $mi);
};

$ins = [
    'usuario' => $pdo->prepare('INSERT INTO usuarios (rol_id, email, password_hash, nombres, apellidos, ultimo_acceso, creado_en) VALUES (?,?,?,?,?,?,?)'),
    'paciente' => $pdo->prepare('INSERT INTO pacientes (codigo, usuario_id, documento_identidad, nombres, apellidos, fecha_nacimiento, sexo, tipo_sangre, estado_civil, ocupacion, telefono, email, direccion, contacto_nombre, contacto_parentesco, contacto_telefono, creado_en) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'),
    'consulta' => $pdo->prepare('INSERT INTO consultas (paciente_id, medico_id, fecha_hora, estado, motivo, enfermedad_actual, presion_sistolica, presion_diastolica, frecuencia_cardiaca, temperatura, saturacion_o2, peso_kg, talla_cm, examen_fisico, plan, creado_por, creado_en) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'),
    'dx' => $pdo->prepare('INSERT INTO diagnosticos (consulta_id, codigo_cie10, descripcion, tipo) VALUES (?,?,?,?)'),
    'tx' => $pdo->prepare('INSERT INTO tratamientos (consulta_id, tipo, descripcion, dosis, frecuencia, duracion) VALUES (?,?,?,?,?,?)'),
    'enf' => $pdo->prepare('INSERT INTO enfermedades (paciente_id, nombre, codigo_cie10, fecha_diagnostico, estado, cronica, registrado_por, creado_en) VALUES (?,?,?,?,?,1,?,?)'),
    'med' => $pdo->prepare("INSERT INTO medicamentos (paciente_id, nombre, dosis, via, frecuencia, motivo, fecha_inicio, activo, registrado_por, creado_en) VALUES (?,?,?,?,?,?,?,1,?,?)"),
    'ale' => $pdo->prepare('INSERT INTO alergias (paciente_id, agente, tipo, reaccion, severidad, fecha_deteccion, registrado_por, creado_en) VALUES (?,?,?,?,?,?,?,?)'),
    'vac' => $pdo->prepare('INSERT INTO vacunas (paciente_id, vacuna, dosis, fecha_aplicacion, proxima_dosis, lote, centro, registrado_por, creado_en) VALUES (?,?,?,?,?,?,?,?,?)'),
    'doc' => $pdo->prepare('INSERT INTO documentos_medicos (paciente_id, consulta_id, categoria_id, titulo, descripcion, fecha_documento, nombre_original, nombre_interno, extension, tipo_mime, tamano_bytes, sha256, cargado_por, cargado_en) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)'),
];
@mkdir(Upload::dir(), 0775, true);

/** Informe PDF real de un examen (laboratorio o imagen), con el diseño de Configuración → Documentos. */
$documento = function (array $pac, array $med, DateTimeImmutable $f, string $tipo, array $perfil): array {
    $bytes = DocumentosDemo::pdf($tipo, $pac, $med, $f->format('Y-m-d H:i:s'), $perfil);
    $interno = Upload::uuid();
    file_put_contents(Upload::path($interno), $bytes);
    return [$interno, strlen($bytes), hash('sha256', $bytes)];
};

// ------------------------------------------------------------ Generación
$pdo->beginTransaction();
$tot = ['pacientes' => 0, 'cuentas' => 0, 'consultas' => 0, 'canceladas' => 0, 'programadas' => 0, 'documentos' => 0];
try {
    for ($n = 0; $n < $nPac; $n++) {
        // Edad: 22 % niños, 63 % adultos, 15 % mayores
        $t = $r(1, 100);
        $edad = $t <= 22 ? $r(0, 13) : ($t <= 85 ? $r(18, 64) : $r(65, 88));
        $sexo = $p(0.54) ? 'F' : 'M';
        $nombre = $pick($sexo === 'F' ? $nombresF : $nombresM);
        $ap = $pick($apellidos) . ' ' . $pick($apellidos);
        $nac = $hoy->modify('-' . $edad . ' year')->modify('-' . $r(0, 360) . ' day');
        do { $dpi = $r(1, 3) . str_pad((string) $r(0, 99999999), 8, '0', STR_PAD_LEFT) . '0101'; } while (isset($dpis[$dpi]));
        $dpis[$dpi] = true;
        // Perfil clínico
        $perfil = [];
        if ($edad >= 40 && $p(0.38)) { $perfil[] = 'hta'; }
        if ($edad >= 35 && $p(0.22)) { $perfil[] = 'dm2'; }
        if ($edad >= 40 && $p(0.25)) { $perfil[] = 'dlp'; }
        if ($edad >= 25 && $sexo === 'F' && $p(0.08)) { $perfil[] = 'hipot'; }
        if ($edad >= 18 && $p(0.12)) { $perfil[] = 'gast'; }
        if ($p($edad < 18 ? 0.14 : 0.05)) { $perfil[] = 'asma'; }
        if ($edad >= 60 && $p(0.12)) { $perfil[] = 'fa'; }
        if ($edad >= 62 && $p(0.08)) { $perfil[] = 'ic'; }
        if ($sexo === 'F' && $edad >= 18 && $edad <= 38 && $p(0.10)) { $perfil[] = 'sop'; }
        // Especialidad principal
        $esp = $edad < 14 ? 2 : (array_intersect($perfil, ['fa', 'ic']) ? 3 : ($sexo === 'F' && $edad <= 45 && $p(0.35) ? 4 : 1));
        $med = $medicos[$esp];

        // Registro: la mayoría son pacientes antiguos (registrados hace más de un año); algunos nuevos cada mes
        $t = $r(1, 100);
        $alta = $hoy->modify('-' . ($t <= 72 ? $r(370, 1800) : ($t <= 90 ? $r(91, 365) : $r(1, 90))) . ' day');
        $alta = $alta->setTime($r(7, 15), $r(0, 59));
        $codigo = sprintf('SAN-%06d', $sig++);
        $tel = $pick(['3', '4', '5']) . $r(100, 999) . '-' . str_pad((string) $r(0, 9999), 4, '0', STR_PAD_LEFT);
        $usuarioId = null;
        $email = null;
        if ($edad >= 18 && $p(0.68)) {
            $base = $sinTilde(explode(' ', $nombre)[0]) . '.' . $sinTilde(explode(' ', $ap)[0]);
            $email = $base . '@correo.test';
            for ($k = 2; isset($correos[$email]); $k++) { $email = $base . $k . '@correo.test'; }
            $correos[$email] = true;
            $acceso = $p(0.7) ? min($ahora, $hoy->modify('-' . $r(0, 40) . ' day')->setTime($r(7, 21), $r(0, 59)))->format('Y-m-d H:i:s') : null;
            $ins['usuario']->execute([$rolPaciente, $email, $hashDemo, $nombre, $ap, $acceso, $alta->modify('+' . $r(0, 20) . ' day')->format('Y-m-d H:i:s')]);
            $usuarioId = (int) $pdo->lastInsertId();
            $tot['cuentas']++;
        }
        $contacto = $pick($sexo === 'F' ? $nombresM : $nombresF) . ' ' . explode(' ', $ap)[0];
        $ins['paciente']->execute([
            $codigo, $usuarioId, $dpi, $nombre, $ap, $nac->format('Y-m-d'), $sexo, $pesoSangre(),
            $edad >= 18 ? $pick(['soltero', 'casado', 'casado', 'unido', 'divorciado', 'viudo']) : 'soltero',
            $edad >= 18 ? $pick($ocupaciones) : ($edad >= 5 ? 'Estudiante' : null), $tel, $email ?? ($p(0.4) ? $sinTilde($nombre) . $r(10, 99) . '@correo.test' : null),
            $r(1, 30) . 'a. avenida ' . $r(1, 25) . '-' . $r(10, 90) . ', ' . $pick($zonas) . ', ' . $pick($municipios),
            $contacto, $edad < 18 ? $pick(['Madre', 'Padre']) : $pick(['Esposo(a)', 'Hijo(a)', 'Hermano(a)', 'Madre']), $pick(['3', '4', '5']) . $r(100, 999) . '-' . str_pad((string) $r(0, 9999), 4, '0', STR_PAD_LEFT),
            $alta->format('Y-m-d H:i:s'),
        ]);
        $pid = (int) $pdo->lastInsertId();
        $pac = ['nombres' => $nombre, 'apellidos' => $ap, 'codigo' => $codigo];
        $tot['pacientes']++;

        // Antecedentes del historial
        foreach ($perfil as $c) {
            [$cie, $nom, , $fm, $dosis, $frec] = $cronicas[$c];
            $dx = $alta->modify('-' . $r(30, 2500) . ' day');
            $ins['enf']->execute([$pid, $nom, $cie, $dx->format('Y-m-d'), $p(0.6) ? 'controlada' : 'activa', $med['usuario_id'], $alta->format('Y-m-d H:i:s')]);
            $ins['med']->execute([$pid, $fm, $dosis, str_contains($fm, 'inhalador') ? 'inhalada' : 'oral', $frec, $nom, $dx->format('Y-m-d'), $med['usuario_id'], $alta->format('Y-m-d H:i:s')]);
        }
        if ($p(0.2)) {
            [$ag, $tipoA, $reac] = $pick($alergenos);
            $ins['ale']->execute([$pid, $ag, $tipoA, $reac, $pick(['leve', 'moderada', 'moderada', 'grave']), $alta->modify('-' . $r(100, 3000) . ' day')->format('Y-m-d'), $med['usuario_id'], $alta->format('Y-m-d H:i:s')]);
        }
        if ($edad < 14) {
            foreach ([['Pentavalente', 'Tercera dosis', 6], ['SPR (sarampión, paperas y rubéola)', 'Primera dosis', 12], ['Influenza estacional', 'Anual', 0]] as [$vac, $dosisV, $minMeses]) {
                if ($edad * 12 < $minMeses) { continue; }
                $ap1 = $hoy->modify('-' . $r(20, 330) . ' day');
                $prox = $vac === 'Influenza estacional' ? $ap1->modify('+1 year')->format('Y-m-d') : null;
                $ins['vac']->execute([$pid, $vac, $dosisV, $ap1->format('Y-m-d'), $prox, strtoupper(substr($sinTilde($vac), 0, 2)) . $r(24, 26) . '-' . $r(1000, 9999), $marca['nombre'], $medicos[2]['usuario_id'], $ap1->format('Y-m-d H:i:s')]);
            }
        }

        // Signos vitales de base (coherentes con edad, sexo y condición)
        $talla = $edad < 18 ? min(175, 52 + $edad * 6.2 + $r(-4, 4)) : ($sexo === 'F' ? $r(148, 168) : $r(160, 182));
        $peso = $edad < 18 ? max(3.5, ($edad < 1 ? 7 : $edad * 2.8 + 8) + $r(-2, 3)) : ($sexo === 'F' ? $r(52, 80) : $r(62, 92));
        foreach ($perfil as $c) { $peso += $cronicas[$c][6]['peso'] ?? 0; }
        $pas = $edad < 18 ? $r(92, 108) : $r(108, 124) + (in_array('hta', $perfil, true) ? $r(14, 26) : 0) + (int) (max(0, $edad - 50) / 4);
        $fcBase = $edad < 5 ? $r(100, 125) : ($edad < 14 ? $r(80, 100) : $r(62, 82)) + (in_array('fa', $perfil, true) ? 14 : 0);

        // Visitas en los últimos 12 meses: crónicos cada 2-3 meses, el resto 1 a 3 visitas
        $visitas = $perfil ? $r(4, 6) : ($edad < 14 ? $r(2, 4) : $r(1, 3));
        $inicio = max($alta, $hoy->modify('-360 day'));
        $fechas = [];
        for ($v = 0; $v < $visitas; $v++) {
            $fechas[] = $slot($inicio, $hoy->modify('-1 day'));
        }
        sort($fechas);
        $tend = $p(0.6) ? -1 : 1;   // los pacientes hipertensos en tratamiento tienden a mejorar
        foreach ($fechas as $i => $f) {
            $cronicaHoy = $perfil && ($i % 2 === 0 || !$p(0.3));
            $espVisita = $esp;
            if ($cronicaHoy && array_intersect($perfil, ['fa', 'ic']) && $esp !== 3) { $espVisita = 3; }
            $m = $medicos[$espVisita];
            if ($p(0.06)) {   // cancelada
                $ins['consulta']->execute([$pid, $m['id'], $f->format('Y-m-d H:i:s'), 'cancelada', 'Control médico', null, null, null, null, null, null, null, null, null, 'La consulta fue cancelada por el paciente.', $m['usuario_id'], $f->modify('-' . $r(3, 20) . ' day')->format('Y-m-d H:i:s')]);
                $tot['canceladas']++;
                continue;
            }
            if ($cronicaHoy) {
                $propias = array_values(array_filter($perfil, fn($x) => $cronicas[$x][2] === $espVisita || $espVisita === 1));
                $c = $pick($propias ?: $perfil);
                $caso = [$cronicas[$c][0], $cronicas[$c][1], 'Control de ' . mb_strtolower($cronicas[$c][1]), [['medicamento', $cronicas[$c][3], $cronicas[$c][4], $cronicas[$c][5], 'Continuo'], ['indicacion', 'Dieta balanceada, actividad física y control en 3 meses', null, null, null]], false];
            } else {
                $caso = $pick($agudos[$espVisita]);
                if ($espVisita === 4 && $caso[0] === 'Z34.9' && ($sexo !== 'F' || $edad > 42)) { $caso = $agudos[4][1]; }
            }
            [$cie, $dxNom, $motivo, $trat, $fiebre] = $caso;
            $pasV = $edad >= 3 ? max(85, $pas + $tend * $i * $r(1, 3) + $r(-6, 6)) : null;
            $temp = $fiebre && $p(0.7) ? $r(375, 388) / 10 : $r(362, 372) / 10;
            $sat = $r(95, 99) + (in_array('asma', $perfil, true) || in_array('ic', $perfil, true) ? -2 : 0);
            $pesoV = round($peso + ($edad < 18 ? $i * 0.4 : $r(-15, 15) / 10), 1);
            $ins['consulta']->execute([
                $pid, $m['id'], $f->format('Y-m-d H:i:s'), 'realizada', $motivo,
                $cronicaHoy ? 'Paciente en control de su enfermedad. Refiere apego al tratamiento.' : 'Cuadro de inicio reciente. ' . $motivo . '.',
                $pasV, $pasV ? (int) round($pasV * 0.64 + $r(-4, 4)) : null, $fcBase + $r(-6, 8) + ($fiebre ? 8 : 0), $temp, $sat, $pesoV, $talla,
                $fiebre ? 'Paciente febril, hidratado, sin signos de alarma.' : 'Paciente consciente, orientado, hidratado. Sin hallazgos patológicos relevantes.',
                $cronicaHoy ? 'Continuar tratamiento actual y control en 3 meses.' : 'Tratamiento indicado. Regresar si los síntomas persisten más de 72 horas.',
                $m['usuario_id'], $f->format('Y-m-d H:i:s'),
            ]);
            $cid = (int) $pdo->lastInsertId();
            $tot['consultas']++;
            $ins['dx']->execute([$cid, $cie, $dxNom, 'principal']);
            if ($cronicaHoy && count($perfil) > 1 && $p(0.5)) {
                $otra = $perfil[array_rand($perfil)];
                if ($cronicas[$otra][0] !== $cie) { $ins['dx']->execute([$cid, $cronicas[$otra][0], $cronicas[$otra][1], 'secundario']); }
            }
            foreach ($trat as [$tt, $desc, $dosis, $frec, $dur]) { $ins['tx']->execute([$cid, $tt, $desc, $dosis, $frec, $dur]); }
            // Documentos: exámenes de laboratorio en controles crónicos; imágenes ocasionales
            $docTipo = null;
            if ($cronicaHoy && $p(0.5)) { $docTipo = [2, 'Perfil metabólico y lipídico']; }
            elseif ($espVisita === 3 && $p(0.6)) { $docTipo = [7, 'Electrocardiograma']; }
            elseif ($espVisita === 4 && $cie === 'Z34.9' && $p(0.7)) { $docTipo = [5, 'Ultrasonido obstétrico']; }
            elseif (in_array($cie, ['J06.9', 'J21.9'], true) && $edad >= 18 && $p(0.15)) { $docTipo = [4, 'Radiografía de tórax']; }
            elseif ($cie === 'Z00.0' && $p(0.8)) { $docTipo = [1, 'Hematología y química sanguínea']; }
            if ($docTipo) {
                [$cat, $tit] = $docTipo;
                $fd = $f->modify('+' . $r(1, 4) . ' day')->setTime($r(8, 16), $r(0, 59));
                if ($fd > $ahora) { $fd = $ahora->modify('-' . $r(5, 120) . ' minute'); }
                [$interno, $tam, $sha] = $documento($pac, $m, $fd, $tit, $perfil);
                $ins['doc']->execute([$pid, $cid, $cat, $tit, 'Resultado adjuntado a la consulta del ' . $f->format('d/m/Y'), $fd->format('Y-m-d'), $sinTilde($tit) . '_' . $fd->format('Ymd') . '.pdf', $interno, 'pdf', 'application/pdf', $tam, $sha, $m['usuario_id'], $fd->format('Y-m-d H:i:s')]);
                $tot['documentos']++;
            }
        }
        // Próxima cita: crónicos y algunos agudos, en los próximos 45 días
        if ($perfil || $p(0.25)) {
            $m = $medicos[array_intersect($perfil, ['fa', 'ic']) ? 3 : $esp];
            $f = $slot($hoy->modify('+1 day'), $hoy->modify('+45 day'));
            $ins['consulta']->execute([$pid, $m['id'], $f->format('Y-m-d H:i:s'), 'programada', $perfil ? 'Control de ' . mb_strtolower($cronicas[$perfil[0]][1]) : 'Control médico', null, null, null, null, null, null, null, null, null, null, $m['usuario_id'], $ahora->format('Y-m-d H:i:s')]);
            $tot['programadas']++;
        }
    }

    // Agenda de hoy: unas citas por médico más tarde en el día
    $ids = $pdo->query("SELECT id FROM pacientes WHERE codigo >= 'SAN-000111' ORDER BY RAND(42) LIMIT 12")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($ids as $i => $pid) {
        $m = $medicos[$i % 4 + 1];
        $h = max((int) $ahora->format('G') + 1 + intdiv($i, 4), 8);
        if ($h > 17) { break; }
        $ins['consulta']->execute([$pid, $m['id'], $hoy->setTime($h, [0, 20, 40][$i % 3])->format('Y-m-d H:i:s'), 'programada', 'Control médico', null, null, null, null, null, null, null, null, null, null, $m['usuario_id'], $ahora->format('Y-m-d H:i:s')]);
        $tot['programadas']++;
    }

    $pdo->prepare("INSERT INTO configuracion (clave, valor, descripcion) VALUES ('demo_historial', ?, 'Fecha de carga del historial de demostración (datos ficticios)') ON DUPLICATE KEY UPDATE valor = VALUES(valor)")->execute([date('Y-m-d H:i')]);
    $pdo->prepare("INSERT INTO auditoria (usuario_id, usuario_nombre, rol, accion, modulo, descripcion) VALUES (NULL, 'Sistema', NULL, 'crear', 'configuracion', ?)")
        ->execute(['Historial de demostración de 12 meses cargado (datos ficticios): ' . $tot['pacientes'] . ' pacientes, ' . $tot['consultas'] . ' consultas, ' . $tot['documentos'] . ' documentos']);
    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    fwrite(STDERR, 'No se cargó el historial: ' . $e->getMessage() . "\n");
    exit(1);
}
foreach ($tot as $k => $v) {
    echo str_pad($k, 12) . $v . "\n";
}
echo "Listo. Datos ficticios para demostración.\n";
