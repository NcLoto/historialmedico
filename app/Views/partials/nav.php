<?php
use App\Core\Auth;

// Un texto suelto en la lista es un encabezado de grupo; se oculta si el grupo queda vacío para el rol.
$rol = $user['rol_codigo'];
$items = match ($rol) {
    'paciente' => [
        ['/', 'home', 'Inicio', 'auth'],
        'Mi salud',
        ['/mi-historial', 'heart', 'Mi historial médico', 'historial.ver'],
        ['/consultas', 'stethoscope', 'Mis consultas', 'consultas.ver'],
        ['/documentos', 'folder', 'Mis documentos', 'documentos.ver'],
        'Cuenta',
        ['/notificaciones', 'bell', 'Notificaciones', 'auth'],
        ['/perfil', 'user', 'Mi perfil', 'auth'],
    ],
    'medico' => [
        ['/', 'home', 'Dashboard', 'auth'],
        'Atención',
        ['/pacientes', 'users', 'Pacientes', 'pacientes.ver'],
        ['/consultas', 'stethoscope', 'Consultas', 'consultas.ver'],
        ['/documentos', 'folder', 'Documentos', 'documentos.ver'],
        'Información',
        ['/reportes', 'chart', 'Reportes', 'reportes.ver'],
        'Cuenta',
        ['/notificaciones', 'bell', 'Notificaciones', 'auth'],
        ['/perfil', 'user', 'Mi perfil', 'auth'],
    ],
    default => [
        ['/', 'home', 'Dashboard', 'auth'],
        'Atención',
        ['/pacientes', 'users', 'Pacientes', 'pacientes.ver'],
        ['/medicos', 'stethoscope', 'Médicos', 'medicos.ver'],
        ['/documentos', 'folder', 'Documentos', 'documentos.ver'],
        'Administración',
        ['/usuarios', 'user', 'Usuarios', 'usuarios.ver'],
        ['/roles', 'key', 'Roles y permisos', 'roles.ver'],
        ['/auditoria', 'clipboard', 'Auditoría', 'auditoria.ver'],
        ['/reportes', 'chart', 'Reportes', 'reportes.ver'],
        ['/configuracion', 'settings', 'Configuración', 'configuracion.ver'],
        'Cuenta',
        ['/notificaciones', 'bell', 'Notificaciones', 'auth'],
        ['/perfil', 'user', 'Mi perfil', 'auth'],
    ],
};
$current = $currentPath ?? '/';
$isActive = function (string $href) use ($current): bool {
    if ($href === '/') {
        return $current === '/';
    }
    if ($href === '/mi-historial') {
        return $current === '/mi-historial' || preg_match('#^/pacientes/\d+#', $current) === 1;
    }
    return $current === $href || str_starts_with($current, $href . '/')
        || ($href === '/pacientes' && preg_match('#^/pacientes#', $current))
        || ($href === '/medicos' && str_starts_with($current, '/especialidades'));
};
$visibles = [];
$grupo = null;
foreach ($items as $item) {
    if (is_string($item)) {
        $grupo = $item;
        continue;
    }
    if ($item[3] !== 'auth' && !Auth::can($item[3])) {
        continue;
    }
    if ($grupo !== null) {
        $visibles[] = $grupo;
        $grupo = null;
    }
    $visibles[] = $item;
}
?>
<nav class="nav" aria-label="Navegación principal">
    <?php foreach ($visibles as $item):
        if (is_string($item)): ?>
            <span class="nav-group"><?= e($item) ?></span>
        <?php continue; endif;
        [$href, $ic, $label] = $item;
        $active = $isActive($href); ?>
        <a class="nav-item<?= $active ? ' is-active' : '' ?>" href="<?= e(url($href)) ?>"<?= $active ? ' aria-current="page"' : '' ?>>
            <?= icon($ic) ?><span><?= e($label) ?></span>
        </a>
    <?php endforeach; ?>
</nav>
