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

<?php $nk = $activeNav ?? ''; ?>
<?php if ($nk === 'dashboard'): ?>
<div class="vh-fab-bar d-lg-none">
    <a href="payments.php?type=in" class="vh-fab vh-fab-dark">Received Payment</a>
    <div class="dropup">
        <button type="button" class="vh-fab-plus" data-bs-toggle="dropdown" aria-label="More actions"><i class="feather-plus"></i></button>
        <div class="dropdown-menu dropdown-menu-center">
            <a class="dropdown-item" href="purchase-form.php"><i class="feather-shopping-cart me-2"></i>Purchase bill</a>
            <a class="dropdown-item" href="payments.php?type=out"><i class="feather-upload me-2"></i>Payment out</a>
            <a class="dropdown-item" href="expenses.php"><i class="feather-dollar-sign me-2"></i>Expense</a>
            <a class="dropdown-item" href="quotation-form.php"><i class="feather-clipboard me-2"></i>Quotation</a>
            <a class="dropdown-item" href="enquiry-form.php"><i class="feather-inbox me-2"></i>Enquiry</a>
            <a class="dropdown-item" href="product-form.php"><i class="feather-package me-2"></i>Add item</a>
            <a class="dropdown-item" href="customer-form.php"><i class="feather-user-plus me-2"></i>Add party</a>
        </div>
    </div>
    <a href="invoice-form.php" class="vh-fab vh-fab-primary">+ Bill / Invoice</a>
</div>
<?php endif; ?>
<nav class="vh-bottom-nav d-lg-none" aria-label="Quick navigation">
    <a href="index.php" class="<?= $nk === 'dashboard' ? 'active' : '' ?>"><i class="feather-home"></i><span>Dashboard</span></a>
    <a href="parties.php" class="<?= in_array($nk, ['parties', 'customers', 'suppliers'], true) ? 'active' : '' ?>"><i class="feather-users"></i><span>Parties</span></a>
    <a href="products.php" class="<?= in_array($nk, ['products', 'product-new', 'inventory', 'categories'], true) ? 'active' : '' ?>"><i class="feather-package"></i><span>Items</span></a>
    <a href="reports-hub.php" class="<?= $nk === 'reports-hub' ? 'active' : '' ?>"><i class="feather-pie-chart"></i><span>Reports</span></a>
    <a href="javascript:void(0)" onclick="var t=document.getElementById('mobile-collapse'); if (t) t.click();"><i class="feather-more-horizontal"></i><span>More</span></a>
</nav>
<script src="<?= asset('vendors/js/vendors.min.js') ?>"></script>
<?php foreach ($extraJs ?? [] as $js): ?>
<script src="<?= asset($js) ?>"></script>
<?php endforeach; ?>
<script src="<?= asset('js/common-init.min.js') ?>"></script>
<script>
    // Refresh the notification badge every minute.
    (function () {
        var badge = document.getElementById('vhNotifBadge');
        if (!badge || !window.fetch) return;
        setInterval(function () {
            fetch('notifications.php?format=count', { credentials: 'same-origin', cache: 'no-store' })
                .then(function (r) { return r.ok ? r.json() : null; })
                .then(function (d) {
                    if (!d) return;
                    badge.textContent = d.unread > 99 ? '99+' : d.unread;
                    badge.style.display = d.unread > 0 ? '' : 'none';
                }).catch(function () {});
        }, 60000);
    })();
    // Label line-item cells so they can stack as cards on phones.
    document.querySelectorAll('.vh-quote-items').forEach(function (t) {
        var heads = Array.prototype.map.call(t.querySelectorAll('thead th'), function (th) { return th.textContent.trim(); });
        t.querySelectorAll('tbody tr').forEach(function (r) {
            r.querySelectorAll('td').forEach(function (td, i) { if (heads[i]) td.setAttribute('data-label', heads[i]); });
        });
    });
    // Payment mode → matching Cash / Bank account.
    document.addEventListener('change', function (e) {
        var n = e.target.name;
        if (n !== 'mode' && n !== 'pay_mode') return;
        var form = e.target.form, pick = form && form.querySelector('.vh-account-pick');
        if (!pick) return;
        var want = e.target.value === 'cash' ? 'cash' : 'bank';
        if (pick.selectedOptions[0] && pick.selectedOptions[0].dataset.type === want) return;
        var opt = Array.prototype.find.call(pick.options, function (o) { return o.dataset.type === want; });
        if (opt) pick.value = opt.value;
    });
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
