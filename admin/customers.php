<?php
require __DIR__ . '/../includes/auth.php';
$admin = require_admin();

// Delete straight from the list (with confirmation in the browser).
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && post('action') === 'delete') {
    require_post();
    $row = q_row('SELECT * FROM customers WHERE id = ?', [(int) post('id')]);
    if ($row) {
        q('DELETE FROM customers WHERE id = ?', [$row['id']]);
        log_activity('customer.delete', 'Deleted customer ' . '"' . ($row['organization'] ?: $row['name']) . '"');
        flash('success', 'Customer ' . '"' . ($row['organization'] ?: $row['name']) . '"' . ' deleted.');
    }
    redirect('admin/customers.php' . (!empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : ''));
}

$types = customer_types();
$type = array_key_exists(get('type'), $types) ? get('type') : '';
$search = get('q');
$where = [];
$params = [];
if ($type) { $where[] = 'c.type = ?'; $params[] = $type; }
if ($search !== '') {
    $where[] = '(c.name LIKE ? OR c.organization LIKE ? OR c.phone LIKE ? OR c.email LIKE ? OR c.city LIKE ?)';
    array_push($params, ...array_fill(0, 5, "%$search%"));
}
$rows = q_all("SELECT c.*,
        (SELECT COUNT(*) FROM quotations qt WHERE qt.customer_id = c.id) quote_count,
        (SELECT COALESCE(SUM(grand_total),0) FROM invoices i WHERE i.customer_id = c.id AND i.status <> 'cancelled') revenue,
        (SELECT COALESCE(SUM(grand_total),0) FROM invoices i WHERE i.customer_id = c.id AND i.status <> 'cancelled')
            - (SELECT COALESCE(SUM(amount),0) FROM payments pm WHERE pm.customer_id = c.id AND pm.direction = 'in') outstanding
    FROM customers c" . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY c.created_at DESC', $params);

$pageTitle = 'Customers';
$activeNav = 'customers';
$breadcrumbs = ['Customers' => null];
$pageActions = '<a href="export.php?type=customers" class="btn btn-light-brand"><i class="feather-download me-2"></i>Export CSV</a>'
    . '<a href="customer-form.php" class="btn btn-primary"><i class="feather-user-plus me-2"></i>Add Customer</a>';
$extraCss = ['vendors/css/dataTables.bs5.min.css'];
$extraJs = ['vendors/js/dataTables.min.js', 'vendors/js/dataTables.bs5.min.js'];
require __DIR__ . '/partials/header.php';
?>
<div class="card">
    <div class="card-body">
        <form class="row g-2 mb-4" method="get">
            <div class="col-md-6"><input type="search" name="q" class="form-control" placeholder="Search name, organisation, phone, city" value="<?= e($search) ?>"></div>
            <div class="col-md-4">
                <select name="type" class="form-select">
                    <option value="">All customer types</option>
                    <?php foreach ($types as $k => $v): ?><option value="<?= $k ?>" <?= $type === $k ? 'selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2 d-flex gap-2"><button class="btn btn-primary flex-fill">Filter</button><a href="customers.php" class="btn btn-light-brand"><i class="feather-x"></i></a></div>
        </form>
        <?php if (!$rows): ?>
            <div class="vh-empty"><i class="feather-users"></i>No customers yet. Add hospitals, clinics, diagnostic centres and dealers you work with — or save them straight from an enquiry.</div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover" id="custTable">
                <thead><tr><th>Customer</th><th>Type</th><th>Contact</th><th>City</th><th>Quotes</th><th>Billed</th><th>Outstanding</th><th data-orderable="false"></th></tr></thead>
                <tbody>
                <?php foreach ($rows as $c): $r = $c; ?>
                    <tr>
                        <td>
                            <div class="d-flex align-items-center gap-3">
                                <span class="avatar-text avatar-md bg-soft-primary text-primary"><?= e(strtoupper(mb_substr($c['organization'] ?: $c['name'], 0, 1))) ?></span>
                                <div>
                                    <a href="customer-form.php?id=<?= (int) $c['id'] ?>" class="fw-semibold text-dark d-block"><?= e($c['organization'] ?: $c['name']) ?></a>
                                    <?php if ($c['organization']): ?><span class="fs-12 text-muted"><?= e($c['name']) ?></span><?php endif; ?>
                                </div>
                            </div>
                        </td>
                        <td><span class="badge bg-gray-200 text-dark"><?= e($types[$c['type']] ?? $c['type']) ?></span></td>
                        <td class="fs-12"><?= e($c['phone']) ?><br><span class="text-muted"><?= e($c['email']) ?></span></td>
                        <td><?= e($c['city'] ?: '—') ?></td>
                        <td><?= (int) $c['quote_count'] ?></td>
                        <td data-order="<?= (float) $c['revenue'] ?>"><?= money($c['revenue']) ?></td>
                        <td data-order="<?= (float) $c['outstanding'] ?>" class="<?= $c['outstanding'] > 0 ? 'text-danger fw-semibold' : 'text-muted' ?>"><?= money($c['outstanding']) ?></td>
                        <td class="text-end">
                            <div class="hstack gap-2 justify-content-end">
                                <a href="invoice-form.php?customer_id=<?= (int) $c['id'] ?>" class="avatar-text avatar-md" title="New invoice"><i class="feather-file-plus"></i></a>
                                <a href="customer-ledger.php?id=<?= (int) $c['id'] ?>" class="avatar-text avatar-md" title="Ledger / statement"><i class="feather-book-open"></i></a>
                                <a href="customer-form.php?id=<?= (int) $c['id'] ?>" class="avatar-text avatar-md" title="Edit"><i class="feather-edit-3"></i></a>
                                <form method="post" class="m-0" data-confirm="Delete this customer? Their quotations are kept."><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                                    <button class="avatar-text avatar-md border-0 text-danger" title="Delete"><i class="feather-trash-2"></i></button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php
$inlineJs = "if ($.fn.DataTable && $('#custTable').length) { $('#custTable').DataTable({ pageLength: 25, order: [], searching: false }); }";
require __DIR__ . '/partials/footer.php';
