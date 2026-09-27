<?php
require __DIR__ . '/../includes/auth.php';
$admin = require_admin();

$icons = ['activity', 'file-text', 'monitor', 'zap', 'radio', 'scissors', 'heart', 'cpu', 'tool', 'droplet', 'grid',
    'package', 'thermometer', 'wind', 'eye', 'shield', 'box', 'truck', 'battery-charging', 'plus-square', 'layers', 'aperture'];

$editId = (int) get('edit');
$editing = $editId ? q_row('SELECT * FROM categories WHERE id = ?', [$editId]) : null;
$form = $editing ?: ['name' => '', 'description' => '', 'icon' => 'package', 'sort_order' => (int) q_val('SELECT COALESCE(MAX(sort_order),0)+1 FROM categories'), 'is_active' => 1];
$errors = [];

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_check();
    $action = post('action');
    if ($action === 'delete') {
        $cat = q_row('SELECT * FROM categories WHERE id = ?', [(int) post('id')]);
        if ($cat) {
            q('DELETE FROM categories WHERE id = ?', [$cat['id']]);
            log_activity('category.delete', 'Deleted category "' . $cat['name'] . '"');
            flash('success', 'Category deleted. Its products are now uncategorised.');
        }
        redirect('admin/categories.php');
    }

    $form = [
        'name' => post('name'),
        'description' => post('description'),
        'icon' => in_array(post('icon'), $icons, true) ? post('icon') : 'package',
        'sort_order' => (int) post('sort_order'),
        'is_active' => isset($_POST['is_active']) ? 1 : 0,
    ];
    if ($form['name'] === '') {
        $errors[] = 'Category name is required.';
    }
    if (!$errors) {
        $id = (int) post('id');
        if ($id) {
            q('UPDATE categories SET name = ?, slug = ?, description = ?, icon = ?, sort_order = ?, is_active = ? WHERE id = ?',
                [$form['name'], unique_slug('categories', $form['name'], $id), $form['description'], $form['icon'], $form['sort_order'], $form['is_active'], $id]);
            log_activity('category.update', 'Updated category "' . $form['name'] . '"');
            flash('success', 'Category updated.');
        } else {
            q('INSERT INTO categories (name, slug, description, icon, sort_order, is_active) VALUES (?, ?, ?, ?, ?, ?)',
                [$form['name'], unique_slug('categories', $form['name']), $form['description'], $form['icon'], $form['sort_order'], $form['is_active']]);
            log_activity('category.create', 'Added category "' . $form['name'] . '"');
            flash('success', 'Category added.');
        }
        redirect('admin/categories.php');
    }
}

$categories = q_all('SELECT c.*, COUNT(p.id) product_count FROM categories c LEFT JOIN products p ON p.category_id = c.id GROUP BY c.id ORDER BY c.sort_order, c.name');

$pageTitle = 'Categories';
$activeNav = 'categories';
$breadcrumbs = ['Products' => 'products.php', 'Categories' => null];
require __DIR__ . '/partials/header.php';
?>
<div class="row">
    <div class="col-xl-8">
        <div class="card">
            <div class="card-header"><h5 class="card-title">Product categories</h5></div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead><tr><th>#</th><th>Category</th><th>Products</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
                        <tbody>
                        <?php foreach ($categories as $c): ?>
                            <tr>
                                <td class="text-muted"><?= (int) $c['sort_order'] ?></td>
                                <td>
                                    <div class="d-flex align-items-center gap-3">
                                        <span class="vh-thumb"><i class="feather-<?= e(category_icon($c['icon'])) ?>"></i></span>
                                        <div>
                                            <span class="fw-semibold text-dark d-block"><?= e($c['name']) ?></span>
                                            <span class="fs-12 text-muted"><?= e(excerpt($c['description'], 70)) ?></span>
                                        </div>
                                    </div>
                                </td>
                                <td><a href="products.php?category=<?= (int) $c['id'] ?>"><?= (int) $c['product_count'] ?></a></td>
                                <td><?= $c['is_active'] ? '<span class="badge bg-soft-success text-success">Visible</span>' : '<span class="badge bg-soft-secondary text-secondary">Hidden</span>' ?></td>
                                <td class="text-end">
                                    <div class="hstack gap-2 justify-content-end">
                                        <a href="product-form.php?category=<?= (int) $c['id'] ?>" class="avatar-text avatar-md" title="Add product"><i class="feather-plus"></i></a>
                                        <a href="categories.php?edit=<?= (int) $c['id'] ?>" class="avatar-text avatar-md" title="Edit"><i class="feather-edit-3"></i></a>
                                        <form method="post" data-confirm="Delete this category? Its products will become uncategorised." class="m-0">
                                            <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                                            <button class="avatar-text avatar-md border-0 text-danger" name="action" value="delete" title="Delete"><i class="feather-trash-2"></i></button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-4">
        <div class="card">
            <div class="card-header"><h5 class="card-title"><?= $editing ? 'Edit category' : 'Add category' ?></h5></div>
            <div class="card-body">
                <?php foreach ($errors as $err): ?><div class="alert alert-danger"><?= e($err) ?></div><?php endforeach; ?>
                <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= (int) ($editing['id'] ?? 0) ?>">
                    <div class="mb-3">
                        <label class="form-label">Name <span class="text-danger">*</span></label>
                        <input name="name" class="form-control" value="<?= e($form['name']) ?>" required maxlength="120">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="3"><?= e($form['description']) ?></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Icon</label>
                        <div class="d-flex flex-wrap gap-2">
                            <?php foreach ($icons as $ic): ?>
                                <input type="radio" class="btn-check" name="icon" id="ic-<?= $ic ?>" value="<?= $ic ?>" <?= $form['icon'] === $ic ? 'checked' : '' ?>>
                                <label class="btn btn-outline-primary btn-sm px-2" for="ic-<?= $ic ?>" title="<?= $ic ?>"><i class="feather-<?= $ic ?>"></i></label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Display order</label>
                        <input type="number" name="sort_order" class="form-control" value="<?= (int) $form['sort_order'] ?>">
                    </div>
                    <div class="form-check form-switch mb-4">
                        <input class="form-check-input" type="checkbox" id="cat_active" name="is_active" <?= $form['is_active'] ? 'checked' : '' ?>>
                        <label class="form-check-label" for="cat_active">Visible on website</label>
                    </div>
                    <div class="d-flex gap-2">
                        <button class="btn btn-primary flex-fill"><?= $editing ? 'Update' : 'Add' ?> category</button>
                        <?php if ($editing): ?><a href="categories.php" class="btn btn-light-brand">Cancel</a><?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?php require __DIR__ . '/partials/footer.php';
