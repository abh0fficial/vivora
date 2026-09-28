<?php
require __DIR__ . '/../includes/auth.php';
$admin = require_admin();

$id = (int) get('id');
$c = q_row('SELECT * FROM customers WHERE id = ?', [$id]);
if (!$c) {
    flash('error', 'Customer not found.');
    redirect('admin/customers.php');
}
$from = preg_match('/^\d{4}-\d{2}-\d{2}$/', get('from')) ? get('from') : substr(financial_year(), 0, 4) . '-04-01';
$to = preg_match('/^\d{4}-\d{2}-\d{2}$/', get('to')) ? get('to') : date('Y-m-d');

// Opening balance = everything before the period.
$opening = (float) q_val("SELECT COALESCE(SUM(grand_total),0) FROM invoices WHERE customer_id = ? AND status <> 'cancelled' AND invoice_date < ?", [$id, $from])
    - (float) q_val("SELECT COALESCE(SUM(amount),0) FROM payments WHERE customer_id = ? AND direction = 'in' AND payment_date < ?", [$id, $from]);
$entries = [];
foreach (q_all("SELECT id, invoice_no, invoice_date, grand_total, due_date FROM invoices WHERE customer_id = ? AND status <> 'cancelled' AND invoice_date BETWEEN ? AND ?", [$id, $from, $to]) as $r) {
    $entries[] = ['date' => $r['invoice_date'], 'type' => 'Invoice', 'ref' => $r['invoice_no'], 'url' => 'invoice-view.php?id=' . $r['id'], 'debit' => (float) $r['grand_total'], 'credit' => 0, 'sort' => 1];
}
foreach (q_all("SELECT p.id, p.receipt_no, p.payment_date, p.amount, p.mode, i.invoice_no FROM payments p LEFT JOIN invoices i ON i.id = p.invoice_id
    WHERE p.customer_id = ? AND p.direction = 'in' AND p.payment_date BETWEEN ? AND ?", [$id, $from, $to]) as $r) {
    $entries[] = ['date' => $r['payment_date'], 'type' => 'Payment (' . (payment_modes()[$r['mode']] ?? $r['mode']) . ')', 'ref' => $r['receipt_no'] . ($r['invoice_no'] ? ' → ' . $r['invoice_no'] : ''),
        'url' => 'payment-receipt.php?id=' . $r['id'], 'debit' => 0, 'credit' => (float) $r['amount'], 'sort' => 2];
}
usort($entries, fn($a, $b) => [$a['date'], $a['sort']] <=> [$b['date'], $b['sort']]);
$bal = $opening;
$totD = $totC = 0;
$company = setting('company_name', 'Vivora Healthcare');
$current = customer_balance($id);

$pageTitle = 'Ledger – ' . ($c['organization'] ?: $c['name']);
$activeNav = 'customers';
$breadcrumbs = ['Customers' => 'customers.php', 'Ledger' => null];
$pageActions = '<button onclick="window.print()" class="btn btn-primary"><i class="feather-printer me-2"></i>Print statement</button>'
    . '<a href="invoice-form.php?customer_id=' . $id . '" class="btn btn-light-brand"><i class="feather-file-plus me-2"></i>New invoice</a>';
require __DIR__ . '/partials/header.php';
?>
<div class="card no-print">
    <div class="card-body d-flex flex-wrap gap-3 align-items-center">
        <form method="get" class="d-flex gap-2 align-items-center flex-wrap">
            <input type="hidden" name="id" value="<?= $id ?>">
            <input type="date" name="from" class="form-control form-control-sm" value="<?= e($from) ?>"><span class="text-muted">to</span>
            <input type="date" name="to" class="form-control form-control-sm" value="<?= e($to) ?>">
            <button class="btn btn-sm btn-primary">Show</button>
        </form>
        <div class="ms-auto fs-13">Current outstanding: <strong class="<?= $current > 0 ? 'text-danger' : 'text-success' ?>"><?= money($current) ?></strong></div>
    </div>
</div>
<div class="card">
    <div class="card-body p-4 p-md-5">
        <div class="d-flex justify-content-between flex-wrap gap-3 mb-4">
            <div>
                <img src="<?= asset('images/logo-full.png') ?>" alt="" style="max-width:190px" class="mb-2">
                <div class="fs-12 text-muted"><?= e($company) ?><?= setting('gstin') ? ' · GSTIN ' . e(setting('gstin')) : '' ?></div>
            </div>
            <div class="text-end">
                <h4 class="fw-bolder text-primary mb-1">STATEMENT OF ACCOUNT</h4>
                <div class="fs-13"><?= e(fmt_date($from)) ?> – <?= e(fmt_date($to)) ?></div>
            </div>
        </div>
        <div class="mb-4 p-3 rounded bg-gray-100">
            <div class="fw-bold text-dark"><?= e($c['organization'] ?: $c['name']) ?></div>
            <div class="fs-12 text-muted"><?= e(trim(implode(', ', array_filter([$c['address'], $c['city'], $c['state']])), ', ')) ?><?= $c['gstin'] ? ' · GSTIN ' . e($c['gstin']) : '' ?><?= $c['phone'] ? ' · ' . e($c['phone']) : '' ?></div>
        </div>
        <div class="table-responsive">
            <table class="table table-bordered fs-13">
                <thead class="bg-gray-100"><tr><th>Date</th><th>Particulars</th><th>Reference</th><th class="text-end">Debit (billed)</th><th class="text-end">Credit (received)</th><th class="text-end">Balance</th></tr></thead>
                <tbody>
                    <tr><td><?= e(fmt_date($from)) ?></td><td colspan="4" class="fw-semibold">Opening balance</td><td class="text-end fw-semibold"><?= money($opening) ?></td></tr>
                    <?php foreach ($entries as $en): $bal += $en['debit'] - $en['credit']; $totD += $en['debit']; $totC += $en['credit']; ?>
                        <tr>
                            <td class="text-nowrap"><?= e(fmt_date($en['date'])) ?></td>
                            <td><?= e($en['type']) ?></td>
                            <td><a href="<?= e($en['url']) ?>"><?= e($en['ref']) ?></a></td>
                            <td class="text-end"><?= $en['debit'] ? money($en['debit']) : '' ?></td>
                            <td class="text-end"><?= $en['credit'] ? money($en['credit']) : '' ?></td>
                            <td class="text-end"><?= money($bal) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$entries): ?><tr><td colspan="6" class="text-center text-muted py-4">No transactions in this period.</td></tr><?php endif; ?>
                </tbody>
                <tfoot class="fw-bold"><tr><td colspan="3" class="text-end">Totals</td><td class="text-end"><?= money($totD) ?></td><td class="text-end"><?= money($totC) ?></td><td class="text-end <?= $bal > 0 ? 'text-danger' : 'text-success' ?>"><?= money($bal) ?></td></tr></tfoot>
            </table>
        </div>
        <p class="fs-12 text-muted mb-0">Closing balance of <strong><?= money($bal) ?></strong> <?= $bal > 0 ? 'is payable to ' . e($company) : ($bal < 0 ? 'is in your favour (advance)' : '— account settled') ?>.</p>
    </div>
</div>
<?php require __DIR__ . '/partials/footer.php';
