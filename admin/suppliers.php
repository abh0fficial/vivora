<?php
require __DIR__ . '/../includes/auth.php';
$admin = require_admin();

$fields = ['name', 'contact_person', 'phone', 'email', 'gstin', 'address', 'city', 'state', 'notes'];
$editId = (int) get('edit');
$editing = $editId ? q_row('SELECT * FROM suppliers WHERE id = ?', [$editId]) : null;
$form = $editing ?: array_fill_keys($fields, '');
$errors = [];

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_check();
    if (post('action') === 'delete') {
        $s = q_row('SELECT * FROM suppliers WHERE id = ?', [(int) post('id')]);
        if ($s) {
            q('DELETE FROM suppliers WHERE id = ?', [$s['id']]);
            log_activity('supplier.delete', 'Deleted supplier "' . $s['name'] . '"');
            flash('success', 'Supplier deleted. Their purchase bills are kept.');
        }
        redirect('admin/suppliers.php');
    }
    foreach ($fields as $f) $form[$f] = post($f);
    $form['gstin'] = strtoupper($form['gstin']);
    if ($form['state'] === '' && state_from_gstin($form['gstin'])) $form['state'] = state_from_gstin($form['gstin']);
    if ($form['name'] === '') $errors[] = 'Supplier name is required.';
    if ($form['email'] !== '' && !filter_var($form['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Email is not valid.';
    if (!$errors) {
        $vals = array_map(fn($f) => $form[$f], $fields);
        if ($id = (int) post('id')) {
            q('UPDATE suppliers SET ' . implode(', ', array_map(fn($f) => "$f = ?", $fields)) . ' WHERE id = ?', array_merge($vals, [$id]));
            log_activity('supplier.update', 'Updated supplier "' . $form['name'] . '"');
            flash('success', 'Supplier saved.');
        } else {
            q('INSERT INTO suppliers (' . implode(', ', $fields) . ') VALUES (' . implode(', ', array_fill(0, count($fields), '?')) . ')', $vals);
            log_activity('supplier.create', 'Added supplier "' . $form['name'] . '"');
            flash('success', 'Supplier added.');
        }
        redirect('admin/suppliers.php');
    }
}

$rows = q_all("SELECT s.*, COUNT(p.id) bills, COALESCE(SUM(p.grand_total),0) purchased, COALESCE(SUM(p.grand_total - p.amount_paid),0) payable
    FROM suppliers s LEFT JOIN purchases p ON p.supplier_id = s.id GROUP BY s.id ORDER BY s.name");

$pageTitle = 'Suppliers';
$activeNav = 'suppliers';
$breadcrumbs = ['Purchases' => 'purchases.php', 'Suppliers' => null];
require __DIR__ . '/partials/header.php';
?>
<div class="row">
    <div class="col-xl-8">
        <div class="card">
            <div class="card-header"><h5 class="card-title">Suppliers & vendors</h5></div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead><tr><th>Supplier</th><th>Contact</th><th class="text-end">Purchased</th><th class="text-end">Payable</th><th class="text-end"></th></tr></thead>
                        <tbody>
                        <?php foreach ($rows as $r): ?>
                            <tr>
                                <td><span class="fw-semibold text-dark d-block"><?= e($r['name']) ?></span><span class="fs-12 text-muted"><?= e($r['gstin'] ?: $r['city']) ?></span></td>
                                <td class="fs-12"><?= e($r['contact_person']) ?><br><span class="text-muted"><?= e($r['phone']) ?></span></td>
                                <td class="text-end"><?= money($r['purchased']) ?> <span class="fs-11 text-muted">(<?= (int) $r['bills'] ?>)</span></td>
                                <td class="text-end <?= $r['payable'] > 0 ? 'text-danger fw-semibold' : '' ?>"><?= money($r['payable']) ?></td>
                                <td class="text-end">
                                    <div class="hstack gap-2 justify-content-end">
                                        <a href="purchase-form.php?supplier_id=<?= (int) $r['id'] ?>" class="avatar-text avatar-md" title="New purchase bill"><i class="feather-file-plus"></i></a>
                                        <a href="suppliers.php?edit=<?= (int) $r['id'] ?>" class="avatar-text avatar-md" title="Edit"><i class="feather-edit-3"></i></a>
                                        <form method="post" class="m-0" data-confirm="Delete this supplier?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                                            <button class="avatar-text avatar-md border-0 text-danger" title="Delete"><i class="feather-trash-2"></i></button></form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$rows): ?><tr><td colspan="5" class="text-center text-muted py-5">No suppliers yet. Add the manufacturers and distributors you buy from.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-4">
        <div class="card">
            <div class="card-header"><h5 class="card-title"><?= $editing ? 'Edit supplier' : 'Add supplier' ?></h5></div>
            <div class="card-body">
                <?php foreach ($errors as $err): ?><div class="alert alert-danger"><?= e($err) ?></div><?php endforeach; ?>
                <form method="post">
                    <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) ($editing['id'] ?? 0) ?>">
                    <div class="mb-3"><label class="form-label">Company name <span class="text-danger">*</span></label><input name="name" class="form-control" value="<?= e($form['name']) ?>" required maxlength="200"></div>
                    <div class="mb-3"><label class="form-label">Contact person</label><input name="contact_person" class="form-control" value="<?= e($form['contact_person']) ?>" maxlength="160"></div>
                    <div class="row">
                        <div class="col-6 mb-3"><label class="form-label">Phone</label><input name="phone" class="form-control" value="<?= e($form['phone']) ?>" maxlength="30"></div>
                        <div class="col-6 mb-3"><label class="form-label">Email</label><input name="email" class="form-control" value="<?= e($form['email']) ?>" maxlength="160"></div>
                    </div>
                    <div class="mb-3"><label class="form-label">GSTIN</label><input name="gstin" class="form-control text-uppercase" value="<?= e($form['gstin']) ?>" maxlength="15"></div>
                    <div class="mb-3"><label class="form-label">Address</label><input name="address" class="form-control" value="<?= e($form['address']) ?>" maxlength="400"></div>
                    <div class="row">
                        <div class="col-6 mb-3"><label class="form-label">City</label><input name="city" class="form-control" value="<?= e($form['city']) ?>" maxlength="80"></div>
                        <div class="col-6 mb-3"><label class="form-label">State</label><select name="state" class="form-select"><option value="">—</option><?php foreach (indian_states() as $st): ?><option <?= $form['state'] === $st ? 'selected' : '' ?>><?= e($st) ?></option><?php endforeach; ?></select></div>
                    </div>
                    <div class="mb-3"><label class="form-label">Notes</label><textarea name="notes" class="form-control" rows="2"><?= e($form['notes']) ?></textarea></div>
                    <div class="d-flex gap-2"><button class="btn btn-primary flex-fill"><?= $editing ? 'Update' : 'Add' ?> supplier</button><?php if ($editing): ?><a href="suppliers.php" class="btn btn-light-brand">Cancel</a><?php endif; ?></div>
                </form>
            </div>
        </div>
    </div>
</div>
<?php require __DIR__ . '/partials/footer.php';
