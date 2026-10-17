<?php
declare(strict_types=1);

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Env;
use App\Core\Flash;

function e(mixed $v): string
{
    return htmlspecialchars((string) ($v ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function url(string $path = ''): string
{
    return rtrim(Env::get('APP_BASE_PATH', ''), '/') . '/' . ltrim($path, '/');
}

function asset(string $path): string
{
    $file = __DIR__ . '/../../public/' . ltrim($path, '/');
    $v = is_file($file) ? filemtime($file) : 1;
    return url($path) . '?v=' . $v;
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(Csrf::token()) . '">';
}

function can(string $permission): bool
{
    return Auth::can($permission);
}

function old(string $key, mixed $default = ''): string
{
    $v = Flash::oldValue($key, $default);
    return is_array($v) ? '' : (string) $v;
}

function field_error(array $errors, string $key): string
{
    return isset($errors[$key]) ? '<p class="field-error" role="alert">' . e($errors[$key]) . '</p>' : '';
}

function fecha(?string $d, string $vacio = '—'): string
{
    if (!$d) {
        return $vacio;
    }
    $t = strtotime($d);
    return $t ? date('d/m/Y', $t) : $vacio;
}

function fecha_hora(?string $d, string $vacio = '—'): string
{
    if (!$d) {
        return $vacio;
    }
    $t = strtotime($d);
    return $t ? date('d/m/Y H:i', $t) : $vacio;
}

function edad(?string $nacimiento): string
{
    if (!$nacimiento) {
        return '—';
    }
    $diff = (new DateTimeImmutable($nacimiento))->diff(new DateTimeImmutable('today'));
    if ($diff->y >= 2) {
        return $diff->y . ' años';
    }
    $meses = $diff->y * 12 + $diff->m;
    return $meses . ($meses === 1 ? ' mes' : ' meses');
}

function nombre_completo(array $r, string $n = 'nombres', string $a = 'apellidos'): string
{
    return trim(($r[$n] ?? '') . ' ' . ($r[$a] ?? ''));
}

function iniciales(string $nombre): string
{
    $partes = preg_split('/\s+/', trim($nombre));
    return mb_strtoupper(mb_substr($partes[0] ?? '', 0, 1) . mb_substr($partes[1] ?? '', 0, 1));
}

/** Icono SVG de trazo (24x24). Los trazos están definidos en Views/partials/icons.php. */
function icon(string $name, int $size = 18): string
{
    static $paths = null;
    $paths ??= require __DIR__ . '/../Views/partials/icons.php';
    $d = $paths[$name] ?? $paths['circle'];
    return '<svg class="icon" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $d . '</svg>';
}

/** <option> para un <select>. $opts: valor => etiqueta. */
function options(array $opts, mixed $selected = '', ?string $blank = ''): string
{
    $html = $blank !== null ? '<option value="">' . e($blank) . '</option>' : '';
    foreach ($opts as $value => $label) {
        $sel = (string) $value === (string) $selected ? ' selected' : '';
        $html .= '<option value="' . e($value) . '"' . $sel . '>' . e($label) . '</option>';
    }
    return $html;
}

function bytes_legible(int $b): string
{
    if ($b >= 1048576) {
        return number_format($b / 1048576, 1) . ' MB';
    }
    return max(1, (int) round($b / 1024)) . ' KB';
}

function ucfirst_mb(string $s): string
{
    return mb_strtoupper(mb_substr($s, 0, 1)) . mb_substr($s, 1);
}

function etiqueta(string $v): string
{
    // Los valores guardados en la base van sin tildes; aquí se muestran bien escritos.
    $tildes = ['medico' => 'médico', 'indicacion' => 'indicación', 'alimentacion' => 'alimentación', 'oftalmica' => 'oftálmica', 'topica' => 'tópica',
        'subcutanea' => 'subcutánea', 'patologico' => 'patológico', 'traumatico' => 'traumático', 'ginecoobstetrico' => 'ginecoobstétrico',
        'sueno' => 'sueño', 'tia' => 'tía', 'tio' => 'tío', 'critico' => 'crítico', 'exito' => 'éxito', 'quirurgico' => 'quirúrgico'];
    $texto = implode(' ', array_map(fn($p) => $tildes[$p] ?? $p, explode('_', $v)));
    return ucfirst_mb($texto);
}

function modulo_label(string $m): string
{
    return ['autenticacion' => 'Autenticación', 'auditoria' => 'Auditoría', 'configuracion' => 'Configuración', 'consultas' => 'Consultas',
        'documentos' => 'Documentos', 'historial' => 'Historial clínico', 'medicos' => 'Médicos', 'pacientes' => 'Pacientes',
        'roles' => 'Roles y permisos', 'seguridad' => 'Seguridad', 'usuarios' => 'Usuarios', 'reportes' => 'Reportes'][$m] ?? etiqueta($m);
}

function accion_label(string $a): string
{
    return ['login' => 'Inicio de sesión', 'logout' => 'Cierre de sesión', 'login_fallido' => 'Acceso fallido', 'login_bloqueado' => 'Cuenta bloqueada',
        'acceso_denegado' => 'Acceso denegado', 'recuperacion' => 'Recuperación', 'cambio_clave' => 'Cambio de contraseña', 'crear' => 'Creación',
        'editar' => 'Edición', 'eliminar' => 'Eliminación', 'consultar' => 'Consulta', 'cargar' => 'Carga', 'descargar' => 'Descarga',
        'permisos' => 'Permisos', 'exportar' => 'Exportación', 'estado' => 'Cambio de estado', 'error' => 'Error'][$a] ?? etiqueta($a);
}

/**
 * Trazo SVG suave que pasa por todos los puntos (interpolación cúbica monótona de Fritsch–Carlson): no inventa
 * picos ni valles entre dos datos, a diferencia de una curva Bézier libre. @param list<array{0:float,1:float}> $p
 */
function svg_curva(array $p): string
{
    $n = count($p);
    if ($n < 2) {
        return $n ? sprintf('M%.1f,%.1f', $p[0][0], $p[0][1]) : '';
    }
    $d = [];
    $m = [];
    for ($i = 0; $i < $n - 1; $i++) {
        $dx = $p[$i + 1][0] - $p[$i][0];
        $d[$i] = $dx != 0 ? ($p[$i + 1][1] - $p[$i][1]) / $dx : 0;
    }
    $m[0] = $d[0];
    $m[$n - 1] = $d[$n - 2];
    for ($i = 1; $i < $n - 1; $i++) {
        $m[$i] = $d[$i - 1] * $d[$i] <= 0 ? 0 : ($d[$i - 1] + $d[$i]) / 2;
    }
    for ($i = 0; $i < $n - 1; $i++) {
        if ($d[$i] == 0) {
            $m[$i] = $m[$i + 1] = 0;
            continue;
        }
        $a = $m[$i] / $d[$i];
        $b = $m[$i + 1] / $d[$i];
        $s = $a * $a + $b * $b;
        if ($s > 9) {
            $t = 3 / sqrt($s);
            $m[$i] = $t * $a * $d[$i];
            $m[$i + 1] = $t * $b * $d[$i];
        }
    }
    $out = sprintf('M%.1f,%.1f', $p[0][0], $p[0][1]);
    for ($i = 0; $i < $n - 1; $i++) {
        $h = ($p[$i + 1][0] - $p[$i][0]) / 3;
        $out .= sprintf(' C%.1f,%.1f %.1f,%.1f %.1f,%.1f', $p[$i][0] + $h, $p[$i][1] + $m[$i] * $h, $p[$i + 1][0] - $h, $p[$i + 1][1] - $m[$i + 1] * $h, $p[$i + 1][0], $p[$i + 1][1]);
    }
    return $out;
}

/** Fecha y hora en palabras: «lunes 14 de octubre a las 07:30» (correos y textos para personas). */
function fecha_larga(string $fechaHora): string
{
    $t = strtotime($fechaHora);
    $dias = ['domingo', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado'];
    $meses = ['', 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
    return $dias[(int) date('w', $t)] . ' ' . date('j', $t) . ' de ' . $meses[(int) date('n', $t)] . (date('Y', $t) !== date('Y') ? ' de ' . date('Y', $t) : '') . ' a las ' . date('H:i', $t);
}

/** Cantidad con su sustantivo en singular o plural: «1 consulta», «3 consultas» (sin «consulta(s)»). */
function cantidad(int $n, string $singular, string $plural): string
{
    return number_format($n, 0, ',', '.') . ' ' . ($n === 1 ? $singular : $plural);
}

/** Severidad clínica (leve, moderada, grave) como escala de tres segmentos seguida de la palabra. */
function severidad(string $nivel): string
{
    $n = ['leve' => 1, 'moderada' => 2, 'grave' => 3][$nivel] ?? 0;
    if (!$n) {
        return '';
    }
    return '<span class="sev sev-' . $nivel . '"><span class="sev-scale" aria-hidden="true"><i></i><i></i><i></i></span>'
        . e(\App\Domain\HistorialSections::SEVERIDAD[$nivel]) . '</span>';
}

/** Identidad institucional vigente: nombre, lema, logo, favicon y fondo (con versión para el caché). */
function brand(): array
{
    static $b = null;
    if ($b === null) {
        try {
            $c = \App\Core\Config::all();
        } catch (\Throwable) {
            $c = [];
        }
        $v = static fn(string $k): string => substr(md5($c[$k] ?? ''), 0, 8);
        $b = [
            'nombre'  => $c['hospital_nombre'] ?? 'Hospital Sanitas',
            'lema'    => $c['hospital_lema'] ?? 'Historial médico electrónico',
            'logo'    => !empty($c['logo_archivo']) ? url('marca/logo') . '?v=' . $v('logo_archivo') : null,
            'tema_css' => \App\Core\Color::isHex($c['color_acento'] ?? '') || (($c['estilo_interfaz'] ?? '') === 'cristal') || (($c['barra_modo'] ?? 'automatico') !== 'automatico')
                ? url('tema.css') . '?v=' . substr(md5(implode('|', array_map(fn($k) => $c[$k] ?? '', ['color_acento', 'estilo_interfaz', 'fondo_archivo', 'cristal_opacidad', 'cristal_desenfoque', 'fondo_oscurecer', 'cristal_intensidad', 'fondo_transparencia', 'fondo_luma', 'barra_modo', 'barra_color']))), 0, 8)
                : null,
            'fondo'   => !empty($c['fondo_archivo']) ? url('marca/fondo') . '?v=' . $v('fondo_archivo') : null,
            'favicon' => !empty($c['favicon_archivo']) ? url('marca/favicon') . '?v=' . $v('favicon_archivo') : url('images/favicon.svg'),
        ];
    }
    return $b;
}

/** Atributos de <html> con el tema, el color de acento y el estilo (sólido o cristal): preferencia del usuario o, si no hay, la del hospital. */
function ui_attrs(): string
{
    try {
        $cfg = \App\Core\Config::all();
        $pref = \App\Core\Auth::user()['tema'] ?? 'sistema';
        $tema = $pref !== 'sistema' ? $pref : ($cfg['tema_defecto'] ?? 'claro');
        $acento = $cfg['color_acento'] ?? 'cian';
        $estilo = ($cfg['estilo_interfaz'] ?? 'solido') === 'cristal' ? 'cristal' : 'solido';
        $fondo = !empty($cfg['fondo_archivo']);
    } catch (\Throwable) {
        $tema = 'claro';
        $acento = 'cian';
        $estilo = 'solido';
        $fondo = false;
    }
    $tema = in_array($tema, ['claro', 'oscuro', 'auto'], true) ? $tema : 'claro';
    $acento = \App\Core\Color::isHex($acento) ? 'personalizado' : (isset(\App\Core\Color::PRESETS[$acento]) ? $acento : 'cian');
    try {
        $barra = \App\Domain\Marca::barra(\App\Core\Config::all())['modo'];
    } catch (\Throwable) {
        $barra = 'automatico';
    }
    return ' data-theme="' . $tema . '" data-accent="' . $acento . '" data-estilo="' . $estilo . '"' . ($estilo === 'cristal' && $fondo ? ' data-fondo="1"' : '') . ($barra !== 'automatico' ? ' data-barra="' . $barra . '"' : '');
}
