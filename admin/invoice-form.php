<?php
require __DIR__ . '/../includes/auth.php';
$admin = require_admin();

$id = (int) get('id');
$inv = $id ? q_row('SELECT * FROM invoices WHERE id = ?', [$id]) : null;
if ($id && !$inv) {
    flash('error', 'Invoice not found.');
    redirect('admin/invoices.php');
}
if ($inv && $inv['status'] === 'cancelled') {
    flash('error', 'A cancelled invoice cannot be edited.');
    redirect('admin/invoice-view.php?id=' . $id);
}

$products = q_all('SELECT id, name, sku, hsn_code, unit, price, gst_rate, stock_qty FROM products WHERE is_active = 1 OR id IN (SELECT product_id FROM invoice_items WHERE invoice_id = ?) ORDER BY name', [$id]);
$productMap = array_column($products, null, 'id');
$customers = q_all('SELECT id, name, organization, email, phone, address, city, state, pincode, gstin FROM customers ORDER BY organization, name');
$customerMap = array_column($customers, null, 'id');
$states = indian_states();
$companyState = setting('company_state');
$addr = fn(array $c) => trim(implode(', ', array_filter([$c['address'], $c['city'], $c['state'], $c['pincode']])), ', ');
$custState = fn(array $c) => state_from_gstin($c['gstin']) ?: $c['state'];

if ($inv) {
    $data = $inv;
    $items = q_all('SELECT * FROM invoice_items WHERE invoice_id = ? ORDER BY sort_order, id', [$id]);
} else {
    $data = [
        'customer_id' => null, 'quotation_id' => null, 'customer_name' => '', 'customer_org' => '', 'customer_email' => '',
        'customer_phone' => '', 'customer_address' => '', 'customer_gstin' => '', 'place_of_supply' => $companyState,
        'invoice_date' => date('Y-m-d'), 'due_date' => date('Y-m-d', strtotime('+' . (int) setting('invoice_due_days', '15') . ' days')),
        'discount' => 0, 'notes' => '', 'terms' => setting('invoice_terms'), 'stock_applied' => 1,
    ];
    $items = [];
    if (($qid = (int) get('quotation_id')) && ($qt = q_row('SELECT * FROM quotations WHERE id = ?', [$qid]))) {
        $data = array_merge($data, ['quotation_id' => $qid, 'customer_id' => $qt['customer_id'], 'customer_name' => $qt['customer_name'],
            'customer_org' => $qt['customer_org'], 'customer_email' => $qt['customer_email'], 'customer_phone' => $qt['customer_phone'],
            'customer_address' => $qt['customer_address'], 'customer_gstin' => $qt['customer_gstin'], 'discount' => $qt['discount'], 'notes' => $qt['notes']]);
        if ($qt['customer_gstin'] && state_from_gstin($qt['customer_gstin'])) $data['place_of_supply'] = state_from_gstin($qt['customer_gstin']);
        elseif ($qt['customer_id'] && isset($customerMap[$qt['customer_id']]) && $custState($customerMap[$qt['customer_id']])) $data['place_of_supply'] = $custState($customerMap[$qt['customer_id']]);
        foreach (q_all('SELECT * FROM quotation_items WHERE quotation_id = ? ORDER BY sort_order, id', [$qid]) as $qi) {
            $p = $productMap[$qi['product_id']] ?? null;
            $items[] = ['product_id' => $qi['product_id'], 'description' => $qi['description'], 'hsn_code' => $p['hsn_code'] ?? '', 'qty' => $qi['qty'],
                'unit' => $p['unit'] ?? 'Unit', 'unit_price' => $qi['unit_price'], 'gst_rate' => $qi['gst_rate']];
        }
    }
    if (($dup = (int) get('duplicate')) && ($src = q_row('SELECT * FROM invoices WHERE id = ?', [$dup]))) {
        foreach (['customer_id', 'customer_name', 'customer_org', 'customer_email', 'customer_phone', 'customer_address', 'customer_gstin', 'place_of_supply', 'discount', 'notes', 'terms'] as $f) {
            $data[$f] = $src[$f];
        }
        $items = q_all('SELECT product_id, description, hsn_code, qty, unit, unit_price, gst_rate FROM invoice_items WHERE invoice_id = ? ORDER BY sort_order, id', [$dup]);
    }
    if (($cid = (int) get('customer_id')) && isset($customerMap[$cid])) {
        $c = $customerMap[$cid];
        $data = array_merge($data, ['customer_id' => $cid, 'customer_name' => $c['name'], 'customer_org' => $c['organization'], 'customer_email' => $c['email'],
            'customer_phone' => $c['phone'], 'customer_address' => $addr($c), 'customer_gstin' => $c['gstin'], 'place_of_supply' => $custState($c) ?: $companyState]);
    }
    if (($pid = (int) get('product_id')) && isset($productMap[$pid])) {
        $p = $productMap[$pid];
        $items[] = ['product_id' => $pid, 'description' => $p['name'], 'hsn_code' => $p['hsn_code'], 'qty' => 1, 'unit' => $p['unit'], 'unit_price' => $p['price'] ?? 0, 'gst_rate' => $p['gst_rate']];
    }
}
$errors = [];
$payNow = ['amount' => '', 'mode' => 'cash', 'reference' => ''];

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_check();
    foreach (['customer_name', 'customer_org', 'customer_email', 'customer_phone', 'customer_address', 'customer_gstin', 'place_of_supply', 'invoice_date', 'due_date', 'notes', 'terms'] as $f) {
        $data[$f] = post($f);
    }
    $data['customer_gstin'] = strtoupper($data['customer_gstin']);
    $data['customer_id'] = isset($customerMap[(int) post('customer_id')]) ? (int) post('customer_id') : null;
    $data['quotation_id'] = (int) post('quotation_id') ?: null;
    $data['discount'] = max(0, (float) post('discount'));
    $data['stock_applied'] = isset($_POST['deduct_stock']) ? 1 : 0;
    if (!in_array($data['place_of_supply'], $states, true)) $data['place_of_supply'] = '';
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data['invoice_date'])) $data['invoice_date'] = date('Y-m-d');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $data['due_date'])) $data['due_date'] = null;
    $payNow = ['amount' => post('pay_amount'), 'mode' => array_key_exists(post('pay_mode'), payment_modes()) ? post('pay_mode') : 'cash', 'reference' => post('pay_reference')];

    $items = [];
    foreach ((array) ($_POST['item_desc'] ?? []) as $i => $desc) {
        $desc = trim((string) $desc);
        $pid = (int) ($_POST['item_product'][$i] ?? 0);
        if ($desc === '' && !$pid) continue;
        $items[] = [
            'product_id' => isset($productMap[$pid]) ? $pid : null,
            'description' => mb_substr($desc !== '' ? $desc : ($productMap[$pid]['name'] ?? 'Item'), 0, 300),
            'hsn_code' => mb_substr(trim((string) ($_POST['item_hsn'][$i] ?? '')), 0, 20),
            'qty' => max(0, (float) ($_POST['item_qty'][$i] ?? 1)),
            'unit' => mb_substr(trim((string) ($_POST['item_unit'][$i] ?? '')), 0, 30),
            'unit_price' => max(0, (float) ($_POST['item_price'][$i] ?? 0)),
            'gst_rate' => max(0, min(100, (float) ($_POST['item_gst'][$i] ?? 0))),
        ];
    }
    if ($data['customer_name'] === '' && $data['customer_org'] === '') $errors[] = 'Enter the customer name or organisation.';
    if (!$items) $errors[] = 'Add at least one item.';
    if ($data['customer_gstin'] !== '' && !preg_match('/^\d{2}[A-Z0-9]{13}$/', $data['customer_gstin'])) $errors[] = 'GSTIN should be 15 characters, e.g. 27ABCDE1234F1Z5.';

    if (!$errors) {
        $isIgst = $companyState !== '' && $data['place_of_supply'] !== '' && $data['place_of_supply'] !== $companyState;
        $t = calc_gst_totals($items, (float) $data['discount'], $isIgst);
        $pdo = db();
        $pdo->beginTransaction();
        try {
            // Undo the old stock deduction before re-applying the edited lines.
            if ($inv && $inv['stock_applied']) {
                foreach (q_all('SELECT product_id, qty FROM invoice_items WHERE invoice_id = ?', [$id]) as $old) {
                    adjust_stock($old['product_id'] ? (int) $old['product_id'] : null, (float) $old['qty'], 'Invoice ' . $inv['invoice_no'] . ' edited (reversed)', $admin['id']);
                }
            }
            $cols = ['customer_id', 'quotation_id', 'customer_name', 'customer_org', 'customer_email', 'customer_phone', 'customer_address', 'customer_gstin',
                'place_of_supply', 'invoice_date', 'due_date', 'notes', 'terms', 'stock_applied'];
            $vals = array_map(fn($c) => $data[$c], $cols);
            $cols = array_merge($cols, ['is_igst', 'subtotal', 'discount', 'taxable_total', 'cgst', 'sgst', 'igst', 'round_off', 'grand_total']);
            $vals = array_merge($vals, [$isIgst ? 1 : 0, $t['subtotal'], $t['discount'], $t['taxable_total'], $t['cgst'], $t['sgst'], $t['igst'], $t['round_off'], $t['grand_total']]);
            if ($inv) {
                q('UPDATE invoices SET ' . implode(', ', array_map(fn($c) => "$c = ?", $cols)) . ' WHERE id = ?', array_merge($vals, [$id]));
                q('DELETE FROM invoice_items WHERE invoice_id = ?', [$id]);
                $no = $inv['invoice_no'];
            } else {
                $no = next_doc_no('invoices', 'invoice_no', setting('invoice_prefix', 'VH-INV-'), $data['invoice_date']);
                q('INSERT INTO invoices (invoice_no, ' . implode(', ', $cols) . ') VALUES (?, ' . implode(', ', array_fill(0, count($cols), '?')) . ')', array_merge([$no], $vals));
                $id = (int) $pdo->lastInsertId();
            }
            foreach ($t['items'] as $n => $it) {
                q('INSERT INTO invoice_items (invoice_id, product_id, description, hsn_code, qty, unit, unit_price, gst_rate, line_total, taxable, tax_amount, sort_order)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                    [$id, $it['product_id'], $it['description'], $it['hsn_code'], $it['qty'], $it['unit'], $it['unit_price'], $it['gst_rate'], $it['line_total'], $it['taxable'], $it['tax_amount'], $n]);
                if ($data['stock_applied']) {
                    adjust_stock($it['product_id'], -$it['qty'], 'Invoice ' . $no, $admin['id']);
                }
            }
            if (!$inv && (float) $payNow['amount'] > 0) {
                q('INSERT INTO payments (receipt_no, direction, invoice_id, customer_id, party_name, payment_date, amount, mode, reference) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)', [
                    next_doc_no('payments', 'receipt_no', setting('receipt_prefix', 'VH-RCPT-'), $data['invoice_date']), 'in', $id, $data['customer_id'],
                    $data['customer_org'] ?: $data['customer_name'], $data['invoice_date'], min((float) $payNow['amount'], $t['grand_total']), $payNow['mode'], mb_substr($payNow['reference'], 0, 120),
                ]);
            }
            refresh_invoice_payment($id);
            if ($data['quotation_id']) {
                q("UPDATE quotations SET status = 'accepted' WHERE id = ? AND status <> 'accepted'", [$data['quotation_id']]);
            }
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
        log_activity($inv ? 'invoice.update' : 'invoice.create', ($inv ? 'Updated' : 'Created') . " invoice $no (" . money($t['grand_total']) . ')');
        flash('success', "Invoice $no saved.");
        redirect('admin/invoice-view.php?id=' . $id);
    }
}

if (!$items) {
    $items[] = ['product_id' => null, 'description' => '', 'hsn_code' => '', 'qty' => 1, 'unit' => 'Unit', 'unit_price' => '', 'gst_rate' => 12];
}

$pageTitle = $inv ? 'Edit ' . $inv['invoice_no'] : 'Create Invoice';
$activeNav = $inv ? 'invoices' : 'invoice-new';
$breadcrumbs = ['Invoices' => 'invoices.php', ($inv ? 'Edit' : 'Create') => null];
require __DIR__ . '/partials/header.php';
?>
<?php foreach ($errors as $err): ?><div class="alert alert-danger"><?= e($err) ?></div><?php endforeach; ?>
<?php if ($companyState === ''): ?>
    <div class="alert alert-info"><i class="feather-info me-2"></i>Set your <strong>company state</strong> in <a href="settings.php">Company Settings</a> so the dashboard can choose CGST + SGST (same state) or IGST (other state) automatically. Until then all invoices use CGST + SGST.</div>
<?php endif; ?>
<form method="post" id="docForm">
    <?= csrf_field() ?>
    <input type="hidden" name="quotation_id" value="<?= (int) $data['quotation_id'] ?>">
    <div class="row">
        <div class="col-xl-8">
            <div class="card stretch stretch-full">
                <div class="card-header"><h5 class="card-title">Bill to</h5>
                    <select name="customer_id" id="customerPick" class="form-select form-select-sm" style="max-width:280px">
                        <option value="">— Pick a saved customer —</option>
                        <?php foreach ($customers as $c): ?>
                            <option value="<?= (int) $c['id'] ?>" <?= (int) $data['customer_id'] === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['organization'] ?: $c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3"><label class="form-label">Contact name</label><input name="customer_name" id="c_name" class="form-control" value="<?= e($data['customer_name']) ?>"></div>
                        <div class="col-md-6 mb-3"><label class="form-label">Hospital / Organisation</label><input name="customer_org" id="c_org" class="form-control" value="<?= e($data['customer_org']) ?>"></div>
                        <div class="col-md-4 mb-3"><label class="form-label">Phone</label><input name="customer_phone" id="c_phone" class="form-control" value="<?= e($data['customer_phone']) ?>"></div>
                        <div class="col-md-4 mb-3"><label class="form-label">Email</label><input name="customer_email" id="c_email" class="form-control" value="<?= e($data['customer_email']) ?>"></div>
                        <div class="col-md-4 mb-3"><label class="form-label">GSTIN</label><input name="customer_gstin" id="c_gstin" class="form-control text-uppercase" maxlength="15" value="<?= e($data['customer_gstin']) ?>"></div>
                        <div class="col-md-8 mb-3 mb-md-0"><label class="form-label">Billing address</label><input name="customer_address" id="c_address" class="form-control" value="<?= e($data['customer_address']) ?>"></div>
                        <div class="col-md-4"><label class="form-label">Place of supply</label>
                            <select name="place_of_supply" id="pos" class="form-select">
                                <option value="">— Select state —</option>
                                <?php foreach ($states as $code => $st): ?><option value="<?= e($st) ?>" <?= $data['place_of_supply'] === $st ? 'selected' : '' ?>><?= $code ?> – <?= e($st) ?></option><?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

        </div>
        <div class="col-xl-4">
            <div class="card stretch stretch-full">
                <div class="card-header"><h5 class="card-title"><?= e($inv['invoice_no'] ?? 'New invoice') ?></h5></div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-6 mb-3"><label class="form-label">Invoice date</label><input type="date" name="invoice_date" class="form-control" value="<?= e($data['invoice_date']) ?>"></div>
                        <div class="col-6 mb-3"><label class="form-label">Due date</label><input type="date" name="due_date" class="form-control" value="<?= e($data['due_date']) ?>"></div>
                    </div>
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" id="deduct_stock" name="deduct_stock" <?= $data['stock_applied'] ? 'checked' : '' ?>>
                        <label class="form-check-label" for="deduct_stock">Deduct items from stock</label>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-12">
            <div class="card">
                <div class="card-header"><h5 class="card-title">Items</h5><span class="fs-12 text-muted" id="taxMode"></span></div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table vh-quote-items mb-0">
                            <thead><tr><th style="min-width:180px">Product</th><th style="min-width:200px">Description</th><th style="width:100px">HSN/SAC</th><th style="width:80px">Qty</th><th style="width:80px">Unit</th><th style="width:120px">Rate (₹)</th><th style="width:80px">GST %</th><th style="width:120px" class="text-end">Amount</th><th></th></tr></thead>
                            <tbody id="itemRows">
                            <?php foreach ($items as $it): ?>
                                <tr class="item-row">
                                    <td><select name="item_product[]" class="form-select item-product"><option value="">Custom item</option>
                                        <?php foreach ($products as $p): ?><option value="<?= (int) $p['id'] ?>" <?= (int) $it['product_id'] === (int) $p['id'] ? 'selected' : '' ?>><?= e($p['name']) ?></option><?php endforeach; ?>
                                    </select><div class="fs-11 text-muted item-stock mt-1"></div></td>
                                    <td><input name="item_desc[]" class="form-control item-desc" value="<?= e($it['description']) ?>" maxlength="300"></td>
                                    <td><input name="item_hsn[]" class="form-control item-hsn" value="<?= e($it['hsn_code']) ?>" maxlength="20"></td>
                                    <td><input type="number" step="0.01" min="0" name="item_qty[]" class="form-control item-qty" value="<?= e((float) $it['qty']) ?>"></td>
                                    <td><input name="item_unit[]" class="form-control item-unit" value="<?= e($it['unit']) ?>" maxlength="30"></td>
                                    <td><input type="number" step="0.01" min="0" name="item_price[]" class="form-control item-price" value="<?= e($it['unit_price'] === '' ? '' : (float) $it['unit_price']) ?>"></td>
                                    <td><input type="number" step="0.01" min="0" max="100" name="item_gst[]" class="form-control item-gst" value="<?= e((float) $it['gst_rate']) ?>"></td>
                                    <td class="text-end fw-semibold item-amount">₹0.00</td>
                                    <td><button type="button" class="btn btn-sm btn-light-brand item-remove" title="Remove"><i class="feather-x"></i></button></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="p-3"><button type="button" id="addItem" class="btn btn-sm btn-light-brand"><i class="feather-plus me-1"></i>Add item</button></div>
                </div>
            </div>

        </div>
        <div class="col-xl-7">
            <div class="card">
                <div class="card-body">
                    <div class="mb-3"><label class="form-label">Notes</label><textarea name="notes" class="form-control" rows="2"><?= e($data['notes']) ?></textarea></div>
                    <div><label class="form-label">Terms & conditions</label><textarea name="terms" class="form-control" rows="4"><?= e($data['terms']) ?></textarea></div>
                </div>
            </div>
        </div>
        <div class="col-xl-5">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between mb-2"><span class="text-muted">Subtotal</span><strong id="tSub">₹0.00</strong></div>
                    <div class="d-flex justify-content-between align-items-center mb-2"><span class="text-muted">Discount (₹)</span><input type="number" step="0.01" min="0" name="discount" id="discount" class="form-control form-control-sm text-end" style="width:130px" value="<?= e((float) $data['discount']) ?>"></div>
                    <div class="d-flex justify-content-between mb-2"><span class="text-muted">Taxable value</span><strong id="tTaxable">₹0.00</strong></div>
                    <div class="d-flex justify-content-between mb-2 row-cgst"><span class="text-muted">CGST</span><strong id="tCgst">₹0.00</strong></div>
                    <div class="d-flex justify-content-between mb-2 row-cgst"><span class="text-muted">SGST</span><strong id="tSgst">₹0.00</strong></div>
                    <div class="d-flex justify-content-between mb-2 row-igst" style="display:none!important"><span class="text-muted">IGST</span><strong id="tIgst">₹0.00</strong></div>
                    <div class="d-flex justify-content-between mb-2"><span class="text-muted">Round off</span><strong id="tRound">₹0.00</strong></div>
                    <div class="d-flex justify-content-between fs-5 border-top pt-2"><span class="fw-bold">Total</span><strong class="text-primary" id="tGrand">₹0</strong></div>
                    <?php if (!$inv): ?>
                        <hr>
                        <label class="form-label">Payment received now (optional)</label>
                        <div class="input-group mb-2">
                            <input type="number" step="0.01" min="0" name="pay_amount" id="payAmount" class="form-control" placeholder="Amount" value="<?= e($payNow['amount']) ?>">
                            <button type="button" class="btn btn-light-brand" id="payFull">Full</button>
                        </div>
                        <div class="d-flex gap-2">
                            <select name="pay_mode" class="form-select form-select-sm"><?php foreach (payment_modes() as $k => $v): ?><option value="<?= $k ?>" <?= $payNow['mode'] === $k ? 'selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?></select>
                            <input name="pay_reference" class="form-control form-control-sm" placeholder="Ref / UTR" value="<?= e($payNow['reference']) ?>">
                        </div>
                    <?php endif; ?>
                    <button class="btn btn-primary w-100 mt-4"><i class="feather-save me-2"></i>Save invoice</button>
                </div>
            </div>
        </div>
    </div>
</form>
<?php
$jsProducts = [];
foreach ($productMap as $pid => $p) {
    $jsProducts[$pid] = ['name' => $p['name'], 'hsn' => $p['hsn_code'], 'unit' => $p['unit'], 'price' => $p['price'] !== null ? (float) $p['price'] : '', 'gst' => (float) $p['gst_rate'], 'stock' => (int) $p['stock_qty']];
}
$jsCustomers = [];
foreach ($customerMap as $cid => $c) {
    $jsCustomers[$cid] = ['name' => $c['name'], 'org' => $c['organization'], 'phone' => $c['phone'], 'email' => $c['email'], 'gstin' => $c['gstin'], 'address' => $addr($c), 'state' => $custState($c)];
}
$inlineJs = 'var VH_PRODUCTS=' . json_encode((object) $jsProducts, JSON_HEX_TAG | JSON_HEX_AMP) . ';var VH_CUSTOMERS=' . json_encode((object) $jsCustomers, JSON_HEX_TAG | JSON_HEX_AMP)
    . ';var VH_COMPANY_STATE=' . json_encode($companyState) . ';var VH_STATES=' . json_encode(indian_states()) . ';' . <<<'JS'
(function () {
    var rows = document.getElementById('itemRows'), pos = document.getElementById('pos'), grand = 0;
    var fmt = function (n) { return '₹' + n.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); };
    var r2 = function (n) { return Math.round(n * 100) / 100; };
    function recalc() {
        var lines = [], sub = 0;
        rows.querySelectorAll('.item-row').forEach(function (r) {
            var amt = r2((parseFloat(r.querySelector('.item-qty').value) || 0) * (parseFloat(r.querySelector('.item-price').value) || 0));
            r.querySelector('.item-amount').textContent = fmt(amt);
            lines.push([amt, parseFloat(r.querySelector('.item-gst').value) || 0]); sub += amt;
            var p = VH_PRODUCTS[r.querySelector('.item-product').value];
            r.querySelector('.item-stock').textContent = p ? 'In stock: ' + p.stock : '';
        });
        var disc = Math.min(parseFloat(document.getElementById('discount').value) || 0, sub), taxable = 0, tax = 0;
        lines.forEach(function (l) { var t = r2(l[0] - (sub > 0 ? disc * l[0] / sub : 0)); taxable += t; tax += r2(t * l[1] / 100); });
        taxable = r2(taxable); tax = r2(tax);
        var igst = VH_COMPANY_STATE !== '' && pos.value !== '' && pos.value !== VH_COMPANY_STATE;
        var exact = r2(taxable + tax); grand = Math.round(exact);
        document.getElementById('tSub').textContent = fmt(sub);
        document.getElementById('tTaxable').textContent = fmt(taxable);
        document.getElementById('tCgst').textContent = fmt(igst ? 0 : r2(tax / 2));
        document.getElementById('tSgst').textContent = fmt(igst ? 0 : r2(tax - r2(tax / 2)));
        document.getElementById('tIgst').textContent = fmt(igst ? tax : 0);
        document.getElementById('tRound').textContent = fmt(r2(grand - exact));
        document.getElementById('tGrand').textContent = '₹' + grand.toLocaleString('en-IN', { minimumFractionDigits: 2 });
        document.querySelectorAll('.row-cgst').forEach(function (el) { el.style.setProperty('display', igst ? 'none' : 'flex', 'important'); });
        document.querySelector('.row-igst').style.setProperty('display', igst ? 'flex' : 'none', 'important');
        document.getElementById('taxMode').textContent = igst ? 'Inter-state supply → IGST' : 'Intra-state supply → CGST + SGST';
    }
    rows.addEventListener('input', recalc);
    pos.addEventListener('change', recalc);
    document.getElementById('discount').addEventListener('input', recalc);
    rows.addEventListener('change', function (e) {
        if (!e.target.classList.contains('item-product')) return;
        var p = VH_PRODUCTS[e.target.value], r = e.target.closest('tr');
        if (p) {
            r.querySelector('.item-desc').value = p.name; r.querySelector('.item-hsn').value = p.hsn;
            r.querySelector('.item-unit').value = p.unit; r.querySelector('.item-gst').value = p.gst;
            if (p.price !== '') r.querySelector('.item-price').value = p.price;
        }
        recalc();
    });
    rows.addEventListener('click', function (e) {
        var btn = e.target.closest('.item-remove');
        if (btn && rows.querySelectorAll('.item-row').length > 1) { btn.closest('tr').remove(); recalc(); }
    });
    document.getElementById('addItem').addEventListener('click', function () {
        var clone = rows.querySelector('.item-row').cloneNode(true);
        clone.querySelectorAll('input').forEach(function (i) {
            i.value = i.classList.contains('item-qty') ? 1 : i.classList.contains('item-gst') ? 12 : i.classList.contains('item-unit') ? 'Unit' : '';
        });
        clone.querySelector('select').value = '';
        rows.appendChild(clone); recalc();
    });
    document.getElementById('customerPick').addEventListener('change', function () {
        var c = VH_CUSTOMERS[this.value];
        if (!c) return;
        ['name', 'org', 'phone', 'email', 'gstin', 'address'].forEach(function (k) { document.getElementById('c_' + k).value = c[k] || ''; });
        if (c.state) { pos.value = c.state; }
        recalc();
    });
    document.getElementById('c_gstin').addEventListener('input', function () {
        var st = VH_STATES[this.value.substr(0, 2)];
        if (this.value.length >= 2 && st) { pos.value = st; recalc(); }
    });
    var full = document.getElementById('payFull');
    if (full) full.addEventListener('click', function () { document.getElementById('payAmount').value = grand; });
    recalc();
})();
JS;
require __DIR__ . '/partials/footer.php';
