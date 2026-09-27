<?php
require __DIR__ . '/../includes/auth.php';
$admin = require_admin();

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    require_post();
    $id = (int) post('product_id');
    $qty = (int) post('qty');
    $mode = post('mode');
    $reason = mb_substr(post('reason'), 0, 200);
    $p = q_row('SELECT id, name, stock_qty FROM products WHERE id = ?', [$id]);
    if ($p && ($qty !== 0 || $mode === 'set')) {
        $change = $mode === 'out' ? -abs($qty) : ($mode === 'set' ? $qty - (int) $p['stock_qty'] : abs($qty));
        $new = (int) $p['stock_qty'] + $change;
        q('UPDATE products SET stock_qty = ? WHERE id = ?', [$new, $id]);
        q('INSERT INTO stock_movements (product_id, change_qty, balance_after, reason, admin_id) VALUES (?, ?, ?, ?, ?)',
            [$id, $change, $new, $reason ?: ($mode === 'in' ? 'Stock received' : ($mode === 'out' ? 'Stock issued / sold' : 'Stock count correction')), $admin['id']]);
        log_activity('stock.adjust', sprintf('Stock %s%d for "%s" (now %d)', $change >= 0 ? '+' : '', $change, $p['name'], $new));
        flash('success', sprintf('Stock for "%s" updated to %d.', $p['name'], $new));
    }
    redirect('admin/inventory.php' . (get('filter') ? '?filter=' . rawurlencode(get('filter')) : ''));
}

$filter = get('filter');
$where = 'WHERE 1=1';
if ($filter === 'low') $where .= ' AND p.min_stock > 0 AND p.stock_qty <= p.min_stock AND p.stock_qty > 0';
if ($filter === 'out') $where .= ' AND p.stock_qty <= 0';
if ($filter === 'attention') $where .= ' AND p.min_stock > 0 AND p.stock_qty <= p.min_stock';

$products = q_all("SELECT p.id, p.name, p.sku, p.unit, p.stock_qty, p.min_stock, p.price, p.image, p.is_active, c.name category_name, c.icon
    FROM products p LEFT JOIN categories c ON c.id = p.category_id $where ORDER BY (p.min_stock > 0 AND p.stock_qty <= p.min_stock) DESC, p.name");
$summary = q_row('SELECT COUNT(*) total, SUM(stock_qty > 0 AND (min_stock = 0 OR stock_qty > min_stock)) ok, SUM(min_stock > 0 AND stock_qty <= min_stock AND stock_qty > 0) low, SUM(stock_qty <= 0) out_of_stock,
    COALESCE(SUM(CASE WHEN price IS NOT NULL AND stock_qty > 0 THEN price * stock_qty END), 0) value FROM products');
$movements = q_all('SELECT m.*, p.name, a.username FROM stock_movements m JOIN products p ON p.id = m.product_id LEFT JOIN admins a ON a.id = m.admin_id ORDER BY m.created_at DESC, m.id DESC LIMIT 15');

$pageTitle = 'Inventory';
$activeNav = 'inventory';
$breadcrumbs = ['Inventory' => null];
$extraCss = ['vendors/css/dataTables.bs5.min.css'];
$extraJs = ['vendors/js/dataTables.min.js', 'vendors/js/dataTables.bs5.min.js'];
require __DIR__ . '/partials/header.php';
?>
<div class="row">
    <?php foreach ([
        ['Total products', (int) $summary['total'], 'package', 'primary', ''],
        ['In stock', (int) $summary['ok'], 'check-circle', 'success', ''],
        ['Low stock', (int) $summary['low'], 'alert-triangle', 'warning', 'low'],
        ['Out of stock', (int) $summary['out_of_stock'], 'x-circle', 'danger', 'out'],
    ] as [$label, $val, $icon, $color, $f]): ?>
        <div class="col-xxl-3 col-md-6">
            <a href="inventory.php<?= $f ? '?filter=' . $f : '' ?>" class="card stretch stretch-full text-reset<?= $filter === $f ? ' border-' . $color : '' ?>">
                <div class="card-body d-flex align-items-center gap-3">
                    <span class="avatar-text avatar-lg bg-soft-<?= $color ?> text-<?= $color ?>"><i class="feather-<?= $icon ?>"></i></span>
                    <div><div class="fs-4 fw-bold text-dark"><?= $val ?></div><div class="fs-12 text-muted"><?= $label ?></div></div>
                </div>
            </a>
        </div>
    <?php endforeach; ?>

    <div class="col-xxl-8">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title">Stock levels<?= $filter ? ' — ' . e(['out' => 'out of stock', 'low' => 'low stock', 'attention' => 'needs re-order'][$filter] ?? '') : '' ?></h5>
                <span class="fs-12 text-muted">Stock value (priced items): <strong class="text-dark"><?= money($summary['value']) ?></strong></span>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover" id="stockTable">
                        <thead><tr><th>Product</th><th>Stock</th><th>Min</th><th data-orderable="false" style="min-width:330px">Adjust</th></tr></thead>
                        <tbody>
                        <?php foreach ($products as $p): ?>
                            <tr>
                                <td>
                                    <a href="product-form.php?id=<?= (int) $p['id'] ?>" class="fw-semibold text-dark d-block"><?= e($p['name']) ?></a>
                                    <span class="fs-12 text-muted"><?= e($p['sku']) ?> · <?= e($p['category_name'] ?: 'Uncategorised') ?></span>
                                </td>
                                <td data-order="<?= (int) $p['stock_qty'] ?>">
                                    <span class="badge <?= $p['stock_qty'] <= 0 ? 'bg-soft-danger text-danger' : ($p['min_stock'] > 0 && $p['stock_qty'] <= $p['min_stock'] ? 'bg-soft-warning text-warning' : 'bg-soft-success text-success') ?> fs-12"><?= (int) $p['stock_qty'] ?> <?= e($p['unit']) ?></span>
                                </td>
                                <td><?= $p['min_stock'] > 0 ? (int) $p['min_stock'] : '<span class="text-muted fs-12">off</span>' ?></td>
                                <td>
                                    <form method="post" class="d-flex gap-1">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="product_id" value="<?= (int) $p['id'] ?>">
                                        <select name="mode" class="form-select form-select-sm" style="width:92px">
                                            <option value="in">+ In</option>
                                            <option value="out">− Out</option>
                                            <option value="set">= Set</option>
                                        </select>
                                        <input type="number" name="qty" class="form-control form-control-sm" style="width:80px" placeholder="Qty" required>
                                        <input type="text" name="reason" class="form-control form-control-sm" placeholder="Note (optional)" maxlength="200">
                                        <button class="btn btn-sm btn-primary"><i class="feather-check"></i></button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xxl-4">
        <div class="card">
            <div class="card-header"><h5 class="card-title">Recent stock movements</h5></div>
            <div class="card-body">
                <?php if (!$movements): ?><div class="vh-empty"><i class="feather-layers"></i>No stock movements yet.</div><?php endif; ?>
                <?php foreach ($movements as $m): ?>
                    <div class="d-flex justify-content-between align-items-start mb-3 pb-3 border-bottom border-bottom-dashed">
                        <div class="me-2">
                            <div class="fw-semibold text-dark fs-13"><?= e($m['name']) ?></div>
                            <div class="fs-12 text-muted"><?= e($m['reason']) ?> · <?= e($m['username'] ?: '') ?> · <?= e(time_ago($m['created_at'])) ?></div>
                        </div>
                        <div class="text-end text-nowrap">
                            <span class="fw-bold <?= $m['change_qty'] >= 0 ? 'text-success' : 'text-danger' ?>"><?= $m['change_qty'] >= 0 ? '+' : '' ?><?= (int) $m['change_qty'] ?></span>
                            <div class="fs-11 text-muted">bal. <?= (int) $m['balance_after'] ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>
<?php
$inlineJs = "if ($.fn.DataTable) { $('#stockTable').DataTable({ pageLength: 25, order: [] }); }";
require __DIR__ . '/partials/footer.php';
