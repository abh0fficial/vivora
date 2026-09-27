<?php
require __DIR__ . '/includes/public.php';

$company = setting('company_name', 'Vivora Healthcare');
$paragraphs = array_filter(array_map('trim', preg_split('/\R{2,}/', setting('about_text'))));
$categories = site_categories();

$meta = page_meta('About Us', excerpt(setting('about_text'), 155));
$activePage = 'about';
require __DIR__ . '/partials/site-header.php';
?>
<section class="vh-page-hero">
    <div class="container">
        <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="<?= base_url('index.php') ?>">Home</a></li><li class="breadcrumb-item active">About Us</li></ol></nav>
        <h1>About <?= e($company) ?></h1>
        <p><?= e(setting('tagline', 'Better Equipment. Healthier Tomorrows.')) ?></p>
    </div>
</section>

<section class="vh-section">
    <div class="container">
        <div class="row g-5 align-items-center">
            <div class="col-lg-6">
                <span class="vh-eyebrow">Who we are</span>
                <h2 class="vh-h2">Helping healthcare providers get the right equipment</h2>
                <div class="vh-prose">
                    <?php foreach ($paragraphs as $para): ?><p><?= nl2br(e($para)) ?></p><?php endforeach; ?>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="vh-about-visual">
                    <img src="<?= asset('images/logo-full.png') ?>" alt="<?= e($company) ?>" class="img-fluid">
                </div>
            </div>
        </div>
    </div>
</section>

<section class="vh-section vh-section-alt">
    <div class="container">
        <div class="row g-4">
            <?php foreach ([
                ['target', 'Our mission', 'Make dependable medical equipment accessible to every hospital, clinic and diagnostic centre we serve.'],
                ['eye', 'Our vision', 'Better equipment for healthier tomorrows — for patients and the professionals who care for them.'],
                ['heart', 'Our promise', 'Honest advice, genuine products and support that continues long after delivery.'],
            ] as [$icon, $title, $text]): ?>
                <div class="col-md-4"><div class="vh-feature h-100"><span class="vh-feature-icon"><i class="feather-<?= $icon ?>"></i></span><h3><?= $title ?></h3><p><?= $text ?></p></div></div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="vh-section">
    <div class="container">
        <div class="vh-section-head">
            <span class="vh-eyebrow">Our range</span>
            <h2>Products we deal in</h2>
        </div>
        <div class="d-flex flex-wrap justify-content-center gap-2">
            <?php foreach ($categories as $c): ?>
                <a href="<?= base_url('products.php?category=' . rawurlencode($c['slug'])) ?>" class="vh-chip"><i class="feather-<?= e(category_icon($c['icon'])) ?>"></i><?= e($c['name']) ?></a>
            <?php endforeach; ?>
            <span class="vh-chip vh-chip-muted"><i class="feather-plus"></i>and many more</span>
        </div>
    </div>
</section>
<?php require __DIR__ . '/partials/site-footer.php';
