<?php
require __DIR__ . '/../includes/auth.php';
$admin = require_admin();

$dir = get('type') === 'out' ? 'out' : 'in';
$modes = payment_modes();

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    require_post();
    if (post('action') === 'delete') {
        $pay = q_row('SELECT * FROM payments WHERE id = ?', [(int) post('id')]);
        if ($pay) {
            q('DELETE FROM payments WHERE id = ?', [$pay['id']]);
            if ($pay['invoice_id']) refresh_invoice_payment((int) $pay['invoice_id']);
            if ($pay['purchase_id']) refresh_purchase_payment((int) $pay['purchase_id']);
            log_activity('payment.delete', 'Deleted payment ' . ($pay['receipt_no'] ?: '#' . $pay['id']) . ' of ' . money($pay['amount']));
            flash('success', 'Payment deleted.');
        }
    } else {
        $amount = round((float) post('amount'), 2);
        $date = preg_match('/^\d{4}-\d{2}-\d{2}$/', post('payment_date')) ? post('payment_date') : date('Y-m-d');
        $mode = array_key_exists(post('mode'), $modes) ? post('mode') : 'cash';
        if ($dir === 'in') {
            $cust = q_row('SELECT id, name, organization FROM customers WHERE id = ?', [(int) post('customer_id')]);
            $inv = q_row("SELECT id, invoice_no, customer_id, customer_name, customer_org FROM invoices WHERE id = ? AND status <> 'cancelled'", [(int) post('invoice_id')]);
            $party = $inv ? ($inv['customer_org'] ?: $inv['customer_name']) : ($cust ? ($cust['organization'] ?: $cust['name']) : post('party_name'));
            $custId = $inv['customer_id'] ?? ($cust['id'] ?? null);
            if ($amount <= 0 || $party === '') {
                flash('error', 'Choose a customer (or enter a name) and the amount received.');
            } else {
                $no = next_doc_no('payments', 'receipt_no', setting('receipt_prefix', 'VH-RCPT-'), $date);
                q('INSERT INTO payments (receipt_no, direction, invoice_id, customer_id, party_name, payment_date, amount, mode, reference, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                    [$no, 'in', $inv['id'] ?? null, $custId, mb_substr($party, 0, 200), $date, $amount, $mode, mb_substr(post('reference'), 0, 120), mb_substr(post('notes'), 0, 400)]);
                if ($inv) refresh_invoice_payment((int) $inv['id']);
                log_activity('payment.create', 'Received ' . money($amount) . " from $party ($no)");
                flash('success', 'Payment of ' . money($amount) . " recorded. Receipt $no.");
            }
        } else {
            $sup = q_row('SELECT id, name FROM suppliers WHERE id = ?', [(int) post('supplier_id')]);
            $pur = q_row('SELECT id, bill_no, supplier_id, supplier_name FROM purchases WHERE id = ?', [(int) post('purchase_id')]);
            $party = $pur ? $pur['supplier_name'] : ($sup['name'] ?? post('party_name'));
            if ($amount <= 0 || $party === '') {
                flash('error', 'Choose a supplier (or enter a name) and the amount paid.');
            } else {
                q('INSERT INTO payments (receipt_no, direction, purchase_id, supplier_id, party_name, payment_date, amount, mode, reference, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                    [mb_substr(post('reference'), 0, 40), 'out', $pur['id'] ?? null, $pur['supplier_id'] ?? ($sup['id'] ?? null), mb_substr($party, 0, 200), $date, $amount, $mode, mb_substr(post('reference'), 0, 120), mb_substr(post('notes'), 0, 400)]);
                if ($pur) refresh_purchase_payment((int) $pur['id']);
                log_activity('payment.create', 'Paid ' . money($amount) . " to $party");
                flash('success', 'Payment of ' . money($amount) . " to $party recorded.");
            }
        }
    }
    redirect('admin/payments.php?type=' . $dir);
}

$from = preg_match('/^\d{4}-\d{2}-\d{2}$/', get('from')) ? get('from') : date('Y-m-01');
$to = preg_match('/^\d{4}-\d{2}-\d{2}$/', get('to')) ? get('to') : date('Y-m-d');
$mode = array_key_exists(get('mode'), $modes) ? get('mode') : '';
$params = [$dir, $from, $to];
$sql = 'SELECT p.*, i.invoice_no, pu.bill_no FROM payments p LEFT JOIN invoices i ON i.id = p.invoice_id LEFT JOIN purchases pu ON pu.id = p.purchase_id
    WHERE p.direction = ? AND p.payment_date BETWEEN ? AND ?';
if ($mode) { $sql .= ' AND p.mode = ?'; $params[] = $mode; }
$rows = q_all($sql . ' ORDER BY p.payment_date DESC, p.id DESC', $params);
$byMode = [];
foreach ($rows as $r) $byMode[$r['mode']] = ($byMode[$r['mode']] ?? 0) + (float) $r['amount'];
$total = array_sum($byMode);

$customers = q_all('SELECT id, name, organization FROM customers ORDER BY organization, name');
$suppliers = q_all('SELECT id, name FROM suppliers ORDER BY name');
$openInvoices = q_all("SELECT id, invoice_no, customer_id, customer_name, customer_org, grand_total - amount_paid bal FROM invoices WHERE status IN ('unpaid','partial') ORDER BY invoice_date");
$openBills = q_all("SELECT id, bill_no, supplier_id, supplier_name, grand_total - amount_paid bal FROM purchases WHERE status IN ('unpaid','partial') ORDER BY bill_date");

$pageTitle = $dir === 'in' ? 'Payments Received' : 'Payments Made';
$activeNav = $dir === 'in' ? 'payments-in' : 'payments-out';
$breadcrumbs = ['Payments' => null];
$pageActions = '<a href="export.php?type=payments_' . $dir . '" class="btn btn-light-brand"><i class="feather-download me-2"></i>Export CSV</a>';
require __DIR__ . '/partials/header.php';
?>
<div class="row">
    <div class="col-xxl-9 col-xl-8">
        <div class="card">
            <div class="card-header flex-wrap gap-2">
                <ul class="nav nav-pills gap-1">
                    <li class="nav-item"><a class="nav-link py-1 px-3<?= $dir === 'in' ? ' active' : '' ?>" href="payments.php?type=in">Received</a></li>
                    <li class="nav-item"><a class="nav-link py-1 px-3<?= $dir === 'out' ? ' active' : '' ?>" href="payments.php?type=out">Paid to suppliers</a></li>
                </ul>
                <form method="get" class="ms-auto d-flex gap-2 flex-wrap">
                    <input type="hidden" name="type" value="<?= $dir ?>">
                    <input type="date" name="from" class="form-control form-control-sm" value="<?= e($from) ?>">
                    <input type="date" name="to" class="form-control form-control-sm" value="<?= e($to) ?>">
                    <select name="mode" class="form-select form-select-sm"><option value="">All modes</option><?php foreach ($modes as $k => $v): ?><option value="<?= $k ?>" <?= $mode === $k ? 'selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?></select>
                    <button class="btn btn-sm btn-primary">Go</button>
                </form>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead><tr><th>Date</th><th><?= $dir === 'in' ? 'Receipt #' : 'Ref' ?></th><th><?= $dir === 'in' ? 'Received from' : 'Paid to' ?></th><th>Against</th><th>Mode</th><th class="text-end">Amount</th><th></th></tr></thead>
                        <tbody>
                        <?php foreach ($rows as $r): ?>
                            <tr>
                                <td class="text-nowrap"><?= e(fmt_date($r['payment_date'])) ?></td>
                                <td><?= $dir === 'in' ? '<a href="payment-receipt.php?id=' . (int) $r['id'] . '" class="fw-semibold">' . e($r['receipt_no']) . '</a>' : e($r['reference'] ?: '—') ?></td>
                                <td class="fw-semibold text-dark"><?= e($r['party_name']) ?></td>
                                <td><?php if ($r['invoice_no']): ?><a href="invoice-view.php?id=<?= (int) $r['invoice_id'] ?>"><?= e($r['invoice_no']) ?></a><?php elseif ($r['bill_no'] !== null): ?><a href="purchase-form.php?id=<?= (int) $r['purchase_id'] ?>">Bill <?= e($r['bill_no'] ?: '#' . $r['purchase_id']) ?></a><?php else: ?><span class="text-muted fs-12">On account</span><?php endif; ?></td>
                                <td><span class="badge bg-gray-200 text-dark"><?= e($modes[$r['mode']] ?? $r['mode']) ?></span><?= $dir === 'in' && $r['reference'] ? '<div class="fs-11 text-muted">' . e($r['reference']) . '</div>' : '' ?></td>
                                <td class="text-end fw-semibold <?= $dir === 'in' ? 'text-success' : 'text-danger' ?>"><?= money($r['amount']) ?></td>
                                <td class="text-end">
                                    <div class="hstack gap-2 justify-content-end">
                                        <?php if ($dir === 'in'): ?><a href="payment-receipt.php?id=<?= (int) $r['id'] ?>" class="avatar-text avatar-md" title="Print receipt"><i class="feather-printer"></i></a><?php endif; ?>
                                        <form method="post" class="m-0" data-confirm="Delete this payment?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                                            <button class="avatar-text avatar-md border-0 text-danger" title="Delete"><i class="feather-trash-2"></i></button></form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$rows): ?><tr><td colspan="7" class="text-center text-muted py-5">No payments in this period.</td></tr><?php endif; ?>
                        </tbody>
                        <?php if ($rows): ?><tfoot><tr><th colspan="5" class="text-end">Total</th><th class="text-end"><?= money($total) ?></th><th></th></tr></tfoot><?php endif; ?>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xxl-3 col-xl-4">
        <div class="card">
            <div class="card-header"><h5 class="card-title"><?= $dir === 'in' ? 'Record payment received' : 'Record payment to supplier' ?></h5></div>
            <div class="card-body">
                <form method="post">
                    <?= csrf_field() ?>
                    <?php if ($dir === 'in'): ?>
                        <div class="mb-3"><label class="form-label">Customer</label>
                            <select name="customer_id" id="partyPick" class="form-select"><option value="">— Select —</option><?php foreach ($customers as $c): ?><option value="<?= (int) $c['id'] ?>"><?= e($c['organization'] ?: $c['name']) ?></option><?php endforeach; ?></select></div>
                        <div class="mb-3"><label class="form-label">Against invoice</label>
                            <select name="invoice_id" id="docPick" class="form-select"><option value="">On account / advance</option>
                                <?php foreach ($openInvoices as $oi): ?><option value="<?= (int) $oi['id'] ?>" data-party="<?= (int) $oi['customer_id'] ?>" data-bal="<?= (float) $oi['bal'] ?>"><?= e($oi['invoice_no']) ?> · <?= e($oi['customer_org'] ?: $oi['customer_name']) ?> · due <?= money($oi['bal']) ?></option><?php endforeach; ?>
                            </select></div>
                        <div class="mb-3"><label class="form-label">Or name (walk-in)</label><input name="party_name" class="form-control" maxlength="200"></div>
                    <?php else: ?>
                        <div class="mb-3"><label class="form-label">Supplier</label>
                            <select name="supplier_id" id="partyPick" class="form-select"><option value="">— Select —</option><?php foreach ($suppliers as $s): ?><option value="<?= (int) $s['id'] ?>"><?= e($s['name']) ?></option><?php endforeach; ?></select></div>
                        <div class="mb-3"><label class="form-label">Against purchase bill</label>
                            <select name="purchase_id" id="docPick" class="form-select"><option value="">On account / advance</option>
                                <?php foreach ($openBills as $ob): ?><option value="<?= (int) $ob['id'] ?>" data-party="<?= (int) $ob['supplier_id'] ?>" data-bal="<?= (float) $ob['bal'] ?>">Bill <?= e($ob['bill_no'] ?: '#' . $ob['id']) ?> · <?= e($ob['supplier_name']) ?> · due <?= money($ob['bal']) ?></option><?php endforeach; ?>
                            </select></div>
                        <div class="mb-3"><label class="form-label">Or name</label><input name="party_name" class="form-control" maxlength="200"></div>
                    <?php endif; ?>
                    <div class="row">
                        <div class="col-6 mb-3"><label class="form-label">Amount (₹)</label><input type="number" step="0.01" min="0.01" name="amount" id="payAmt" class="form-control" required></div>
                        <div class="col-6 mb-3"><label class="form-label">Date</label><input type="date" name="payment_date" class="form-control" value="<?= date('Y-m-d') ?>"></div>
                    </div>
                    <div class="mb-3"><label class="form-label">Mode</label><select name="mode" class="form-select"><?php foreach ($modes as $k => $v): ?><option value="<?= $k ?>"><?= e($v) ?></option><?php endforeach; ?></select></div>
                    <div class="mb-3"><label class="form-label">Reference (UTR / cheque no.)</label><input name="reference" class="form-control" maxlength="120"></div>
                    <div class="mb-3"><label class="form-label">Notes</label><input name="notes" class="form-control" maxlength="400"></div>
                    <button class="btn btn-primary w-100"><i class="feather-save me-2"></i>Save payment</button>
                </form>
            </div>
        </div>
        <div class="card">
            <div class="card-header"><h5 class="card-title">By mode (<?= e(fmt_date($from)) ?> – <?= e(fmt_date($to)) ?>)</h5></div>
            <div class="card-body">
                <?php foreach ($byMode as $k => $v): ?><div class="d-flex justify-content-between mb-2"><span><?= e($modes[$k] ?? $k) ?></span><strong><?= money($v) ?></strong></div><?php endforeach; ?>
                <div class="d-flex justify-content-between border-top pt-2"><span class="fw-bold">Total</span><strong class="text-primary"><?= money($total) ?></strong></div>
            </div>
        </div>
    </div>
</div>
<?php
$inlineJs = <<<'JS'
(function () {
    var party = document.getElementById('partyPick'), doc = document.getElementById('docPick'), amt = document.getElementById('payAmt');
    party.addEventListener('change', function () {
        Array.prototype.forEach.call(doc.options, function (o) { if (o.value) o.hidden = party.value !== '' && o.dataset.party !== party.value; });
        if (doc.selectedOptions[0] && doc.selectedOptions[0].hidden) doc.value = '';
    });
    doc.addEventListener('change', function () {
        var o = doc.selectedOptions[0];
        if (o && o.value) { amt.value = o.dataset.bal; if (o.dataset.party && o.dataset.party !== '0') party.value = o.dataset.party; }
    });
})();
JS;
require __DIR__ . '/partials/footer.php';
