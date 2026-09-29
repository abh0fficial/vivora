<?php
require __DIR__ . '/../includes/auth.php';
$admin = require_admin();

$popular = [
    ['billing-reports.php?tab=billprofit', 'file-text', 'Bill-wise profit', 'Profit on every sales invoice'],
    ['billing-reports.php?tab=salessummary', 'trending-up', 'Sales summary', 'Day-wise or month-wise sales'],
    ['billing-reports.php?tab=daybook', 'book-open', 'Daybook', 'Every transaction, date by date'],
    ['billing-reports.php?tab=pl', 'bar-chart', 'Profit and loss', 'Sales − cost of goods − expenses'],
    ['parties.php', 'user', 'Party statement (ledger)', 'Pick a customer or supplier to see their statement'],
    ['billing-reports.php?tab=stock', 'layers', 'Stock summary', 'A summary of price & stock of all items'],
    ['billing-reports.php?tab=balancesheet', 'sliders', 'Balance sheet', 'Assets, liabilities and capital'],
    ['billing-reports.php?tab=cashbank', 'credit-card', 'Cash and bank (all payments)', 'Money in & out of every account'],
];
$more = [
    ['billing-reports.php?tab=party', 'users', 'Party reports', 'Sales, purchases and balances by party'],
    ['billing-reports.php?tab=item', 'package', 'Item reports', 'Quantity sold / purchased and profit by item'],
    ['billing-reports.php?tab=gst', 'percent', 'GST reports', 'GSTR-3B summary, GSTR-1 and HSN summary'],
    ['billing-reports.php?tab=transactions', 'shopping-cart', 'Transaction reports', 'Filter all transactions by type and party'],
    ['billing-reports.php?tab=sales', 'list', 'Sales register', 'Invoice-wise sales with GST break-up'],
    ['billing-reports.php?tab=purchases', 'truck', 'Purchase register', 'Bill-wise purchases with input GST'],
    ['billing-reports.php?tab=outstanding', 'clock', 'Receivables & payables', 'Who owes you, whom you owe (with ageing)'],
    ['reports.php', 'pie-chart', 'Business reports', 'Enquiries, quotations and service'],
];
$gst = [
    ['billing-reports.php?tab=gst', 'GSTR-3B summary'], ['billing-reports.php?tab=gstr1', 'GSTR-1 (B2B / B2C)'], ['billing-reports.php?tab=hsn', 'HSN summary'],
];

$pageTitle = 'Reports';
$activeNav = 'reports-hub';
$breadcrumbs = ['Reports' => null];
require __DIR__ . '/partials/header.php';
$item = function ($r) {
    return '<a href="' . e($r[0]) . '" class="list-group-item list-group-item-action d-flex align-items-center gap-3 py-3 px-4">'
        . '<span class="avatar-text avatar-md bg-soft-primary text-primary"><i class="feather-' . e($r[1]) . '"></i></span>'
        . '<span class="flex-grow-1"><span class="d-block fw-semibold text-dark fs-14">' . e($r[2]) . '</span><span class="fs-12 text-muted">' . e($r[3]) . '</span></span>'
        . '<i class="feather-chevron-right text-primary"></i></a>';
};
?>
<div class="row">
    <div class="col-xl-6">
        <div class="card">
            <div class="card-header bg-gray-100"><h5 class="card-title">Popular</h5></div>
            <div class="list-group list-group-flush"><?php foreach ($popular as $r) echo $item($r); ?></div>
        </div>
    </div>
    <div class="col-xl-6">
        <div class="card">
            <div class="card-header bg-gray-100"><h5 class="card-title">More</h5></div>
            <div class="list-group list-group-flush"><?php foreach ($more as $r) echo $item($r); ?></div>
        </div>
        <div class="card">
            <div class="card-header bg-gray-100"><h5 class="card-title">GST returns</h5></div>
            <div class="card-body d-flex flex-wrap gap-2">
                <?php foreach ($gst as [$u, $l]): ?><a href="<?= e($u) ?>" class="btn btn-light-brand"><i class="feather-percent me-1"></i><?= e($l) ?></a><?php endforeach; ?>
            </div>
        </div>
    </div>
</div>
<?php require __DIR__ . '/partials/footer.php';
