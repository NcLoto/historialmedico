<?php
// Logo institucional: el cargado en Configuración → Apariencia o, si no hay, la cruz hospitalaria por defecto.
$logo = brand()['logo'];
?>
<?php if ($logo): ?>
    <img class="brand-logo" src="<?= e($logo) ?>" alt="<?= e(brand()['nombre'] ?? '') ?>">
<?php else: ?>
<svg class="logo-mark" width="32" height="32" viewBox="0 0 32 32" aria-hidden="true">
    <rect width="32" height="32" rx="6" fill="currentColor"/>
    <path d="M13 7h6v6h6v6h-6v6h-6v-6H7v-6h6z" fill="#fff"/>
</svg>
<?php endif; ?>
