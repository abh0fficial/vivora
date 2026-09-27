<?php
require __DIR__ . '/../includes/auth.php';
$admin = require_admin();

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    require_post();
    $id = (int) post('id');
    $product = q_row('SELECT * FROM products WHERE id = ?', [$id]);
    if ($product) {
        switch (post('action')) {
            case 'delete':
                q('DELETE FROM products WHERE id = ?', [$id]);
                delete_upload('products', $product['image']);
                delete_upload('brochures', $product['brochure']);
                log_activity('product.delete', 'Deleted product "' . $product['name'] . '"');
                flash('success', 'Product "' . $product['name'] . '" deleted.');
                break;
            case 'toggle_active':
                q('UPDATE products SET is_active = 1 - is_active WHERE id = ?', [$id]);
                flash('success', 'Product "' . $product['name'] . '" is now ' . ($product['is_active'] ? 'inactive' : 'active') . '.');
                break;
        }
    }
    redirect('admin/products.php' . (!empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : ''));
}

$search = get('q');
$catId = (int) get('category');
$status = get('status');

$where = [];
$params = [];
if ($search !== '') {
    $where[] = '(p.name LIKE ? OR p.sku LIKE ? OR p.brand LIKE ? OR p.model LIKE ?)';
    array_push($params, "%$search%", "%$search%", "%$search%", "%$search%");
}
if ($catId) {
    $where[] = 'p.category_id = ?';
    $params[] = $catId;
}
if ($status === 'active') $where[] = 'p.is_active = 1';
if ($status === 'hidden') $where[] = 'p.is_active = 0';

$products = q_all('SELECT p.*, c.name category_name, c.icon,
        (SELECT COUNT(*) FROM enquiries e WHERE e.product_id = p.id) enquiry_count
    FROM products p LEFT JOIN categories c ON c.id = p.category_id'
    . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY p.created_at DESC, p.id DESC', $params);
$categories = q_all('SELECT id, name FROM categories ORDER BY sort_order, name');

$pageTitle = 'Products';
$activeNav = 'products';
$breadcrumbs = ['Products' => null];
$pageActions = '<a href="export.php?type=products" class="btn btn-light-brand"><i class="feather-download me-2"></i>Export CSV</a><a href="categories.php" class="btn btn-light-brand"><i class="feather-grid me-2"></i>Categories</a>'
    . '<a href="product-form.php" class="btn btn-primary"><i class="feather-plus me-2"></i>Add Product</a>';
$extraCss = ['vendors/css/dataTables.bs5.min.css'];
$extraJs = ['vendors/js/dataTables.min.js', 'vendors/js/dataTables.bs5.min.js'];
require __DIR__ . '/partials/header.php';
?>
<div class="card">
    <div class="card-body">
        <form class="row g-2 mb-4" method="get">
            <div class="col-md-4"><input type="search" name="q" class="form-control" placeholder="Search name, SKU, brand, model" value="<?= e($search) ?>"></div>
            <div class="col-md-3">
                <select name="category" class="form-select">
                    <option value="">All categories</option>
                    <?php foreach ($categories as $c): ?>
                        <option value="<?= (int) $c['id'] ?>" <?= $catId === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <select name="status" class="form-select">
                    <option value="">Any status</option>
                    <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Active</option>
                    <option value="hidden" <?= $status === 'hidden' ? 'selected' : '' ?>>Inactive</option>
                </select>
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button class="btn btn-primary flex-fill">Filter</button>
                <a href="products.php" class="btn btn-light-brand" title="Reset"><i class="feather-x"></i></a>
            </div>
        </form>

        <?php if (!$products): ?>
            <div class="vh-empty"><i class="feather-package"></i>No products found. <a href="product-form.php">Add your first product</a>.</div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover" id="productsTable">
                <thead>
                <tr>
                    <th>Product</th>
                    <th>Category</th>
                    <th>Price</th>
                    <th>Stock</th>
                    <th>Enquiries</th>
                    <th>Status</th>
                    <th class="text-end" data-orderable="false">Actions</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($products as $p): $img = product_image_url($p); ?>
                    <tr>
                        <td>
                            <div class="d-flex align-items-center gap-3">
                                <?php if ($img): ?><img src="<?= e($img) ?>" class="vh-thumb" alt=""><?php else: ?><span class="vh-thumb"><i class="feather-<?= e(category_icon($p['icon'])) ?>"></i></span><?php endif; ?>
                                <div>
                                    <a href="product-form.php?id=<?= (int) $p['id'] ?>" class="fw-semibold text-dark d-block"><?= e($p['name']) ?></a>
                                    <span class="fs-12 text-muted"><?= e($p['sku']) ?><?= $p['brand'] ? ' · ' . e($p['brand']) : '' ?><?= $p['model'] ? ' ' . e($p['model']) : '' ?></span>
                                </div>
                            </div>
                        </td>
                        <td><?= e($p['category_name'] ?: '—') ?></td>
                        <td data-order="<?= (float) $p['price'] ?>"><?= $p['price'] !== null ? money($p['price']) : '<span class="text-muted fs-12">On request</span>' ?></td>
                        <td data-order="<?= (int) $p['stock_qty'] ?>">
                            <?php if ($p['stock_qty'] <= 0 && $p['min_stock'] == 0): ?>
                                <span class="badge bg-gray-200 text-dark">On order</span>
                            <?php elseif ($p['stock_qty'] <= 0): ?>
                                <span class="badge bg-soft-danger text-danger">Out of stock</span>
                            <?php elseif ($p['min_stock'] > 0 && $p['stock_qty'] <= $p['min_stock']): ?>
                                <span class="badge bg-soft-warning text-warning"><?= (int) $p['stock_qty'] ?> · low</span>
                            <?php else: ?>
                                <span class="badge bg-soft-success text-success"><?= (int) $p['stock_qty'] ?> <?= e($p['unit']) ?></span>
                            <?php endif; ?>
                        </td>
                        <td><?= (int) $p['enquiry_count'] ?></td>
                        <td>
                            <?= $p['is_active'] ? '<span class="badge bg-soft-success text-success">Active</span>' : '<span class="badge bg-soft-secondary text-secondary">Inactive</span>' ?>
                        </td>
                        <td class="text-end">
                            <div class="hstack gap-2 justify-content-end">
                                <a href="product-form.php?id=<?= (int) $p['id'] ?>" class="avatar-text avatar-md" title="Edit"><i class="feather-edit-3"></i></a>
                                <div class="dropdown">
                                    <a href="javascript:void(0)" class="avatar-text avatar-md" data-bs-toggle="dropdown"><i class="feather-more-horizontal"></i></a>
                                    <div class="dropdown-menu dropdown-menu-end">
                                        <form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                                            <button class="dropdown-item" name="action" value="toggle_active"><i class="feather-<?= $p['is_active'] ? 'eye-off' : 'eye' ?> me-2"></i><?= $p['is_active'] ? 'Mark inactive' : 'Mark active' ?></button>
                                        </form>
                                        <a class="dropdown-item" href="quotation-form.php?product_id=<?= (int) $p['id'] ?>"><i class="feather-file-plus me-2"></i>Create quotation</a>
                                        <div class="dropdown-divider"></div>
                                        <form method="post" data-confirm="Delete this product permanently?"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                                            <button class="dropdown-item text-danger" name="action" value="delete"><i class="feather-trash-2 me-2"></i>Delete</button>
                                        </form>
                                    </div>
                                </div>
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
$inlineJs = "if (window.jQuery && $.fn.DataTable && $('#productsTable').length) { $('#productsTable').DataTable({ pageLength: 25, order: [], searching: false }); }";
require __DIR__ . '/partials/footer.php';
