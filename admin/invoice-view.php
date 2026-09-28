<?php
require __DIR__ . '/../includes/auth.php';
$admin = require_admin();

$id = (int) get('id');
$inv = q_row('SELECT * FROM invoices WHERE id = ?', [$id]);
if (!$inv) {
    flash('error', 'Invoice not found.');
    redirect('admin/invoices.php');
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    require_post();
    $action = post('action');
    if ($action === 'payment' && $inv['status'] !== 'cancelled') {
        $amount = round((float) post('amount'), 2);
        $date = preg_match('/^\d{4}-\d{2}-\d{2}$/', post('payment_date')) ? post('payment_date') : date('Y-m-d');
        $mode = array_key_exists(post('mode'), payment_modes()) ? post('mode') : 'cash';
        if ($amount <= 0) {
            flash('error', 'Enter the amount received.');
        } else {
            $no = next_doc_no('payments', 'receipt_no', setting('receipt_prefix', 'VH-RCPT-'), $date);
            q('INSERT INTO payments (receipt_no, direction, invoice_id, customer_id, party_name, payment_date, amount, mode, reference, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [$no, 'in', $id, $inv['customer_id'], $inv['customer_org'] ?: $inv['customer_name'], $date, $amount, $mode, mb_substr(post('reference'), 0, 120), mb_substr(post('notes'), 0, 400)]);
            refresh_invoice_payment($id);
            log_activity('payment.create', "Received " . money($amount) . " against {$inv['invoice_no']} ($no)");
            flash('success', 'Payment of ' . money($amount) . " recorded (receipt $no).");
        }
    } elseif ($action === 'delete_payment') {
        $pay = q_row("SELECT * FROM payments WHERE id = ? AND invoice_id = ?", [(int) post('payment_id'), $id]);
        if ($pay) {
            q('DELETE FROM payments WHERE id = ?', [$pay['id']]);
            refresh_invoice_payment($id);
            log_activity('payment.delete', "Deleted payment {$pay['receipt_no']} of " . money($pay['amount']) . " on {$inv['invoice_no']}");
            flash('success', 'Payment removed.');
        }
    } elseif ($action === 'cancel' && $inv['status'] !== 'cancelled') {
        if ($inv['stock_applied']) {
            foreach (q_all('SELECT product_id, qty FROM invoice_items WHERE invoice_id = ?', [$id]) as $it) {
                adjust_stock($it['product_id'] ? (int) $it['product_id'] : null, (float) $it['qty'], "Invoice {$inv['invoice_no']} cancelled", $admin['id']);
            }
        }
        q("UPDATE invoices SET status = 'cancelled', stock_applied = 0 WHERE id = ?", [$id]);
        log_activity('invoice.cancel', "Cancelled invoice {$inv['invoice_no']}");
        flash('success', 'Invoice cancelled' . ($inv['stock_applied'] ? ' and items returned to stock.' : '.'));
    } elseif ($action === 'duplicate') {
        redirect('admin/invoice-form.php?duplicate=' . $id);
    } elseif ($action === 'delete') {
        if (!is_owner($admin)) {
            flash('error', 'Only an administrator can delete invoices. Cancel it instead.');
        } else {
            if ($inv['stock_applied']) {
                foreach (q_all('SELECT product_id, qty FROM invoice_items WHERE invoice_id = ?', [$id]) as $it) {
                    adjust_stock($it['product_id'] ? (int) $it['product_id'] : null, (float) $it['qty'], "Invoice {$inv['invoice_no']} deleted", $admin['id']);
                }
            }
            q('DELETE FROM invoices WHERE id = ?', [$id]);
            log_activity('invoice.delete', "Deleted invoice {$inv['invoice_no']}");
            flash('success', "Invoice {$inv['invoice_no']} deleted.");
            redirect('admin/invoices.php');
        }
    }
    redirect('admin/invoice-view.php?id=' . $id);
}

$items = q_all('SELECT * FROM invoice_items WHERE invoice_id = ? ORDER BY sort_order, id', [$id]);
$payments = q_all("SELECT * FROM payments WHERE invoice_id = ? AND direction = 'in' ORDER BY payment_date, id", [$id]);
$balance = round((float) $inv['grand_total'] - (float) $inv['amount_paid'], 2);
$overdue = $inv['status'] !== 'cancelled' && $balance > 0 && $inv['due_date'] && $inv['due_date'] < date('Y-m-d');
$company = setting('company_name', 'Vivora Healthcare');
$companyState = setting('company_state');
$igst = (bool) $inv['is_igst'];
// Tax summary by HSN + rate.
$taxRows = [];
foreach ($items as $it) {
    $k = $it['hsn_code'] . '|' . (float) $it['gst_rate'];
    $taxRows[$k] = $taxRows[$k] ?? ['hsn' => $it['hsn_code'], 'rate' => (float) $it['gst_rate'], 'taxable' => 0, 'tax' => 0];
    $taxRows[$k]['taxable'] += (float) $it['taxable'];
    $taxRows[$k]['tax'] += (float) $it['tax_amount'];
}
$wa = preg_replace('/\D+/', '', $inv['customer_phone']);
if (strlen($wa) === 10) $wa = '91' . $wa;
$waText = "Dear {$inv['customer_name']},\nInvoice {$inv['invoice_no']} dated " . fmt_date($inv['invoice_date']) . ' for ' . money($inv['grand_total'])
    . ($balance > 0 ? '. Balance due: ' . money($balance) . ($inv['due_date'] ? ' by ' . fmt_date($inv['due_date']) : '') : '. Paid in full – thank you!')
    . (setting('upi_id') && $balance > 0 ? "\nUPI: " . setting('upi_id') : '') . "\n— $company";
$fmtQty = fn($q) => rtrim(rtrim(number_format((float) $q, 2, '.', ''), '0'), '.');
$fmtRate = fn($r) => rtrim(rtrim((string) (float) $r, '0'), '.') ?: '0';

$pageTitle = 'Invoice ' . $inv['invoice_no'];
$activeNav = 'invoices';
$breadcrumbs = ['Invoices' => 'invoices.php', $inv['invoice_no'] => null];
$pageActions = '<button onclick="window.print()" class="btn btn-primary"><i class="feather-printer me-2"></i>Print / Save PDF</button>'
    . ($inv['status'] !== 'cancelled' ? '<a href="invoice-form.php?id=' . $id . '" class="btn btn-light-brand"><i class="feather-edit-3 me-2"></i>Edit</a>' : '');
require __DIR__ . '/partials/header.php';
?>
<div class="row">
    <div class="col-12 vh-doc-main">
        <div class="card" id="printArea">
            <div class="card-body p-4 p-md-5 vh-invoice">
                <?php if ($inv['status'] === 'cancelled'): ?><div class="vh-stamp">CANCELLED</div><?php elseif ($inv['status'] === 'paid'): ?><div class="vh-stamp vh-stamp-paid">PAID</div><?php endif; ?>
                <div class="d-flex justify-content-between align-items-start flex-wrap gap-4 mb-4">
                    <div>
                        <img src="<?= asset('images/logo-full.png') ?>" alt="<?= e($company) ?>" style="max-width:220px" class="mb-3">
                        <div class="fs-12 text-muted" style="max-width:340px">
                            <strong class="text-dark"><?= e($company) ?></strong><br>
                            <?= setting('address') ? nl2br(e(setting('address'))) . '<br>' : '' ?><?= setting('city') ? e(setting('city')) . '<br>' : '' ?>
                            <?= setting('phone') ? 'Phone: ' . e(setting('phone')) . '<br>' : '' ?><?= setting('email') ? 'Email: ' . e(setting('email')) . '<br>' : '' ?>
                            <?= setting('gstin') ? '<strong class="text-dark">GSTIN: ' . e(setting('gstin')) . '</strong><br>' : '' ?>
                            <?= $companyState ? 'State: ' . e($companyState) . ' (' . e(state_code($companyState)) . ')' : '' ?>
                        </div>
                    </div>
                    <div class="text-end">
                        <h2 class="fw-bolder text-primary mb-1">TAX INVOICE</h2>
                        <div class="fs-11 text-muted mb-2">Original for recipient</div>
                        <table class="ms-auto fs-13">
                            <tr><td class="text-muted pe-3">Invoice no.</td><td class="fw-bold"><?= e($inv['invoice_no']) ?></td></tr>
                            <tr><td class="text-muted pe-3">Date</td><td><?= e(fmt_date($inv['invoice_date'])) ?></td></tr>
                            <?php if ($inv['due_date']): ?><tr><td class="text-muted pe-3">Due date</td><td><?= e(fmt_date($inv['due_date'])) ?></td></tr><?php endif; ?>
                            <?php if ($inv['place_of_supply']): ?><tr><td class="text-muted pe-3">Place of supply</td><td><?= e($inv['place_of_supply']) ?> (<?= e(state_code($inv['place_of_supply'])) ?>)</td></tr><?php endif; ?>
                        </table>
                    </div>
                </div>

                <div class="mb-4 p-3 rounded bg-gray-100">
                    <div class="fs-11 text-uppercase text-muted fw-bold mb-1">Bill to</div>
                    <div class="fw-bold text-dark"><?= e($inv['customer_org'] ?: $inv['customer_name']) ?></div>
                    <?php if ($inv['customer_org'] && $inv['customer_name']): ?><div class="fs-13">Attn: <?= e($inv['customer_name']) ?></div><?php endif; ?>
                    <div class="fs-12 text-muted">
                        <?= $inv['customer_address'] ? e($inv['customer_address']) . '<br>' : '' ?>
                        <?= e(implode(' · ', array_filter([$inv['customer_phone'], $inv['customer_email']]))) ?>
                        <?= $inv['customer_gstin'] ? '<br><strong class="text-dark">GSTIN: ' . e($inv['customer_gstin']) . '</strong>' : '' ?>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-bordered fs-12 vh-inv-table">
                        <thead class="bg-gray-100">
                            <tr>
                                <th>#</th><th>Item / Description</th><th>HSN/SAC</th><th class="text-end">Qty</th><th class="text-end">Rate</th><th class="text-end">Taxable</th><th class="text-end">GST</th>
                                <?php if ($igst): ?><th class="text-end">IGST</th><?php else: ?><th class="text-end">CGST</th><th class="text-end">SGST</th><?php endif; ?>
                                <th class="text-end">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($items as $i => $it): $half = round($it['tax_amount'] / 2, 2); ?>
                            <tr>
                                <td><?= $i + 1 ?></td>
                                <td><?= e($it['description']) ?></td>
                                <td><?= e($it['hsn_code'] ?: '—') ?></td>
                                <td class="text-end text-nowrap"><?= e($fmtQty($it['qty'])) ?> <?= e($it['unit']) ?></td>
                                <td class="text-end"><?= money($it['unit_price']) ?></td>
                                <td class="text-end"><?= money($it['taxable']) ?></td>
                                <td class="text-end"><?= e($fmtRate($it['gst_rate'])) ?>%</td>
                                <?php if ($igst): ?><td class="text-end"><?= money($it['tax_amount']) ?></td><?php else: ?><td class="text-end"><?= money($half) ?></td><td class="text-end"><?= money($it['tax_amount'] - $half) ?></td><?php endif; ?>
                                <td class="text-end fw-semibold"><?= money($it['taxable'] + $it['tax_amount']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="row mt-3">
                    <div class="col-md-7">
                        <div class="fs-12 text-muted mb-1">Amount in words</div>
                        <div class="fw-semibold mb-3"><?= e(amount_in_words((float) $inv['grand_total'])) ?></div>
                        <table class="table table-sm table-bordered fs-11 mb-3">
                            <thead class="bg-gray-100"><tr><th>HSN/SAC</th><th class="text-end">Taxable</th><th class="text-end">Rate</th><?= $igst ? '<th class="text-end">IGST</th>' : '<th class="text-end">CGST</th><th class="text-end">SGST</th>' ?><th class="text-end">Total tax</th></tr></thead>
                            <?php foreach ($taxRows as $tr): $h = round($tr['tax'] / 2, 2); ?>
                                <tr><td><?= e($tr['hsn'] ?: '—') ?></td><td class="text-end"><?= money($tr['taxable']) ?></td><td class="text-end"><?= e($fmtRate($tr['rate'])) ?>%</td>
                                    <?= $igst ? '<td class="text-end">' . money($tr['tax']) . '</td>' : '<td class="text-end">' . money($h) . '</td><td class="text-end">' . money($tr['tax'] - $h) . '</td>' ?>
                                    <td class="text-end"><?= money($tr['tax']) ?></td></tr>
                            <?php endforeach; ?>
                        </table>
                        <?php if (setting('bank_details') || setting('upi_id')): ?>
                            <div class="fs-12 text-uppercase text-muted fw-bold mb-1">Payment details</div>
                            <div class="fs-12 mb-3" style="white-space:pre-line"><?= e(setting('bank_details')) ?><?= setting('upi_id') ? "\nUPI ID: " . e(setting('upi_id')) : '' ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-5">
                        <table class="table table-sm">
                            <tr><td class="text-muted">Subtotal</td><td class="text-end"><?= money($inv['subtotal']) ?></td></tr>
                            <?php if ((float) $inv['discount'] > 0): ?><tr><td class="text-muted">Discount</td><td class="text-end">− <?= money($inv['discount']) ?></td></tr><?php endif; ?>
                            <tr><td class="text-muted">Taxable value</td><td class="text-end"><?= money($inv['taxable_total']) ?></td></tr>
                            <?php if ($igst): ?><tr><td class="text-muted">IGST</td><td class="text-end"><?= money($inv['igst']) ?></td></tr>
                            <?php else: ?><tr><td class="text-muted">CGST</td><td class="text-end"><?= money($inv['cgst']) ?></td></tr><tr><td class="text-muted">SGST</td><td class="text-end"><?= money($inv['sgst']) ?></td></tr><?php endif; ?>
                            <?php if ((float) $inv['round_off'] != 0): ?><tr><td class="text-muted">Round off</td><td class="text-end"><?= money($inv['round_off']) ?></td></tr><?php endif; ?>
                            <tr class="fs-5"><td class="fw-bold">Grand total</td><td class="text-end fw-bold text-primary"><?= money($inv['grand_total']) ?></td></tr>
                            <tr><td class="text-muted">Paid</td><td class="text-end"><?= money($inv['amount_paid']) ?></td></tr>
                            <tr><td class="fw-bold">Balance due</td><td class="text-end fw-bold <?= $balance > 0 ? 'text-danger' : 'text-success' ?>"><?= money(max(0, $balance)) ?></td></tr>
                        </table>
                    </div>
                </div>
                <?php if ($inv['notes']): ?><div class="fs-12 mb-3"><span class="text-muted">Notes:</span> <?= nl2br(e($inv['notes'])) ?></div><?php endif; ?>
                <?php if ($inv['terms']): ?><div class="fs-12 text-uppercase text-muted fw-bold mb-1">Terms & conditions</div><div class="fs-11" style="white-space:pre-line"><?= e($inv['terms']) ?></div><?php endif; ?>
                <div class="d-flex justify-content-between align-items-end mt-5">
                    <div class="fs-11 text-muted">This is a computer-generated invoice.</div>
                    <div class="text-center"><div style="height:50px"></div><div class="border-top pt-2 fs-12">For <?= e($company) ?><br>Authorised Signatory</div></div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 vh-doc-side no-print">
        <div class="card">
            <div class="card-header"><h5 class="card-title">Status</h5><?= status_badge($overdue ? 'overdue' : $inv['status']) ?></div>
            <div class="card-body">
                <div class="d-flex justify-content-between mb-2"><span class="text-muted">Total</span><strong><?= money($inv['grand_total']) ?></strong></div>
                <div class="d-flex justify-content-between mb-2"><span class="text-muted">Received</span><strong class="text-success"><?= money($inv['amount_paid']) ?></strong></div>
                <div class="d-flex justify-content-between"><span class="text-muted">Balance</span><strong class="<?= $balance > 0 ? 'text-danger' : '' ?>"><?= money(max(0, $balance)) ?></strong></div>
                <?php if ($overdue): ?><div class="alert alert-danger py-2 fs-12 mt-3 mb-0">Overdue since <?= e(fmt_date($inv['due_date'])) ?></div><?php endif; ?>
            </div>
        </div>

        <?php if ($inv['status'] !== 'cancelled' && $balance > 0): ?>
        <div class="card">
            <div class="card-header"><h5 class="card-title">Record payment</h5></div>
            <div class="card-body">
                <form method="post">
                    <?= csrf_field() ?><input type="hidden" name="action" value="payment">
                    <div class="mb-2"><label class="form-label">Amount (₹)</label><input type="number" step="0.01" min="0.01" name="amount" class="form-control" value="<?= e($balance) ?>" required></div>
                    <div class="mb-2"><label class="form-label">Date</label><input type="date" name="payment_date" class="form-control" value="<?= date('Y-m-d') ?>"></div>
                    <div class="mb-2"><label class="form-label">Mode</label><select name="mode" class="form-select"><?php foreach (payment_modes() as $k => $v): ?><option value="<?= $k ?>"><?= e($v) ?></option><?php endforeach; ?></select></div>
                    <div class="mb-3"><label class="form-label">Reference (UTR / cheque no.)</label><input name="reference" class="form-control" maxlength="120"></div>
                    <button class="btn btn-success w-100"><i class="feather-check me-2"></i>Save payment</button>
                </form>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($payments): ?>
        <div class="card">
            <div class="card-header"><h5 class="card-title">Payments</h5></div>
            <div class="card-body">
                <?php foreach ($payments as $pay): ?>
                    <div class="d-flex justify-content-between align-items-start mb-3 pb-3 border-bottom border-bottom-dashed">
                        <div>
                            <a href="payment-receipt.php?id=<?= (int) $pay['id'] ?>" class="fw-semibold"><?= e($pay['receipt_no']) ?></a>
                            <div class="fs-11 text-muted"><?= e(fmt_date($pay['payment_date'])) ?> · <?= e(payment_modes()[$pay['mode']] ?? $pay['mode']) ?><?= $pay['reference'] ? ' · ' . e($pay['reference']) : '' ?></div>
                        </div>
                        <div class="text-end">
                            <div class="fw-semibold text-success"><?= money($pay['amount']) ?></div>
                            <form method="post" data-confirm="Remove this payment?" class="m-0"><?= csrf_field() ?><input type="hidden" name="action" value="delete_payment"><input type="hidden" name="payment_id" value="<?= (int) $pay['id'] ?>">
                                <button class="btn btn-link p-0 fs-11 text-danger">remove</button></form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header"><h5 class="card-title">Actions</h5></div>
            <div class="card-body d-grid gap-2">
                <?php if (strlen($wa) >= 11): ?><a href="https://wa.me/<?= e($wa) ?>?text=<?= rawurlencode($waText) ?>" target="_blank" class="btn btn-success"><i class="feather-message-circle me-2"></i>Share on WhatsApp</a><?php endif; ?>
                <?php if ($inv['customer_email']): ?><a href="mailto:<?= e($inv['customer_email']) ?>?subject=<?= rawurlencode("Invoice {$inv['invoice_no']} – $company") ?>&body=<?= rawurlencode($waText) ?>" class="btn btn-light-brand"><i class="feather-mail me-2"></i>Email customer</a><?php endif; ?>
                <?php if ($inv['customer_id']): ?><a href="customer-ledger.php?id=<?= (int) $inv['customer_id'] ?>" class="btn btn-light-brand"><i class="feather-book-open me-2"></i>Customer ledger</a><?php endif; ?>
                <a href="invoice-form.php?duplicate=<?= $id ?>" class="btn btn-light-brand"><i class="feather-copy me-2"></i>Duplicate</a>
                <?php if ($inv['status'] !== 'cancelled'): ?>
                    <form method="post" data-confirm="Cancel this invoice?<?= $inv['stock_applied'] ? ' Its items will be returned to stock.' : '' ?>"><?= csrf_field() ?><button name="action" value="cancel" class="btn btn-outline-warning w-100"><i class="feather-slash me-2"></i>Cancel invoice</button></form>
                <?php endif; ?>
                <?php if (is_owner($admin)): ?>
                    <form method="post" data-confirm="Delete this invoice and its payments permanently?"><?= csrf_field() ?><button name="action" value="delete" class="btn btn-outline-danger w-100"><i class="feather-trash-2 me-2"></i>Delete</button></form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php require __DIR__ . '/partials/footer.php';
