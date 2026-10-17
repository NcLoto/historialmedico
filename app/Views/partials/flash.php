<?php foreach ($flash as $f):
    $cls = ['success' => 'alert-success', 'error' => 'alert-error', 'warning' => 'alert-warning', 'info' => 'alert-info'][$f['type']] ?? 'alert-info';
    $ic = ['success' => 'check', 'error' => 'alert', 'warning' => 'alert', 'info' => 'info'][$f['type']] ?? 'info'; ?>
    <div class="alert <?= $cls ?>" role="<?= $f['type'] === 'error' ? 'alert' : 'status' ?>">
        <?= icon($ic, 18) ?><span><?= e($f['message']) ?></span>
    </div>
<?php endforeach; ?>
