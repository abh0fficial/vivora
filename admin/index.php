<?php
require __DIR__ . '/../includes/auth.php';
$admin = require_admin();

$stats = [
    'products'      => (int) q_val('SELECT COUNT(*) FROM products WHERE is_active = 1'),
    'products_all'  => (int) q_val('SELECT COUNT(*) FROM products'),
    'categories'    => (int) q_val('SELECT COUNT(*) FROM categories WHERE is_active = 1'),
    'enq_new'       => (int) q_val("SELECT COUNT(*) FROM enquiries WHERE status = 'new'"),
    'enq_month'     => (int) q_val("SELECT COUNT(*) FROM enquiries WHERE created_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')"),
    'enq_total'     => (int) q_val('SELECT COUNT(*) FROM enquiries'),
    'enq_won'       => (int) q_val("SELECT COUNT(*) FROM enquiries WHERE status = 'won'"),
    'svc_open'      => (int) q_val("SELECT COUNT(*) FROM service_requests WHERE status IN ('open','scheduled','in_progress')"),
    'svc_total'     => (int) q_val('SELECT COUNT(*) FROM service_requests'),
    'q_accepted_m'  => (float) q_val("SELECT COALESCE(SUM(grand_total),0) FROM quotations WHERE status = 'accepted' AND quote_date >= DATE_FORMAT(CURDATE(), '%Y-%m-01')"),
    'inv_month'     => (float) q_val("SELECT COALESCE(SUM(grand_total),0) FROM invoices WHERE status <> 'cancelled' AND invoice_date >= DATE_FORMAT(CURDATE(), '%Y-%m-01')"),
    'inv_count_m'   => (int) q_val("SELECT COUNT(*) FROM invoices WHERE status <> 'cancelled' AND invoice_date >= DATE_FORMAT(CURDATE(), '%Y-%m-01')"),
    'received_m'    => (float) q_val("SELECT COALESCE(SUM(amount),0) FROM payments WHERE direction = 'in' AND payment_date >= DATE_FORMAT(CURDATE(), '%Y-%m-01')"),
    'outstanding'   => (float) q_val("SELECT COALESCE(SUM(grand_total - amount_paid),0) FROM invoices WHERE status IN ('unpaid','partial')"),
    'overdue'       => (float) q_val("SELECT COALESCE(SUM(grand_total - amount_paid),0) FROM invoices WHERE status IN ('unpaid','partial') AND due_date < CURDATE()"),
    'overdue_n'     => (int) q_val("SELECT COUNT(*) FROM invoices WHERE status IN ('unpaid','partial') AND due_date < CURDATE()"),
    'purchases_m'   => (float) q_val("SELECT COALESCE(SUM(grand_total),0) FROM purchases WHERE bill_date >= DATE_FORMAT(CURDATE(), '%Y-%m-01')"),
    'expenses_m'    => (float) q_val("SELECT COALESCE(SUM(amount),0) FROM expenses WHERE expense_date >= DATE_FORMAT(CURDATE(), '%Y-%m-01')"),
    'payable'       => (float) q_val("SELECT COALESCE(SUM(grand_total - amount_paid),0) FROM purchases WHERE status IN ('unpaid','partial')"),
    'q_pipeline'    => (float) q_val("SELECT COALESCE(SUM(grand_total),0) FROM quotations WHERE status = 'sent'"),
    'q_count'       => (int) q_val('SELECT COUNT(*) FROM quotations'),
    'q_accepted'    => (int) q_val("SELECT COUNT(*) FROM quotations WHERE status = 'accepted'"),
    'customers'     => (int) q_val('SELECT COUNT(*) FROM customers'),
    'low_stock'     => (int) q_val('SELECT COUNT(*) FROM products WHERE is_active = 1 AND min_stock > 0 AND stock_qty <= min_stock'),
];
$pct = fn($a, $b) => $b > 0 ? (int) round($a / $b * 100) : 0;

// Last 12 months: enquiries, service requests, accepted quotation value.
$months = [];
for ($i = 11; $i >= 0; $i--) {
    $key = date('Y-m', strtotime("first day of -$i month"));
    $months[$key] = ['label' => date('M y', strtotime($key . '-01')), 'enq' => 0, 'svc' => 0, 'sales' => 0];
}
$since = array_key_first($months) . '-01';
foreach (q_all("SELECT DATE_FORMAT(created_at,'%Y-%m') m, COUNT(*) c FROM enquiries WHERE created_at >= ? GROUP BY m", [$since]) as $r) {
    if (isset($months[$r['m']])) $months[$r['m']]['enq'] = (int) $r['c'];
}
foreach (q_all("SELECT DATE_FORMAT(created_at,'%Y-%m') m, COUNT(*) c FROM service_requests WHERE created_at >= ? GROUP BY m", [$since]) as $r) {
    if (isset($months[$r['m']])) $months[$r['m']]['svc'] = (int) $r['c'];
}
foreach (q_all("SELECT DATE_FORMAT(invoice_date,'%Y-%m') m, SUM(grand_total) s FROM invoices WHERE status <> 'cancelled' AND invoice_date >= ? GROUP BY m", [$since]) as $r) {
    if (isset($months[$r['m']])) $months[$r['m']]['sales'] = round((float) $r['s'], 2);
}

// Enquiries by product category.
$byCategory = q_all("SELECT COALESCE(c.name, 'General') name, COUNT(*) c
    FROM enquiries e LEFT JOIN products p ON p.id = e.product_id LEFT JOIN categories c ON c.id = p.category_id
    GROUP BY c.name ORDER BY c DESC LIMIT 8");
// Products per category (catalogue mix) as fallback when no enquiries yet.
$catalogueMix = q_all('SELECT c.name, COUNT(p.id) c FROM categories c LEFT JOIN products p ON p.category_id = c.id AND p.is_active = 1 GROUP BY c.id ORDER BY c.sort_order');

$statusCounts = [];
foreach (q_all('SELECT status, COUNT(*) c FROM enquiries GROUP BY status') as $r) {
    $statusCounts[$r['status']] = (int) $r['c'];
}

$recentEnquiries = q_all('SELECT e.*, p.name product_name FROM enquiries e LEFT JOIN products p ON p.id = e.product_id ORDER BY e.created_at DESC LIMIT 6');
$lowStockItems = q_all('SELECT p.id, p.name, p.sku, p.stock_qty, p.min_stock, p.image, c.icon FROM products p LEFT JOIN categories c ON c.id = p.category_id WHERE p.is_active = 1 AND p.min_stock > 0 AND p.stock_qty <= p.min_stock ORDER BY p.stock_qty ASC, p.name LIMIT 6');
$recentService = q_all("SELECT * FROM service_requests ORDER BY FIELD(status,'open','scheduled','in_progress','resolved','closed'), created_at DESC LIMIT 5");
$recentInvoices = q_all('SELECT * FROM invoices ORDER BY invoice_date DESC, id DESC LIMIT 6');
$topProducts = q_all('SELECT p.id, p.name, COUNT(e.id) enquiries FROM products p LEFT JOIN enquiries e ON e.product_id = p.id WHERE p.is_active = 1 GROUP BY p.id ORDER BY enquiries DESC, p.name LIMIT 5');
$activity = q_all('SELECT a.*, ad.username FROM activity_log a LEFT JOIN admins ad ON ad.id = a.admin_id ORDER BY a.created_at DESC LIMIT 6');

// Tiles like the billing app: to collect / to pay / stock / week's sale / cash + bank.
$toCollect = receivables_total();
$toPay = payables_total();
$stockVal = stock_value();
$weekStart = date('Y-m-d', strtotime('monday this week'));
$weekSale = (float) q_val("SELECT COALESCE(SUM(grand_total),0) FROM invoices WHERE status <> 'cancelled' AND invoice_date >= ?", [$weekStart]);
$cashBank = array_sum(array_map(fn($a) => $a['is_active'] ? $a['balance'] : 0, account_balances()));

// Transactions feed for the selected financial year.
$fyPrev = get('fy') === 'prev';
$fyStartYear = (int) substr(financial_year(), 0, 4) - ($fyPrev ? 1 : 0);
$fyFrom = $fyStartYear . '-04-01';
$fyTo = ($fyStartYear + 1) . '-03-31';
$feed = [];
foreach (q_all("SELECT id, invoice_no, invoice_date d, COALESCE(NULLIF(customer_org,''), customer_name) party, customer_phone phone, grand_total amt, status, created_at FROM invoices WHERE invoice_date BETWEEN ? AND ? ORDER BY invoice_date DESC, id DESC LIMIT 12", [$fyFrom, $fyTo]) as $r) {
    $feed[] = ['d' => $r['d'], 'ts' => $r['created_at'], 'party' => $r['party'], 'label' => 'Sale ' . $r['invoice_no'], 'amt' => $r['amt'], 'sub' => ucfirst($r['status']), 'url' => 'invoice-view.php?id=' . $r['id'], 'phone' => $r['phone'], 'kind' => 'sale',
        'msg' => 'Invoice ' . $r['invoice_no'] . ' for ' . money($r['amt']) . ' from ' . setting('company_name', 'Vivora Healthcare') . '.'];
}
foreach (q_all("SELECT p.id, p.receipt_no, p.payment_date d, p.party_name party, p.amount amt, p.mode, p.created_at, c.phone FROM payments p LEFT JOIN customers c ON c.id = p.customer_id WHERE p.direction = 'in' AND p.payment_date BETWEEN ? AND ? ORDER BY p.payment_date DESC, p.id DESC LIMIT 12", [$fyFrom, $fyTo]) as $r) {
    $feed[] = ['d' => $r['d'], 'ts' => $r['created_at'], 'party' => $r['party'], 'label' => 'Received Payment #' . $r['receipt_no'], 'amt' => $r['amt'], 'sub' => payment_modes()[$r['mode']] ?? $r['mode'], 'url' => 'payment-receipt.php?id=' . $r['id'], 'phone' => $r['phone'], 'kind' => 'receipt',
        'msg' => 'Payment received with thanks: ' . money($r['amt']) . ' (receipt ' . $r['receipt_no'] . ', ' . fmt_date($r['d']) . '). – ' . setting('company_name', 'Vivora Healthcare')];
}
foreach (q_all('SELECT id, bill_no, bill_date d, supplier_name party, grand_total amt, status, created_at FROM purchases WHERE bill_date BETWEEN ? AND ? ORDER BY bill_date DESC, id DESC LIMIT 12', [$fyFrom, $fyTo]) as $r) {
    $feed[] = ['d' => $r['d'], 'ts' => $r['created_at'], 'party' => $r['party'], 'label' => 'Purchase ' . ($r['bill_no'] ?: '#' . $r['id']), 'amt' => $r['amt'], 'sub' => ucfirst($r['status']), 'url' => 'purchase-form.php?id=' . $r['id'], 'phone' => '', 'kind' => 'purchase', 'msg' => ''];
}
foreach (q_all("SELECT id, payment_date d, party_name party, amount amt, mode, created_at, purchase_id FROM payments WHERE direction = 'out' AND payment_date BETWEEN ? AND ? ORDER BY payment_date DESC, id DESC LIMIT 12", [$fyFrom, $fyTo]) as $r) {
    $feed[] = ['d' => $r['d'], 'ts' => $r['created_at'], 'party' => $r['party'], 'label' => 'Payment Out', 'amt' => $r['amt'], 'sub' => payment_modes()[$r['mode']] ?? $r['mode'], 'url' => $r['purchase_id'] ? 'purchase-form.php?id=' . $r['purchase_id'] : 'payments.php?type=out', 'phone' => '', 'kind' => 'payout', 'msg' => ''];
}
foreach (q_all('SELECT id, expense_date d, category, paid_to, amount amt, mode, created_at FROM expenses WHERE expense_date BETWEEN ? AND ? ORDER BY expense_date DESC, id DESC LIMIT 12', [$fyFrom, $fyTo]) as $r) {
    $feed[] = ['d' => $r['d'], 'ts' => $r['created_at'], 'party' => $r['paid_to'] ?: $r['category'], 'label' => 'Expense – ' . $r['category'], 'amt' => $r['amt'], 'sub' => payment_modes()[$r['mode']] ?? $r['mode'], 'url' => 'expenses.php?edit=' . $r['id'], 'phone' => '', 'kind' => 'expense', 'msg' => ''];
}
usort($feed, fn($a, $b) => [$b['d'], $b['ts']] <=> [$a['d'], $a['ts']]);
$feed = array_slice($feed, 0, 12);
$waFor = function (string $phone, string $msg): string {
    $n = preg_replace('/\D+/', '', $phone);
    if (strlen($n) === 10) $n = '91' . $n;
    return 'https://wa.me/' . (strlen($n) >= 11 ? $n : '') . '?text=' . rawurlencode($msg);
};

$pageTitle = 'Dashboard';
$activeNav = 'dashboard';
$breadcrumbs = ['Dashboard' => null];
$pageActions = '<a href="enquiry-form.php" class="btn btn-light-brand"><i class="feather-inbox me-2"></i>Add Enquiry</a><a href="invoice-form.php" class="btn btn-success"><i class="feather-file-plus me-2"></i>New Invoice</a><a href="product-form.php" class="btn btn-primary"><i class="feather-plus me-2"></i>Add Product</a>'
    . '<a href="quotation-form.php" class="btn btn-light-brand"><i class="feather-file-plus me-2"></i>New Quotation</a>';
$extraJs = ['vendors/js/apexcharts.min.js'];
require __DIR__ . '/partials/header.php';

$cards = [
    ['label' => 'Active Products', 'value' => $stats['products'], 'icon' => 'package', 'color' => 'primary', 'url' => 'products.php',
     'foot' => $stats['categories'] . ' categories', 'right' => $stats['low_stock'] . ' low stock', 'pct' => $pct($stats['products'], max(1, $stats['products_all']))],
    ['label' => 'New Enquiries', 'value' => $stats['enq_new'], 'icon' => 'inbox', 'color' => 'warning', 'url' => 'enquiries.php?status=new',
     'foot' => $stats['enq_month'] . ' this month', 'right' => $stats['enq_won'] . ' won (' . $pct($stats['enq_won'], $stats['enq_total']) . '%)', 'pct' => $pct($stats['enq_won'], $stats['enq_total'])],
    ['label' => 'Open Service Requests', 'value' => $stats['svc_open'], 'icon' => 'tool', 'color' => 'danger', 'url' => 'service-requests.php',
     'foot' => $stats['svc_total'] . ' total tickets', 'right' => $pct($stats['svc_total'] - $stats['svc_open'], $stats['svc_total']) . '% resolved', 'pct' => $pct($stats['svc_total'] - $stats['svc_open'], $stats['svc_total'])],
    ['label' => 'Sales This Month', 'value' => money($stats['inv_month']), 'icon' => 'trending-up', 'color' => 'success', 'url' => 'invoices.php',
     'foot' => $stats['inv_count_m'] . ' invoices', 'right' => money($stats['received_m']) . ' received', 'pct' => $stats['inv_month'] > 0 ? min(100, $pct($stats['received_m'], $stats['inv_month'])) : 0],
];
?>
<div class="row">
    <?php foreach ([
        ['To Collect', $toCollect, 'arrow-down', 'success', 'parties.php?tab=collect', 'vh-tile-collect'],
        ['To Pay', $toPay, 'arrow-up', 'danger', 'parties.php?tab=pay', 'vh-tile-pay'],
        ['Stock Value', $stockVal, 'layers', 'primary', 'billing-reports.php?tab=stock', ''],
        ["This week's sale", $weekSale, 'trending-up', 'primary', 'billing-reports.php?tab=salessummary&from=' . $weekStart . '&to=' . date('Y-m-d'), ''],
        ['Total Balance (Cash + Bank)', $cashBank, 'briefcase', 'primary', 'cash-bank.php', ''],
        ['Reports', null, 'pie-chart', 'primary', 'reports-hub.php', ''],
    ] as [$l, $v, $i, $col, $u, $cls]): ?>
        <div class="col-xxl-2 col-lg-4 col-6">
            <a href="<?= e($u) ?>" class="card stretch stretch-full text-reset vh-tile <?= $cls ?>">
                <div class="card-body d-flex align-items-center justify-content-between gap-2">
                    <div class="min-w-0">
                        <?php if ($v !== null): ?>
                            <div class="fs-5 fw-bold <?= $col === 'primary' ? 'text-dark' : 'text-' . $col ?> text-nowrap"><?= money($v) ?></div>
                            <div class="fs-13 fw-semibold <?= $col === 'primary' ? 'text-muted' : 'text-' . $col ?>"><?= e($l) ?> <?= $col !== 'primary' ? '<i class="feather-' . $i . '"></i>' : '' ?></div>
                        <?php else: ?>
                            <div class="fs-5 fw-bold text-dark">Reports</div><div class="fs-13 text-muted text-truncate">Sales, party, GST…</div>
                        <?php endif; ?>
                    </div>
                    <i class="feather-chevron-right text-muted"></i>
                </div>
            </a>
        </div>
    <?php endforeach; ?>

    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title">Transactions</h5>
                <a href="index.php<?= $fyPrev ? '' : '?fy=prev' ?>" class="btn btn-sm btn-light-brand"><i class="feather-calendar me-1"></i><?= $fyPrev ? 'Previous fiscal year (' . $fyStartYear . '-' . substr((string) ($fyStartYear + 1), -2) . ')' : 'This fiscal year (' . financial_year() . ')' ?> <i class="feather-repeat ms-1"></i></a>
            </div>
            <div class="card-body p-0">
                <?php if (!$feed): ?><div class="vh-empty"><i class="feather-list"></i>No transactions in this fiscal year yet.</div><?php endif; ?>
                <div class="row g-0">
                    <?php foreach ($feed as $f): $inflow = in_array($f['kind'], ['sale', 'receipt'], true); ?>
                        <div class="col-xl-6">
                            <div class="vh-feed-item">
                                <div class="d-flex justify-content-between gap-2">
                                    <a href="<?= e($f['url']) ?>" class="fw-bold text-dark text-truncate"><?= e($f['party'] ?: '—') ?></a>
                                    <span class="fw-bold text-nowrap <?= $inflow ? 'text-success' : 'text-danger' ?>"><?= money($f['amt']) ?></span>
                                </div>
                                <div class="d-flex justify-content-between gap-2 fs-12 text-muted mt-1">
                                    <span class="text-truncate"><?= e($f['label']) ?></span><span class="text-nowrap"><?= e($f['sub']) ?></span>
                                </div>
                                <div class="d-flex justify-content-between align-items-center mt-2">
                                    <span class="fs-12 text-muted"><?= e(fmt_date($f['d'])) ?></span>
                                    <?php if ($f['msg'] !== ''): ?>
                                        <a href="<?= e($waFor((string) $f['phone'], $f['msg'])) ?>" target="_blank" class="fs-12 fw-semibold"><i class="feather-share-2 me-1"></i><?= $f['kind'] === 'receipt' ? 'Send Receipt' : 'Share' ?></a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
    <?php foreach ($cards as $c): ?>
    <div class="col-xxl-3 col-md-6">
        <div class="card stretch stretch-full">
            <div class="card-body">
                <div class="d-flex align-items-start justify-content-between mb-4">
                    <div class="d-flex gap-4 align-items-center">
                        <div class="avatar-text avatar-lg bg-soft-<?= $c['color'] ?> text-<?= $c['color'] ?>"><i class="feather-<?= $c['icon'] ?>"></i></div>
                        <div>
                            <div class="fs-4 fw-bold text-dark"><?= is_int($c['value']) ? '<span class="counter">' . $c['value'] . '</span>' : e($c['value']) ?></div>
                            <h3 class="fs-13 fw-semibold text-truncate-1-line"><?= e($c['label']) ?></h3>
                        </div>
                    </div>
                    <a href="<?= e($c['url']) ?>" class="text-muted"><i class="feather-arrow-up-right"></i></a>
                </div>
                <div class="pt-4">
                    <div class="d-flex align-items-center justify-content-between">
                        <span class="fs-12 fw-medium text-muted text-truncate-1-line"><?= e($c['foot']) ?></span>
                        <span class="fs-12 text-dark text-end text-nowrap"><?= e($c['right']) ?></span>
                    </div>
                    <div class="progress mt-2 ht-3">
                        <div class="progress-bar bg-<?= $c['color'] ?>" role="progressbar" style="width: <?= (int) $c['pct'] ?>%"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>

    <div class="col-xxl-8">
        <div class="card stretch stretch-full">
            <div class="card-header">
                <h5 class="card-title">Enquiries, Service & Sales — last 12 months</h5>
            </div>
            <div class="card-body custom-card-action p-0">
                <div id="activity-chart" class="px-3 pt-3"></div>
            </div>
            <div class="card-footer">
                <div class="row g-4">
                    <div class="col-md-3 col-6">
                        <div class="p-3 border border-dashed rounded">
                            <div class="fs-12 text-muted mb-1">Total Enquiries</div>
                            <h6 class="fw-bold text-dark mb-0"><?= $stats['enq_total'] ?></h6>
                        </div>
                    </div>
                    <div class="col-md-3 col-6">
                        <div class="p-3 border border-dashed rounded">
                            <div class="fs-12 text-muted mb-1">Service Tickets</div>
                            <h6 class="fw-bold text-dark mb-0"><?= $stats['svc_total'] ?></h6>
                        </div>
                    </div>
                    <div class="col-md-3 col-6">
                        <div class="p-3 border border-dashed rounded">
                            <div class="fs-12 text-muted mb-1">Quotations</div>
                            <h6 class="fw-bold text-dark mb-0"><?= $stats['q_count'] ?></h6>
                        </div>
                    </div>
                    <div class="col-md-3 col-6">
                        <div class="p-3 border border-dashed rounded">
                            <div class="fs-12 text-muted mb-1">Customers</div>
                            <h6 class="fw-bold text-dark mb-0"><?= $stats['customers'] ?></h6>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xxl-4">
        <div class="card stretch stretch-full">
            <div class="card-header">
                <h5 class="card-title"><?= $byCategory ? 'Enquiries by Category' : 'Catalogue by Category' ?></h5>
            </div>
            <div class="card-body">
                <div id="category-chart"></div>
            </div>
        </div>
    </div>

    <div class="col-xxl-8">
        <div class="card stretch stretch-full">
            <div class="card-header">
                <h5 class="card-title">Latest Enquiries</h5>
                <a href="enquiries.php" class="btn btn-sm btn-light-brand">View all</a>
            </div>
            <div class="card-body custom-card-action p-0">
                <?php if (!$recentEnquiries): ?>
                    <div class="vh-empty"><i class="feather-inbox"></i>No enquiries yet. <a href="enquiry-form.php">Add your first enquiry</a>.</div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead><tr><th>Customer</th><th>Product</th><th>Source</th><th>Status</th><th class="text-end">Received</th></tr></thead>
                        <tbody>
                        <?php foreach ($recentEnquiries as $r): ?>
                            <tr>
                                <td>
                                    <a href="enquiry-view.php?id=<?= (int) $r['id'] ?>" class="fw-semibold text-dark d-block"><?= e($r['name']) ?></a>
                                    <span class="fs-12 text-muted"><?= e($r['organization'] ?: $r['phone']) ?></span>
                                </td>
                                <td><?= e($r['product_name'] ?: ($r['subject'] ?: '—')) ?></td>
                                <td><span class="badge bg-gray-200 text-dark"><?= e(enquiry_sources()[$r['source']] ?? ucfirst($r['source'])) ?></span></td>
                                <td><?= status_badge($r['status']) ?></td>
                                <td class="text-end fs-12 text-muted"><?= e(time_ago($r['created_at'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-xxl-4">
        <div class="card stretch stretch-full">
            <div class="card-header">
                <h5 class="card-title">Enquiry Pipeline</h5>
            </div>
            <div class="card-body">
                <?php
                $pipeline = ['new' => 'primary', 'contacted' => 'info', 'quoted' => 'warning', 'won' => 'success', 'lost' => 'danger'];
                foreach ($pipeline as $st => $color):
                    $n = $statusCounts[$st] ?? 0; ?>
                    <div class="mb-4">
                        <div class="d-flex justify-content-between mb-2">
                            <a href="enquiries.php?status=<?= $st ?>" class="fw-semibold text-dark"><?= ucfirst($st) ?></a>
                            <span class="fs-12 text-muted"><?= $n ?> (<?= $pct($n, $stats['enq_total']) ?>%)</span>
                        </div>
                        <div class="progress ht-5"><div class="progress-bar bg-<?= $color ?>" style="width: <?= $pct($n, $stats['enq_total']) ?>%"></div></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div class="col-xxl-4 col-lg-6">
        <div class="card stretch stretch-full">
            <div class="card-header">
                <h5 class="card-title">Low Stock Alerts</h5>
                <a href="inventory.php?filter=attention" class="btn btn-sm btn-light-brand">Inventory</a>
            </div>
            <div class="card-body">
                <?php if (!$lowStockItems): ?>
                    <div class="vh-empty"><i class="feather-check-circle"></i>All products are above their minimum stock level.</div>
                <?php endif; ?>
                <?php foreach ($lowStockItems as $p): $img = product_image_url($p); ?>
                    <div class="d-flex align-items-center gap-3 mb-3 pb-3 border-bottom border-bottom-dashed">
                        <?php if ($img): ?><img src="<?= e($img) ?>" class="vh-thumb" alt=""><?php else: ?><span class="vh-thumb"><i class="feather-<?= e(category_icon($p['icon'])) ?>"></i></span><?php endif; ?>
                        <div class="flex-grow-1 min-w-0">
                            <a href="product-form.php?id=<?= (int) $p['id'] ?>" class="fw-semibold text-dark text-truncate-1-line"><?= e($p['name']) ?></a>
                            <span class="fs-12 text-muted"><?= e($p['sku']) ?> · min <?= (int) $p['min_stock'] ?></span>
                        </div>
                        <span class="badge <?= $p['stock_qty'] <= 0 ? 'bg-soft-danger text-danger' : 'bg-soft-warning text-warning' ?>"><?= (int) $p['stock_qty'] ?> left</span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div class="col-xxl-4 col-lg-6">
        <div class="card stretch stretch-full">
            <div class="card-header">
                <h5 class="card-title">Service Desk</h5>
                <a href="service-requests.php" class="btn btn-sm btn-light-brand">All tickets</a>
            </div>
            <div class="card-body">
                <?php if (!$recentService): ?>
                    <div class="vh-empty"><i class="feather-tool"></i>No service requests yet.</div>
                <?php endif; ?>
                <?php foreach ($recentService as $s): ?>
                    <div class="d-flex align-items-start justify-content-between mb-3 pb-3 border-bottom border-bottom-dashed">
                        <div class="min-w-0">
                            <a href="service-view.php?id=<?= (int) $s['id'] ?>" class="fw-semibold text-dark d-block"><?= e($s['ticket_no']) ?> · <?= e(ucfirst($s['request_type'])) ?></a>
                            <span class="fs-12 text-muted text-truncate-1-line"><?= e($s['equipment'] ?: 'Equipment') ?> — <?= e($s['organization'] ?: $s['name']) ?></span>
                        </div>
                        <?= status_badge($s['status']) ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div class="col-xxl-4">
        <div class="card stretch stretch-full">
            <div class="card-header">
                <h5 class="card-title">Most Requested Products</h5>
            </div>
            <div class="card-body">
                <?php foreach ($topProducts as $i => $p): ?>
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <div class="d-flex align-items-center gap-3 min-w-0">
                            <span class="avatar-text avatar-sm bg-soft-primary text-primary"><?= $i + 1 ?></span>
                            <a href="product-form.php?id=<?= (int) $p['id'] ?>" class="text-dark fw-semibold text-truncate-1-line"><?= e($p['name']) ?></a>
                        </div>
                        <span class="fs-12 text-muted text-nowrap"><?= (int) $p['enquiries'] ?> enquir<?= (int) $p['enquiries'] === 1 ? 'y' : 'ies' ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div class="col-xxl-8">
        <div class="card stretch stretch-full">
            <div class="card-header">
                <h5 class="card-title">Recent Invoices</h5>
                <div class="d-flex gap-2"><a href="invoices.php" class="btn btn-sm btn-light-brand">View all</a><a href="invoice-form.php" class="btn btn-sm btn-primary"><i class="feather-plus me-1"></i>Create</a></div>
            </div>
            <div class="card-body custom-card-action p-0">
                <?php if (!$recentInvoices): ?>
                    <div class="vh-empty"><i class="feather-file-text"></i>No invoices yet. <a href="invoice-form.php">Create your first GST invoice</a> or convert a quotation.</div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead><tr><th>Invoice #</th><th>Customer</th><th>Date</th><th>Status</th><th class="text-end">Amount</th><th class="text-end">Balance</th></tr></thead>
                        <tbody>
                        <?php foreach ($recentInvoices as $iv): $b = $iv['status'] === 'cancelled' ? 0 : $iv['grand_total'] - $iv['amount_paid']; $od = $b > 0 && $iv['due_date'] && $iv['due_date'] < date('Y-m-d'); ?>
                            <tr>
                                <td><a href="invoice-view.php?id=<?= (int) $iv['id'] ?>" class="fw-semibold"><?= e($iv['invoice_no']) ?></a></td>
                                <td><?= e($iv['customer_org'] ?: $iv['customer_name']) ?></td>
                                <td class="fs-12"><?= e(fmt_date($iv['invoice_date'])) ?></td>
                                <td><?= status_badge($od ? 'overdue' : $iv['status']) ?></td>
                                <td class="text-end fw-semibold"><?= money($iv['grand_total']) ?></td>
                                <td class="text-end <?= $b > 0 ? 'text-danger' : 'text-muted' ?>"><?= money(max(0, $b)) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-xxl-4">
        <div class="card stretch stretch-full">
            <div class="card-header">
                <h5 class="card-title">Recent Activity</h5>
                <a href="activity.php" class="btn btn-sm btn-light-brand">View all</a>
            </div>
            <div class="card-body">
                <?php foreach ($activity as $a): ?>
                    <div class="d-flex gap-3 mb-3">
                        <span class="avatar-text avatar-sm bg-gray-200"><i class="feather-activity"></i></span>
                        <div>
                            <div class="fs-13 text-dark"><?= e($a['details'] ?: $a['action']) ?></div>
                            <div class="fs-11 text-muted"><?= e($a['username'] ?: 'System') ?> · <?= e(time_ago($a['created_at'])) ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>
<?php
$chartMonths = array_values($months);
$catData = $byCategory ?: $catalogueMix;
$inlineJs = 'var VH_MONTHS=' . json_encode(array_column($chartMonths, 'label')) . ';'
    . 'var VH_ENQ=' . json_encode(array_column($chartMonths, 'enq')) . ';'
    . 'var VH_SVC=' . json_encode(array_column($chartMonths, 'svc')) . ';'
    . 'var VH_SALES=' . json_encode(array_column($chartMonths, 'sales')) . ';'
    . 'var VH_CAT_L=' . json_encode(array_column($catData, 'name')) . ';'
    . 'var VH_CAT_V=' . json_encode(array_map('intval', array_column($catData, 'c'))) . ';'
    . <<<'JS'
(function () {
    var navy = '#0b4f8a', teal = '#139693', amber = '#ffa21d';
    new ApexCharts(document.querySelector('#activity-chart'), {
        chart: { height: 340, type: 'line', toolbar: { show: false }, fontFamily: 'inherit' },
        series: [
            { name: 'Enquiries', type: 'column', data: VH_ENQ },
            { name: 'Service requests', type: 'column', data: VH_SVC },
            { name: 'Invoiced sales (₹)', type: 'area', data: VH_SALES }
        ],
        colors: [navy, teal, amber],
        stroke: { width: [0, 0, 2], curve: 'smooth' },
        fill: { opacity: [0.9, 0.9, 0.15] },
        plotOptions: { bar: { columnWidth: '45%', borderRadius: 3 } },
        xaxis: { categories: VH_MONTHS },
        yaxis: [
            { seriesName: 'Enquiries', title: { text: 'Count' }, min: 0, forceNiceScale: true, labels: { formatter: function (v) { return Number.isInteger(v) ? v : ''; } } },
            { seriesName: 'Enquiries', show: false },
            { seriesName: 'Invoiced sales (₹)', opposite: true, title: { text: 'Sales (₹)' }, labels: { formatter: function (v) { return '₹' + Math.round(v).toLocaleString('en-IN'); } } }
        ],
        legend: { position: 'top', horizontalAlign: 'right' },
        dataLabels: { enabled: false },
        grid: { strokeDashArray: 3 }
    }).render();

    new ApexCharts(document.querySelector('#category-chart'), {
        chart: { type: 'donut', height: 360, fontFamily: 'inherit' },
        series: VH_CAT_V,
        labels: VH_CAT_L,
        colors: [navy, teal, '#3dc7be', amber, '#ea4d4d', '#6f42c1', '#17c666', '#64748b', '#0dcaf0', '#fd7e14', '#283c50'],
        legend: { position: 'bottom', fontSize: '12px' },
        dataLabels: { enabled: false },
        plotOptions: { pie: { donut: { size: '68%', labels: { show: true, total: { show: true, label: 'Total' } } } } }
    }).render();
})();
JS;
require __DIR__ . '/partials/footer.php';
