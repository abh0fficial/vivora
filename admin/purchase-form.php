<?php
require __DIR__ . '/../includes/auth.php';
$admin = require_admin();

$id = (int) get('id');
$pur = $id ? q_row('SELECT * FROM purchases WHERE id = ?', [$id]) : null;
if ($id && !$pur) {
    flash('error', 'Purchase bill not found.');
    redirect('admin/purchases.php');
}
$products = q_all('SELECT id, name, purchase_price, price, gst_rate FROM products ORDER BY name');
$productMap = array_column($products, null, 'id');
$suppliers = q_all('SELECT id, name FROM suppliers ORDER BY name');
$supplierMap = array_column($suppliers, null, 'id');
$modes = payment_modes();

if ($pur) {
    $data = $pur;
    $items = q_all('SELECT * FROM purchase_items WHERE purchase_id = ? ORDER BY sort_order, id', [$id]);
} else {
    $data = ['supplier_id' => (int) get('supplier_id') ?: null, 'supplier_name' => '', 'bill_no' => '', 'bill_date' => date('Y-m-d'), 'due_date' => '', 'notes' => '', 'stock_applied' => 1];
    $items = [];
}
$errors = [];

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_check();
    $action = post('action', 'save');
    if ($pur && $action === 'payment') {
        $amount = round((float) post('amount'), 2);
        if ($amount > 0) {
            $date = preg_match('/^\d{4}-\d{2}-\d{2}$/', post('payment_date')) ? post('payment_date') : date('Y-m-d');
            q('INSERT INTO payments (receipt_no, direction, purchase_id, supplier_id, party_name, payment_date, amount, mode, reference) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [mb_substr(post('reference'), 0, 40), 'out', $id, $pur['supplier_id'], $pur['supplier_name'], $date, $amount,
                    array_key_exists(post('mode'), $modes) ? post('mode') : 'cash', mb_substr(post('reference'), 0, 120)]);
            set_payment_account((int) db()->lastInsertId(), $_POST['account_id'] ?? null);
            refresh_purchase_payment($id);
            log_activity('payment.create', 'Paid ' . money($amount) . ' to ' . $pur['supplier_name'] . ' for bill ' . $pur['bill_no']);
            flash('success', 'Payment of ' . money($amount) . ' recorded.');
        }
        redirect('admin/purchase-form.php?id=' . $id);
    }
    if ($pur && $action === 'delete_payment') {
        q("DELETE FROM payments WHERE id = ? AND purchase_id = ?", [(int) post('payment_id'), $id]);
        refresh_purchase_payment($id);
        flash('success', 'Payment removed.');
        redirect('admin/purchase-form.php?id=' . $id);
    }

    $data['supplier_id'] = isset($supplierMap[(int) post('supplier_id')]) ? (int) post('supplier_id') : null;
    $data['supplier_name'] = $data['supplier_id'] ? $supplierMap[$data['supplier_id']]['name'] : post('supplier_name');
    $data['bill_no'] = mb_substr(post('bill_no'), 0, 60);
    $data['bill_date'] = preg_match('/^\d{4}-\d{2}-\d{2}$/', post('bill_date')) ? post('bill_date') : date('Y-m-d');
    $data['due_date'] = preg_match('/^\d{4}-\d{2}-\d{2}$/', post('due_date')) ? post('due_date') : null;
    $data['notes'] = post('notes');
    $data['stock_applied'] = isset($_POST['add_stock']) ? 1 : 0;
    $items = [];
    foreach ((array) ($_POST['item_desc'] ?? []) as $i => $desc) {
        $desc = trim((string) $desc);
        $pid = (int) ($_POST['item_product'][$i] ?? 0);
        if ($desc === '' && !$pid) continue;
        $qty = max(0, (float) ($_POST['item_qty'][$i] ?? 1));
        $price = max(0, (float) ($_POST['item_price'][$i] ?? 0));
        $rate = max(0, min(100, (float) ($_POST['item_gst'][$i] ?? 0)));
        $line = round($qty * $price, 2);
        $items[] = ['product_id' => isset($productMap[$pid]) ? $pid : null, 'description' => mb_substr($desc !== '' ? $desc : $productMap[$pid]['name'], 0, 300),
            'qty' => $qty, 'unit_price' => $price, 'gst_rate' => $rate, 'line_total' => $line, 'tax_amount' => round($line * $rate / 100, 2)];
    }
    if ($data['supplier_name'] === '') $errors[] = 'Choose a supplier or type the supplier name.';
    if (!$items) $errors[] = 'Add at least one item.';
    if (!$errors) {
        $sub = round(array_sum(array_column($items, 'line_total')), 2);
        $tax = round(array_sum(array_column($items, 'tax_amount')), 2);
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $label = 'Purchase bill ' . ($data['bill_no'] ?: '') . ' – ' . $data['supplier_name'];
            if ($pur && $pur['stock_applied']) {
                foreach (q_all('SELECT product_id, qty FROM purchase_items WHERE purchase_id = ?', [$id]) as $old) {
                    adjust_stock($old['product_id'] ? (int) $old['product_id'] : null, -(float) $old['qty'], $label . ' edited (reversed)', $admin['id']);
                }
            }
            $cols = ['supplier_id', 'supplier_name', 'bill_no', 'bill_date', 'due_date', 'notes', 'stock_applied'];
            $vals = array_merge(array_map(fn($c) => $data[$c], $cols), [$sub, $tax, round($sub + $tax, 2)]);
            $cols = array_merge($cols, ['subtotal', 'tax_total', 'grand_total']);
            if ($pur) {
                q('UPDATE purchases SET ' . implode(', ', array_map(fn($c) => "$c = ?", $cols)) . ' WHERE id = ?', array_merge($vals, [$id]));
                q('DELETE FROM purchase_items WHERE purchase_id = ?', [$id]);
            } else {
                q('INSERT INTO purchases (' . implode(', ', $cols) . ') VALUES (' . implode(', ', array_fill(0, count($cols), '?')) . ')', $vals);
                $id = (int) $pdo->lastInsertId();
            }
            foreach ($items as $n => $it) {
                q('INSERT INTO purchase_items (purchase_id, product_id, description, qty, unit_price, gst_rate, line_total, tax_amount, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
                    [$id, $it['product_id'], $it['description'], $it['qty'], $it['unit_price'], $it['gst_rate'], $it['line_total'], $it['tax_amount'], $n]);
                if ($data['stock_applied']) adjust_stock($it['product_id'], $it['qty'], $label, $admin['id']);
                if ($it['product_id']) q('UPDATE products SET purchase_price = ? WHERE id = ?', [$it['unit_price'], $it['product_id']]);
            }
            if (!$pur && (float) post('pay_amount') > 0) {
                q('INSERT INTO payments (receipt_no, direction, purchase_id, supplier_id, party_name, payment_date, amount, mode, reference) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
                    ['', 'out', $id, $data['supplier_id'], $data['supplier_name'], $data['bill_date'], min((float) post('pay_amount'), $sub + $tax),
                        array_key_exists(post('pay_mode'), $modes) ? post('pay_mode') : 'cash', mb_substr(post('pay_reference'), 0, 120)]);
                set_payment_account((int) db()->lastInsertId(), $_POST['account_id'] ?? null);
            }
            refresh_purchase_payment($id);
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
        log_activity($pur ? 'purchase.update' : 'purchase.create', ($pur ? 'Updated' : 'Recorded') . ' purchase from ' . $data['supplier_name'] . ' (' . money($sub + $tax) . ')');
        flash('success', 'Purchase bill saved' . ($data['stock_applied'] ? ' and stock updated.' : '.'));
        redirect('admin/purchase-form.php?id=' . $id);
    }
}
if (!$items) $items[] = ['product_id' => null, 'description' => '', 'qty' => 1, 'unit_price' => '', 'gst_rate' => 12];
$payments = $pur ? q_all("SELECT * FROM payments WHERE purchase_id = ? AND direction = 'out' ORDER BY payment_date, id", [$id]) : [];
$balance = $pur ? round($pur['grand_total'] - $pur['amount_paid'], 2) : 0;

$pageTitle = $pur ? 'Purchase Bill ' . ($pur['bill_no'] ?: '#' . $id) : 'New Purchase Bill';
$activeNav = $pur ? 'purchases' : 'purchase-new';
$breadcrumbs = ['Purchases' => 'purchases.php', ($pur ? 'Bill' : 'New') => null];
require __DIR__ . '/partials/header.php';
?>
<?php foreach ($errors as $err): ?><div class="alert alert-danger"><?= e($err) ?></div><?php endforeach; ?>
<div class="row">
    <div class="col-12">
        <form method="post" id="purForm">
            <?= csrf_field() ?>
            <div class="card">
                <div class="card-header"><h5 class="card-title">Supplier bill</h5><?= $pur ? status_badge($pur['status']) : '' ?></div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3"><label class="form-label">Supplier</label>
                            <select name="supplier_id" class="form-select"><option value="">— Type name below —</option><?php foreach ($suppliers as $s): ?><option value="<?= (int) $s['id'] ?>" <?= (int) $data['supplier_id'] === (int) $s['id'] ? 'selected' : '' ?>><?= e($s['name']) ?></option><?php endforeach; ?></select>
                            <div class="form-text"><a href="suppliers.php">+ Add a supplier</a></div></div>
                        <div class="col-md-6 mb-3"><label class="form-label">…or supplier name</label><input name="supplier_name" class="form-control" value="<?= e($data['supplier_id'] ? '' : $data['supplier_name']) ?>" maxlength="200"></div>
                        <div class="col-md-4 mb-3"><label class="form-label">Supplier bill no.</label><input name="bill_no" class="form-control" value="<?= e($data['bill_no']) ?>" maxlength="60"></div>
                        <div class="col-md-4 mb-3"><label class="form-label">Bill date</label><input type="date" name="bill_date" class="form-control" value="<?= e($data['bill_date']) ?>"></div>
                        <div class="col-md-4 mb-3"><label class="form-label">Payment due</label><input type="date" name="due_date" class="form-control" value="<?= e($data['due_date']) ?>"></div>
                    </div>
                    <div class="form-check form-switch"><input class="form-check-input" type="checkbox" id="add_stock" name="add_stock" <?= $data['stock_applied'] ? 'checked' : '' ?>><label class="form-check-label" for="add_stock">Add these items to stock</label></div>
                </div>
            </div>
            <div class="card">
                <div class="card-header"><h5 class="card-title">Items</h5></div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table vh-quote-items mb-0">
                            <thead><tr><th style="min-width:180px">Product</th><th style="min-width:200px">Description</th><th style="width:90px">Qty</th><th style="width:130px">Cost rate (₹)</th><th style="width:90px">GST %</th><th style="width:130px" class="text-end">Amount</th><th></th></tr></thead>
                            <tbody id="itemRows">
                            <?php foreach ($items as $it): ?>
                                <tr class="item-row">
                                    <td><select name="item_product[]" class="form-select item-product"><option value="">Other item</option><?php foreach ($products as $p): ?><option value="<?= (int) $p['id'] ?>" <?= (int) $it['product_id'] === (int) $p['id'] ? 'selected' : '' ?>><?= e($p['name']) ?></option><?php endforeach; ?></select></td>
                                    <td><input name="item_desc[]" class="form-control item-desc" value="<?= e($it['description']) ?>" maxlength="300"></td>
                                    <td><input type="number" step="0.01" min="0" name="item_qty[]" class="form-control item-qty" value="<?= e((float) $it['qty']) ?>"></td>
                                    <td><input type="number" step="0.01" min="0" name="item_price[]" class="form-control item-price" value="<?= e($it['unit_price'] === '' ? '' : (float) $it['unit_price']) ?>"></td>
                                    <td><input type="number" step="0.01" min="0" max="100" name="item_gst[]" class="form-control item-gst" value="<?= e((float) $it['gst_rate']) ?>"></td>
                                    <td class="text-end fw-semibold item-amount">₹0.00</td>
                                    <td><button type="button" class="btn btn-sm btn-light-brand item-remove"><i class="feather-x"></i></button></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="p-3 d-flex justify-content-between flex-wrap gap-3">
                        <button type="button" id="addItem" class="btn btn-sm btn-light-brand"><i class="feather-plus me-1"></i>Add item</button>
                        <div class="text-end fs-13"><div>Subtotal <strong id="tSub">₹0.00</strong></div><div>GST (input credit) <strong id="tTax">₹0.00</strong></div><div class="fs-5">Total <strong class="text-primary" id="tGrand">₹0.00</strong></div></div>
                    </div>
                </div>
            </div>
            <div class="card">
                <div class="card-body">
                    <label class="form-label">Notes</label><textarea name="notes" class="form-control" rows="2"><?= e($data['notes']) ?></textarea>
                    <?php if (!$pur): ?>
                        <div class="row mt-3">
                            <div class="col-md-4"><label class="form-label">Paid now (₹, optional)</label><input type="number" step="0.01" min="0" name="pay_amount" class="form-control"></div>
                            <div class="col-md-4"><label class="form-label">Mode</label><select name="pay_mode" class="form-select"><?php foreach ($modes as $k => $v): ?><option value="<?= $k ?>"><?= e($v) ?></option><?php endforeach; ?></select></div>
                            <div class="col-md-4"><label class="form-label">Reference</label><input name="pay_reference" class="form-control"></div>
                            <div class="col-md-4 mt-3"><label class="form-label">Paid from</label><?= account_select('account_id', null, 'cash') ?></div>
                        </div>
                    <?php endif; ?>
                    <button class="btn btn-primary mt-4"><i class="feather-save me-2"></i>Save purchase bill</button>
                </div>
            </div>
        </form>
    </div>
    <div class="col-xl-6">
        <?php if ($pur): ?>
            <div class="card">
                <div class="card-header"><h5 class="card-title">Payment</h5></div>
                <div class="card-body">
                    <div class="d-flex justify-content-between mb-2"><span class="text-muted">Bill total</span><strong><?= money($pur['grand_total']) ?></strong></div>
                    <div class="d-flex justify-content-between mb-2"><span class="text-muted">Paid</span><strong class="text-success"><?= money($pur['amount_paid']) ?></strong></div>
                    <div class="d-flex justify-content-between mb-3"><span class="text-muted">Balance payable</span><strong class="<?= $balance > 0 ? 'text-danger' : '' ?>"><?= money(max(0, $balance)) ?></strong></div>
                    <?php foreach ($payments as $pay): ?>
                        <div class="d-flex justify-content-between align-items-center fs-12 border-top py-2">
                            <span><?= e(fmt_date($pay['payment_date'])) ?> · <?= e($modes[$pay['mode']] ?? $pay['mode']) ?><?= $pay['reference'] ? ' · ' . e($pay['reference']) : '' ?></span>
                            <span><strong><?= money($pay['amount']) ?></strong>
                                <form method="post" class="d-inline" data-confirm="Remove this payment?"><?= csrf_field() ?><input type="hidden" name="action" value="delete_payment"><input type="hidden" name="payment_id" value="<?= (int) $pay['id'] ?>"><button class="btn btn-link p-0 ms-1 text-danger"><i class="feather-x"></i></button></form></span>
                        </div>
                    <?php endforeach; ?>
                    <?php if ($balance > 0): ?>
                        <form method="post" class="border-top pt-3 mt-2">
                            <?= csrf_field() ?><input type="hidden" name="action" value="payment">
                            <div class="row g-2">
                                <div class="col-6"><input type="number" step="0.01" min="0.01" name="amount" class="form-control" value="<?= e($balance) ?>" required></div>
                                <div class="col-6"><input type="date" name="payment_date" class="form-control" value="<?= date('Y-m-d') ?>"></div>
                                <div class="col-6"><select name="mode" class="form-select"><?php foreach ($modes as $k => $v): ?><option value="<?= $k ?>"><?= e($v) ?></option><?php endforeach; ?></select></div>
                                <div class="col-6"><input name="reference" class="form-control" placeholder="Ref / cheque"></div>
                                <div class="col-12"><?= account_select('account_id', null, 'cash') ?></div>
                            </div>
                            <button class="btn btn-success w-100 mt-3"><i class="feather-check me-2"></i>Record payment</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        <?php else: ?>
            <div class="card"><div class="card-body fs-13 text-muted"><i class="feather-info me-1"></i>Record bills from your suppliers here. Stock goes up automatically, the product's cost price is updated for profit reports, and GST on purchases shows as input credit in the GST report.</div></div>
        <?php endif; ?>
    </div>
</div>
<?php
$inlineJs = 'var VH_PRODUCTS=' . json_encode((object) array_map(fn($p) => ['name' => $p['name'], 'cost' => $p['purchase_price'] !== null ? (float) $p['purchase_price'] : '', 'gst' => (float) $p['gst_rate']], $productMap), JSON_HEX_TAG | JSON_HEX_AMP) . ';' . <<<'JS'
(function () {
    var rows = document.getElementById('itemRows');
    var fmt = function (n) { return '₹' + n.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); };
    function recalc() {
        var sub = 0, tax = 0;
        rows.querySelectorAll('.item-row').forEach(function (r) {
            var amt = Math.round((parseFloat(r.querySelector('.item-qty').value) || 0) * (parseFloat(r.querySelector('.item-price').value) || 0) * 100) / 100;
            sub += amt; tax += Math.round(amt * (parseFloat(r.querySelector('.item-gst').value) || 0)) / 100;
            r.querySelector('.item-amount').textContent = fmt(amt);
        });
        document.getElementById('tSub').textContent = fmt(sub); document.getElementById('tTax').textContent = fmt(tax); document.getElementById('tGrand').textContent = fmt(sub + tax);
    }
    rows.addEventListener('input', recalc);
    rows.addEventListener('change', function (e) {
        if (!e.target.classList.contains('item-product')) return;
        var p = VH_PRODUCTS[e.target.value], r = e.target.closest('tr');
        if (p) { r.querySelector('.item-desc').value = p.name; r.querySelector('.item-gst').value = p.gst; if (p.cost !== '') r.querySelector('.item-price').value = p.cost; }
        recalc();
    });
    rows.addEventListener('click', function (e) { var b = e.target.closest('.item-remove'); if (b && rows.querySelectorAll('.item-row').length > 1) { b.closest('tr').remove(); recalc(); } });
    document.getElementById('addItem').addEventListener('click', function () {
        var c = rows.querySelector('.item-row').cloneNode(true);
        c.querySelectorAll('input').forEach(function (i) { i.value = i.classList.contains('item-qty') ? 1 : i.classList.contains('item-gst') ? 12 : ''; });
        c.querySelector('select').value = ''; rows.appendChild(c); recalc();
    });
    recalc();
})();
JS;
require __DIR__ . '/partials/footer.php';
