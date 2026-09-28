<?php
require __DIR__ . '/../includes/auth.php';
$admin = require_admin();

$id = (int) get('id');
$quote = $id ? q_row('SELECT * FROM quotations WHERE id = ?', [$id]) : null;
if ($id && !$quote) {
    flash('error', 'Quotation not found.');
    redirect('admin/quotations.php');
}

$products = q_all('SELECT id, name, sku, price, gst_rate, unit FROM products ORDER BY name');
$productMap = array_column($products, null, 'id');
$customers = q_all('SELECT id, name, organization, email, phone, address, city, state, pincode, gstin FROM customers ORDER BY organization, name');
$customerMap = array_column($customers, null, 'id');

$customerAddress = function (array $c): string {
    return trim(implode(', ', array_filter([$c['address'], $c['city'], $c['state'], $c['pincode']])), ', ');
};

if ($quote) {
    $data = $quote;
    $items = q_all('SELECT * FROM quotation_items WHERE quotation_id = ? ORDER BY sort_order, id', [$id]);
} else {
    $data = [
        'quote_no' => next_quote_no(), 'customer_id' => null, 'enquiry_id' => null, 'customer_name' => '', 'customer_org' => '',
        'customer_email' => '', 'customer_phone' => '', 'customer_address' => '', 'customer_gstin' => '',
        'quote_date' => date('Y-m-d'), 'valid_until' => date('Y-m-d', strtotime('+' . (int) setting('quote_validity_days', '15') . ' days')),
        'status' => 'draft', 'discount' => 0, 'notes' => '', 'terms' => setting('quote_terms'),
    ];
    $items = [];
    // Pre-fill from an enquiry, a customer or a product.
    if ($eid = (int) get('enquiry_id')) {
        if ($enq = q_row('SELECT * FROM enquiries WHERE id = ?', [$eid])) {
            $data['enquiry_id'] = $eid;
            $data['customer_id'] = $enq['customer_id'];
            $data['customer_name'] = $enq['name'];
            $data['customer_org'] = $enq['organization'];
            $data['customer_email'] = $enq['email'];
            $data['customer_phone'] = $enq['phone'];
            $data['customer_address'] = $enq['city'];
            if ($enq['product_id'] && isset($productMap[$enq['product_id']])) {
                $p = $productMap[$enq['product_id']];
                $items[] = ['product_id' => $p['id'], 'description' => $p['name'], 'qty' => $enq['quantity'] ?: 1, 'unit_price' => $p['price'] ?? 0, 'gst_rate' => $p['gst_rate']];
            }
        }
    }
    if (($cid = (int) get('customer_id')) && isset($customerMap[$cid])) {
        $c = $customerMap[$cid];
        $data = array_merge($data, ['customer_id' => $cid, 'customer_name' => $c['name'], 'customer_org' => $c['organization'], 'customer_email' => $c['email'],
            'customer_phone' => $c['phone'], 'customer_address' => $customerAddress($c), 'customer_gstin' => $c['gstin']]);
    }
    if (($pid = (int) get('product_id')) && isset($productMap[$pid])) {
        $p = $productMap[$pid];
        $items[] = ['product_id' => $p['id'], 'description' => $p['name'], 'qty' => 1, 'unit_price' => $p['price'] ?? 0, 'gst_rate' => $p['gst_rate']];
    }
}
$errors = [];

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_check();
    foreach (['customer_name', 'customer_org', 'customer_email', 'customer_phone', 'customer_address', 'customer_gstin', 'quote_date', 'valid_until', 'notes', 'terms'] as $f) {
        $data[$f] = post($f);
    }
    $data['customer_id'] = isset($customerMap[(int) post('customer_id')]) ? (int) post('customer_id') : null;
    $data['enquiry_id'] = (int) post('enquiry_id') ?: null;
    $data['status'] = in_array(post('status'), ['draft', 'sent', 'accepted', 'rejected'], true) ? post('status') : 'draft';
    $data['discount'] = max(0, (float) post('discount'));
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data['quote_date'])) $data['quote_date'] = date('Y-m-d');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data['valid_until'])) $data['valid_until'] = null;

    $items = [];
    $descs = $_POST['item_desc'] ?? [];
    foreach ((array) $descs as $i => $desc) {
        $desc = trim((string) $desc);
        $pid = (int) ($_POST['item_product'][$i] ?? 0);
        if ($desc === '' && !$pid) continue;
        $items[] = [
            'product_id' => isset($productMap[$pid]) ? $pid : null,
            'description' => $desc !== '' ? mb_substr($desc, 0, 300) : ($productMap[$pid]['name'] ?? 'Item'),
            'qty' => max(0, (float) ($_POST['item_qty'][$i] ?? 1)),
            'unit_price' => max(0, (float) ($_POST['item_price'][$i] ?? 0)),
            'gst_rate' => max(0, min(100, (float) ($_POST['item_gst'][$i] ?? 0))),
        ];
    }
    if ($data['customer_name'] === '' && $data['customer_org'] === '') $errors[] = 'Enter the customer name or organisation.';
    if (!$items) $errors[] = 'Add at least one item.';

    if (!$errors) {
        $subtotal = 0.0;
        $taxRaw = 0.0;
        foreach ($items as &$it) {
            $it['line_total'] = round($it['qty'] * $it['unit_price'], 2);
            $subtotal += $it['line_total'];
            $taxRaw += $it['line_total'] * $it['gst_rate'] / 100;
        }
        unset($it);
        $discount = min($data['discount'], $subtotal);
        $tax = $subtotal > 0 ? round($taxRaw * (1 - $discount / $subtotal), 2) : 0.0;
        $grand = round($subtotal - $discount + $tax, 2);

        $pdo = db();
        $pdo->beginTransaction();
        try {
            $cols = ['customer_id', 'enquiry_id', 'customer_name', 'customer_org', 'customer_email', 'customer_phone', 'customer_address',
                'customer_gstin', 'quote_date', 'valid_until', 'status', 'notes', 'terms'];
            $vals = array_map(fn($c) => $data[$c], $cols);
            $cols = array_merge($cols, ['subtotal', 'discount', 'tax_total', 'grand_total']);
            $vals = array_merge($vals, [$subtotal, $discount, $tax, $grand]);
            if ($quote) {
                q('UPDATE quotations SET ' . implode(', ', array_map(fn($c) => "$c = ?", $cols)) . ' WHERE id = ?', array_merge($vals, [$id]));
                q('DELETE FROM quotation_items WHERE quotation_id = ?', [$id]);
            } else {
                $quoteNo = next_quote_no();
                q('INSERT INTO quotations (quote_no, ' . implode(', ', $cols) . ') VALUES (?, ' . implode(', ', array_fill(0, count($cols), '?')) . ')',
                    array_merge([$quoteNo], $vals));
                $id = (int) $pdo->lastInsertId();
            }
            foreach ($items as $n => $it) {
                q('INSERT INTO quotation_items (quotation_id, product_id, description, qty, unit_price, gst_rate, line_total, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                    [$id, $it['product_id'], $it['description'], $it['qty'], $it['unit_price'], $it['gst_rate'], $it['line_total'], $n]);
            }
            if ($data['enquiry_id'] && in_array($data['status'], ['sent', 'accepted', 'rejected'], true)) {
                $enqStatus = ['sent' => 'quoted', 'accepted' => 'won', 'rejected' => 'lost'][$data['status']];
                q("UPDATE enquiries SET status = ? WHERE id = ? AND status <> 'won'", [$enqStatus, $data['enquiry_id']]);
            }
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
        $no = $quote['quote_no'] ?? $quoteNo;
        log_activity($quote ? 'quote.update' : 'quote.create', ($quote ? 'Updated' : 'Created') . " quotation $no (" . money($grand) . ')');
        flash('success', 'Quotation ' . $no . ' saved.');
        redirect('admin/quotation-view.php?id=' . $id);
    }
}

if (!$items) {
    $items[] = ['product_id' => null, 'description' => '', 'qty' => 1, 'unit_price' => '', 'gst_rate' => 12];
}

$pageTitle = $quote ? 'Edit ' . $quote['quote_no'] : 'Create Quotation';
$activeNav = $quote ? 'quotations' : 'quotation-new';
$breadcrumbs = ['Quotations' => 'quotations.php', ($quote ? 'Edit' : 'Create') => null];
require __DIR__ . '/partials/header.php';
?>
<?php foreach ($errors as $err): ?><div class="alert alert-danger"><?= e($err) ?></div><?php endforeach; ?>
<form method="post" id="quoteForm">
    <?= csrf_field() ?>
    <input type="hidden" name="enquiry_id" value="<?= (int) $data['enquiry_id'] ?>">
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
                        <div class="col-md-4 mb-3"><label class="form-label">GSTIN</label><input name="customer_gstin" id="c_gstin" class="form-control text-uppercase" value="<?= e($data['customer_gstin']) ?>"></div>
                        <div class="col-12"><label class="form-label">Address</label><input name="customer_address" id="c_address" class="form-control" value="<?= e($data['customer_address']) ?>"></div>
                    </div>
                </div>
            </div>

        </div>
        <div class="col-xl-4">
            <div class="card stretch stretch-full">
                <div class="card-header"><h5 class="card-title"><?= e($quote['quote_no'] ?? $data['quote_no']) ?></h5></div>
                <div class="card-body">
                    <div class="mb-3"><label class="form-label">Quotation date</label><input type="date" name="quote_date" class="form-control" value="<?= e($data['quote_date']) ?>"></div>
                    <div class="mb-3"><label class="form-label">Valid until</label><input type="date" name="valid_until" class="form-control" value="<?= e($data['valid_until']) ?>"></div>
                    <div class="mb-3"><label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <?php foreach (['draft' => 'Draft', 'sent' => 'Sent to customer', 'accepted' => 'Accepted (order)', 'rejected' => 'Rejected'] as $k => $v): ?>
                                <option value="<?= $k ?>" <?= $data['status'] === $k ? 'selected' : '' ?>><?= $v ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-12">
            <div class="card">
                <div class="card-header"><h5 class="card-title">Items</h5></div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table vh-quote-items mb-0">
                            <thead><tr><th style="min-width:200px">Product</th><th style="min-width:220px">Description</th><th style="width:90px">Qty</th><th style="width:130px">Rate (₹)</th><th style="width:90px">GST %</th><th style="width:130px" class="text-end">Amount</th><th></th></tr></thead>
                            <tbody id="itemRows">
                            <?php foreach ($items as $it): ?>
                                <tr class="item-row">
                                    <td>
                                        <select name="item_product[]" class="form-select item-product">
                                            <option value="">Custom item</option>
                                            <?php foreach ($products as $p): ?>
                                                <option value="<?= (int) $p['id'] ?>" <?= (int) $it['product_id'] === (int) $p['id'] ? 'selected' : '' ?>><?= e($p['name']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                    <td><input name="item_desc[]" class="form-control item-desc" value="<?= e($it['description']) ?>" maxlength="300"></td>
                                    <td><input type="number" step="0.01" min="0" name="item_qty[]" class="form-control item-qty" value="<?= e((float) $it['qty']) ?>"></td>
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
                    <div class="mb-3"><label class="form-label">Notes to customer</label><textarea name="notes" class="form-control" rows="3"><?= e($data['notes']) ?></textarea></div>
                    <div><label class="form-label">Terms & conditions</label><textarea name="terms" class="form-control" rows="5"><?= e($data['terms']) ?></textarea></div>
                </div>
            </div>
        </div>
        <div class="col-xl-5">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between mb-2"><span class="text-muted">Subtotal</span><strong id="tSub">₹0.00</strong></div>
                    <div class="d-flex justify-content-between align-items-center mb-2"><span class="text-muted">Discount (₹)</span><input type="number" step="0.01" min="0" name="discount" id="discount" class="form-control form-control-sm text-end" style="width:130px" value="<?= e((float) $data['discount']) ?>"></div>
                    <div class="d-flex justify-content-between mb-2"><span class="text-muted">GST</span><strong id="tTax">₹0.00</strong></div>
                    <div class="d-flex justify-content-between fs-5 border-top pt-2"><span class="fw-bold">Total</span><strong class="text-primary" id="tGrand">₹0.00</strong></div>
                    <button class="btn btn-primary w-100 mt-4"><i class="feather-save me-2"></i>Save quotation</button>
                </div>
            </div>
        </div>
    </div>
</form>
<?php
$inlineJs = 'var VH_PRODUCTS=' . json_encode(array_map(fn($p) => ['name' => $p['name'], 'price' => $p['price'] !== null ? (float) $p['price'] : '', 'gst' => (float) $p['gst_rate']], $productMap), JSON_HEX_TAG | JSON_HEX_AMP) . ';'
    . 'var VH_CUSTOMERS=' . json_encode(array_map(fn($c) => ['name' => $c['name'], 'org' => $c['organization'], 'phone' => $c['phone'], 'email' => $c['email'], 'gstin' => $c['gstin'], 'address' => $customerAddress($c)], $customerMap), JSON_HEX_TAG | JSON_HEX_AMP) . ';'
    . <<<'JS'
(function () {
    var rows = document.getElementById('itemRows');
    var fmt = function (n) { return '₹' + n.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); };
    function recalc() {
        var sub = 0, tax = 0;
        rows.querySelectorAll('.item-row').forEach(function (r) {
            var amt = (parseFloat(r.querySelector('.item-qty').value) || 0) * (parseFloat(r.querySelector('.item-price').value) || 0);
            sub += amt; tax += amt * (parseFloat(r.querySelector('.item-gst').value) || 0) / 100;
            r.querySelector('.item-amount').textContent = fmt(amt);
        });
        var disc = Math.min(parseFloat(document.getElementById('discount').value) || 0, sub);
        var taxAfter = sub > 0 ? tax * (1 - disc / sub) : 0;
        document.getElementById('tSub').textContent = fmt(sub);
        document.getElementById('tTax').textContent = fmt(taxAfter);
        document.getElementById('tGrand').textContent = fmt(sub - disc + taxAfter);
    }
    rows.addEventListener('input', recalc);
    document.getElementById('discount').addEventListener('input', recalc);
    rows.addEventListener('change', function (e) {
        if (!e.target.classList.contains('item-product')) return;
        var p = VH_PRODUCTS[e.target.value], r = e.target.closest('tr');
        if (p) {
            r.querySelector('.item-desc').value = p.name;
            if (p.price !== '') r.querySelector('.item-price').value = p.price;
            r.querySelector('.item-gst').value = p.gst;
        }
        recalc();
    });
    rows.addEventListener('click', function (e) {
        var btn = e.target.closest('.item-remove');
        if (!btn) return;
        if (rows.querySelectorAll('.item-row').length > 1) btn.closest('tr').remove();
        recalc();
    });
    document.getElementById('addItem').addEventListener('click', function () {
        var clone = rows.querySelector('.item-row').cloneNode(true);
        clone.querySelectorAll('input').forEach(function (i) { i.value = i.classList.contains('item-qty') ? 1 : (i.classList.contains('item-gst') ? 12 : ''); });
        clone.querySelector('select').value = '';
        rows.appendChild(clone);
        recalc();
    });
    document.getElementById('customerPick').addEventListener('change', function () {
        var c = VH_CUSTOMERS[this.value];
        if (!c) return;
        ['name', 'org', 'phone', 'email', 'gstin', 'address'].forEach(function (k) { document.getElementById('c_' + k).value = c[k] || ''; });
    });
    recalc();
})();
JS;
require __DIR__ . '/partials/footer.php';
