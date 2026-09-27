<?php /** Product card. Expects $p with product + category columns (category_name, icon). */ ?>
<article class="vh-product-card">
    <a href="<?= base_url('product.php?slug=' . rawurlencode($p['slug'])) ?>" class="vh-product-media">
        <?= product_visual($p) ?>
        <?php if (!empty($p['is_featured'])): ?><span class="vh-tag">Featured</span><?php endif; ?>
    </a>
    <div class="vh-product-body">
        <span class="vh-product-cat"><?= e($p['category_name'] ?? '') ?></span>
        <h3><a href="<?= base_url('product.php?slug=' . rawurlencode($p['slug'])) ?>"><?= e($p['name']) ?></a></h3>
        <p><?= e(excerpt($p['short_description'] ?: $p['description'], 90)) ?></p>
        <div class="vh-product-foot">
            <span class="vh-price"><?= product_price_label($p) ?></span>
            <a href="<?= base_url('product.php?slug=' . rawurlencode($p['slug'])) ?>#enquire" class="vh-link">Enquire <i class="feather-arrow-right"></i></a>
        </div>
    </div>
</article>
