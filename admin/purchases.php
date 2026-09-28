<?php
require __DIR__ . '/../includes/auth.php';
$admin = require_admin();

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && post('action') === 'delete') {
    require_post();
    $pur = q_row('SELECT * FROM purchases WHERE id = ?', [(int) post('id')]);
    if ($pur) {
        if ($pur['stock_applied']) {
            foreach (q_all('SELECT product_id, qty FROM purchase_items WHERE purchase_id = ?', [$pur['id']]) as $it) {
                adjust_stock($it['product_id'] ? (int) $it['product_id'] : null, -(float) $it['qty'], 'Purchase bill ' . $pur['bill_no'] . ' deleted', $admin['id']);
            }
        }
        q('DELETE FROM purchases WHERE id = ?', [$pur['id']]);
        log_activity('purchase.delete', 'Deleted purchase bill ' . ($pur['bill_no'] ?: '#' . $pur['id']) . ' from ' . $pur['supplier_name']);
        flash('success', 'Purchase bill deleted' . ($pur['stock_applied'] ? ' and stock reversed.' : '.'));
    }
    redirect('admin/purchases.php');
}

$status = in_array(get('status'), ['unpaid', 'partial', 'paid'], true) ? get('status') : '';
$from = preg_match('/^\d{4}-\d{2}-\d{2}$/', get('from')) ? get('from') : '';
$to = preg_match('/^\d{4}-\d{2}-\d{2}$/', get('to')) ? get('to') : '';
$where = [];
$params = [];
if ($status) { $where[] = 'status = ?'; $params[] = $status; }
if ($from) { $where[] = 'bill_date >= ?'; $params[] = $from; }
if ($to) { $where[] = 'bill_date <= ?'; $params[] = $to; }
$rows = q_all('SELECT * FROM purchases' . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY bill_date DESC, id DESC', $params);
$sum = q_row("SELECT COALESCE(SUM(CASE WHEN bill_date >= DATE_FORMAT(CURDATE(), '%Y-%m-01') THEN grand_total END),0) month_total,
    COALESCE(SUM(grand_total - amount_paid),0) payable, COALESCE(SUM(CASE WHEN bill_date >= DATE_FORMAT(CURDATE(), '%Y-%m-01') THEN tax_total END),0) itc FROM purchases");

$pageTitle = 'Purchase Bills';
$activeNav = 'purchases';
$breadcrumbs = ['Purchases' => null];
$pageActions = '<a href="export.php?type=purchases" class="btn btn-light-brand"><i class="feather-download me-2"></i>Export CSV</a><a href="purchase-form.php" class="btn btn-primary"><i class="feather-plus me-2"></i>New Purchase Bill</a>';
require __DIR__ . '/partials/header.php';
?>
<div class="row">
    <?php foreach ([['Purchases this month', $sum['month_total'], 'shopping-cart', 'primary'], ['GST input credit this month', $sum['itc'], 'percent', 'info'], ['Payable to suppliers', $sum['payable'], 'clock', 'danger']] as [$l, $v, $i, $c]): ?>
        <div class="col-md-4"><div class="card stretch stretch-full"><div class="card-body d-flex align-items-center gap-3">
            <span class="avatar-text avatar-lg bg-soft-<?= $c ?> text-<?= $c ?>"><i class="feather-<?= $i ?>"></i></span>
            <div><div class="fs-5 fw-bold text-dark"><?= money($v) ?></div><div class="fs-12 text-muted"><?= $l ?></div></div>
        </div></div></div>
    <?php endforeach; ?>
</div>
<div class="card">
    <div class="card-header flex-wrap gap-2">
        <ul class="nav nav-pills gap-1">
            <?php foreach (['' => 'All', 'unpaid' => 'Unpaid', 'partial' => 'Partly paid', 'paid' => 'Paid'] as $k => $v): ?><li class="nav-item"><a class="nav-link py-1 px-3<?= $status === $k ? ' active' : '' ?>" href="purchases.php<?= $k ? '?status=' . $k : '' ?>"><?= $v ?></a></li><?php endforeach; ?>
        </ul>
        <form method="get" class="ms-auto d-flex gap-2"><?php if ($status): ?><input type="hidden" name="status" value="<?= e($status) ?>"><?php endif; ?>
            <input type="date" name="from" class="form-control form-control-sm" value="<?= e($from) ?>"><input type="date" name="to" class="form-control form-control-sm" value="<?= e($to) ?>"><button class="btn btn-sm btn-primary">Go</button></form>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead><tr><th>Bill date</th><th>Bill no.</th><th>Supplier</th><th class="text-end">GST</th><th class="text-end">Total</th><th class="text-end">Balance</th><th>Status</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($rows as $r): ?>
                    <tr>
                        <td><?= e(fmt_date($r['bill_date'])) ?></td>
                        <td><a href="purchase-form.php?id=<?= (int) $r['id'] ?>" class="fw-semibold"><?= e($r['bill_no'] ?: '#' . $r['id']) ?></a></td>
                        <td class="fw-semibold text-dark"><?= e($r['supplier_name']) ?></td>
                        <td class="text-end"><?= money($r['tax_total']) ?></td>
                        <td class="text-end fw-semibold"><?= money($r['grand_total']) ?></td>
                        <td class="text-end <?= $r['grand_total'] - $r['amount_paid'] > 0 ? 'text-danger' : 'text-muted' ?>"><?= money(max(0, $r['grand_total'] - $r['amount_paid'])) ?></td>
                        <td><?= status_badge($r['status']) ?></td>
                        <td class="text-end"><div class="hstack gap-2 justify-content-end">
                            <a href="purchase-form.php?id=<?= (int) $r['id'] ?>" class="avatar-text avatar-md" title="Open / pay"><i class="feather-edit-3"></i></a>
                            <form method="post" class="m-0" data-confirm="Delete this purchase bill?<?= $r['stock_applied'] ? ' Its stock will be reversed.' : '' ?>"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><button class="avatar-text avatar-md border-0 text-danger" title="Delete"><i class="feather-trash-2"></i></button></form>
                        </div></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$rows): ?><tr><td colspan="8" class="text-center text-muted py-5">No purchase bills yet. <a href="purchase-form.php">Record one</a> when stock arrives from a supplier.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php require __DIR__ . '/partials/footer.php';
