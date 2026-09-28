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
