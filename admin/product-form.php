<?php
require __DIR__ . '/../includes/auth.php';
$admin = require_admin();

$id = (int) get('id');
$product = $id ? q_row('SELECT * FROM products WHERE id = ?', [$id]) : null;
if ($id && !$product) {
    flash('error', 'Product not found.');
    redirect('admin/products.php');
}

$defaults = [
    'category_id' => (int) get('category') ?: null, 'name' => '', 'sku' => '', 'brand' => '', 'model' => '',
    'short_description' => '', 'description' => '', 'specifications' => '', 'price' => null, 'gst_rate' => '12.00',
    'unit' => 'Unit', 'stock_qty' => 0, 'min_stock' => 0, 'warranty' => '', 'image' => null, 'brochure' => null,
    'show_price' => 0, 'is_featured' => 0, 'is_active' => 1,
];
$data = $product ?: $defaults;
$errors = [];

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_check();
    $data = array_merge($data, [
        'category_id' => (int) post('category_id') ?: null,
        'name' => post('name'),
        'sku' => post('sku'),
        'brand' => post('brand'),
        'model' => post('model'),
        'short_description' => mb_substr(post('short_description'), 0, 300),
        'description' => post('description'),
        'specifications' => post('specifications'),
        'price' => post('price') === '' ? null : (float) str_replace(',', '', post('price')),
        'gst_rate' => (float) post('gst_rate', '12'),
        'unit' => post('unit') ?: 'Unit',
        'stock_qty' => (int) post('stock_qty'),
        'min_stock' => max(0, (int) post('min_stock')),
        'warranty' => post('warranty'),
        'is_active' => isset($_POST['is_active']) ? 1 : 0,
    ]);

    if ($data['name'] === '') $errors[] = 'Product name is required.';
    if ($data['price'] !== null && $data['price'] < 0) $errors[] = 'Price cannot be negative.';
    if ($data['sku'] !== '' && q_val('SELECT id FROM products WHERE sku = ? AND id <> ?', [$data['sku'], $id])) {
        $errors[] = 'Another product already uses SKU "' . $data['sku'] . '".';
    }

    $newImage = $newBrochure = null;
    if (!$errors) {
        try {
            $newImage = handle_upload('image', 'products', 'image');
            $newBrochure = handle_upload('brochure', 'brochures', 'pdf');
        } catch (RuntimeException $e) {
            $errors[] = $e->getMessage();
            delete_upload('products', $newImage);
        }
    }

    if (!$errors) {
        if ($newImage) {
            if ($product) delete_upload('products', $product['image']);
            $data['image'] = $newImage;
        } elseif (isset($_POST['remove_image']) && $product) {
            delete_upload('products', $product['image']);
            $data['image'] = null;
        }
        if ($newBrochure) {
            if ($product) delete_upload('brochures', $product['brochure']);
            $data['brochure'] = $newBrochure;
        } elseif (isset($_POST['remove_brochure']) && $product) {
            delete_upload('brochures', $product['brochure']);
            $data['brochure'] = null;
        }

        $fields = ['category_id', 'name', 'sku', 'brand', 'model', 'short_description', 'description', 'specifications', 'price',
            'gst_rate', 'unit', 'stock_qty', 'min_stock', 'warranty', 'image', 'brochure', 'show_price', 'is_featured', 'is_active'];
        $values = array_map(fn($f) => $data[$f], $fields);

        if ($product) {
            $set = implode(', ', array_map(fn($f) => "$f = ?", $fields));
            q("UPDATE products SET $set WHERE id = ?", array_merge($values, [$id]));
            if ((int) $product['stock_qty'] !== (int) $data['stock_qty']) {
                q('INSERT INTO stock_movements (product_id, change_qty, balance_after, reason, admin_id) VALUES (?, ?, ?, ?, ?)',
                    [$id, $data['stock_qty'] - $product['stock_qty'], $data['stock_qty'], 'Edited on product form', $admin['id']]);
            }
            log_activity('product.update', 'Updated product "' . $data['name'] . '"');
            flash('success', 'Product updated.');
        } else {
            $slug = unique_slug('products', $data['name']);
            q('INSERT INTO products (slug, ' . implode(', ', $fields) . ') VALUES (?, ' . implode(', ', array_fill(0, count($fields), '?')) . ')',
                array_merge([$slug], $values));
            $id = (int) db()->lastInsertId();
            if ($data['sku'] === '') {
                q('UPDATE products SET sku = ? WHERE id = ?', [sprintf('VH-%04d', $id), $id]);
            }
            if ($data['stock_qty'] != 0) {
                q('INSERT INTO stock_movements (product_id, change_qty, balance_after, reason, admin_id) VALUES (?, ?, ?, ?, ?)',
                    [$id, $data['stock_qty'], $data['stock_qty'], 'Opening stock', $admin['id']]);
            }
            log_activity('product.create', 'Added product "' . $data['name'] . '"');
            flash('success', 'Product added.');
        }
        redirect(isset($_POST['save_new']) ? 'admin/product-form.php' : 'admin/product-form.php?id=' . $id);
    }
}

$categories = q_all('SELECT id, name, icon FROM categories ORDER BY sort_order, name');
$img = $product ? product_image_url($data) : '';

$pageTitle = $product ? 'Edit Product' : 'Add Product';
$activeNav = $product ? 'products' : 'product-new';
$breadcrumbs = ['Products' => 'products.php', $pageTitle => null];
if ($product) {
    $pageActions = '<a href="quotation-form.php?product_id=' . $id . '" class="btn btn-light-brand"><i class="feather-file-plus me-2"></i>Create quotation</a>';
}
require __DIR__ . '/partials/header.php';
?>
<?php foreach ($errors as $err): ?><div class="alert alert-danger"><?= e($err) ?></div><?php endforeach; ?>
<form method="post" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <div class="row">
        <div class="col-xl-8">
            <div class="card">
                <div class="card-header"><h5 class="card-title">Product details</h5></div>
                <div class="card-body">
                    <div class="mb-4">
                        <label class="form-label">Product name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" value="<?= e($data['name']) ?>" required maxlength="200" placeholder="e.g. 12-Channel ECG Machine">
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-4">
                            <label class="form-label">Category</label>
                            <select name="category_id" class="form-select">
                                <option value="">— None —</option>
                                <?php foreach ($categories as $c): ?>
                                    <option value="<?= (int) $c['id'] ?>" <?= (int) $data['category_id'] === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-4">
                            <label class="form-label">SKU / Item code</label>
                            <input type="text" name="sku" class="form-control" value="<?= e($data['sku']) ?>" maxlength="60" placeholder="Auto-generated if empty">
                        </div>
                        <div class="col-md-6 mb-4">
                            <label class="form-label">Brand / Manufacturer</label>
                            <input type="text" name="brand" class="form-control" value="<?= e($data['brand']) ?>" maxlength="120">
                        </div>
                        <div class="col-md-6 mb-4">
                            <label class="form-label">Model</label>
                            <input type="text" name="model" class="form-control" value="<?= e($data['model']) ?>" maxlength="120">
                        </div>
                    </div>
                    <div class="mb-4">
                        <label class="form-label">Short description</label>
                        <input type="text" name="short_description" class="form-control" value="<?= e($data['short_description']) ?>" maxlength="300" placeholder="One-line summary">
                    </div>
                    <div class="mb-4">
                        <label class="form-label">Full description</label>
                        <textarea name="description" class="form-control" rows="6"><?= e($data['description']) ?></textarea>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Technical specifications</label>
                        <textarea name="specifications" class="form-control font-monospace fs-12" rows="8" placeholder="One per line, e.g.&#10;Channels: 12&#10;Display: 7&quot; colour LCD&#10;Power: AC / Battery"><?= e($data['specifications']) ?></textarea>
                        <div class="form-text">Write one specification per line as <code>Name: Value</code>.</div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h5 class="card-title">Pricing & inventory</h5></div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4 mb-4">
                            <label class="form-label">Price (₹, excl. GST)</label>
                            <input type="number" step="0.01" min="0" name="price" class="form-control" value="<?= e($data['price']) ?>" placeholder="Leave blank = on request">
                        </div>
                        <div class="col-md-4 mb-4">
                            <label class="form-label">GST rate (%)</label>
                            <select name="gst_rate" class="form-select">
                                <?php foreach (['0', '5', '12', '18', '28'] as $g): ?>
                                    <option value="<?= $g ?>" <?= (float) $data['gst_rate'] == (float) $g ? 'selected' : '' ?>><?= $g ?>%</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4 mb-4">
                            <label class="form-label">Unit</label>
                            <input type="text" name="unit" class="form-control" value="<?= e($data['unit']) ?>" maxlength="30" placeholder="Unit, Box, Roll, Set…">
                        </div>
                        <div class="col-md-4 mb-4">
                            <label class="form-label">Stock quantity</label>
                            <input type="number" name="stock_qty" class="form-control" value="<?= (int) $data['stock_qty'] ?>">
                        </div>
                        <div class="col-md-4 mb-4">
                            <label class="form-label">Low-stock alert at</label>
                            <input type="number" min="0" name="min_stock" class="form-control" value="<?= (int) $data['min_stock'] ?>">
                            <div class="form-text">0 = don't track / supplied on order</div>
                        </div>
                        <div class="col-md-4 mb-4">
                            <label class="form-label">Warranty</label>
                            <input type="text" name="warranty" class="form-control" value="<?= e($data['warranty']) ?>" maxlength="120" placeholder="e.g. 1 year manufacturer">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-4">
            <div class="card">
                <div class="card-header"><h5 class="card-title">Status</h5></div>
                <div class="card-body">
                    <div class="form-check form-switch mb-4">
                        <input class="form-check-input" type="checkbox" id="is_active" name="is_active" <?= $data['is_active'] ? 'checked' : '' ?>>
                        <label class="form-check-label" for="is_active">Active (available for quotations)</label>
                    </div>
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary"><i class="feather-save me-2"></i>Save product</button>
                        <button type="submit" name="save_new" value="1" class="btn btn-light-brand">Save & add another</button>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h5 class="card-title">Product image</h5></div>
                <div class="card-body">
                    <?php if ($img): ?>
                        <img src="<?= e($img) ?>" alt="" class="img-fluid rounded border mb-3">
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" id="remove_image" name="remove_image">
                            <label class="form-check-label" for="remove_image">Remove current image</label>
                        </div>
                    <?php endif; ?>
                    <input type="file" name="image" class="form-control" accept="image/jpeg,image/png,image/webp,image/gif">
                    <div class="form-text">JPG, PNG or WEBP, up to 5 MB. Square images look best.</div>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h5 class="card-title">Brochure / datasheet</h5></div>
                <div class="card-body">
                    <?php if (!empty($data['brochure'])): ?>
                        <a href="<?= e(base_url('uploads/brochures/' . rawurlencode($data['brochure']))) ?>" target="_blank" class="d-block mb-2"><i class="feather-file me-1"></i>Current brochure (PDF)</a>
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" id="remove_brochure" name="remove_brochure">
                            <label class="form-check-label" for="remove_brochure">Remove brochure</label>
                        </div>
                    <?php endif; ?>
                    <input type="file" name="brochure" class="form-control" accept="application/pdf">
                    <div class="form-text">PDF up to 15 MB — keep datasheets handy for customers.</div>
                </div>
            </div>

            <?php if ($product): ?>
            <div class="card">
                <div class="card-body fs-12 text-muted">
                    <div class="d-flex justify-content-between mb-2"><span>Enquiries</span><strong class="text-dark"><?= (int) q_val('SELECT COUNT(*) FROM enquiries WHERE product_id = ?', [$id]) ?></strong></div>
                    <div class="d-flex justify-content-between mb-2"><span>Created</span><span><?= e(fmt_date($product['created_at'], 'd M Y, h:i A')) ?></span></div>
                    <div class="d-flex justify-content-between"><span>Updated</span><span><?= e(fmt_date($product['updated_at'], 'd M Y, h:i A')) ?></span></div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</form>
<?php require __DIR__ . '/partials/footer.php';
