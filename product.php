<?php
require __DIR__ . '/includes/public.php';

$slug = get('slug');
$p = q_row('SELECT p.*, c.name category_name, c.slug category_slug, c.icon FROM products p LEFT JOIN categories c ON c.id = p.category_id
    WHERE p.slug = ? AND p.is_active = 1', [$slug]);
if (!$p) {
    http_response_code(404);
    $meta = page_meta('Product not found');
    require __DIR__ . '/partials/site-header.php';
    echo '<section class="vh-section"><div class="container"><div class="vh-empty-state"><i class="feather-alert-circle"></i><h3>Product not found</h3>'
        . '<p>This product may have been moved or is no longer listed.</p><a href="' . base_url('products.php') . '" class="btn btn-primary vh-btn">Browse products</a></div></div></section>';
    require __DIR__ . '/partials/site-footer.php';
    exit;
}

$errors = [];
$old = ['name' => '', 'organization' => '', 'email' => '', 'phone' => '', 'city' => '', 'quantity' => '1', 'message' => ''];
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    [$errors, $old] = handle_enquiry_post('product', (int) $p['id'], 'product.php?slug=' . rawurlencode($p['slug']) . '#enquire');
} else {
    // Count a view once per session per product.
    if (empty($_SESSION['viewed'][$p['id']])) {
        $_SESSION['viewed'][$p['id']] = 1;
        q('UPDATE products SET views = views + 1 WHERE id = ?', [$p['id']]);
    }
}

$specs = parse_specs($p['specifications']);
$related = q_all('SELECT p.*, c.name category_name, c.icon FROM products p LEFT JOIN categories c ON c.id = p.category_id
    WHERE p.is_active = 1 AND p.category_id <=> ? AND p.id <> ? ORDER BY p.is_featured DESC, RAND() LIMIT 4', [$p['category_id'], $p['id']]);
$waLink = whatsapp_link('Hello, I am interested in "' . $p['name'] . '". Please share price and details.');
$img = product_image_url($p);

$meta = page_meta($p['name'], excerpt($p['short_description'] ?: $p['description'], 155));
if ($img) $meta['image'] = $img;
$activePage = 'products';
require __DIR__ . '/partials/site-header.php';
?>
<section class="vh-page-hero vh-page-hero-sm">
    <div class="container">
        <nav aria-label="breadcrumb"><ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="<?= base_url('index.php') ?>">Home</a></li>
            <li class="breadcrumb-item"><a href="<?= base_url('products.php') ?>">Products</a></li>
            <?php if ($p['category_name']): ?><li class="breadcrumb-item"><a href="<?= base_url('products.php?category=' . rawurlencode($p['category_slug'])) ?>"><?= e($p['category_name']) ?></a></li><?php endif; ?>
            <li class="breadcrumb-item active" aria-current="page"><?= e($p['name']) ?></li>
        </ol></nav>
    </div>
</section>

<section class="vh-section pt-5">
    <div class="container">
        <div class="row g-5">
            <div class="col-lg-6">
                <div class="vh-product-gallery"><?= product_visual($p) ?></div>
            </div>
            <div class="col-lg-6">
                <?php if ($p['category_name']): ?><a href="<?= base_url('products.php?category=' . rawurlencode($p['category_slug'])) ?>" class="vh-eyebrow"><?= e($p['category_name']) ?></a><?php endif; ?>
                <h1 class="vh-product-title"><?= e($p['name']) ?></h1>
                <?php if ($p['short_description']): ?><p class="lead text-muted"><?= e($p['short_description']) ?></p><?php endif; ?>
                <div class="vh-product-price"><?= product_price_label($p) ?></div>
                <ul class="vh-meta-list list-unstyled">
                    <?php if ($p['brand']): ?><li><span>Brand</span><?= e($p['brand']) ?></li><?php endif; ?>
                    <?php if ($p['model']): ?><li><span>Model</span><?= e($p['model']) ?></li><?php endif; ?>
                    <?php if ($p['sku']): ?><li><span>Item code</span><?= e($p['sku']) ?></li><?php endif; ?>
                    <?php if ($p['warranty']): ?><li><span>Warranty</span><?= e($p['warranty']) ?></li><?php endif; ?>
                    <li><span>Availability</span><?= $p['stock_qty'] > 0 ? '<strong class="text-success">In stock</strong>' : '<strong class="text-primary">Available on order</strong>' ?></li>
                </ul>
                <div class="d-flex flex-wrap gap-2 mt-4">
                    <a href="#enquire" class="btn btn-primary btn-lg vh-btn"><i class="feather-file-text me-2"></i>Get a Quote</a>
                    <?php if ($waLink): ?><a href="<?= e($waLink) ?>" target="_blank" rel="noopener" class="btn btn-success btn-lg vh-btn"><i class="feather-message-circle me-2"></i>WhatsApp</a><?php endif; ?>
                    <?php if (setting('phone')): ?><a href="<?= e(tel_link(setting('phone'))) ?>" class="btn btn-outline-primary btn-lg vh-btn"><i class="feather-phone me-2"></i>Call</a><?php endif; ?>
                </div>
                <?php if ($p['brochure'] && is_file(__DIR__ . '/uploads/brochures/' . $p['brochure'])): ?>
                    <a href="<?= e(base_url('uploads/brochures/' . rawurlencode($p['brochure']))) ?>" target="_blank" class="vh-brochure"><i class="feather-download"></i>Download brochure (PDF)</a>
                <?php endif; ?>
                <div class="vh-assurance">
                    <div><i class="feather-truck"></i>Delivery &amp; installation</div>
                    <div><i class="feather-users"></i>Staff training</div>
                    <div><i class="feather-tool"></i>After-sales service</div>
                </div>
            </div>
        </div>

        <div class="row g-5 mt-2">
            <div class="col-lg-7">
                <?php if ($p['description']): ?>
                    <h2 class="vh-h3">Overview</h2>
                    <div class="vh-prose"><?= nl2br(e($p['description'])) ?></div>
                <?php endif; ?>
                <?php if ($specs): ?>
                    <h2 class="vh-h3 mt-5">Specifications</h2>
                    <table class="table vh-spec-table">
                        <tbody>
                        <?php foreach ($specs as [$k, $v]): ?>
                            <tr><?= $k !== '' ? '<th scope="row">' . e($k) . '</th><td>' . e($v) . '</td>' : '<td colspan="2">' . e($v) . '</td>' ?></tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <p class="small text-muted">Specifications vary by brand and model. Contact us for exact details of the variant you need.</p>
                <?php endif; ?>
            </div>
            <div class="col-lg-5">
                <div class="vh-form-card" id="enquire">
                    <h2 class="vh-h3">Request a quote</h2>
                    <p class="text-muted small mb-4">for <strong><?= e($p['name']) ?></strong>. We usually reply within one business day.</p>
                    <?php foreach ($errors as $err): ?><div class="alert alert-danger py-2 small"><?= e($err) ?></div><?php endforeach; ?>
                    <form method="post" action="#enquire" novalidate>
                        <?= spam_fields() ?>
                        <div class="row g-3">
                            <div class="col-sm-6"><label class="form-label" for="f-name">Your name *</label><input id="f-name" name="name" class="form-control" value="<?= e($old['name']) ?>" required maxlength="160" autocomplete="name"></div>
                            <div class="col-sm-6"><label class="form-label" for="f-phone">Phone *</label><input id="f-phone" name="phone" type="tel" class="form-control" value="<?= e($old['phone']) ?>" required maxlength="20" autocomplete="tel"></div>
                            <div class="col-12"><label class="form-label" for="f-org">Hospital / Clinic</label><input id="f-org" name="organization" class="form-control" value="<?= e($old['organization']) ?>" maxlength="200" autocomplete="organization"></div>
                            <div class="col-sm-6"><label class="form-label" for="f-email">Email</label><input id="f-email" name="email" type="email" class="form-control" value="<?= e($old['email']) ?>" maxlength="160" autocomplete="email"></div>
                            <div class="col-sm-3 col-6"><label class="form-label" for="f-city">City</label><input id="f-city" name="city" class="form-control" value="<?= e($old['city']) ?>" maxlength="80"></div>
                            <div class="col-sm-3 col-6"><label class="form-label" for="f-qty">Qty</label><input id="f-qty" name="quantity" type="number" min="1" class="form-control" value="<?= e($old['quantity']) ?>"></div>
                            <div class="col-12"><label class="form-label" for="f-msg">Requirements</label><textarea id="f-msg" name="message" class="form-control" rows="3" maxlength="5000" placeholder="Preferred brand, configuration, delivery location…"><?= e($old['message']) ?></textarea></div>
                            <div class="col-12"><button class="btn btn-primary w-100 btn-lg vh-btn">Send enquiry</button></div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>

<?php if ($related): ?>
<section class="vh-section vh-section-alt">
    <div class="container">
        <h2 class="vh-h2 mb-4">Related products</h2>
        <div class="row g-4">
            <?php foreach ($related as $p): ?>
                <div class="col-sm-6 col-lg-3"><?php include __DIR__ . '/partials/product-card.php'; ?></div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>
<?php require __DIR__ . '/partials/site-footer.php';
