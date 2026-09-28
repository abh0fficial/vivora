<?php
require __DIR__ . '/../includes/auth.php';
$admin = require_admin();

$types = customer_types();
$id = (int) get('id');
$customer = $id ? q_row('SELECT * FROM customers WHERE id = ?', [$id]) : null;
if ($id && !$customer) {
    flash('error', 'Customer not found.');
    redirect('admin/customers.php');
}
$fields = ['name', 'organization', 'type', 'email', 'phone', 'gstin', 'address', 'city', 'state', 'pincode', 'notes'];
$data = $customer ?: array_fill_keys($fields, '');
if (!$customer) $data['type'] = 'hospital';
$errors = [];

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_check();
    if (post('action') === 'delete' && $customer) {
        q('DELETE FROM customers WHERE id = ?', [$id]);
        log_activity('customer.delete', 'Deleted customer "' . ($customer['organization'] ?: $customer['name']) . '"');
        flash('success', 'Customer deleted.');
        redirect('admin/customers.php');
    }
    foreach ($fields as $f) $data[$f] = post($f);
    if (!array_key_exists($data['type'], $types)) $data['type'] = 'other';
    $data['gstin'] = strtoupper($data['gstin']);
    if ($data['state'] === '' && state_from_gstin($data['gstin'])) $data['state'] = state_from_gstin($data['gstin']);
    if ($data['name'] === '') $errors[] = 'Contact name is required.';
    if ($data['email'] !== '' && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Email address is not valid.';
    if (!$errors) {
        $values = array_map(fn($f) => $data[$f], $fields);
        if ($customer) {
            q('UPDATE customers SET ' . implode(', ', array_map(fn($f) => "$f = ?", $fields)) . ' WHERE id = ?', array_merge($values, [$id]));
            log_activity('customer.update', 'Updated customer "' . ($data['organization'] ?: $data['name']) . '"');
            flash('success', 'Customer saved.');
        } else {
            q('INSERT INTO customers (' . implode(', ', $fields) . ') VALUES (' . implode(', ', array_fill(0, count($fields), '?')) . ')', $values);
            $id = (int) db()->lastInsertId();
            log_activity('customer.create', 'Added customer "' . ($data['organization'] ?: $data['name']) . '"');
            flash('success', 'Customer added.');
        }
        redirect('admin/customer-form.php?id=' . $id);
    }
}

$quotes = $customer ? q_all('SELECT id, quote_no, quote_date, status, grand_total FROM quotations WHERE customer_id = ? ORDER BY quote_date DESC', [$id]) : [];
$enquiries = $customer ? q_all('SELECT e.id, e.created_at, e.status, p.name product_name, e.subject FROM enquiries e LEFT JOIN products p ON p.id = e.product_id WHERE e.customer_id = ? ORDER BY e.created_at DESC', [$id]) : [];

$pageTitle = $customer ? ($customer['organization'] ?: $customer['name']) : 'Add Customer';
$activeNav = 'customers';
$breadcrumbs = ['Customers' => 'customers.php', ($customer ? 'Edit' : 'Add') => null];
if ($customer) $pageActions = '<a href="customer-ledger.php?id=' . $id . '" class="btn btn-light-brand"><i class="feather-book-open me-2"></i>Ledger</a>'
    . '<a href="quotation-form.php?customer_id=' . $id . '" class="btn btn-light-brand"><i class="feather-clipboard me-2"></i>New quotation</a>'
    . '<a href="invoice-form.php?customer_id=' . $id . '" class="btn btn-primary"><i class="feather-file-plus me-2"></i>New invoice</a>';
$invoices = $customer ? q_all('SELECT id, invoice_no, invoice_date, status, grand_total, amount_paid FROM invoices WHERE customer_id = ? ORDER BY invoice_date DESC LIMIT 20', [$id]) : [];
$balance = $customer ? customer_balance($id) : 0;
require __DIR__ . '/partials/header.php';
?>
<?php foreach ($errors as $err): ?><div class="alert alert-danger"><?= e($err) ?></div><?php endforeach; ?>
<div class="row">
    <div class="col-xl-8">
        <form method="post" class="card">
            <?= csrf_field() ?>
            <div class="card-header"><h5 class="card-title">Customer details</h5></div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-4"><label class="form-label">Contact person <span class="text-danger">*</span></label><input name="name" class="form-control" value="<?= e($data['name']) ?>" required maxlength="160"></div>
                    <div class="col-md-6 mb-4"><label class="form-label">Hospital / Organisation</label><input name="organization" class="form-control" value="<?= e($data['organization']) ?>" maxlength="200"></div>
                    <div class="col-md-4 mb-4"><label class="form-label">Type</label>
                        <select name="type" class="form-select"><?php foreach ($types as $k => $v): ?><option value="<?= $k ?>" <?= $data['type'] === $k ? 'selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?></select>
                    </div>
                    <div class="col-md-4 mb-4"><label class="form-label">Phone</label><input name="phone" class="form-control" value="<?= e($data['phone']) ?>" maxlength="30"></div>
                    <div class="col-md-4 mb-4"><label class="form-label">Email</label><input type="email" name="email" class="form-control" value="<?= e($data['email']) ?>" maxlength="160"></div>
                    <div class="col-md-12 mb-4"><label class="form-label">Address</label><input name="address" class="form-control" value="<?= e($data['address']) ?>" maxlength="400"></div>
                    <div class="col-md-4 mb-4"><label class="form-label">City</label><input name="city" class="form-control" value="<?= e($data['city']) ?>" maxlength="80"></div>
                    <div class="col-md-4 mb-4"><label class="form-label">State</label><select name="state" class="form-select"><option value="">—</option><?php foreach (indian_states() as $st): ?><option <?= $data['state'] === $st ? 'selected' : '' ?>><?= e($st) ?></option><?php endforeach; ?><?php if ($data['state'] !== '' && !in_array($data['state'], indian_states(), true)): ?><option selected><?= e($data['state']) ?></option><?php endif; ?></select></div>
                    <div class="col-md-4 mb-4"><label class="form-label">PIN code</label><input name="pincode" class="form-control" value="<?= e($data['pincode']) ?>" maxlength="10"></div>
                    <div class="col-md-6 mb-4"><label class="form-label">GSTIN</label><input name="gstin" class="form-control text-uppercase" value="<?= e($data['gstin']) ?>" maxlength="20"></div>
                    <div class="col-md-12"><label class="form-label">Notes</label><textarea name="notes" class="form-control" rows="4"><?= e($data['notes']) ?></textarea></div>
                </div>
            </div>
            <div class="card-footer d-flex justify-content-between">
                <button class="btn btn-primary"><i class="feather-save me-2"></i>Save customer</button>
                <?php if ($customer): ?>
                    <button name="action" value="delete" class="btn btn-outline-danger" formnovalidate onclick="return confirm('Delete this customer? Their quotations are kept.')"><i class="feather-trash-2 me-2"></i>Delete</button>
                <?php endif; ?>
            </div>
        </form>
    </div>
    <?php if ($customer): ?>
    <div class="col-xl-4">
        <div class="card">
            <div class="card-header"><h5 class="card-title">Invoices</h5><span class="fs-12">Outstanding: <strong class="<?= $balance > 0 ? 'text-danger' : 'text-success' ?>"><?= money($balance) ?></strong></span></div>
            <div class="card-body">
                <?php if (!$invoices): ?><p class="text-muted fs-12 mb-0">No invoices yet.</p><?php endif; ?>
                <?php foreach ($invoices as $iv): ?>
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div><a href="invoice-view.php?id=<?= (int) $iv['id'] ?>" class="fw-semibold"><?= e($iv['invoice_no']) ?></a><div class="fs-11 text-muted"><?= e(fmt_date($iv['invoice_date'])) ?></div></div>
                        <div class="text-end"><?= status_badge($iv['status']) ?><div class="fs-12 fw-semibold"><?= money($iv['grand_total']) ?></div></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="card">
            <div class="card-header"><h5 class="card-title">Quotations</h5></div>
            <div class="card-body">
                <?php if (!$quotes): ?><p class="text-muted fs-12 mb-0">No quotations yet.</p><?php endif; ?>
                <?php foreach ($quotes as $qt): ?>
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div><a href="quotation-view.php?id=<?= (int) $qt['id'] ?>" class="fw-semibold"><?= e($qt['quote_no']) ?></a><div class="fs-11 text-muted"><?= e(fmt_date($qt['quote_date'])) ?></div></div>
                        <div class="text-end"><?= status_badge($qt['status']) ?><div class="fs-12 fw-semibold"><?= money($qt['grand_total']) ?></div></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="card">
            <div class="card-header"><h5 class="card-title">Enquiries</h5></div>
            <div class="card-body">
                <?php if (!$enquiries): ?><p class="text-muted fs-12 mb-0">No linked enquiries.</p><?php endif; ?>
                <?php foreach ($enquiries as $en): ?>
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div><a href="enquiry-view.php?id=<?= (int) $en['id'] ?>" class="fw-semibold"><?= e($en['product_name'] ?: ($en['subject'] ?: 'Enquiry #' . $en['id'])) ?></a><div class="fs-11 text-muted"><?= e(fmt_date($en['created_at'])) ?></div></div>
                        <?= status_badge($en['status']) ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/partials/footer.php';
