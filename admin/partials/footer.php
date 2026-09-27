<?php
/** Dashboard layout footer. Optional $extraJs (array of asset paths) and $inlineJs (string). */
?>
        </div>
    </div>
    <footer class="footer">
        <p class="fs-11 text-muted fw-medium text-uppercase mb-0 copyright">
            <span>Copyright ©</span> <?= date('Y') ?> <?= e(setting('company_name', 'Vivora Healthcare')) ?>
        </p>
        <p class="fs-11 text-muted fw-medium mb-0"><?= e(setting('tagline', 'Better Equipment. Healthier Tomorrows.')) ?></p>
    </footer>
</main>

<script src="<?= asset('vendors/js/vendors.min.js') ?>"></script>
<?php foreach ($extraJs ?? [] as $js): ?>
<script src="<?= asset($js) ?>"></script>
<?php endforeach; ?>
<script src="<?= asset('js/common-init.min.js') ?>"></script>
<script src="<?= asset('js/theme-customizer-init.min.js') ?>"></script>
<script>
    // Confirm destructive actions: <form data-confirm="Delete this?">
    document.addEventListener('submit', function (e) {
        var msg = e.target.getAttribute('data-confirm');
        if (msg && !window.confirm(msg)) { e.preventDefault(); }
    });
</script>
<?php if (!empty($inlineJs)): ?>
<script><?= $inlineJs ?></script>
<?php endif; ?>
</body>
</html>
