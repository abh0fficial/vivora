<?php
require __DIR__ . '/../includes/auth.php';
$admin = require_admin();

// Delete straight from the list (with confirmation in the browser).
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && post('action') === 'delete') {
    require_post();
    $row = q_row('SELECT * FROM service_requests WHERE id = ?', [(int) post('id')]);
    if ($row) {
        q('DELETE FROM service_requests WHERE id = ?', [$row['id']]);
        log_activity('service ticket.delete', 'Deleted service ticket ' . $row['ticket_no']);
        flash('success', 'Service ticket ' . $row['ticket_no'] . ' deleted.');
    }
    redirect('admin/service-requests.php' . (!empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : ''));
}

$types = service_types();
$statuses = ['open', 'scheduled', 'in_progress', 'resolved', 'closed'];
$status = in_array(get('status'), $statuses, true) ? get('status') : '';
$type = array_key_exists(get('type'), $types) ? get('type') : '';
$where = [];
$params = [];
if ($status) { $where[] = 'status = ?'; $params[] = $status; }
if ($type) { $where[] = 'request_type = ?'; $params[] = $type; }
$rows = q_all('SELECT * FROM service_requests' . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
    . " ORDER BY FIELD(status,'open','scheduled','in_progress','resolved','closed'), FIELD(priority,'urgent','high','normal','low'), created_at DESC", $params);
$counts = ['' => (int) q_val('SELECT COUNT(*) FROM service_requests')];
foreach (q_all('SELECT status, COUNT(*) c FROM service_requests GROUP BY status') as $r) $counts[$r['status']] = (int) $r['c'];

$pageTitle = 'Service Requests';
$activeNav = 'service';
$breadcrumbs = ['Service Requests' => null];
$pageActions = '<a href="export.php?type=service" class="btn btn-light-brand"><i class="feather-download me-2"></i>Export CSV</a>'
    . '<a href="service-view.php" class="btn btn-primary"><i class="feather-plus me-2"></i>New Ticket</a>';
$extraCss = ['vendors/css/dataTables.bs5.min.css'];
$extraJs = ['vendors/js/dataTables.min.js', 'vendors/js/dataTables.bs5.min.js'];
require __DIR__ . '/partials/header.php';
$prioColor = ['urgent' => 'danger', 'high' => 'warning', 'normal' => 'info', 'low' => 'secondary'];
?>
<div class="card">
    <div class="card-header flex-wrap gap-2">
        <ul class="nav nav-pills gap-1">
            <?php foreach (array_merge([''], $statuses) as $s): ?>
                <li class="nav-item"><a class="nav-link py-1 px-3<?= $status === $s ? ' active' : '' ?>" href="service-requests.php?<?= http_build_query(array_filter(['status' => $s, 'type' => $type])) ?>"><?= $s ? ucwords(str_replace('_', ' ', $s)) : 'All' ?> <span class="badge bg-gray-200 text-dark ms-1"><?= $counts[$s] ?? 0 ?></span></a></li>
            <?php endforeach; ?>
        </ul>
        <form method="get" class="ms-auto">
            <?php if ($status): ?><input type="hidden" name="status" value="<?= e($status) ?>"><?php endif; ?>
            <select name="type" class="form-select form-select-sm" onchange="this.form.submit()">
                <option value="">All request types</option>
                <?php foreach ($types as $k => $v): ?><option value="<?= $k ?>" <?= $type === $k ? 'selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?>
            </select>
        </form>
    </div>
    <div class="card-body">
        <?php if (!$rows): ?>
            <div class="vh-empty"><i class="feather-tool"></i>No service requests yet. <a href="service-view.php">Create a ticket</a> for installations, repairs, AMC visits or calibration.</div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover" id="svcTable">
                <thead><tr><th>Ticket</th><th>Customer</th><th>Equipment</th><th>Type</th><th>Priority</th><th>Status</th><th>Engineer</th><th>Raised</th><th data-orderable="false"></th></tr></thead>
                <tbody>
                <?php foreach ($rows as $r): ?>
                    <tr>
                        <td><a href="service-view.php?id=<?= (int) $r['id'] ?>" class="fw-semibold"><?= e($r['ticket_no']) ?></a></td>
                        <td><span class="text-dark d-block"><?= e($r['organization'] ?: $r['name']) ?></span><span class="fs-12 text-muted"><?= e($r['phone']) ?><?= $r['city'] ? ' · ' . e($r['city']) : '' ?></span></td>
                        <td><?= e($r['equipment'] ?: '—') ?><?= $r['brand_model'] ? '<div class="fs-12 text-muted">' . e($r['brand_model']) . '</div>' : '' ?></td>
                        <td><?= e($types[$r['request_type']] ?? $r['request_type']) ?></td>
                        <td><span class="badge bg-soft-<?= $prioColor[$r['priority']] ?> text-<?= $prioColor[$r['priority']] ?>"><?= e(ucfirst($r['priority'])) ?></span></td>
                        <td><?= status_badge($r['status']) ?></td>
                        <td><?= e($r['engineer'] ?: '—') ?></td>
                        <td class="fs-12" data-order="<?= e($r['created_at']) ?>"><?= e(fmt_date($r['created_at'])) ?><br><span class="text-muted"><?= e(time_ago($r['created_at'])) ?></span></td>
                        <td class="text-end">
                            <div class="hstack gap-2 justify-content-end">
                                <a href="service-view.php?id=<?= (int) $r['id'] ?>" class="avatar-text avatar-md" title="Open / edit"><i class="feather-edit-3"></i></a>
                                <form method="post" class="m-0" data-confirm="Delete ticket <?= e($r['ticket_no']) ?>?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                                    <button class="avatar-text avatar-md border-0 text-danger" title="Delete"><i class="feather-trash-2"></i></button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php
$inlineJs = "if ($.fn.DataTable && $('#svcTable').length) { $('#svcTable').DataTable({ pageLength: 25, order: [] }); }";
require __DIR__ . '/partials/footer.php';
