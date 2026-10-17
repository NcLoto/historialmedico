<div class="page-head">
    <div><h1>Roles y permisos</h1></div>
</div>
<section class="panel">
    <div class="table-wrap">
        <table class="table stackable">
            <thead><tr><th scope="col">Rol</th><th scope="col">Descripción</th><th scope="col" class="right">Usuarios</th><th scope="col" class="right">Permisos</th><th scope="col"><span class="sr-only">Acciones</span></th></tr></thead>
            <tbody>
            <?php foreach ($rows as $r_): ?>
                <tr>
                    <td data-label="Rol"><span class="cell-title"><?= e($r_['nombre']) ?></span></td>
                    <td data-label="Descripción"><?= e($r_['descripcion']) ?></td>
                    <td data-label="Usuarios" class="right num"><?= (int) $r_['usuarios'] ?></td>
                    <td data-label="Permisos" class="right num"><?= (int) $r_['permisos'] ?> de <?= (int) $total ?></td>
                    <td class="col-actions"><a class="btn btn-secondary btn-sm" href="<?= e(url('roles/' . $r_['id'])) ?>"><?= can('roles.editar') ? 'Editar permisos' : 'Ver permisos' ?></a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
