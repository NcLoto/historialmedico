<div class="page-head">
    <div><h1>Usuarios</h1></div>
    <div class="page-actions">
        <?php App\Core\View::partial('partials/menu_exportar', ['id' => 'exp-usuarios', 'base' => 'usuarios/exportar', 'query' => ['q' => $q, 'rol' => $rol, 'estado' => $estado]]); ?><?php if (can('usuarios.crear')): ?><a class="btn btn-primary" href="<?= e(url('usuarios/nuevo')) ?>"><?= icon('plus', 16) ?> Nuevo usuario</a><?php endif; ?></div>
</div>

<section class="panel">
    <form class="filters" method="get" action="<?= e(url('usuarios')) ?>">
        <div class="field grow"><label for="q">Buscar</label><input type="search" id="q" name="q" value="<?= e($q) ?>" placeholder="Nombre o correo"></div>
        <div class="field"><label for="rol">Rol</label><select id="rol" name="rol"><?= options($roles, $rol, 'Todos') ?></select></div>
        <div class="field"><label for="estado">Estado</label><select id="estado" name="estado"><?= options(['activos' => 'Activos', 'inactivos' => 'Inactivos'], $estado, 'Todos') ?></select></div>
        <button class="btn btn-secondary" type="submit">Filtrar</button>
    </form>
    <?php if (!$rows): ?>
        <div class="empty"><?= icon('user', 28) ?><strong>Sin resultados</strong><p>Ningún usuario coincide con los filtros.</p></div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table stackable">
                <thead><tr><th scope="col">Usuario</th><th scope="col">Rol</th><th scope="col">Estado</th><th scope="col">Último acceso</th><th scope="col"><span class="sr-only">Acciones</span></th></tr></thead>
                <tbody>
                <?php foreach ($rows as $u): $bloq = $u['bloqueado_hasta'] && strtotime($u['bloqueado_hasta']) > time(); ?>
                    <tr>
                        <td data-label="Usuario"><?php if (can('usuarios.editar')): ?><a class="cell-title" href="<?= e(url('usuarios/' . $u['id'] . '/editar')) ?>"><?php else: ?><span class="cell-title"><?php endif; ?><?= e($u['apellidos'] . ', ' . $u['nombres']) ?><?= (int) $u['id'] === $yo ? ' <span class="muted">(usted)</span>' : '' ?><?= can('usuarios.editar') ? '</a>' : '</span>' ?><span class="cell-sub"><?= e($u['email']) ?></span></td>
                        <td data-label="Rol"><?= e($u['rol']) ?></td>
                        <td data-label="Estado">
                            <?= $u['activo'] ? '<span class="badge badge-success">Activo</span>' : '<span class="badge">Inactivo</span>' ?>
                            <?= $bloq ? '<span class="badge badge-warning">Bloqueado</span>' : '' ?>
                            <?= $u['debe_cambiar_clave'] ? '<span class="badge badge-info">Clave temporal</span>' : '' ?>
                        </td>
                        <td data-label="Último acceso" class="num nowrap"><?= e(fecha_hora($u['ultimo_acceso'], 'Nunca')) ?></td>
                        <td class="col-actions">
                            <?php if (can('usuarios.editar') || (can('usuarios.eliminar') && (int) $u['id'] !== $yo)): ?>
                            <details class="dropdown row-menu">
                                <summary class="icon-btn" aria-label="Acciones para <?= e(nombre_completo($u)) ?>"><?= icon('more', 18) ?></summary>
                                <div class="dropdown-panel menu-list">
                                    <?php if (can('usuarios.editar')): ?>
                                        <a href="<?= e(url('usuarios/' . $u['id'] . '/editar')) ?>"><?= icon('edit', 15) ?> Editar</a>
                                        <a href="<?= e(url('usuarios/' . $u['id'] . '/clave')) ?>"><?= icon('key', 15) ?> Cambiar contraseña</a>
                                    <?php endif; ?>
                                    <?php if (can('usuarios.eliminar') && (int) $u['id'] !== $yo): ?>
                                        <form method="post" action="<?= e(url('usuarios/' . $u['id'] . '/estado')) ?>" data-confirm="<?= $u['activo'] ? 'El usuario ya no podrá ingresar al sistema. Sus registros se conservan.' : 'El usuario podrá ingresar nuevamente.' ?>"
                                              data-confirm-title="<?= $u['activo'] ? 'Desactivar usuario' : 'Activar usuario' ?>" data-confirm-label="<?= $u['activo'] ? 'Desactivar' : 'Activar' ?>">
                                            <?= csrf_field() ?><button type="submit"<?= $u['activo'] ? ' class="is-danger"' : '' ?>><?= icon($u['activo'] ? 'lock' : 'check', 15) ?> <?= $u['activo'] ? 'Desactivar cuenta' : 'Activar cuenta' ?></button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </details>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php App\Core\View::partial('partials/pagination', ['pager' => $pager, 'query' => ['q' => $q, 'rol' => $rol, 'estado' => $estado], 'path' => 'usuarios']); ?>
    <?php endif; ?>
</section>
