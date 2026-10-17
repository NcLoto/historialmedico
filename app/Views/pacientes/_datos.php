<?php
$estadoCivil = ['soltero' => 'Soltero(a)', 'casado' => 'Casado(a)', 'unido' => 'Unido(a)', 'divorciado' => 'Divorciado(a)', 'viudo' => 'Viudo(a)'];
$v = fn($x) => ($x === null || $x === '') ? '—' : e($x);
?>
<div class="panel-body cols-2">
    <dl class="kv">
        <dt>Nombre completo</dt><dd><?= e(nombre_completo($p)) ?></dd>
        <dt>Código</dt><dd class="num"><?= e($p['codigo']) ?></dd>
        <dt>Documento</dt><dd class="num"><?= e($p['documento_identidad']) ?></dd>
        <dt>Nacimiento</dt><dd><?= e(fecha($p['fecha_nacimiento'])) ?> (<?= e(edad($p['fecha_nacimiento'])) ?>)</dd>
        <dt>Sexo</dt><dd><?= $p['sexo'] === 'F' ? 'Femenino' : 'Masculino' ?></dd>
        <dt>Tipo de sangre</dt><dd><?= $v($p['tipo_sangre']) ?></dd>
        <dt>Estado civil</dt><dd><?= $v($estadoCivil[$p['estado_civil']] ?? null) ?></dd>
        <dt>Ocupación</dt><dd><?= $v($p['ocupacion']) ?></dd>
    </dl>
    <dl class="kv">
        <dt>Teléfono</dt><dd><?= $v($p['telefono']) ?></dd>
        <dt>Correo</dt><dd><?= $v($p['email']) ?></dd>
        <dt>Dirección</dt><dd><?= $v($p['direccion']) ?></dd>
        <dt>Contacto de emergencia</dt><dd><?= $v($p['contacto_nombre']) ?><?= $p['contacto_parentesco'] ? ' (' . e($p['contacto_parentesco']) . ')' : '' ?></dd>
        <dt>Teléfono de emergencia</dt><dd><?= $v($p['contacto_telefono']) ?></dd>
        <dt>Cuenta de acceso</dt><dd><?= $p['usuario_id'] ? 'Con acceso al sistema' : 'Sin cuenta' ?></dd>
        <dt>Registrado</dt><dd><?= e(fecha($p['creado_en'])) ?></dd>
    </dl>
</div>
