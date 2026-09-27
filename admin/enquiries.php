<?php
require __DIR__ . '/../includes/auth.php';
$admin = require_admin();

$statuses = ['new', 'contacted', 'quoted', 'won', 'lost'];
$status = in_array(get('status'), $statuses, true) ? get('status') : '';
$search = get('q');

$where = [];
$params = [];
if ($status) { $where[] = 'e.status = ?'; $params[] = $status; }
if ($search !== '') {
    $where[] = '(e.name LIKE ? OR e.organization LIKE ? OR e.email LIKE ? OR e.phone LIKE ? OR e.city LIKE ? OR p.name LIKE ?)';
    array_push($params, ...array_fill(0, 6, "%$search%"));
}
$rows = q_all('SELECT e.*, p.name product_name FROM enquiries e LEFT JOIN products p ON p.id = e.product_id'
    . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY e.created_at DESC LIMIT 1000', $params);
$counts = ['' => (int) q_val('SELECT COUNT(*) FROM enquiries')];
foreach (q_all('SELECT status, COUNT(*) c FROM enquiries GROUP BY status') as $r) $counts[$r['status']] = (int) $r['c'];

$pageTitle = 'Enquiries';
$activeNav = 'enquiries';
$breadcrumbs = ['Enquiries' => null];
$pageActions = '<a href="export.php?type=enquiries" class="btn btn-light-brand"><i class="feather-download me-2"></i>Export CSV</a>';
$extraCss = ['vendors/css/dataTables.bs5.min.css'];
$extraJs = ['vendors/js/dataTables.min.js', 'vendors/js/dataTables.bs5.min.js'];
require __DIR__ . '/partials/header.php';
?>
<div class="card">
    <div class="card-header flex-wrap gap-2">
        <ul class="nav nav-pills gap-1">
            <?php foreach (array_merge([''], $statuses) as $s): ?>
                <li class="nav-item">
                    <a class="nav-link py-1 px-3<?= $status === $s ? ' active' : '' ?>" href="enquiries.php<?= $s ? '?status=' . $s : '' ?>">
                        <?= $s ? ucfirst($s) : 'All' ?> <span class="badge bg-gray-200 text-dark ms-1"><?= $counts[$s] ?? 0 ?></span>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
        <form method="get" class="ms-auto d-flex gap-2">
            <?php if ($status): ?><input type="hidden" name="status" value="<?= e($status) ?>"><?php endif; ?>
            <input type="search" name="q" class="form-control form-control-sm" placeholder="Search name, phone, city…" value="<?= e($search) ?>">
            <button class="btn btn-sm btn-primary"><i class="feather-search"></i></button>
        </form>
    </div>
    <div class="card-body">
        <?php if (!$rows): ?>
            <div class="vh-empty"><i class="feather-inbox"></i>No enquiries here yet. Customers can send enquiries from product pages, the “Request a Quote” page and the contact form on your website.</div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover" id="enqTable">
                <thead><tr><th>#</th><th>Customer</th><th>Contact</th><th>Product / Subject</th><th>Qty</th><th>Status</th><th>Received</th><th data-orderable="false"></th></tr></thead>
                <tbody>
                <?php foreach ($rows as $r): ?>
                    <tr class="<?= $r['status'] === 'new' ? 'fw-semibold' : '' ?>">
                        <td class="text-muted">#<?= (int) $r['id'] ?></td>
                        <td>
                            <a href="enquiry-view.php?id=<?= (int) $r['id'] ?>" class="text-dark d-block"><?= e($r['name']) ?></a>
                            <span class="fs-12 text-muted fw-normal"><?= e(trim($r['organization'] . ($r['city'] ? ', ' . $r['city'] : ''), ', ')) ?></span>
                        </td>
                        <td class="fs-12 fw-normal"><?= e($r['phone']) ?><br><span class="text-muted"><?= e($r['email']) ?></span></td>
                        <td class="fw-normal"><?= e($r['product_name'] ?: ($r['subject'] ?: ucfirst($r['source']) . ' enquiry')) ?></td>
                        <td class="fw-normal"><?= $r['quantity'] ? (int) $r['quantity'] : '—' ?></td>
                        <td><?= status_badge($r['status']) ?></td>
                        <td class="fs-12 fw-normal" data-order="<?= e($r['created_at']) ?>"><?= e(fmt_date($r['created_at'], 'd M Y')) ?><br><span class="text-muted"><?= e(time_ago($r['created_at'])) ?></span></td>
                        <td class="text-end"><a href="enquiry-view.php?id=<?= (int) $r['id'] ?>" class="avatar-text avatar-md"><i class="feather-arrow-right"></i></a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php
$inlineJs = "if ($.fn.DataTable && $('#enqTable').length) { $('#enqTable').DataTable({ pageLength: 25, order: [], searching: false }); }";
require __DIR__ . '/partials/footer.php';
