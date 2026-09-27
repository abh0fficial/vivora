<?php
require __DIR__ . '/../includes/auth.php';
$admin = require_admin();

$types = service_types();
$id = (int) get('id');
$ticket = $id ? q_row('SELECT * FROM service_requests WHERE id = ?', [$id]) : null;
if ($id && !$ticket) {
    flash('error', 'Service request not found.');
    redirect('admin/service-requests.php');
}
$fields = ['name', 'organization', 'email', 'phone', 'city', 'equipment', 'brand_model', 'serial_no', 'request_type', 'priority', 'description', 'preferred_date', 'status', 'engineer', 'notes'];
$data = $ticket ?: array_merge(array_fill_keys($fields, ''), ['request_type' => 'repair', 'priority' => 'normal', 'status' => 'open']);
$errors = [];

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_check();
    if (post('action') === 'delete' && $ticket) {
        q('DELETE FROM service_requests WHERE id = ?', [$id]);
        log_activity('service.delete', "Deleted service ticket {$ticket['ticket_no']}");
        flash('success', 'Service request deleted.');
        redirect('admin/service-requests.php');
    }
    foreach ($fields as $f) $data[$f] = post($f);
    if (!array_key_exists($data['request_type'], $types)) $data['request_type'] = 'other';
    if (!in_array($data['priority'], ['low', 'normal', 'high', 'urgent'], true)) $data['priority'] = 'normal';
    if (!in_array($data['status'], ['open', 'scheduled', 'in_progress', 'resolved', 'closed'], true)) $data['status'] = 'open';
    $data['preferred_date'] = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $data['preferred_date']) ? $data['preferred_date'] : null;
    if ($data['name'] === '') $errors[] = 'Customer name is required.';
    if (!$errors) {
        $values = array_map(fn($f) => $data[$f], $fields);
        if ($ticket) {
            q('UPDATE service_requests SET ' . implode(', ', array_map(fn($f) => "$f = ?", $fields)) . ' WHERE id = ?', array_merge($values, [$id]));
            if ($ticket['status'] !== $data['status']) {
                log_activity('service.status', "Ticket {$ticket['ticket_no']} moved to " . str_replace('_', ' ', $data['status']));
            } else {
                log_activity('service.update', "Updated ticket {$ticket['ticket_no']}");
            }
            flash('success', 'Service request updated.');
        } else {
            q('INSERT INTO service_requests (' . implode(', ', $fields) . ') VALUES (' . implode(', ', array_fill(0, count($fields), '?')) . ')', $values);
            $id = (int) db()->lastInsertId();
            $no = 'SR-' . date('y') . str_pad((string) $id, 5, '0', STR_PAD_LEFT);
            q('UPDATE service_requests SET ticket_no = ? WHERE id = ?', [$no, $id]);
            log_activity('service.create', "Created service ticket $no");
            flash('success', "Service ticket $no created.");
        }
        redirect('admin/service-view.php?id=' . $id);
    }
}

$pageTitle = $ticket ? 'Ticket ' . $ticket['ticket_no'] : 'New Service Ticket';
$activeNav = 'service';
$breadcrumbs = ['Service Requests' => 'service-requests.php', ($ticket ? $ticket['ticket_no'] : 'New') => null];
require __DIR__ . '/partials/header.php';
?>
<?php foreach ($errors as $err): ?><div class="alert alert-danger"><?= e($err) ?></div><?php endforeach; ?>
<form method="post">
    <?= csrf_field() ?>
    <div class="row">
        <div class="col-xl-8">
            <div class="card">
                <div class="card-header"><h5 class="card-title">Customer & equipment</h5><?= $ticket ? '<span class="fs-12 text-muted">Raised ' . e(fmt_date($ticket['created_at'], 'd M Y, h:i A')) . '</span>' : '' ?></div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3"><label class="form-label">Contact name <span class="text-danger">*</span></label><input name="name" class="form-control" value="<?= e($data['name']) ?>" required></div>
                        <div class="col-md-6 mb-3"><label class="form-label">Hospital / Organisation</label><input name="organization" class="form-control" value="<?= e($data['organization']) ?>"></div>
                        <div class="col-md-4 mb-3"><label class="form-label">Phone</label>
                            <div class="input-group"><input name="phone" class="form-control" value="<?= e($data['phone']) ?>"><?php if ($data['phone']): ?><a class="btn btn-light-brand" href="<?= e(tel_link($data['phone'])) ?>"><i class="feather-phone"></i></a><?php endif; ?></div>
                        </div>
                        <div class="col-md-4 mb-3"><label class="form-label">Email</label><input type="email" name="email" class="form-control" value="<?= e($data['email']) ?>"></div>
                        <div class="col-md-4 mb-3"><label class="form-label">City</label><input name="city" class="form-control" value="<?= e($data['city']) ?>"></div>
                        <div class="col-md-4 mb-3"><label class="form-label">Equipment</label><input name="equipment" class="form-control" value="<?= e($data['equipment']) ?>"></div>
                        <div class="col-md-4 mb-3"><label class="form-label">Brand / Model</label><input name="brand_model" class="form-control" value="<?= e($data['brand_model']) ?>"></div>
                        <div class="col-md-4 mb-3"><label class="form-label">Serial no.</label><input name="serial_no" class="form-control" value="<?= e($data['serial_no']) ?>"></div>
                        <div class="col-12"><label class="form-label">Problem / requirement</label><textarea name="description" class="form-control" rows="5"><?= e($data['description']) ?></textarea></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-4">
            <div class="card">
                <div class="card-header"><h5 class="card-title">Ticket</h5></div>
                <div class="card-body">
                    <div class="mb-3"><label class="form-label">Request type</label>
                        <select name="request_type" class="form-select"><?php foreach ($types as $k => $v): ?><option value="<?= $k ?>" <?= $data['request_type'] === $k ? 'selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?></select>
                    </div>
                    <div class="mb-3"><label class="form-label">Priority</label>
                        <select name="priority" class="form-select"><?php foreach (['low', 'normal', 'high', 'urgent'] as $p): ?><option value="<?= $p ?>" <?= $data['priority'] === $p ? 'selected' : '' ?>><?= ucfirst($p) ?></option><?php endforeach; ?></select>
                    </div>
                    <div class="mb-3"><label class="form-label">Status</label>
                        <select name="status" class="form-select"><?php foreach (['open', 'scheduled', 'in_progress', 'resolved', 'closed'] as $s): ?><option value="<?= $s ?>" <?= $data['status'] === $s ? 'selected' : '' ?>><?= ucwords(str_replace('_', ' ', $s)) ?></option><?php endforeach; ?></select>
                    </div>
                    <div class="mb-3"><label class="form-label">Visit date</label><input type="date" name="preferred_date" class="form-control" value="<?= e($data['preferred_date']) ?>"></div>
                    <div class="mb-3"><label class="form-label">Assigned engineer</label><input name="engineer" class="form-control" value="<?= e($data['engineer']) ?>" maxlength="120"></div>
                    <div class="mb-4"><label class="form-label">Internal notes</label><textarea name="notes" class="form-control" rows="4" placeholder="Diagnosis, parts used, visit report…"><?= e($data['notes']) ?></textarea></div>
                    <button class="btn btn-primary w-100"><i class="feather-save me-2"></i>Save ticket</button>
                    <?php if ($ticket): ?>
                        <button name="action" value="delete" class="btn btn-outline-danger w-100 mt-2" formnovalidate onclick="return confirm('Delete this service request?')"><i class="feather-trash-2 me-2"></i>Delete</button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</form>
<?php require __DIR__ . '/partials/footer.php';
