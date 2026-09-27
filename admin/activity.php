<?php
require __DIR__ . '/../includes/auth.php';
$admin = require_admin();

$page = max(1, (int) get('page', '1'));
$per = 50;
$total = (int) q_val('SELECT COUNT(*) FROM activity_log');
$rows = q_all('SELECT a.*, ad.username FROM activity_log a LEFT JOIN admins ad ON ad.id = a.admin_id ORDER BY a.id DESC LIMIT ' . $per . ' OFFSET ' . (($page - 1) * $per));
$pages = max(1, (int) ceil($total / $per));

$pageTitle = 'Activity Log';
$activeNav = 'activity';
$breadcrumbs = ['Activity Log' => null];
require __DIR__ . '/partials/header.php';
?>
<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead><tr><th>When</th><th>User</th><th>Action</th><th>Details</th><th>IP</th></tr></thead>
                <tbody>
                <?php foreach ($rows as $r): ?>
                    <tr>
                        <td class="fs-12 text-nowrap"><?= e(fmt_date($r['created_at'], 'd M Y, h:i A')) ?><br><span class="text-muted"><?= e(time_ago($r['created_at'])) ?></span></td>
                        <td><?= e($r['username'] ?: 'System') ?></td>
                        <td><span class="badge bg-gray-200 text-dark"><?= e($r['action']) ?></span></td>
                        <td><?= e($r['details']) ?></td>
                        <td class="fs-12 text-muted"><?= e($r['ip']) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$rows): ?><tr><td colspan="5" class="text-center text-muted py-5">No activity yet.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php if ($pages > 1): ?>
        <div class="card-footer">
            <ul class="pagination mb-0">
                <?php for ($i = 1; $i <= $pages; $i++): ?>
                    <li class="page-item<?= $i === $page ? ' active' : '' ?>"><a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a></li>
                <?php endfor; ?>
            </ul>
        </div>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/partials/footer.php';
