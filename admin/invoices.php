<?php
require __DIR__ . '/../includes/auth.php';
$admin = require_admin();

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && post('action') === 'delete') {
    require_post();
    $inv = q_row('SELECT * FROM invoices WHERE id = ?', [(int) post('id')]);
    if ($inv && is_owner($admin)) {
        if ($inv['stock_applied']) {
            foreach (q_all('SELECT product_id, qty FROM invoice_items WHERE invoice_id = ?', [$inv['id']]) as $it) {
                adjust_stock($it['product_id'] ? (int) $it['product_id'] : null, (float) $it['qty'], "Invoice {$inv['invoice_no']} deleted", $admin['id']);
            }
        }
        q('DELETE FROM invoices WHERE id = ?', [$inv['id']]);
        log_activity('invoice.delete', "Deleted invoice {$inv['invoice_no']}");
        flash('success', "Invoice {$inv['invoice_no']} deleted.");
    } elseif ($inv) {
        flash('error', 'Only an administrator can delete invoices. Open the invoice and cancel it instead.');
    }
    redirect('admin/invoices.php' . (!empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : ''));
}

$status = in_array(get('status'), ['unpaid', 'partial', 'paid', 'cancelled', 'overdue', 'due'], true) ? get('status') : '';
$from = preg_match('/^\d{4}-\d{2}-\d{2}$/', get('from')) ? get('from') : '';
$to = preg_match('/^\d{4}-\d{2}-\d{2}$/', get('to')) ? get('to') : '';
$search = get('q');
$where = [];
$params = [];
if ($status === 'overdue') $where[] = "i.status IN ('unpaid','partial') AND i.due_date < CURDATE()";
elseif ($status === 'due') $where[] = "i.status IN ('unpaid','partial')";
elseif ($status) { $where[] = 'i.status = ?'; $params[] = $status; }
if ($from) { $where[] = 'i.invoice_date >= ?'; $params[] = $from; }
if ($to) { $where[] = 'i.invoice_date <= ?'; $params[] = $to; }
if ($search !== '') {
    $where[] = '(i.invoice_no LIKE ? OR i.customer_name LIKE ? OR i.customer_org LIKE ? OR i.customer_phone LIKE ? OR i.customer_gstin LIKE ?)';
    array_push($params, ...array_fill(0, 5, "%$search%"));
}
$rows = q_all('SELECT i.* FROM invoices i' . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY i.invoice_date DESC, i.id DESC LIMIT 2000', $params);

$fyStart = substr(financial_year(), 0, 4) . '-04-01';
$sum = q_row("SELECT
    COALESCE(SUM(CASE WHEN status <> 'cancelled' AND invoice_date >= DATE_FORMAT(CURDATE(), '%Y-%m-01') THEN grand_total END),0) month_sales,
    COALESCE(SUM(CASE WHEN status <> 'cancelled' AND invoice_date >= ? THEN grand_total END),0) fy_sales,
    COALESCE(SUM(CASE WHEN status IN ('unpaid','partial') THEN grand_total - amount_paid END),0) outstanding,
    COALESCE(SUM(CASE WHEN status IN ('unpaid','partial') AND due_date < CURDATE() THEN grand_total - amount_paid END),0) overdue,
    SUM(status IN ('unpaid','partial') AND due_date < CURDATE()) overdue_count
    FROM invoices", [$fyStart]);
$receivedMonth = (float) q_val("SELECT COALESCE(SUM(amount),0) FROM payments WHERE direction = 'in' AND payment_date >= DATE_FORMAT(CURDATE(), '%Y-%m-01')");

$pageTitle = 'Invoices';
$activeNav = 'invoices';
$breadcrumbs = ['Invoices' => null];
$pageActions = '<a href="export.php?type=invoices" class="btn btn-light-brand"><i class="feather-download me-2"></i>Export CSV</a>'
    . '<a href="invoice-form.php" class="btn btn-primary"><i class="feather-plus me-2"></i>Create Invoice</a>';
$extraCss = ['vendors/css/dataTables.bs5.min.css'];
$extraJs = ['vendors/js/dataTables.min.js', 'vendors/js/dataTables.bs5.min.js'];
require __DIR__ . '/partials/header.php';
?>
<div class="row">
    <?php foreach ([
        ['Sales this month', money($sum['month_sales']), 'trending-up', 'primary', ''],
        ['Received this month', money($receivedMonth), 'check-circle', 'success', ''],
        ['Outstanding', money($sum['outstanding']), 'clock', 'warning', 'due'],
        ['Overdue (' . (int) $sum['overdue_count'] . ')', money($sum['overdue']), 'alert-triangle', 'danger', 'overdue'],
    ] as [$label, $val, $icon, $color, $f]): ?>
        <div class="col-xxl-3 col-md-6">
            <a href="invoices.php<?= $f ? '?status=' . $f : '' ?>" class="card stretch stretch-full text-reset">
                <div class="card-body d-flex align-items-center gap-3">
                    <span class="avatar-text avatar-lg bg-soft-<?= $color ?> text-<?= $color ?>"><i class="feather-<?= $icon ?>"></i></span>
                    <div><div class="fs-5 fw-bold text-dark"><?= e($val) ?></div><div class="fs-12 text-muted"><?= e($label) ?></div></div>
                </div>
            </a>
        </div>
    <?php endforeach; ?>
</div>
<div class="card">
    <div class="card-header flex-wrap gap-2">
        <ul class="nav nav-pills gap-1">
            <?php foreach (['' => 'All', 'due' => 'Unpaid', 'overdue' => 'Overdue', 'partial' => 'Partly paid', 'paid' => 'Paid', 'cancelled' => 'Cancelled'] as $k => $v): ?>
                <li class="nav-item"><a class="nav-link py-1 px-3<?= $status === $k ? ' active' : '' ?>" href="invoices.php<?= $k ? '?status=' . $k : '' ?>"><?= $v ?></a></li>
            <?php endforeach; ?>
        </ul>
        <form method="get" class="ms-auto d-flex gap-2 flex-wrap">
            <?php if ($status): ?><input type="hidden" name="status" value="<?= e($status) ?>"><?php endif; ?>
            <input type="date" name="from" class="form-control form-control-sm" value="<?= e($from) ?>" title="From">
            <input type="date" name="to" class="form-control form-control-sm" value="<?= e($to) ?>" title="To">
            <input type="search" name="q" class="form-control form-control-sm" placeholder="Invoice no, customer, GSTIN" value="<?= e($search) ?>">
            <button class="btn btn-sm btn-primary"><i class="feather-search"></i></button>
        </form>
    </div>
    <div class="card-body">
        <?php if (!$rows): ?>
            <div class="vh-empty"><i class="feather-file-text"></i>No invoices found. <a href="invoice-form.php">Create an invoice</a> or convert an accepted quotation.</div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover" id="invTable">
                <thead><tr><th>Invoice #</th><th>Date</th><th>Customer</th><th class="text-end">Total</th><th class="text-end">Balance</th><th>Due</th><th>Status</th><th data-orderable="false"></th></tr></thead>
                <tbody>
                <?php foreach ($rows as $r):
                    $bal = $r['status'] === 'cancelled' ? 0 : (float) $r['grand_total'] - (float) $r['amount_paid'];
                    $od = $bal > 0 && $r['due_date'] && $r['due_date'] < date('Y-m-d'); ?>
                    <tr>
                        <td><a href="invoice-view.php?id=<?= (int) $r['id'] ?>" class="fw-semibold"><?= e($r['invoice_no']) ?></a></td>
                        <td data-order="<?= e($r['invoice_date']) ?>"><?= e(fmt_date($r['invoice_date'])) ?></td>
                        <td><span class="text-dark fw-semibold d-block"><?= e($r['customer_org'] ?: $r['customer_name']) ?></span><span class="fs-12 text-muted"><?= e($r['customer_gstin'] ?: $r['customer_phone']) ?></span></td>
                        <td class="text-end fw-semibold" data-order="<?= (float) $r['grand_total'] ?>"><?= money($r['grand_total']) ?></td>
                        <td class="text-end <?= $bal > 0 ? 'text-danger' : 'text-muted' ?>" data-order="<?= $bal ?>"><?= money(max(0, $bal)) ?></td>
                        <td class="<?= $od ? 'text-danger' : '' ?>" data-order="<?= e($r['due_date']) ?>"><?= e(fmt_date($r['due_date'])) ?></td>
                        <td><?= status_badge($od ? 'overdue' : $r['status']) ?></td>
                        <td class="text-end">
                            <div class="hstack gap-2 justify-content-end">
                                <a href="invoice-view.php?id=<?= (int) $r['id'] ?>" class="avatar-text avatar-md" title="View / print / payment"><i class="feather-printer"></i></a>
                                <?php if ($r['status'] !== 'cancelled'): ?><a href="invoice-form.php?id=<?= (int) $r['id'] ?>" class="avatar-text avatar-md" title="Edit"><i class="feather-edit-3"></i></a><?php endif; ?>
                                <?php if (is_owner($admin)): ?>
                                <form method="post" class="m-0" data-confirm="Delete invoice <?= e($r['invoice_no']) ?> and its payments permanently?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                                    <button class="avatar-text avatar-md border-0 text-danger" title="Delete"><i class="feather-trash-2"></i></button></form>
                                <?php endif; ?>
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
$inlineJs = "if ($.fn.DataTable && $('#invTable').length) { $('#invTable').DataTable({ pageLength: 25, order: [] }); }";
require __DIR__ . '/partials/footer.php';
