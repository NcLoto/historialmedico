<?php
// Foto de perfil de una cuenta, o sus iniciales si no tiene. $u: id, nombres, apellidos, foto_archivo; $clase: '', 'lg', 'xl'.
$clase = trim('user-initials ' . ($clase ?? ''));
$src = App\Domain\Avatar::url($u);
?>
<?php if ($src): ?><img class="<?= e($clase) ?> is-photo" src="<?= e($src) ?>" alt="<?= e($alt ?? '') ?>" width="96" height="96" decoding="async"<?= empty($alt) ? ' aria-hidden="true"' : '' ?>><?php else: ?><span class="<?= e($clase) ?>" aria-hidden="true"><?= e(iniciales(nombre_completo($u))) ?></span><?php endif; ?>
