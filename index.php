<?php
require __DIR__ . '/includes/public.php';

$featured = q_all('SELECT p.*, c.name category_name, c.icon, c.slug category_slug FROM products p
    LEFT JOIN categories c ON c.id = p.category_id
    WHERE p.is_active = 1 ORDER BY p.is_featured DESC, p.updated_at DESC LIMIT 8');
$categories = site_categories();
$productCount = (int) q_val('SELECT COUNT(*) FROM products WHERE is_active = 1');

$meta = page_meta();
$activePage = 'home';
require __DIR__ . '/partials/site-header.php';
?>
<section class="vh-hero">
    <div class="container">
        <div class="row align-items-center g-5">
            <div class="col-lg-6 vh-reveal">
                <span class="vh-eyebrow"><i class="feather-plus-circle me-2"></i><?= e(setting('tagline', 'Better Equipment. Healthier Tomorrows.')) ?></span>
                <h1 class="vh-hero-title"><?= e(setting('hero_title', 'Trusted Medical Equipment for Better Patient Care')) ?></h1>
                <p class="vh-hero-lead"><?= e(setting('hero_subtitle')) ?></p>
                <div class="d-flex flex-wrap gap-3 mt-4">
                    <a href="<?= base_url('products.php') ?>" class="btn btn-primary btn-lg vh-btn">Explore Products <i class="feather-arrow-right ms-2"></i></a>
                    <a href="<?= base_url('contact.php?type=quote') ?>" class="btn btn-outline-primary btn-lg vh-btn">Request a Quote</a>
                </div>
                <div class="vh-hero-points">
                    <span><i class="feather-check-circle"></i>Quality-assured equipment</span>
                    <span><i class="feather-check-circle"></i>Installation &amp; training</span>
                    <span><i class="feather-check-circle"></i>After-sales service</span>
                </div>
            </div>
            <div class="col-lg-6 vh-reveal">
                <div class="vh-hero-card">
                    <div class="vh-monitor">
                        <div class="vh-monitor-top">
                            <span class="vh-dot"></span><span class="vh-dot"></span><span class="vh-dot"></span>
                            <span class="ms-auto vh-monitor-label">ECG · Lead II</span>
                        </div>
                        <svg class="vh-ecg" viewBox="0 0 600 160" preserveAspectRatio="none" aria-hidden="true">
                            <defs><pattern id="grid" width="20" height="20" patternUnits="userSpaceOnUse"><path d="M20 0H0V20" fill="none" stroke="rgba(19,150,147,.18)" stroke-width="1"/></pattern></defs>
                            <rect width="600" height="160" fill="url(#grid)"/>
                            <path class="vh-ecg-line" d="M0 90 H70 l10 -8 l10 8 H120 l8 12 l12 -80 l12 96 l8 -20 H200 l14 -14 l14 14 H300 l10 -8 l10 8 H350 l8 12 l12 -80 l12 96 l8 -20 H430 l14 -14 l14 14 H530 l10 -8 l10 8 H600"/>
                        </svg>
                        <div class="vh-vitals">
                            <div><small>HR</small><strong>72</strong><em>bpm</em></div>
                            <div><small>SpO₂</small><strong>98</strong><em>%</em></div>
                            <div><small>NIBP</small><strong>120/80</strong><em>mmHg</em></div>
                        </div>
                    </div>
                    <div class="vh-hero-badge vh-hero-badge-1"><i class="feather-activity"></i><div><strong>ECG &amp; Monitoring</strong><span>Diagnostics you can rely on</span></div></div>
                    <div class="vh-hero-badge vh-hero-badge-2"><i class="feather-shield"></i><div><strong>Service &amp; AMC</strong><span>Support after the sale</span></div></div>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="vh-strip">
    <div class="container">
        <div class="row g-4 text-center">
            <div class="col-6 col-md-3"><strong><?= count($categories) ?>+</strong><span>Product categories</span></div>
            <div class="col-6 col-md-3"><strong><?= $productCount ?>+</strong><span>Products listed</span></div>
            <div class="col-6 col-md-3"><strong>B2B</strong><span>Hospitals, clinics &amp; labs</span></div>
            <div class="col-6 col-md-3"><strong>End-to-end</strong><span>Supply, install &amp; service</span></div>
        </div>
    </div>
</section>

<section class="vh-section">
    <div class="container">
        <div class="vh-section-head vh-reveal">
            <span class="vh-eyebrow">What we supply</span>
            <h2>Shop by category</h2>
            <p>From cardiology and critical care to surgical instruments and everyday consumables.</p>
        </div>
        <div class="row g-4">
            <?php foreach ($categories as $c): ?>
                <div class="col-6 col-md-4 col-xl-3 vh-reveal">
                    <a href="<?= base_url('products.php?category=' . rawurlencode($c['slug'])) ?>" class="vh-cat-card">
                        <span class="vh-cat-icon"><i class="feather-<?= e(category_icon($c['icon'])) ?>"></i></span>
                        <h3><?= e($c['name']) ?></h3>
                        <p><?= e(excerpt($c['description'], 80)) ?></p>
                        <span class="vh-cat-more"><?= (int) $c['product_count'] ?> product<?= (int) $c['product_count'] === 1 ? '' : 's' ?> <i class="feather-arrow-right"></i></span>
                    </a>
                </div>
            <?php endforeach; ?>
            <div class="col-6 col-md-4 col-xl-3 vh-reveal">
                <a href="<?= base_url('contact.php?type=quote') ?>" class="vh-cat-card vh-cat-card-cta">
                    <span class="vh-cat-icon"><i class="feather-plus"></i></span>
                    <h3>And much more</h3>
                    <p>Can't find what you need? We source a wide range of other medical products.</p>
                    <span class="vh-cat-more">Ask us <i class="feather-arrow-right"></i></span>
                </a>
            </div>
        </div>
    </div>
</section>

<?php if ($featured): ?>
<section class="vh-section vh-section-alt">
    <div class="container">
        <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-5 vh-reveal">
            <div class="vh-section-head text-start mb-0">
                <span class="vh-eyebrow">Featured</span>
                <h2>Popular equipment</h2>
            </div>
            <a href="<?= base_url('products.php') ?>" class="btn btn-outline-primary vh-btn">View all products <i class="feather-arrow-right ms-1"></i></a>
        </div>
        <div class="row g-4">
            <?php foreach ($featured as $p): ?>
                <div class="col-sm-6 col-lg-3 vh-reveal"><?php include __DIR__ . '/partials/product-card.php'; ?></div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<section class="vh-section">
    <div class="container">
        <div class="row g-5 align-items-center">
            <div class="col-lg-5 vh-reveal">
                <span class="vh-eyebrow">Why Vivora</span>
                <h2 class="vh-h2">A partner for the full life of your equipment</h2>
                <p class="text-muted">We don't just deliver boxes. We help you choose the right equipment, set it up, train your staff and keep it running.</p>
                <a href="<?= base_url('about.php') ?>" class="btn btn-primary vh-btn mt-2">About us</a>
            </div>
            <div class="col-lg-7">
                <div class="row g-4">
                    <?php foreach ([
                        ['award', 'Quality-assured products', 'Equipment and consumables sourced from reliable manufacturers.'],
                        ['users', 'Expert guidance', 'Help choosing the right configuration for your facility and budget.'],
                        ['truck', 'Delivery & installation', 'Safe delivery, installation and on-site demonstration.'],
                        ['tool', 'Service, AMC & calibration', 'Repairs, preventive maintenance and calibration support.'],
                    ] as [$icon, $title, $text]): ?>
                        <div class="col-sm-6 vh-reveal">
                            <div class="vh-feature">
                                <span class="vh-feature-icon"><i class="feather-<?= $icon ?>"></i></span>
                                <h3><?= e($title) ?></h3>
                                <p><?= e($text) ?></p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="vh-section vh-section-alt">
    <div class="container">
        <div class="vh-section-head vh-reveal">
            <span class="vh-eyebrow">Who we serve</span>
            <h2>Built for healthcare providers</h2>
        </div>
        <div class="row g-4 text-center">
            <?php foreach ([['home', 'Hospitals'], ['plus-square', 'Clinics & OPDs'], ['activity', 'Diagnostic Centres'], ['heart', 'Nursing Homes'], ['briefcase', 'Doctors'], ['truck', 'Dealers & Distributors']] as [$icon, $label]): ?>
                <div class="col-6 col-md-4 col-lg-2 vh-reveal">
                    <div class="vh-serve"><i class="feather-<?= $icon ?>"></i><span><?= $label ?></span></div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="vh-section">
    <div class="container">
        <div class="vh-service-banner vh-reveal">
            <div class="row align-items-center g-4">
                <div class="col-lg-8">
                    <h2 class="h3 fw-bold mb-2">Need installation, repair or AMC for your equipment?</h2>
                    <p class="mb-0">Raise a service request online and our team will get in touch to schedule a visit.</p>
                </div>
                <div class="col-lg-4 text-lg-end">
                    <a href="<?= base_url('service.php') ?>" class="btn btn-primary btn-lg vh-btn"><i class="feather-tool me-2"></i>Book a Service</a>
                </div>
            </div>
        </div>
    </div>
</section>
<?php require __DIR__ . '/partials/site-footer.php';
