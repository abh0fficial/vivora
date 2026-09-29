<?php
require __DIR__ . '/../includes/auth.php';
$admin = require_admin();

$id = (int) get('id');
$s = q_row('SELECT * FROM suppliers WHERE id = ?', [$id]);
if (!$s) {
    flash('error', 'Supplier not found.');
    redirect('admin/parties.php?tab=suppliers');
}
$from = preg_match('/^\d{4}-\d{2}-\d{2}$/', get('from')) ? get('from') : substr(financial_year(), 0, 4) . '-04-01';
$to = preg_match('/^\d{4}-\d{2}-\d{2}$/', get('to')) ? get('to') : date('Y-m-d');
$opening = (float) q_val('SELECT COALESCE(SUM(grand_total),0) FROM purchases WHERE supplier_id = ? AND bill_date < ?', [$id, $from])
    - (float) q_val("SELECT COALESCE(SUM(amount),0) FROM payments WHERE supplier_id = ? AND direction = 'out' AND payment_date < ?", [$id, $from]);
$entries = [];
foreach (q_all('SELECT id, bill_no, bill_date, grand_total FROM purchases WHERE supplier_id = ? AND bill_date BETWEEN ? AND ?', [$id, $from, $to]) as $r) {
    $entries[] = ['date' => $r['bill_date'], 'type' => 'Purchase bill', 'ref' => $r['bill_no'] ?: '#' . $r['id'], 'url' => 'purchase-form.php?id=' . $r['id'], 'credit' => (float) $r['grand_total'], 'debit' => 0, 'sort' => 1];
}
foreach (q_all("SELECT id, payment_date, amount, mode, reference, purchase_id FROM payments WHERE supplier_id = ? AND direction = 'out' AND payment_date BETWEEN ? AND ?", [$id, $from, $to]) as $r) {
    $entries[] = ['date' => $r['payment_date'], 'type' => 'Payment (' . (payment_modes()[$r['mode']] ?? $r['mode']) . ')', 'ref' => $r['reference'] ?: '—',
        'url' => $r['purchase_id'] ? 'purchase-form.php?id=' . $r['purchase_id'] : 'payments.php?type=out', 'credit' => 0, 'debit' => (float) $r['amount'], 'sort' => 2];
}
usort($entries, fn($a, $b) => [$a['date'], $a['sort']] <=> [$b['date'], $b['sort']]);
$bal = $opening;
$totC = $totD = 0;
$company = setting('company_name', 'Vivora Healthcare');

$pageTitle = 'Ledger – ' . $s['name'];
$activeNav = 'parties';
$breadcrumbs = ['Parties' => 'parties.php', 'Supplier ledger' => null];
$pageActions = '<button onclick="window.print()" class="btn btn-primary"><i class="feather-printer me-2"></i>Print statement</button>'
    . '<a href="purchase-form.php?supplier_id=' . $id . '" class="btn btn-light-brand"><i class="feather-shopping-cart me-2"></i>New purchase</a>';
require __DIR__ . '/partials/header.php';
?>
<div class="card no-print">
    <div class="card-body d-flex flex-wrap gap-3 align-items-center">
        <form method="get" class="d-flex gap-2 align-items-center flex-wrap">
            <input type="hidden" name="id" value="<?= $id ?>">
            <input type="date" name="from" class="form-control form-control-sm" style="width:auto" value="<?= e($from) ?>"><span class="text-muted">to</span>
            <input type="date" name="to" class="form-control form-control-sm" style="width:auto" value="<?= e($to) ?>"><button class="btn btn-sm btn-primary">Show</button>
        </form>
        <div class="ms-auto fs-13">Current payable: <strong class="<?= supplier_balance($id) > 0 ? 'text-danger' : 'text-success' ?>"><?= money(supplier_balance($id)) ?></strong></div>
    </div>
</div>
<div class="card">
    <div class="card-body p-4 p-md-5">
        <div class="d-flex justify-content-between flex-wrap gap-3 mb-4">
            <div><img src="<?= asset('images/logo-full.png') ?>" alt="" style="max-width:190px" class="mb-2"><div class="fs-12 text-muted"><?= e($company) ?><?= setting('gstin') ? ' · GSTIN ' . e(setting('gstin')) : '' ?></div></div>
            <div class="text-end"><h4 class="fw-bolder text-primary mb-1">SUPPLIER STATEMENT</h4><div class="fs-13"><?= e(fmt_date($from)) ?> – <?= e(fmt_date($to)) ?></div></div>
        </div>
        <div class="mb-4 p-3 rounded bg-gray-100">
            <div class="fw-bold text-dark"><?= e($s['name']) ?></div>
            <div class="fs-12 text-muted"><?= e(trim(implode(', ', array_filter([$s['address'], $s['city'], $s['state']])), ', ')) ?><?= $s['gstin'] ? ' · GSTIN ' . e($s['gstin']) : '' ?><?= $s['phone'] ? ' · ' . e($s['phone']) : '' ?></div>
        </div>
        <div class="table-responsive">
            <table class="table table-bordered fs-13">
                <thead class="bg-gray-100"><tr><th>Date</th><th>Particulars</th><th>Reference</th><th class="text-end">Billed (credit)</th><th class="text-end">Paid (debit)</th><th class="text-end">Payable</th></tr></thead>
                <tbody>
                    <tr><td><?= e(fmt_date($from)) ?></td><td colspan="4" class="fw-semibold">Opening balance</td><td class="text-end fw-semibold"><?= money($opening) ?></td></tr>
                    <?php foreach ($entries as $en): $bal += $en['credit'] - $en['debit']; $totC += $en['credit']; $totD += $en['debit']; ?>
                        <tr><td class="text-nowrap"><?= e(fmt_date($en['date'])) ?></td><td><?= e($en['type']) ?></td><td><a href="<?= e($en['url']) ?>"><?= e($en['ref']) ?></a></td>
                            <td class="text-end"><?= $en['credit'] ? money($en['credit']) : '' ?></td><td class="text-end"><?= $en['debit'] ? money($en['debit']) : '' ?></td><td class="text-end"><?= money($bal) ?></td></tr>
                    <?php endforeach; ?>
                    <?php if (!$entries): ?><tr><td colspan="6" class="text-center text-muted py-4">No transactions in this period.</td></tr><?php endif; ?>
                </tbody>
                <tfoot class="fw-bold"><tr><td colspan="3" class="text-end">Totals</td><td class="text-end"><?= money($totC) ?></td><td class="text-end"><?= money($totD) ?></td><td class="text-end <?= $bal > 0 ? 'text-danger' : 'text-success' ?>"><?= money($bal) ?></td></tr></tfoot>
            </table>
        </div>
    </div>
</div>
<?php require __DIR__ . '/partials/footer.php';
