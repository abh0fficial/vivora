<?php
/** Public site header. Expects $meta (from page_meta()) and $activePage. */
$meta = $meta ?? page_meta();
$activePage = $activePage ?? '';
$company = setting('company_name', 'Vivora Healthcare');
$phone = setting('phone');
$email = setting('email');
$waLink = whatsapp_link('Hello ' . $company . ', I would like to enquire about your products.');
$navCats = site_categories();
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$canonical = $scheme . '://' . preg_replace('/[^a-z0-9.:-]/i', '', $_SERVER['HTTP_HOST'] ?? '') . strtok($_SERVER['REQUEST_URI'] ?? '/', '#');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($meta['title']) ?></title>
    <meta name="description" content="<?= e($meta['description']) ?>">
    <meta name="theme-color" content="#0b4f8a">
    <link rel="canonical" href="<?= e($canonical) ?>">
    <meta property="og:type" content="website">
    <meta property="og:title" content="<?= e($meta['title']) ?>">
    <meta property="og:description" content="<?= e($meta['description']) ?>">
    <meta property="og:image" content="<?= e($meta['image'] ?? asset('images/logo-full.png')) ?>">
    <link rel="icon" type="image/png" href="<?= asset('images/favicon.png') ?>">
    <link rel="apple-touch-icon" href="<?= asset('images/apple-touch-icon.png') ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= asset('css/bootstrap.min.css') ?>">
    <link rel="stylesheet" href="<?= asset('vendors/css/vendors.min.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/site.css') ?>?v=<?= APP_VERSION ?>">
</head>
<body>
<a class="visually-hidden-focusable vh-skip" href="#main">Skip to content</a>

<?php if ($phone || $email || setting('business_hours')): ?>
<div class="vh-topbar d-none d-md-block">
    <div class="container d-flex justify-content-between align-items-center">
        <div class="d-flex gap-4">
            <?php if ($phone): ?><a href="<?= e(tel_link($phone)) ?>"><i class="feather-phone me-1"></i><?= e($phone) ?></a><?php endif; ?>
            <?php if ($email): ?><a href="mailto:<?= e($email) ?>"><i class="feather-mail me-1"></i><?= e($email) ?></a><?php endif; ?>
        </div>
        <div class="d-flex gap-4 align-items-center">
            <?php if (setting('business_hours')): ?><span><i class="feather-clock me-1"></i><?= e(setting('business_hours')) ?></span><?php endif; ?>
            <a href="<?= base_url('service.php') ?>"><i class="feather-tool me-1"></i>Book a service</a>
        </div>
    </div>
</div>
<?php endif; ?>

<header class="vh-header sticky-top">
    <nav class="navbar navbar-expand-lg">
        <div class="container">
            <a class="navbar-brand" href="<?= base_url('index.php') ?>">
                <img src="<?= asset('images/logo-nav.png') ?>" alt="<?= e($company) ?>" height="48">
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-controls="mainNav" aria-expanded="false" aria-label="Toggle navigation">
                <i class="feather-menu"></i>
            </button>
            <div class="collapse navbar-collapse" id="mainNav">
                <ul class="navbar-nav mx-auto">
                    <li class="nav-item"><a class="nav-link<?= $activePage === 'home' ? ' active' : '' ?>" href="<?= base_url('index.php') ?>">Home</a></li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle<?= $activePage === 'products' ? ' active' : '' ?>" href="<?= base_url('products.php') ?>" role="button" data-bs-toggle="dropdown" aria-expanded="false">Products</a>
                        <div class="dropdown-menu vh-mega">
                            <div class="row g-1">
                                <?php foreach ($navCats as $c): ?>
                                    <div class="col-lg-6">
                                        <a class="dropdown-item" href="<?= base_url('products.php?category=' . rawurlencode($c['slug'])) ?>">
                                            <span class="vh-mega-icon"><i class="feather-<?= e(category_icon($c['icon'])) ?>"></i></span><?= e($c['name']) ?>
                                        </a>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="border-top mt-2 pt-2"><a class="dropdown-item fw-semibold text-primary" href="<?= base_url('products.php') ?>">View all products <i class="feather-arrow-right ms-1"></i></a></div>
                        </div>
                    </li>
                    <li class="nav-item"><a class="nav-link<?= $activePage === 'service' ? ' active' : '' ?>" href="<?= base_url('service.php') ?>">Service &amp; Support</a></li>
                    <li class="nav-item"><a class="nav-link<?= $activePage === 'about' ? ' active' : '' ?>" href="<?= base_url('about.php') ?>">About Us</a></li>
                    <li class="nav-item"><a class="nav-link<?= $activePage === 'contact' ? ' active' : '' ?>" href="<?= base_url('contact.php') ?>">Contact</a></li>
                </ul>
                <form class="vh-nav-search d-lg-none d-xl-flex my-3 my-lg-0 me-lg-3" action="<?= base_url('products.php') ?>" method="get" role="search">
                    <input class="form-control" type="search" name="q" placeholder="Search equipment…" aria-label="Search products" value="<?= e(get('q')) ?>">
                    <button type="submit" aria-label="Search"><i class="feather-search"></i></button>
                </form>
                <a href="<?= base_url('contact.php?type=quote') ?>" class="btn btn-primary vh-btn">Request a Quote</a>
            </div>
        </div>
    </nav>
</header>
<main id="main">
<?php $flashHtml = render_flashes(); if ($flashHtml): ?>
    <div class="container pt-4"><?= $flashHtml ?></div>
<?php endif; ?>
