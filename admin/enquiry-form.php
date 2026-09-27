<?php
require __DIR__ . '/../includes/auth.php';
$admin = require_admin();

// Sources offered for new entries (older ones stay readable on existing records).
$sources = array_slice(enquiry_sources(), 0, 8, true);
$id = (int) get('id');
$enq = $id ? q_row('SELECT * FROM enquiries WHERE id = ?', [$id]) : null;
if ($id && !$enq) {
    flash('error', 'Enquiry not found.');
    redirect('admin/enquiries.php');
}
$fields = ['source', 'name', 'organization', 'phone', 'email', 'city', 'product_id', 'quantity', 'subject', 'message', 'status', 'notes'];
$data = $enq ?: array_merge(array_fill_keys($fields, ''), ['source' => 'phone', 'status' => 'new', 'product_id' => (int) get('product_id') ?: '']);
$errors = [];

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_check();
    foreach ($fields as $f) $data[$f] = post($f);
    if (!array_key_exists($data['source'], enquiry_sources())) $data['source'] = 'other';
    if (!in_array($data['status'], ['new', 'contacted', 'quoted', 'won', 'lost'], true)) $data['status'] = 'new';
    $data['product_id'] = (int) $data['product_id'] && q_val('SELECT id FROM products WHERE id = ?', [(int) $data['product_id']]) ? (int) $data['product_id'] : null;
    $data['quantity'] = (int) $data['quantity'] > 0 ? (int) $data['quantity'] : null;
    if ($data['name'] === '') $errors[] = 'Customer name is required.';
    if ($data['phone'] === '' && $data['email'] === '') $errors[] = 'Enter a phone number or email so you can follow up.';
    if ($data['email'] !== '' && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Email address is not valid.';
    if (!$errors) {
        $values = array_map(fn($f) => $data[$f], $fields);
        if ($enq) {
            q('UPDATE enquiries SET ' . implode(', ', array_map(fn($f) => "$f = ?", $fields)) . ' WHERE id = ?', array_merge($values, [$id]));
            log_activity('enquiry.update', "Updated enquiry #$id from {$data['name']}");
            flash('success', 'Enquiry updated.');
        } else {
            q('INSERT INTO enquiries (' . implode(', ', $fields) . ', ip) VALUES (' . implode(', ', array_fill(0, count($fields), '?')) . ', ?)', array_merge($values, [client_ip()]));
            $id = (int) db()->lastInsertId();
            log_activity('enquiry.create', "Added enquiry #$id from {$data['name']}");
            flash('success', 'Enquiry added.');
        }
        redirect('admin/enquiry-view.php?id=' . $id);
    }
}

$products = q_all('SELECT p.id, p.name, c.name category_name FROM products p LEFT JOIN categories c ON c.id = p.category_id ORDER BY c.sort_order, p.name');
$pageTitle = $enq ? 'Edit Enquiry #' . $id : 'Add Enquiry';
$activeNav = $enq ? 'enquiries' : 'enquiry-new';
$breadcrumbs = ['Enquiries' => 'enquiries.php', ($enq ? '#' . $id : 'Add') => null];
require __DIR__ . '/partials/header.php';
?>
<?php foreach ($errors as $err): ?><div class="alert alert-danger"><?= e($err) ?></div><?php endforeach; ?>
<form method="post">
    <?= csrf_field() ?>
    <div class="row">
        <div class="col-xl-8">
            <div class="card">
                <div class="card-header"><h5 class="card-title">Customer</h5></div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3"><label class="form-label">Contact name <span class="text-danger">*</span></label><input name="name" class="form-control" value="<?= e($data['name']) ?>" required maxlength="160"></div>
                        <div class="col-md-6 mb-3"><label class="form-label">Hospital / Clinic / Company</label><input name="organization" class="form-control" value="<?= e($data['organization']) ?>" maxlength="200"></div>
                        <div class="col-md-4 mb-3"><label class="form-label">Phone</label><input name="phone" class="form-control" value="<?= e($data['phone']) ?>" maxlength="30"></div>
                        <div class="col-md-4 mb-3"><label class="form-label">Email</label><input type="email" name="email" class="form-control" value="<?= e($data['email']) ?>" maxlength="160"></div>
                        <div class="col-md-4 mb-3"><label class="form-label">City</label><input name="city" class="form-control" value="<?= e($data['city']) ?>" maxlength="80"></div>
                    </div>
                </div>
            </div>
            <div class="card">
                <div class="card-header"><h5 class="card-title">Requirement</h5></div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-8 mb-3"><label class="form-label">Product</label>
                            <select name="product_id" class="form-select">
                                <option value="">— Not a listed product —</option>
                                <?php $grp = null; foreach ($products as $pr): ?>
                                    <?php if ($pr['category_name'] !== $grp): ?><?= $grp !== null ? '</optgroup>' : '' ?><optgroup label="<?= e($pr['category_name'] ?: 'Uncategorised') ?>"><?php $grp = $pr['category_name']; endif; ?>
                                    <option value="<?= (int) $pr['id'] ?>" <?= (int) $data['product_id'] === (int) $pr['id'] ? 'selected' : '' ?>><?= e($pr['name']) ?></option>
                                <?php endforeach; ?><?= $grp !== null ? '</optgroup>' : '' ?>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3"><label class="form-label">Quantity</label><input type="number" min="1" name="quantity" class="form-control" value="<?= e($data['quantity']) ?>"></div>
                        <div class="col-12 mb-3"><label class="form-label">Subject / other items</label><input name="subject" class="form-control" value="<?= e($data['subject']) ?>" maxlength="200" placeholder="e.g. ICU setup – 5 monitors + 1 defibrillator"></div>
                        <div class="col-12"><label class="form-label">Details</label><textarea name="message" class="form-control" rows="5" placeholder="Brand preference, configuration, budget, delivery location, timeline…"><?= e($data['message']) ?></textarea></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-4">
            <div class="card">
                <div class="card-header"><h5 class="card-title">Enquiry</h5></div>
                <div class="card-body">
                    <div class="mb-3"><label class="form-label">Received via</label>
                        <select name="source" class="form-select">
                            <?php foreach ($sources + ($enq && !isset($sources[$enq['source']]) ? [$enq['source'] => enquiry_sources()[$enq['source']]] : []) as $k => $v): ?>
                                <option value="<?= $k ?>" <?= $data['source'] === $k ? 'selected' : '' ?>><?= e($v) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3"><label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <?php foreach (['new' => 'New', 'contacted' => 'Contacted', 'quoted' => 'Quotation sent', 'won' => 'Won (order received)', 'lost' => 'Lost'] as $k => $v): ?>
                                <option value="<?= $k ?>" <?= $data['status'] === $k ? 'selected' : '' ?>><?= $v ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-4"><label class="form-label">Internal notes</label><textarea name="notes" class="form-control" rows="4"><?= e($data['notes']) ?></textarea></div>
                    <button class="btn btn-primary w-100"><i class="feather-save me-2"></i>Save enquiry</button>
                </div>
            </div>
        </div>
    </div>
</form>
<?php require __DIR__ . '/partials/footer.php';
