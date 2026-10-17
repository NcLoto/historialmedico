<?php if (!$rows): ?>
    <div class="empty">
        <?= icon('search', 28) ?>
        <?php if ($q !== ''): ?>
            <strong>Sin resultados para «<?= e($q) ?>»</strong>
            <p>Revise la ortografía, pruebe con solo el apellido o con el número de documento. También puede incluir pacientes inactivos.</p>
        <?php else: ?>
            <strong>No hay pacientes en esta lista</strong>
            <p>Registre al primer paciente para comenzar su historial médico.</p>
            <?php if (can('pacientes.crear')): ?><a class="btn btn-primary" href="<?= e(url('pacientes/nuevo')) ?>">Registrar paciente</a><?php endif; ?>
        <?php endif; ?>
    </div>
<?php else: ?>
    <div class="table-wrap">
        <table class="table stackable">
            <thead>
                <tr>
                    <th scope="col">Código</th><th scope="col">Paciente</th><th scope="col">Documento</th><th scope="col">Edad</th><th scope="col">Sexo</th><th scope="col">Teléfono</th>
                    <?php if ($clinico): ?><th scope="col">Última consulta</th><?php endif; ?>
                    <th scope="col">Portal</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $p): ?>
                <tr data-href="<?= e(url('pacientes/' . $p['id'])) ?>">
                    <td data-label="Código" class="num nowrap"><?= e($p['codigo']) ?></td>
                    <td data-label="Paciente">
                        <a class="cell-title" href="<?= e(url('pacientes/' . $p['id'])) ?>"><?= e($p['apellidos'] . ', ' . $p['nombres']) ?></a>
                        <?php if (!$p['activo']): ?><span class="badge">Inactivo</span><?php endif; ?>
                        <?php if ((int) $p['alergias_graves'] > 0): ?><span class="badge badge-critical"><?= icon('alert', 12) ?> Alergia grave</span><?php endif; ?>
                    </td>
                    <td data-label="Documento" class="num"><?= e($p['documento_identidad']) ?></td>
                    <td data-label="Edad" class="num nowrap"><?= e(edad($p['fecha_nacimiento'])) ?></td>
                    <td data-label="Sexo"><?= $p['sexo'] === 'F' ? 'F' : 'M' ?></td>
                    <td data-label="Teléfono" class="num nowrap"><?= e($p['telefono'] ?: '—') ?></td>
                    <?php if ($clinico): ?><td data-label="Última consulta" class="num"><?= e(fecha($p['ultima_consulta'], 'Sin consultas')) ?></td><?php endif; ?>
                    <td data-label="Portal"><?= $p['usuario_id'] ? 'Activo' : '<span class="muted">No</span>' ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php App\Core\View::partial('partials/pagination', ['pager' => $pager, 'query' => ['q' => $q, 'estado' => $estado], 'path' => 'pacientes']); ?>
<?php endif; ?>
