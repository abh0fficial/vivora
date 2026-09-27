<?php
require __DIR__ . '/../includes/auth.php';
$admin = require_admin();

$from = preg_match('/^\d{4}-\d{2}-\d{2}$/', get('from')) ? get('from') : date('Y-m-01', strtotime('-5 months'));
$to = preg_match('/^\d{4}-\d{2}-\d{2}$/', get('to')) ? get('to') : date('Y-m-d');
$range = [$from . ' 00:00:00', $to . ' 23:59:59'];

$enqByStatus = q_all('SELECT status, COUNT(*) c FROM enquiries WHERE created_at BETWEEN ? AND ? GROUP BY status', $range);
$enqBySource = q_all('SELECT source, COUNT(*) c FROM enquiries WHERE created_at BETWEEN ? AND ? GROUP BY source', $range);
$enqByCategory = q_all("SELECT COALESCE(c.name,'General enquiry') name, COUNT(*) c, SUM(e.status='won') won FROM enquiries e
    LEFT JOIN products p ON p.id = e.product_id LEFT JOIN categories c ON c.id = p.category_id
    WHERE e.created_at BETWEEN ? AND ? GROUP BY c.name ORDER BY c DESC", $range);
$topProducts = q_all('SELECT p.name, COUNT(*) c FROM enquiries e JOIN products p ON p.id = e.product_id WHERE e.created_at BETWEEN ? AND ? GROUP BY p.id ORDER BY c DESC LIMIT 10', $range);
$quoteByStatus = q_all('SELECT status, COUNT(*) c, SUM(grand_total) s FROM quotations WHERE quote_date BETWEEN ? AND ? GROUP BY status', [$from, $to]);
$salesByMonth = q_all("SELECT DATE_FORMAT(quote_date,'%b %Y') m, DATE_FORMAT(quote_date,'%Y-%m') k, SUM(grand_total) s, COUNT(*) c FROM quotations
    WHERE status='accepted' AND quote_date BETWEEN ? AND ? GROUP BY k, m ORDER BY k", [$from, $to]);
$topCustomers = q_all("SELECT COALESCE(NULLIF(customer_org,''), customer_name) name, COUNT(*) c, SUM(grand_total) s FROM quotations
    WHERE status='accepted' AND quote_date BETWEEN ? AND ? GROUP BY name ORDER BY s DESC LIMIT 10", [$from, $to]);
$svcByType = q_all('SELECT request_type, COUNT(*) c, SUM(status IN (\'resolved\',\'closed\')) done FROM service_requests WHERE created_at BETWEEN ? AND ? GROUP BY request_type ORDER BY c DESC', $range);

$sum = fn(array $rows, string $k) => array_sum(array_map(fn($r) => (float) $r[$k], $rows));
$types = service_types();

$pageTitle = 'Reports';
$activeNav = 'reports';
$breadcrumbs = ['Reports' => null];
$pageActions = '<form class="d-flex gap-2 align-items-center" method="get">'
    . '<input type="date" name="from" class="form-control form-control-sm" value="' . e($from) . '">'
    . '<span class="text-muted">to</span><input type="date" name="to" class="form-control form-control-sm" value="' . e($to) . '">'
    . '<button class="btn btn-sm btn-primary">Apply</button></form>';
$extraJs = ['vendors/js/apexcharts.min.js'];
require __DIR__ . '/partials/header.php';
?>
<div class="row">
    <?php foreach ([
        ['Enquiries', (int) $sum($enqByStatus, 'c'), 'inbox', 'primary'],
        ['Quotations', (int) $sum($quoteByStatus, 'c'), 'file-text', 'info'],
        ['Sales (accepted)', money($sum($salesByMonth, 's')), 'trending-up', 'success'],
        ['Service tickets', (int) $sum($svcByType, 'c'), 'tool', 'danger'],
    ] as [$label, $val, $icon, $color]): ?>
        <div class="col-xxl-3 col-md-6">
            <div class="card stretch stretch-full"><div class="card-body d-flex align-items-center gap-3">
                <span class="avatar-text avatar-lg bg-soft-<?= $color ?> text-<?= $color ?>"><i class="feather-<?= $icon ?>"></i></span>
                <div><div class="fs-5 fw-bold text-dark"><?= e((string) $val) ?></div><div class="fs-12 text-muted"><?= $label ?> · <?= e(fmt_date($from)) ?> – <?= e(fmt_date($to)) ?></div></div>
            </div></div>
        </div>
    <?php endforeach; ?>

    <div class="col-xxl-8">
        <div class="card stretch stretch-full">
            <div class="card-header"><h5 class="card-title">Sales by month (accepted quotations)</h5></div>
            <div class="card-body"><?php if (!$salesByMonth): ?><div class="vh-empty"><i class="feather-bar-chart-2"></i>No accepted quotations in this period.</div><?php endif; ?><div id="salesChart"></div></div>
        </div>
    </div>
    <div class="col-xxl-4">
        <div class="card stretch stretch-full">
            <div class="card-header"><h5 class="card-title">Top customers</h5></div>
            <div class="card-body">
                <?php if (!$topCustomers): ?><p class="text-muted fs-12">No sales in this period.</p><?php endif; ?>
                <?php foreach ($topCustomers as $c): ?>
                    <div class="d-flex justify-content-between mb-3"><span class="text-dark fw-semibold text-truncate-1-line me-2"><?= e($c['name']) ?></span><span class="text-nowrap"><?= money($c['s']) ?> <span class="fs-11 text-muted">(<?= (int) $c['c'] ?>)</span></span></div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div class="col-xxl-6">
        <div class="card stretch stretch-full">
            <div class="card-header"><h5 class="card-title">Enquiries by product category</h5></div>
            <div class="card-body p-0">
                <table class="table mb-0">
                    <thead><tr><th>Category</th><th class="text-end">Enquiries</th><th class="text-end">Won</th><th class="text-end">Conversion</th></tr></thead>
                    <?php foreach ($enqByCategory as $r): ?>
                        <tr><td><?= e($r['name']) ?></td><td class="text-end"><?= (int) $r['c'] ?></td><td class="text-end"><?= (int) $r['won'] ?></td><td class="text-end"><?= $r['c'] ? round($r['won'] / $r['c'] * 100) : 0 ?>%</td></tr>
                    <?php endforeach; ?>
                    <?php if (!$enqByCategory): ?><tr><td colspan="4" class="text-muted text-center py-4">No enquiries in this period.</td></tr><?php endif; ?>
                </table>
            </div>
        </div>
    </div>
    <div class="col-xxl-6">
        <div class="card stretch stretch-full">
            <div class="card-header"><h5 class="card-title">Most enquired products</h5></div>
            <div class="card-body p-0">
                <table class="table mb-0">
                    <?php foreach ($topProducts as $i => $r): ?>
                        <tr><td style="width:40px" class="text-muted"><?= $i + 1 ?></td><td><?= e($r['name']) ?></td><td class="text-end fw-semibold"><?= (int) $r['c'] ?></td></tr>
                    <?php endforeach; ?>
                    <?php if (!$topProducts): ?><tr><td class="text-muted text-center py-4">No product enquiries in this period.</td></tr><?php endif; ?>
                </table>
            </div>
        </div>
    </div>

    <div class="col-xxl-4 col-md-6">
        <div class="card stretch stretch-full">
            <div class="card-header"><h5 class="card-title">Enquiry status</h5></div>
            <div class="card-body">
                <?php foreach ($enqByStatus as $r): ?><div class="d-flex justify-content-between mb-2"><?= status_badge($r['status']) ?><strong><?= (int) $r['c'] ?></strong></div><?php endforeach; ?>
                <hr>
                <div class="fs-12 text-muted mb-2">By source</div>
                <?php foreach ($enqBySource as $r): ?><div class="d-flex justify-content-between mb-2"><span><?= e(ucfirst($r['source'])) ?> form</span><strong><?= (int) $r['c'] ?></strong></div><?php endforeach; ?>
            </div>
        </div>
    </div>
    <div class="col-xxl-4 col-md-6">
        <div class="card stretch stretch-full">
            <div class="card-header"><h5 class="card-title">Quotations</h5></div>
            <div class="card-body">
                <?php foreach ($quoteByStatus as $r): ?><div class="d-flex justify-content-between mb-2"><span><?= status_badge($r['status']) ?> <span class="fs-12 text-muted ms-1"><?= (int) $r['c'] ?></span></span><strong><?= money($r['s']) ?></strong></div><?php endforeach; ?>
                <?php if (!$quoteByStatus): ?><p class="text-muted fs-12">No quotations in this period.</p><?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-xxl-4">
        <div class="card stretch stretch-full">
            <div class="card-header"><h5 class="card-title">Service by type</h5></div>
            <div class="card-body">
                <?php foreach ($svcByType as $r): ?>
                    <div class="mb-3">
                        <div class="d-flex justify-content-between mb-1"><span><?= e($types[$r['request_type']] ?? $r['request_type']) ?></span><span class="fs-12"><?= (int) $r['done'] ?>/<?= (int) $r['c'] ?> resolved</span></div>
                        <div class="progress ht-3"><div class="progress-bar bg-success" style="width: <?= $r['c'] ? round($r['done'] / $r['c'] * 100) : 0 ?>%"></div></div>
                    </div>
                <?php endforeach; ?>
                <?php if (!$svcByType): ?><p class="text-muted fs-12">No service tickets in this period.</p><?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php
if ($salesByMonth) {
    $inlineJs = 'new ApexCharts(document.querySelector("#salesChart"), { chart: { type: "bar", height: 320, toolbar: { show: false }, fontFamily: "inherit" },'
        . ' series: [{ name: "Sales", data: ' . json_encode(array_map(fn($r) => round((float) $r['s'], 2), $salesByMonth)) . ' }],'
        . ' xaxis: { categories: ' . json_encode(array_column($salesByMonth, 'm')) . ' }, colors: ["#0b4f8a"],'
        . ' plotOptions: { bar: { borderRadius: 4, columnWidth: "45%" } }, dataLabels: { enabled: false },'
        . ' yaxis: { labels: { formatter: function (v) { return "₹" + Math.round(v).toLocaleString("en-IN"); } } } }).render();';
}
require __DIR__ . '/partials/footer.php';
