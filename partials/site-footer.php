<?php
$company = setting('company_name', 'Vivora Healthcare');
$footerCats = array_slice(site_categories(), 0, 11);
$waLink = whatsapp_link('Hello ' . $company . ', I would like to enquire about your products.');
$socials = array_filter([
    'facebook' => setting('facebook'), 'instagram' => setting('instagram'), 'linkedin' => setting('linkedin'), 'youtube' => setting('youtube'),
]);
?>
</main>

<section class="vh-cta-band">
    <div class="container d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-4">
        <div>
            <h2 class="h3 fw-bold mb-2 text-white">Equipping a hospital, clinic or diagnostic centre?</h2>
            <p class="mb-0 text-white-50">Tell us what you need — we'll send a detailed quotation with the best available options.</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="<?= base_url('contact.php?type=quote') ?>" class="btn btn-light btn-lg vh-btn">Get a Quote</a>
            <?php if ($waLink): ?><a href="<?= e($waLink) ?>" target="_blank" rel="noopener" class="btn btn-outline-light btn-lg vh-btn"><i class="feather-message-circle me-2"></i>WhatsApp Us</a><?php endif; ?>
        </div>
    </div>
</section>

<footer class="vh-footer">
    <div class="container">
        <div class="row g-5">
            <div class="col-lg-4">
                <img src="<?= asset('images/logo-full-white.png') ?>" alt="<?= e($company) ?>" class="mb-4" style="max-width:250px">
                <p class="text-white-50"><?= e(excerpt(setting('about_text'), 200)) ?></p>
                <?php if ($socials): ?>
                    <div class="d-flex gap-2 mt-3">
                        <?php foreach ($socials as $net => $url): ?>
                            <a href="<?= e($url) ?>" target="_blank" rel="noopener" class="vh-social" aria-label="<?= e(ucfirst($net)) ?>"><i class="feather-<?= $net === 'youtube' ? 'youtube' : $net ?>"></i></a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            <div class="col-6 col-lg-3">
                <h6 class="vh-footer-title">Products</h6>
                <ul class="list-unstyled vh-footer-links">
                    <?php foreach ($footerCats as $c): ?>
                        <li><a href="<?= base_url('products.php?category=' . rawurlencode($c['slug'])) ?>"><?= e($c['name']) ?></a></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <div class="col-6 col-lg-2">
                <h6 class="vh-footer-title">Company</h6>
                <ul class="list-unstyled vh-footer-links">
                    <li><a href="<?= base_url('about.php') ?>">About Us</a></li>
                    <li><a href="<?= base_url('products.php') ?>">All Products</a></li>
                    <li><a href="<?= base_url('service.php') ?>">Service &amp; AMC</a></li>
                    <li><a href="<?= base_url('contact.php?type=quote') ?>">Request a Quote</a></li>
                    <li><a href="<?= base_url('contact.php') ?>">Contact Us</a></li>
                </ul>
            </div>
            <div class="col-lg-3">
                <h6 class="vh-footer-title">Get in touch</h6>
                <ul class="list-unstyled vh-footer-contact">
                    <?php if (setting('address')): ?><li><i class="feather-map-pin"></i><span><?= nl2br(e(setting('address'))) ?><?= setting('city') ? '<br>' . e(setting('city')) : '' ?></span></li><?php endif; ?>
                    <?php if (setting('phone')): ?><li><i class="feather-phone"></i><a href="<?= e(tel_link(setting('phone'))) ?>"><?= e(setting('phone')) ?></a></li><?php endif; ?>
                    <?php if (setting('phone2')): ?><li><i class="feather-phone"></i><a href="<?= e(tel_link(setting('phone2'))) ?>"><?= e(setting('phone2')) ?></a></li><?php endif; ?>
                    <?php if (setting('email')): ?><li><i class="feather-mail"></i><a href="mailto:<?= e(setting('email')) ?>"><?= e(setting('email')) ?></a></li><?php endif; ?>
                    <?php if (setting('business_hours')): ?><li><i class="feather-clock"></i><span><?= e(setting('business_hours')) ?></span></li><?php endif; ?>
                    <?php if (setting('gstin')): ?><li><i class="feather-file-text"></i><span>GSTIN: <?= e(setting('gstin')) ?></span></li><?php endif; ?>
                </ul>
            </div>
        </div>
        <div class="vh-footer-bottom d-flex flex-column flex-md-row justify-content-between gap-2">
            <span>© <?= date('Y') ?> <?= e($company) ?>. All rights reserved.</span>
            <span><?= e(setting('tagline', 'Better Equipment. Healthier Tomorrows.')) ?></span>
        </div>
    </div>
</footer>

<?php if ($waLink): ?>
    <a href="<?= e($waLink) ?>" class="vh-wa-float" target="_blank" rel="noopener" aria-label="Chat on WhatsApp">
        <svg viewBox="0 0 32 32" width="28" height="28" fill="#fff" aria-hidden="true"><path d="M16.04 3C9 3 3.3 8.7 3.3 15.73c0 2.24.59 4.43 1.7 6.36L3.2 28.8l6.87-1.8a12.7 12.7 0 0 0 5.97 1.52h.01c7.03 0 12.73-5.7 12.73-12.73A12.7 12.7 0 0 0 16.04 3Zm0 23.37h-.01a10.57 10.57 0 0 1-5.39-1.48l-.39-.23-4.08 1.07 1.09-3.98-.25-.41a10.56 10.56 0 0 1-1.62-5.62c0-5.84 4.75-10.6 10.6-10.6a10.6 10.6 0 0 1 10.6 10.61c0 5.84-4.76 10.64-10.55 10.64Zm5.81-7.94c-.32-.16-1.88-.93-2.17-1.03-.29-.11-.5-.16-.71.16-.21.32-.82 1.03-1 1.24-.19.21-.37.24-.69.08-.32-.16-1.34-.49-2.55-1.57-.94-.84-1.58-1.88-1.76-2.2-.19-.32-.02-.49.14-.65.14-.14.32-.37.48-.56.16-.19.21-.32.32-.53.11-.21.05-.4-.03-.56-.08-.16-.71-1.72-.98-2.35-.26-.62-.52-.53-.71-.54h-.61c-.21 0-.56.08-.85.4-.29.32-1.11 1.09-1.11 2.65s1.14 3.08 1.3 3.29c.16.21 2.24 3.42 5.43 4.8.76.33 1.35.52 1.81.67.76.24 1.45.21 2 .13.61-.09 1.88-.77 2.14-1.51.27-.74.27-1.38.19-1.51-.08-.13-.29-.21-.61-.37Z"/></svg>
    </a>
<?php endif; ?>

<script src="<?= asset('vendors/js/vendors.min.js') ?>"></script>
<script>
    // Scroll-reveal for sections (respects reduced motion).
    (function () {
        var els = document.querySelectorAll('.vh-reveal');
        if (!('IntersectionObserver' in window) || window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            els.forEach(function (el) { el.classList.add('is-visible'); });
            return;
        }
        var io = new IntersectionObserver(function (entries) {
            entries.forEach(function (en) { if (en.isIntersecting) { en.target.classList.add('is-visible'); io.unobserve(en.target); } });
        }, { threshold: 0.12 });
        els.forEach(function (el) { io.observe(el); });
    })();
</script>
</body>
</html>
