<?php
require __DIR__ . '/../includes/auth.php';
$admin = require_admin();

$tabs = ['sales' => 'Sales Register', 'gst' => 'GST Summary', 'hsn' => 'HSN Summary', 'purchases' => 'Purchase Register',
    'pl' => 'Profit & Loss', 'outstanding' => 'Receivables & Payables', 'daybook' => 'Day Book'];
$tab = array_key_exists(get('tab'), $tabs) ? get('tab') : 'sales';
$fyStart = substr(financial_year(), 0, 4) . '-04-01';
$ranges = [
    'This month' => [date('Y-m-01'), date('Y-m-d')],
    'Last month' => [date('Y-m-01', strtotime('first day of last month')), date('Y-m-t', strtotime('last day of last month'))],
    'This FY' => [$fyStart, date('Y-m-d')],
    'Last FY' => [date('Y-m-d', strtotime($fyStart . ' -1 year')), date('Y-m-d', strtotime($fyStart . ' -1 day'))],
];
$from = preg_match('/^\d{4}-\d{2}-\d{2}$/', get('from')) ? get('from') : date('Y-m-01');
$to = preg_match('/^\d{4}-\d{2}-\d{2}$/', get('to')) ? get('to') : date('Y-m-d');
$R = [$from, $to];

$table = ['head' => [], 'rows' => [], 'foot' => null, 'money' => []];
$blocks = [];
switch ($tab) {
    case 'sales':
        $rows = q_all("SELECT invoice_no, invoice_date, COALESCE(NULLIF(customer_org,''), customer_name) customer, customer_gstin, place_of_supply,
            taxable_total, cgst, sgst, igst, round_off, grand_total, amount_paid, status FROM invoices WHERE status <> 'cancelled' AND invoice_date BETWEEN ? AND ? ORDER BY invoice_date, id", $R);
        $table = ['head' => ['Invoice', 'Date', 'Customer', 'GSTIN', 'Place of supply', 'Taxable', 'CGST', 'SGST', 'IGST', 'Round off', 'Total', 'Received', 'Status'],
            'rows' => array_map(fn($r) => [$r['invoice_no'], fmt_date($r['invoice_date']), $r['customer'], $r['customer_gstin'], $r['place_of_supply'], $r['taxable_total'], $r['cgst'], $r['sgst'], $r['igst'], $r['round_off'], $r['grand_total'], $r['amount_paid'], ucfirst($r['status'])], $rows),
            'money' => [5, 6, 7, 8, 9, 10, 11]];
        $table['foot'] = ['Total (' . count($rows) . ')', '', '', '', '', ...array_map(fn($k) => array_sum(array_column($rows, $k)), ['taxable_total', 'cgst', 'sgst', 'igst', 'round_off', 'grand_total', 'amount_paid']), ''];
        break;

    case 'gst':
        $out = q_all("SELECT ii.gst_rate, SUM(ii.taxable) taxable, SUM(CASE WHEN i.is_igst = 0 THEN ii.tax_amount ELSE 0 END) cs, SUM(CASE WHEN i.is_igst = 1 THEN ii.tax_amount ELSE 0 END) ig,
            SUM(CASE WHEN i.customer_gstin <> '' THEN ii.taxable ELSE 0 END) b2b, SUM(CASE WHEN i.customer_gstin = '' THEN ii.taxable ELSE 0 END) b2c
            FROM invoice_items ii JOIN invoices i ON i.id = ii.invoice_id WHERE i.status <> 'cancelled' AND i.invoice_date BETWEEN ? AND ? GROUP BY ii.gst_rate ORDER BY ii.gst_rate", $R);
        $in = q_all('SELECT pi.gst_rate, SUM(pi.line_total) taxable, SUM(pi.tax_amount) tax FROM purchase_items pi JOIN purchases p ON p.id = pi.purchase_id WHERE p.bill_date BETWEEN ? AND ? GROUP BY pi.gst_rate ORDER BY pi.gst_rate', $R);
        $table = ['head' => ['GST rate', 'Taxable value', 'B2B (with GSTIN)', 'B2C', 'CGST', 'SGST', 'IGST', 'Total output tax'],
            'rows' => array_map(fn($r) => [(float) $r['gst_rate'] . '%', $r['taxable'], $r['b2b'], $r['b2c'], round($r['cs'] / 2, 2), round($r['cs'] - round($r['cs'] / 2, 2), 2), $r['ig'], $r['cs'] + $r['ig']], $out),
            'money' => [1, 2, 3, 4, 5, 6, 7]];
        $outTax = array_sum(array_map(fn($r) => $r['cs'] + $r['ig'], $out));
        $inTax = array_sum(array_column($in, 'tax'));
        $table['foot'] = ['Total', array_sum(array_column($out, 'taxable')), array_sum(array_column($out, 'b2b')), array_sum(array_column($out, 'b2c')),
            round(array_sum(array_column($out, 'cs')) / 2, 2), array_sum(array_column($out, 'cs')) - round(array_sum(array_column($out, 'cs')) / 2, 2), array_sum(array_column($out, 'ig')), $outTax];
        $blocks[] = ['title' => 'Input tax credit (purchases)', 'head' => ['GST rate', 'Taxable value', 'Input GST'], 'money' => [1, 2],
            'rows' => array_map(fn($r) => [(float) $r['gst_rate'] . '%', $r['taxable'], $r['tax']], $in)];
        $blocks[] = ['title' => 'Net GST', 'head' => ['', 'Amount'], 'money' => [1], 'rows' => [
            ['Output GST on sales', $outTax], ['Less: input tax credit on purchases', -$inTax], [($outTax - $inTax) >= 0 ? 'Net GST payable' : 'Excess credit carried forward', $outTax - $inTax]]];
        break;

    case 'hsn':
        $rows = q_all("SELECT ii.hsn_code, MIN(ii.description) description, MIN(ii.unit) unit, SUM(ii.qty) qty, SUM(ii.taxable) taxable, ii.gst_rate,
            SUM(CASE WHEN i.is_igst = 1 THEN ii.tax_amount ELSE 0 END) ig, SUM(CASE WHEN i.is_igst = 0 THEN ii.tax_amount ELSE 0 END) cs
            FROM invoice_items ii JOIN invoices i ON i.id = ii.invoice_id WHERE i.status <> 'cancelled' AND i.invoice_date BETWEEN ? AND ?
            GROUP BY ii.hsn_code, ii.gst_rate ORDER BY ii.hsn_code", $R);
        $table = ['head' => ['HSN/SAC', 'Description', 'UQC', 'Qty', 'Rate', 'Taxable value', 'IGST', 'CGST', 'SGST', 'Total value'],
            'rows' => array_map(fn($r) => [$r['hsn_code'] ?: '(none)', $r['description'], $r['unit'], (float) $r['qty'], (float) $r['gst_rate'] . '%', $r['taxable'], $r['ig'], round($r['cs'] / 2, 2), $r['cs'] - round($r['cs'] / 2, 2), $r['taxable'] + $r['ig'] + $r['cs']], $rows),
            'money' => [5, 6, 7, 8, 9]];
        break;

    case 'purchases':
        $rows = q_all('SELECT p.bill_date, p.bill_no, p.supplier_name, s.gstin, p.subtotal, p.tax_total, p.grand_total, p.amount_paid, p.status FROM purchases p LEFT JOIN suppliers s ON s.id = p.supplier_id WHERE p.bill_date BETWEEN ? AND ? ORDER BY p.bill_date, p.id', $R);
        $table = ['head' => ['Date', 'Bill no.', 'Supplier', 'GSTIN', 'Taxable', 'GST (ITC)', 'Total', 'Paid', 'Status'],
            'rows' => array_map(fn($r) => [fmt_date($r['bill_date']), $r['bill_no'], $r['supplier_name'], $r['gstin'], $r['subtotal'], $r['tax_total'], $r['grand_total'], $r['amount_paid'], ucfirst($r['status'])], $rows),
            'money' => [4, 5, 6, 7]];
        $table['foot'] = ['Total (' . count($rows) . ')', '', '', '', ...array_map(fn($k) => array_sum(array_column($rows, $k)), ['subtotal', 'tax_total', 'grand_total', 'amount_paid']), ''];
        break;

    case 'pl':
        $sales = (float) q_val("SELECT COALESCE(SUM(taxable_total),0) FROM invoices WHERE status <> 'cancelled' AND invoice_date BETWEEN ? AND ?", $R);
        $cogs = (float) q_val("SELECT COALESCE(SUM(ii.qty * COALESCE(p.purchase_price, 0)),0) FROM invoice_items ii JOIN invoices i ON i.id = ii.invoice_id
            LEFT JOIN products p ON p.id = ii.product_id WHERE i.status <> 'cancelled' AND i.invoice_date BETWEEN ? AND ?", $R);
        $noCost = (int) q_val("SELECT COUNT(*) FROM invoice_items ii JOIN invoices i ON i.id = ii.invoice_id LEFT JOIN products p ON p.id = ii.product_id
            WHERE i.status <> 'cancelled' AND i.invoice_date BETWEEN ? AND ? AND (p.purchase_price IS NULL)", $R);
        $exp = q_all('SELECT category, SUM(amount) amt FROM expenses WHERE expense_date BETWEEN ? AND ? GROUP BY category ORDER BY amt DESC', $R);
        $expTotal = array_sum(array_column($exp, 'amt'));
        $rows = [['Sales (taxable value, excl. GST)', $sales], ['Less: cost of goods sold', -$cogs], ['Gross profit', $sales - $cogs]];
        foreach ($exp as $x) $rows[] = ['Less: ' . $x['category'], -(float) $x['amt']];
        $rows[] = ['Net profit', $sales - $cogs - $expTotal];
        $table = ['head' => ['Particulars', 'Amount'], 'rows' => $rows, 'money' => [1]];
        if ($noCost) $blocks[] = ['note' => "$noCost invoiced line(s) have no cost price, so their cost is counted as ₹0. Add a purchase price on the product (or record a purchase bill) for accurate profit."];
        break;

    case 'outstanding':
        $rows = q_all("SELECT COALESCE(NULLIF(customer_org,''), customer_name) customer, customer_phone, COUNT(*) n,
            SUM(CASE WHEN DATEDIFF(CURDATE(), invoice_date) <= 30 THEN grand_total - amount_paid ELSE 0 END) a,
            SUM(CASE WHEN DATEDIFF(CURDATE(), invoice_date) BETWEEN 31 AND 60 THEN grand_total - amount_paid ELSE 0 END) b,
            SUM(CASE WHEN DATEDIFF(CURDATE(), invoice_date) BETWEEN 61 AND 90 THEN grand_total - amount_paid ELSE 0 END) c,
            SUM(CASE WHEN DATEDIFF(CURDATE(), invoice_date) > 90 THEN grand_total - amount_paid ELSE 0 END) d,
            SUM(grand_total - amount_paid) total
            FROM invoices WHERE status IN ('unpaid','partial') GROUP BY customer, customer_phone ORDER BY total DESC");
        $table = ['head' => ['Customer (receivable)', 'Phone', 'Invoices', '0–30 days', '31–60 days', '61–90 days', '90+ days', 'Total due'],
            'rows' => array_map(fn($r) => [$r['customer'], $r['customer_phone'], (int) $r['n'], $r['a'], $r['b'], $r['c'], $r['d'], $r['total']], $rows), 'money' => [3, 4, 5, 6, 7]];
        $table['foot'] = ['Total', '', array_sum(array_column($rows, 'n')), ...array_map(fn($k) => array_sum(array_column($rows, $k)), ['a', 'b', 'c', 'd', 'total'])];
        $pay = q_all("SELECT supplier_name, COUNT(*) n, SUM(grand_total - amount_paid) total, MIN(due_date) due FROM purchases WHERE status IN ('unpaid','partial') GROUP BY supplier_name ORDER BY total DESC");
        $blocks[] = ['title' => 'Payable to suppliers', 'head' => ['Supplier', 'Bills', 'Earliest due', 'Total payable'], 'money' => [3],
            'rows' => array_map(fn($r) => [$r['supplier_name'], (int) $r['n'], fmt_date($r['due']), $r['total']], $pay)];
        break;

    case 'daybook':
        $rows = [];
        foreach (q_all("SELECT invoice_date d, invoice_no ref, COALESCE(NULLIF(customer_org,''), customer_name) party, grand_total amt FROM invoices WHERE status <> 'cancelled' AND invoice_date BETWEEN ? AND ?", $R) as $r) $rows[] = [$r['d'], 'Sales invoice', $r['ref'], $r['party'], $r['amt'], 0];
        foreach (q_all("SELECT payment_date d, receipt_no ref, party_name party, amount amt, mode FROM payments WHERE direction = 'in' AND payment_date BETWEEN ? AND ?", $R) as $r) $rows[] = [$r['d'], 'Receipt (' . (payment_modes()[$r['mode']] ?? $r['mode']) . ')', $r['ref'], $r['party'], $r['amt'], 0];
        foreach (q_all('SELECT bill_date d, bill_no ref, supplier_name party, grand_total amt FROM purchases WHERE bill_date BETWEEN ? AND ?', $R) as $r) $rows[] = [$r['d'], 'Purchase bill', $r['ref'], $r['party'], 0, $r['amt']];
        foreach (q_all("SELECT payment_date d, reference ref, party_name party, amount amt, mode FROM payments WHERE direction = 'out' AND payment_date BETWEEN ? AND ?", $R) as $r) $rows[] = [$r['d'], 'Payment made (' . (payment_modes()[$r['mode']] ?? $r['mode']) . ')', $r['ref'], $r['party'], 0, $r['amt']];
        foreach (q_all('SELECT expense_date d, category, description, paid_to, amount FROM expenses WHERE expense_date BETWEEN ? AND ?', $R) as $r) $rows[] = [$r['d'], 'Expense – ' . $r['category'], $r['description'], $r['paid_to'], 0, $r['amount']];
        usort($rows, fn($a, $b) => strcmp($a[0], $b[0]));
        $table = ['head' => ['Date', 'Type', 'Reference', 'Party', 'Inflow / billed', 'Outflow / cost'], 'rows' => array_map(fn($r) => [fmt_date($r[0]), $r[1], $r[2], $r[3], $r[4] ?: '', $r[5] ?: ''], $rows), 'money' => [4, 5]];
        $table['foot'] = ['Total', '', '', '', array_sum(array_column($rows, 4)), array_sum(array_column($rows, 5))];
        break;
}

// CSV download of the main table.
if (get('csv') === '1') {
    log_activity('export', 'Exported ' . $tabs[$tab] . " report ($from to $to)");
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="vivora-' . $tab . '-' . $from . '-to-' . $to . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, $table['head'], ',', '"', '\\');
    foreach (array_merge($table['rows'], $table['foot'] ? [$table['foot']] : []) as $row) {
        fputcsv($out, array_map(fn($v) => is_float($v) || is_int($v) ? $v : (is_string($v) && $v !== '' && strpbrk($v[0], '=+-@') !== false && !is_numeric($v) ? "'" . $v : $v), $row), ',', '"', '\\');
    }
    fclose($out);
    exit;
}

$cell = function ($v, bool $isMoney) {
    if ($isMoney && $v !== '' && $v !== null) {
        $n = (float) $v;
        return '<span class="' . ($n < 0 ? 'text-danger' : '') . '">' . money($n) . '</span>';
    }
    return e(is_float($v) ? rtrim(rtrim(number_format($v, 2, '.', ''), '0'), '.') : (string) $v);
};
$renderTable = function (array $t) use ($cell) {
    echo '<div class="table-responsive"><table class="table table-hover table-sm mb-0 fs-13"><thead><tr>';
    foreach ($t['head'] as $i => $h) echo '<th class="' . (in_array($i, $t['money'], true) ? 'text-end' : '') . '">' . e($h) . '</th>';
    echo '</tr></thead><tbody>';
    foreach ($t['rows'] as $row) {
        $strong = isset($row[0]) && in_array($row[0], ['Gross profit', 'Net profit', 'Net GST payable', 'Excess credit carried forward'], true);
        echo '<tr' . ($strong ? ' class="fw-bold bg-gray-100"' : '') . '>';
        foreach ($row as $i => $v) echo '<td class="' . (in_array($i, $t['money'], true) ? 'text-end text-nowrap' : '') . '">' . $cell($v, in_array($i, $t['money'], true)) . '</td>';
        echo '</tr>';
    }
    if (!$t['rows']) echo '<tr><td colspan="' . count($t['head']) . '" class="text-center text-muted py-4">No data for this period.</td></tr>';
    echo '</tbody>';
    if (!empty($t['foot']) && $t['rows']) {
        echo '<tfoot class="fw-bold"><tr>';
        foreach ($t['foot'] as $i => $v) echo '<td class="' . (in_array($i, $t['money'], true) ? 'text-end text-nowrap' : '') . '">' . $cell($v, in_array($i, $t['money'], true)) . '</td>';
        echo '</tr></tfoot>';
    }
    echo '</table></div>';
};

$qs = fn(array $o) => 'billing-reports.php?' . http_build_query(array_merge(['tab' => $tab, 'from' => $from, 'to' => $to], $o));
$pageTitle = 'Billing & GST Reports';
$activeNav = 'billing-reports';
$breadcrumbs = ['Reports' => null];
$pageActions = '<a href="' . e($qs(['csv' => 1])) . '" class="btn btn-light-brand"><i class="feather-download me-2"></i>Export CSV</a><button onclick="window.print()" class="btn btn-primary"><i class="feather-printer me-2"></i>Print</button>';
require __DIR__ . '/partials/header.php';
?>
<div class="card no-print">
    <div class="card-body">
        <ul class="nav nav-pills gap-1 mb-3 flex-wrap">
            <?php foreach ($tabs as $k => $v): ?><li class="nav-item"><a class="nav-link py-1 px-3<?= $tab === $k ? ' active' : '' ?>" href="<?= e($qs(['tab' => $k])) ?>"><?= e($v) ?></a></li><?php endforeach; ?>
        </ul>
        <form method="get" class="d-flex gap-2 flex-wrap align-items-center">
            <input type="hidden" name="tab" value="<?= e($tab) ?>">
            <?php if ($tab !== 'outstanding'): ?>
                <input type="date" name="from" class="form-control form-control-sm" style="width:auto" value="<?= e($from) ?>"><span class="text-muted">to</span>
                <input type="date" name="to" class="form-control form-control-sm" style="width:auto" value="<?= e($to) ?>">
                <button class="btn btn-sm btn-primary">Apply</button>
                <?php foreach ($ranges as $label => [$f, $t]): ?><a class="btn btn-sm btn-light-brand" href="<?= e($qs(['from' => $f, 'to' => $t])) ?>"><?= e($label) ?></a><?php endforeach; ?>
            <?php else: ?><span class="fs-12 text-muted">Shows everything unpaid as of today, aged by invoice date.</span><?php endif; ?>
        </form>
    </div>
</div>
<div class="card">
    <div class="card-header"><h5 class="card-title"><?= e($tabs[$tab]) ?><?= $tab !== 'outstanding' ? ' · ' . e(fmt_date($from)) . ' – ' . e(fmt_date($to)) : '' ?></h5></div>
    <div class="card-body p-0"><?php $renderTable($table); ?></div>
</div>
<?php foreach ($blocks as $b): ?>
    <?php if (isset($b['note'])): ?><div class="alert alert-warning"><?= e($b['note']) ?></div><?php continue; endif; ?>
    <div class="card">
        <div class="card-header"><h5 class="card-title"><?= e($b['title']) ?></h5></div>
        <div class="card-body p-0"><?php $renderTable($b + ['foot' => null]); ?></div>
    </div>
<?php endforeach; ?>
<?php require __DIR__ . '/partials/footer.php';
