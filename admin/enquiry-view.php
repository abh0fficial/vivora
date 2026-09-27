<?php
require __DIR__ . '/../includes/auth.php';
$admin = require_admin();

$id = (int) get('id');
$enq = q_row('SELECT e.*, p.name product_name, p.slug product_slug FROM enquiries e LEFT JOIN products p ON p.id = e.product_id WHERE e.id = ?', [$id]);
if (!$enq) {
    flash('error', 'Enquiry not found.');
    redirect('admin/enquiries.php');
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    require_post();
    switch (post('action')) {
        case 'update':
            $status = in_array(post('status'), ['new', 'contacted', 'quoted', 'won', 'lost'], true) ? post('status') : $enq['status'];
            q('UPDATE enquiries SET status = ?, notes = ? WHERE id = ?', [$status, post('notes'), $id]);
            log_activity('enquiry.update', "Enquiry #$id from {$enq['name']} marked " . $status);
            flash('success', 'Enquiry updated.');
            break;
        case 'to_customer':
            $existing = null;
            if ($enq['phone'] !== '' || $enq['email'] !== '') {
                $existing = q_val("SELECT id FROM customers WHERE (phone <> '' AND phone = ?) OR (email <> '' AND email = ?) LIMIT 1", [$enq['phone'], $enq['email']]);
            }
            if ($existing) {
                $cid = (int) $existing;
                flash('info', 'This contact already exists as a customer — enquiry linked to it.');
            } else {
                q('INSERT INTO customers (name, organization, email, phone, city, notes) VALUES (?, ?, ?, ?, ?, ?)',
                    [$enq['name'], $enq['organization'], $enq['email'], $enq['phone'], $enq['city'], 'Created from enquiry #' . $id]);
                $cid = (int) db()->lastInsertId();
                log_activity('customer.create', 'Added customer "' . $enq['name'] . '" from enquiry #' . $id);
                flash('success', 'Customer created from this enquiry.');
            }
            q('UPDATE enquiries SET customer_id = ? WHERE id = ?', [$cid, $id]);
            break;
        case 'delete':
            q('DELETE FROM enquiries WHERE id = ?', [$id]);
            log_activity('enquiry.delete', "Deleted enquiry #$id from {$enq['name']}");
            flash('success', 'Enquiry deleted.');
            redirect('admin/enquiries.php');
    }
    redirect('admin/enquiry-view.php?id=' . $id);
}

// Opening a new enquiry is not the same as contacting — keep status, just show it.
$quotes = q_all('SELECT id, quote_no, status, grand_total, quote_date FROM quotations WHERE enquiry_id = ? ORDER BY id DESC', [$id]);
$wa = preg_replace('/\D+/', '', $enq['phone']);
if (strlen($wa) === 10) $wa = '91' . $wa;
$waText = 'Hello ' . $enq['name'] . ', thank you for your enquiry' . ($enq['product_name'] ? ' about ' . $enq['product_name'] : '') . ' with ' . setting('company_name', 'Vivora Healthcare') . '.';

$pageTitle = 'Enquiry #' . $id;
$activeNav = 'enquiries';
$breadcrumbs = ['Enquiries' => 'enquiries.php', '#' . $id => null];
$pageActions = '<a href="quotation-form.php?enquiry_id=' . $id . '" class="btn btn-primary"><i class="feather-file-plus me-2"></i>Create quotation</a>';
require __DIR__ . '/partials/header.php';
?>
<div class="row">
    <div class="col-xl-8">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title"><?= e($enq['name']) ?><?= $enq['organization'] ? ' · <span class="text-muted fw-normal">' . e($enq['organization']) . '</span>' : '' ?></h5>
                <?= status_badge($enq['status']) ?>
            </div>
            <div class="card-body">
                <dl class="row vh-kv mb-0">
                    <div class="col-md-6"><dt>Phone</dt><dd><?= $enq['phone'] ? '<a href="' . e(tel_link($enq['phone'])) . '">' . e($enq['phone']) . '</a>' : '—' ?></dd></div>
                    <div class="col-md-6"><dt>Email</dt><dd><?= $enq['email'] ? '<a href="mailto:' . e($enq['email']) . '">' . e($enq['email']) . '</a>' : '—' ?></dd></div>
                    <div class="col-md-6"><dt>City</dt><dd><?= e($enq['city'] ?: '—') ?></dd></div>
                    <div class="col-md-6"><dt>Source</dt><dd><?= e(ucfirst($enq['source'])) ?> form</dd></div>
                    <div class="col-md-6"><dt>Product</dt><dd>
                        <?php if ($enq['product_name']): ?>
                            <a href="<?= e(base_url('product.php?slug=' . rawurlencode($enq['product_slug']))) ?>" target="_blank"><?= e($enq['product_name']) ?></a>
                        <?php else: ?><?= e($enq['subject'] ?: '—') ?><?php endif; ?>
                    </dd></div>
                    <div class="col-md-6"><dt>Quantity</dt><dd><?= $enq['quantity'] ? (int) $enq['quantity'] : '—' ?></dd></div>
                    <div class="col-12"><dt>Message</dt><dd style="white-space:pre-line"><?= e($enq['message'] ?: '—') ?></dd></div>
                    <div class="col-md-6"><dt>Received</dt><dd><?= e(fmt_date($enq['created_at'], 'd M Y, h:i A')) ?> (<?= e(time_ago($enq['created_at'])) ?>)</dd></div>
                    <div class="col-md-6"><dt>IP address</dt><dd class="fs-12 text-muted"><?= e($enq['ip'] ?: '—') ?></dd></div>
                </dl>
            </div>
            <div class="card-footer d-flex flex-wrap gap-2">
                <?php if ($enq['phone']): ?><a href="<?= e(tel_link($enq['phone'])) ?>" class="btn btn-light-brand"><i class="feather-phone me-2"></i>Call</a><?php endif; ?>
                <?php if (strlen($wa) >= 11): ?><a href="https://wa.me/<?= e($wa) ?>?text=<?= rawurlencode($waText) ?>" target="_blank" class="btn btn-success"><i class="feather-message-circle me-2"></i>WhatsApp</a><?php endif; ?>
                <?php if ($enq['email']): ?><a href="mailto:<?= e($enq['email']) ?>?subject=<?= rawurlencode('Re: Your enquiry with ' . setting('company_name', 'Vivora Healthcare')) ?>" class="btn btn-light-brand"><i class="feather-mail me-2"></i>Email</a><?php endif; ?>
                <?php if (!$enq['customer_id']): ?>
                    <form method="post" class="m-0"><?= csrf_field() ?><button name="action" value="to_customer" class="btn btn-light-brand"><i class="feather-user-plus me-2"></i>Save as customer</button></form>
                <?php else: ?>
                    <a href="customer-form.php?id=<?= (int) $enq['customer_id'] ?>" class="btn btn-light-brand"><i class="feather-user me-2"></i>View customer</a>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($quotes): ?>
        <div class="card">
            <div class="card-header"><h5 class="card-title">Quotations for this enquiry</h5></div>
            <div class="card-body p-0">
                <table class="table mb-0">
                    <?php foreach ($quotes as $qt): ?>
                        <tr>
                            <td><a href="quotation-view.php?id=<?= (int) $qt['id'] ?>" class="fw-semibold"><?= e($qt['quote_no']) ?></a></td>
                            <td><?= e(fmt_date($qt['quote_date'])) ?></td>
                            <td><?= status_badge($qt['status']) ?></td>
                            <td class="text-end fw-semibold"><?= money($qt['grand_total']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            </div>
        </div>
        <?php endif; ?>
    </div>
    <div class="col-xl-4">
        <div class="card">
            <div class="card-header"><h5 class="card-title">Follow-up</h5></div>
            <div class="card-body">
                <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="update">
                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <?php foreach (['new' => 'New', 'contacted' => 'Contacted', 'quoted' => 'Quotation sent', 'won' => 'Won (order received)', 'lost' => 'Lost'] as $k => $v): ?>
                                <option value="<?= $k ?>" <?= $enq['status'] === $k ? 'selected' : '' ?>><?= $v ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Internal notes</label>
                        <textarea name="notes" class="form-control" rows="6" placeholder="Call notes, pricing discussed, follow-up date…"><?= e($enq['notes']) ?></textarea>
                    </div>
                    <button class="btn btn-primary w-100">Save</button>
                </form>
            </div>
        </div>
        <form method="post" data-confirm="Delete this enquiry permanently?">
            <?= csrf_field() ?>
            <button name="action" value="delete" class="btn btn-outline-danger w-100"><i class="feather-trash-2 me-2"></i>Delete enquiry</button>
        </form>
    </div>
</div>
<?php require __DIR__ . '/partials/footer.php';
