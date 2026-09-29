<?php
require __DIR__ . '/../includes/auth.php';
$admin = require_owner();

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    require_post();
    $log = vivora_schema_repair(db());
    log_activity('system.repair', $log ? 'Repaired database: ' . implode(' ', $log) : 'Database check: nothing to repair');
    flash('success', $log ? 'Database repaired: ' . implode(' ', $log) : 'Database is already complete — nothing to repair.');
    redirect('admin/system.php');
}

$status = vivora_schema_status(db());
$problems = 0;
foreach ($status as $s) {
    if (!$s['exists'] || $s['missing'] || $s['changed']) $problems++;
}
$dbVersion = (string) q_val('SELECT VERSION()');
$uploadsOk = is_writable(APP_ROOT . '/uploads/products') && is_writable(APP_ROOT . '/uploads/brochures');
$defaultPw = password_verify('admin123', (string) q_val('SELECT password_hash FROM admins WHERE id = ?', [$admin['id']]));
$checks = [
    ['PHP version', PHP_VERSION, version_compare(PHP_VERSION, '8.0', '>='), 'PHP 8.0 or newer is required (8.2 recommended). Change it in hPanel → Advanced → PHP Configuration.'],
    ['MySQL / MariaDB', $dbVersion, true, ''],
    ['Database tables', $problems ? "$problems need repair" : count($status) . ' tables OK', $problems === 0, 'Click “Repair database” below.'],
    ['Upload folders writable', $uploadsOk ? 'Yes' : 'No', $uploadsOk, 'Set uploads/products and uploads/brochures to permission 755 in File Manager.'],
    ['File type detection (fileinfo)', function_exists('finfo_open') ? 'Enabled' : 'Missing', function_exists('finfo_open'), 'Enable the fileinfo extension in hPanel → PHP Configuration.'],
    ['Max upload size', ini_get('upload_max_filesize') . ' / post ' . ini_get('post_max_size'), true, ''],
    ['Installer removed', is_file(APP_ROOT . '/install.php') ? 'install.php still present' : 'Yes', true, ''],
    ['Admin password changed', $defaultPw ? 'Still admin123' : 'Yes', !$defaultPw, 'Change it in My Profile.'],
];

$pageTitle = 'System Check';
$activeNav = 'system';
$breadcrumbs = ['System Check' => null];
$pageActions = '<form method="post" class="m-0">' . csrf_field() . '<button class="btn btn-primary"><i class="feather-tool me-2"></i>Repair database</button></form>';
require __DIR__ . '/partials/header.php';
?>
<div class="row">
    <div class="col-xl-5">
        <div class="card stretch stretch-full">
            <div class="card-header"><h5 class="card-title">Server checks</h5></div>
            <div class="card-body p-0">
                <table class="table mb-0">
                    <?php foreach ($checks as [$label, $value, $ok, $hint]): ?>
                        <tr>
                            <td style="width:36px"><i class="feather-<?= $ok ? 'check-circle text-success' : 'alert-triangle text-warning' ?>"></i></td>
                            <td><span class="fw-semibold text-dark"><?= e($label) ?></span><?php if (!$ok && $hint): ?><div class="fs-12 text-muted"><?= e($hint) ?></div><?php endif; ?></td>
                            <td class="text-end fs-12"><?= e($value) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            </div>
        </div>
    </div>
    <div class="col-xl-7">
        <div class="card stretch stretch-full">
            <div class="card-header">
                <h5 class="card-title">Database tables</h5>
                <?= $problems ? '<span class="badge bg-soft-danger text-danger">' . $problems . ' need repair</span>' : '<span class="badge bg-soft-success text-success">All ' . count($status) . ' tables OK</span>' ?>
            </div>
            <div class="card-body p-0">
                <table class="table table-hover mb-0">
                    <thead><tr><th>Table</th><th>Status</th><th class="text-end">Rows</th></tr></thead>
                    <tbody>
                    <?php foreach ($status as $table => $s): ?>
                        <tr>
                            <td class="fw-semibold text-dark"><code><?= e($table) ?></code></td>
                            <td>
                                <?php if (!$s['exists']): ?><span class="badge bg-soft-danger text-danger">Missing</span>
                                <?php elseif ($s['missing'] || $s['changed']): ?><span class="badge bg-soft-warning text-warning">Needs update: <?= e(implode(', ', array_merge($s['missing'], $s['changed']))) ?></span>
                                <?php else: ?><span class="badge bg-soft-success text-success">OK</span><?php endif; ?>
                            </td>
                            <td class="text-end"><?= $s['rows'] === null ? '—' : (int) $s['rows'] ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php require __DIR__ . '/partials/footer.php';
