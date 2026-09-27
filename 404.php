<?php
require __DIR__ . '/includes/public.php';
http_response_code(404);
$meta = page_meta('Page not found');
require __DIR__ . '/partials/site-header.php';
?>
<section class="vh-section">
    <div class="container">
        <div class="vh-empty-state">
            <i class="feather-compass"></i>
            <h3>Page not found</h3>
            <p>The page you're looking for doesn't exist or has moved.</p>
            <div class="d-flex justify-content-center gap-2">
                <a href="<?= base_url('index.php') ?>" class="btn btn-primary vh-btn">Go to home</a>
                <a href="<?= base_url('products.php') ?>" class="btn btn-outline-primary vh-btn">Browse products</a>
            </div>
        </div>
    </div>
</section>
<?php require __DIR__ . '/partials/site-footer.php';
