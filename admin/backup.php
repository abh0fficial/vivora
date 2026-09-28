<?php
/** Database backup: downloads a complete .sql file (restore it from hPanel → phpMyAdmin → Import). */
require __DIR__ . '/../includes/auth.php';
$admin = require_owner();

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    require_post();
    @set_time_limit(300);
    $pdo = db();
    $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    log_activity('backup', 'Downloaded database backup (' . count($tables) . ' tables)');
    header('Content-Type: application/sql; charset=utf-8');
    header('Content-Disposition: attachment; filename="vivora-backup-' . date('Y-m-d-His') . '.sql"');
    header('Cache-Control: no-store');
    echo "-- Vivora Healthcare dashboard backup\n-- Created " . date('Y-m-d H:i:s') . "\n-- Restore: hPanel → Databases → phpMyAdmin → Import\n\n";
    echo "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS = 0;\n\n";
    foreach ($tables as $t) {
        $create = $pdo->query("SHOW CREATE TABLE `$t`")->fetch(PDO::FETCH_NUM)[1];
        echo "DROP TABLE IF EXISTS `$t`;\n$create;\n\n";
        $st = $pdo->query("SELECT * FROM `$t`");
        $batch = [];
        while ($row = $st->fetch(PDO::FETCH_NUM)) {
            $batch[] = '(' . implode(',', array_map(fn($v) => $v === null ? 'NULL' : $pdo->quote((string) $v), $row)) . ')';
            if (count($batch) >= 200) {
                echo "INSERT INTO `$t` VALUES\n" . implode(",\n", $batch) . ";\n";
                $batch = [];
            }
        }
        if ($batch) echo "INSERT INTO `$t` VALUES\n" . implode(",\n", $batch) . ";\n";
        echo "\n";
    }
    echo "SET FOREIGN_KEY_CHECKS = 1;\n";
    exit;
}

$counts = [];
foreach (db()->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $t) {
    $counts[$t] = (int) q_val("SELECT COUNT(*) FROM `$t`");
}
$last = q_row("SELECT a.created_at, ad.username FROM activity_log a LEFT JOIN admins ad ON ad.id = a.admin_id WHERE a.action = 'backup' ORDER BY a.id DESC LIMIT 1");

$pageTitle = 'Backup';
$activeNav = 'backup';
$breadcrumbs = ['Backup' => null];
require __DIR__ . '/partials/header.php';
?>
<div class="row">
    <div class="col-xl-6">
        <div class="card">
            <div class="card-body p-5 text-center">
                <span class="avatar-text avatar-xl bg-soft-primary text-primary mx-auto mb-4" style="width:72px;height:72px;font-size:30px"><i class="feather-download-cloud"></i></span>
                <h4 class="fw-bold">Download a full backup</h4>
                <p class="text-muted">Everything in your dashboard — products, customers, invoices, payments, purchases, expenses, settings — in one <code>.sql</code> file. Keep a copy on your computer or Google Drive every week.</p>
                <form method="post"><?= csrf_field() ?><button class="btn btn-primary btn-lg"><i class="feather-download me-2"></i>Download backup now</button></form>
                <p class="fs-12 text-muted mt-3 mb-0">Last backup: <?= $last ? e(fmt_date($last['created_at'], 'd M Y, h:i A')) . ' by ' . e($last['username']) : 'never' ?></p>
            </div>
        </div>
        <div class="card"><div class="card-body fs-13">
            <strong>To restore:</strong> hPanel → Databases → phpMyAdmin → select your database → <em>Import</em> → choose the backup file → Go.
            Product images and brochures are files in <code>uploads/</code>; download that folder from File Manager too.
        </div></div>
    </div>
    <div class="col-xl-6">
        <div class="card">
            <div class="card-header"><h5 class="card-title">What will be backed up</h5></div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0"><?php foreach ($counts as $t => $n): ?><tr><td><code><?= e($t) ?></code></td><td class="text-end"><?= $n ?> rows</td></tr><?php endforeach; ?></table>
            </div>
        </div>
    </div>
</div>
<?php require __DIR__ . '/partials/footer.php';
