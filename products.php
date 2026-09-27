<?php
require __DIR__ . '/includes/public.php';

$categories = site_categories();
$catSlug = get('category');
$search = get('q');
$sort = get('sort', 'featured');
$current = null;
foreach ($categories as $c) {
    if ($c['slug'] === $catSlug) $current = $c;
}

$where = ['p.is_active = 1', '(c.id IS NULL OR c.is_active = 1)'];
$params = [];
if ($current) { $where[] = 'p.category_id = ?'; $params[] = $current['id']; }
if ($search !== '') {
    $where[] = '(p.name LIKE ? OR p.short_description LIKE ? OR p.brand LIKE ? OR p.model LIKE ? OR c.name LIKE ?)';
    array_push($params, ...array_fill(0, 5, '%' . $search . '%'));
}
$order = ['featured' => 'p.is_featured DESC, p.name', 'name' => 'p.name', 'newest' => 'p.created_at DESC', 'popular' => 'p.views DESC'][$sort] ?? 'p.is_featured DESC, p.name';

$perPage = 12;
$total = (int) q_val('SELECT COUNT(*) FROM products p LEFT JOIN categories c ON c.id = p.category_id WHERE ' . implode(' AND ', $where), $params);
$pages = max(1, (int) ceil($total / $perPage));
$page = min($pages, max(1, (int) get('page', '1')));
$products = q_all('SELECT p.*, c.name category_name, c.icon FROM products p LEFT JOIN categories c ON c.id = p.category_id
    WHERE ' . implode(' AND ', $where) . " ORDER BY $order LIMIT $perPage OFFSET " . (($page - 1) * $perPage), $params);

$qs = function (array $over) use ($catSlug, $search, $sort) {
    return base_url('products.php') . '?' . http_build_query(array_filter(array_merge(['category' => $catSlug, 'q' => $search, 'sort' => $sort !== 'featured' ? $sort : ''], $over), fn($v) => $v !== '' && $v !== null));
};

$title = $current ? $current['name'] : ($search !== '' ? 'Search: ' . $search : 'All Products');
$meta = page_meta($title, $current ? excerpt($current['description'], 155) : '');
$activePage = 'products';
require __DIR__ . '/partials/site-header.php';
?>
<section class="vh-page-hero">
    <div class="container">
        <nav aria-label="breadcrumb"><ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?= base_url('index.php') ?>">Home</a></li>
            <li class="breadcrumb-item<?= $current ? '' : ' active' ?>"><?= $current ? '<a href="' . base_url('products.php') . '">Products</a>' : 'Products' ?></li>
            <?php if ($current): ?><li class="breadcrumb-item active"><?= e($current['name']) ?></li><?php endif; ?>
        </ol></nav>
        <h1><?= e($title) ?></h1>
        <p><?= $current ? e($current['description']) : 'Medical equipment, diagnostic devices, surgical instruments and consumables for healthcare providers.' ?></p>
    </div>
</section>

<section class="vh-section pt-5">
    <div class="container">
        <div class="row g-4">
            <aside class="col-lg-3">
                <div class="vh-sidebar">
                    <form action="<?= base_url('products.php') ?>" method="get" class="vh-side-search mb-4">
                        <?php if ($current): ?><input type="hidden" name="category" value="<?= e($current['slug']) ?>"><?php endif; ?>
                        <input type="search" name="q" class="form-control" placeholder="Search products…" value="<?= e($search) ?>" aria-label="Search products">
                        <button aria-label="Search"><i class="feather-search"></i></button>
                    </form>
                    <h2 class="vh-side-title">Categories</h2>
                    <ul class="vh-side-cats list-unstyled">
                        <li><a href="<?= base_url('products.php') . ($search !== '' ? '?q=' . rawurlencode($search) : '') ?>" class="<?= !$current ? 'active' : '' ?>"><i class="feather-grid"></i>All products</a></li>
                        <?php foreach ($categories as $c): ?>
                            <li><a href="<?= e($qs(['category' => $c['slug'], 'page' => ''])) ?>" class="<?= $current && $current['id'] === $c['id'] ? 'active' : '' ?>">
                                <i class="feather-<?= e(category_icon($c['icon'])) ?>"></i><?= e($c['name']) ?><span><?= (int) $c['product_count'] ?></span></a></li>
                        <?php endforeach; ?>
                    </ul>
                    <div class="vh-side-help">
                        <i class="feather-headphones"></i>
                        <h3>Need help choosing?</h3>
                        <p>Our team will recommend the right equipment for your needs.</p>
                        <a href="<?= base_url('contact.php?type=quote') ?>" class="btn btn-primary btn-sm vh-btn">Talk to us</a>
                    </div>
                </div>
            </aside>
            <div class="col-lg-9">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
                    <span class="text-muted"><?= $total ?> product<?= $total === 1 ? '' : 's' ?><?= $search !== '' ? ' for “' . e($search) . '”' : '' ?></span>
                    <form method="get" class="d-flex align-items-center gap-2">
                        <?php if ($catSlug): ?><input type="hidden" name="category" value="<?= e($catSlug) ?>"><?php endif; ?>
                        <?php if ($search !== ''): ?><input type="hidden" name="q" value="<?= e($search) ?>"><?php endif; ?>
                        <label for="sort" class="text-muted small text-nowrap">Sort by</label>
                        <select id="sort" name="sort" class="form-select form-select-sm" onchange="this.form.submit()">
                            <?php foreach (['featured' => 'Featured', 'name' => 'Name (A–Z)', 'newest' => 'Newest', 'popular' => 'Most viewed'] as $k => $v): ?>
                                <option value="<?= $k ?>" <?= $sort === $k ? 'selected' : '' ?>><?= $v ?></option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                </div>
                <?php if (!$products): ?>
                    <div class="vh-empty-state">
                        <i class="feather-search"></i>
                        <h3>No products found</h3>
                        <p>We may still be able to supply it — send us your requirement.</p>
                        <a href="<?= base_url('contact.php?type=quote' . ($search !== '' ? '&subject=' . rawurlencode($search) : '')) ?>" class="btn btn-primary vh-btn">Request this product</a>
                    </div>
                <?php else: ?>
                    <div class="row g-4">
                        <?php foreach ($products as $p): ?>
                            <div class="col-sm-6 col-xl-4"><?php include __DIR__ . '/partials/product-card.php'; ?></div>
                        <?php endforeach; ?>
                    </div>
                    <?php if ($pages > 1): ?>
                        <nav class="mt-5" aria-label="Product pages"><ul class="pagination justify-content-center">
                            <?php for ($i = 1; $i <= $pages; $i++): ?>
                                <li class="page-item<?= $i === $page ? ' active' : '' ?>"><a class="page-link" href="<?= e($qs(['page' => $i > 1 ? $i : ''])) ?>"><?= $i ?></a></li>
                            <?php endfor; ?>
                        </ul></nav>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>
<?php require __DIR__ . '/partials/site-footer.php';
