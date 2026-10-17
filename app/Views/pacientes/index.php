<div class="page-head">
    <div>
        <h1>Pacientes</h1>
    </div>
    <div class="page-actions">
        <?php App\Core\View::partial('partials/menu_exportar', ['id' => 'exp-pacientes', 'base' => 'pacientes/exportar', 'query' => ['q' => $q, 'estado' => $estado]]); ?>
        <?php if (can('pacientes.crear')): ?>
            <a class="btn btn-primary" href="<?= e(url('pacientes/nuevo')) ?>"><?= icon('plus', 16) ?> Registrar paciente</a>
        <?php endif; ?>
    </div>
</div>

<section class="panel">
    <form class="filters" method="get" action="<?= e(url('pacientes')) ?>" role="search">
        <div class="field grow search-box">
            <label for="q" class="sr-only">Buscar paciente</label>
            <?= icon('search', 16) ?>
            <input type="search" id="q" name="q" value="<?= e($q) ?>" placeholder="Nombre, documento o código" autocomplete="off" autofocus
                   data-live-search="#resultados" data-live-url="<?= e(url('pacientes/buscar')) ?>">
        </div>
        <div class="field">
            <label for="estado" class="sr-only">Estado</label>
            <select id="estado" name="estado">
                <?= options(['activos' => 'Activos', 'inactivos' => 'Inactivos', 'todos' => 'Todos'], $estado, null) ?>
            </select>
        </div>
        <noscript><button class="btn btn-secondary" type="submit">Buscar</button></noscript>
    </form>
    <div id="resultados" aria-live="polite">
        <?php App\Core\View::partial('pacientes/_resultados', compact('q', 'estado', 'rows', 'pager', 'clinico')); ?>
    </div>
</section>
