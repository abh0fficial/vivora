<?php
require __DIR__ . '/../includes/auth.php';
$admin = require_admin();

$tabs = [
    'billprofit' => 'Bill-wise Profit', 'salessummary' => 'Sales Summary', 'daybook' => 'Day Book', 'pl' => 'Profit & Loss',
    'stock' => 'Stock Summary', 'balancesheet' => 'Balance Sheet', 'cashbank' => 'Cash & Bank', 'party' => 'Party Report',
    'item' => 'Item Report', 'sales' => 'Sales Register', 'purchases' => 'Purchase Register', 'gst' => 'GST Summary (GSTR-3B)',
    'gstr1' => 'GSTR-1', 'hsn' => 'HSN Summary', 'transactions' => 'Transactions', 'outstanding' => 'Receivables & Payables',
];
$tab = array_key_exists(get('tab'), $tabs) ? get('tab') : 'billprofit';
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
    case 'billprofit':
        $rows = q_all("SELECT i.id, i.invoice_no, i.invoice_date, COALESCE(NULLIF(i.customer_org,''), i.customer_name) customer, i.taxable_total,
            COALESCE(SUM(ii.qty * p.purchase_price), 0) cost, SUM(ii.product_id IS NOT NULL AND p.purchase_price IS NULL) missing
            FROM invoices i JOIN invoice_items ii ON ii.invoice_id = i.id LEFT JOIN products p ON p.id = ii.product_id
            WHERE i.status <> 'cancelled' AND i.invoice_date BETWEEN ? AND ? GROUP BY i.id, i.invoice_no, i.invoice_date, customer, i.taxable_total ORDER BY i.invoice_date, i.id", $R);
        $table = ['head' => ['Invoice', 'Date', 'Party', 'Sale (excl. GST)', 'Cost of items', 'Profit', 'Margin'],
            'rows' => array_map(fn($r) => [$r['invoice_no'] . ($r['missing'] ? ' *' : ''), fmt_date($r['invoice_date']), $r['customer'], $r['taxable_total'], $r['cost'], $r['taxable_total'] - $r['cost'],
                ($r['taxable_total'] > 0 ? round(($r['taxable_total'] - $r['cost']) / $r['taxable_total'] * 100, 1) : 0) . '%'], $rows),
            'money' => [3, 4, 5]];
        $ts = array_sum(array_column($rows, 'taxable_total')); $tc = array_sum(array_column($rows, 'cost'));
        $table['foot'] = ['Total (' . count($rows) . ' bills)', '', '', $ts, $tc, $ts - $tc, ($ts > 0 ? round(($ts - $tc) / $ts * 100, 1) : 0) . '%'];
        if (array_sum(array_column($rows, 'missing'))) $blocks[] = ['note' => '* Some items on these bills have no cost price, so their cost is counted as ₹0. Add a purchase price to the product (or record a purchase bill) for exact profit.'];
        break;

    case 'salessummary':
        $group = get('group') === 'month' ? 'month' : 'day';
        $fmt = $group === 'month' ? '%Y-%m' : '%Y-%m-%d';
        $rows = q_all("SELECT DATE_FORMAT(invoice_date, '$fmt') k, COUNT(*) n, SUM(taxable_total) taxable, SUM(cgst + sgst + igst) tax, SUM(grand_total) total, SUM(amount_paid) paid
            FROM invoices WHERE status <> 'cancelled' AND invoice_date BETWEEN ? AND ? GROUP BY k ORDER BY k", $R);
        $table = ['head' => [$group === 'month' ? 'Month' : 'Date', 'Invoices', 'Taxable value', 'GST', 'Total sales', 'Received against these', 'Balance'],
            'rows' => array_map(fn($r) => [$group === 'month' ? date('M Y', strtotime($r['k'] . '-01')) : fmt_date($r['k']), (int) $r['n'], $r['taxable'], $r['tax'], $r['total'], $r['paid'], $r['total'] - $r['paid']], $rows),
            'money' => [2, 3, 4, 5, 6]];
        $table['foot'] = ['Total', array_sum(array_column($rows, 'n')), ...array_map(fn($k) => array_sum(array_column($rows, $k)), ['taxable', 'tax', 'total', 'paid']), array_sum(array_column($rows, 'total')) - array_sum(array_column($rows, 'paid'))];
        $top = q_all("SELECT COALESCE(NULLIF(customer_org,''), customer_name) party, COUNT(*) n, SUM(grand_total) total FROM invoices WHERE status <> 'cancelled' AND invoice_date BETWEEN ? AND ? GROUP BY party ORDER BY total DESC LIMIT 10", $R);
        $blocks[] = ['title' => 'Top customers in this period', 'head' => ['Party', 'Invoices', 'Sales'], 'money' => [2], 'rows' => array_map(fn($r) => [$r['party'], (int) $r['n'], $r['total']], $top)];
        break;

    case 'stock':
        $rows = q_all('SELECT p.sku, p.name, c.name category, p.hsn_code, p.unit, p.stock_qty, p.min_stock, p.purchase_price, p.price FROM products p LEFT JOIN categories c ON c.id = p.category_id WHERE p.is_active = 1 ORDER BY c.sort_order, p.name');
        $table = ['head' => ['Item code', 'Item', 'Category', 'HSN', 'Stock', 'Cost price', 'Sale price', 'Stock value (cost)', 'Stock value (sale)'],
            'rows' => array_map(fn($r) => [$r['sku'], $r['name'] . ($r['min_stock'] > 0 && $r['stock_qty'] <= $r['min_stock'] ? ' ⚠' : ''), $r['category'], $r['hsn_code'],
                (int) $r['stock_qty'] . ' ' . $r['unit'], $r['purchase_price'] ?? '', $r['price'] ?? '', max(0, $r['stock_qty']) * (float) ($r['purchase_price'] ?? $r['price'] ?? 0), max(0, $r['stock_qty']) * (float) ($r['price'] ?? 0)], $rows),
            'money' => [5, 6, 7, 8]];
        $table['foot'] = ['Total', count($rows) . ' items', '', '', array_sum(array_map(fn($r) => max(0, (int) $r['stock_qty']), $rows)), '', '',
            array_sum(array_map(fn($r) => max(0, $r['stock_qty']) * (float) ($r['purchase_price'] ?? $r['price'] ?? 0), $rows)), array_sum(array_map(fn($r) => max(0, $r['stock_qty']) * (float) ($r['price'] ?? 0), $rows))];
        break;

    case 'balancesheet':
        $asOf = $to;
        $accts = account_balances($asOf);
        $recv = (float) q_val("SELECT COALESCE(SUM(grand_total),0) FROM invoices WHERE status <> 'cancelled' AND invoice_date <= ?", [$asOf])
            - (float) q_val("SELECT COALESCE(SUM(amount),0) FROM payments WHERE direction = 'in' AND payment_date <= ?", [$asOf]);
        $pay = (float) q_val('SELECT COALESCE(SUM(grand_total),0) FROM purchases WHERE bill_date <= ?', [$asOf])
            - (float) q_val("SELECT COALESCE(SUM(amount),0) FROM payments WHERE direction = 'out' AND payment_date <= ?", [$asOf]);
        $stockVal = stock_value();
        $outTax = (float) q_val("SELECT COALESCE(SUM(cgst + sgst + igst),0) FROM invoices WHERE status <> 'cancelled' AND invoice_date <= ?", [$asOf]);
        $inTax = (float) q_val('SELECT COALESCE(SUM(tax_total),0) FROM purchases WHERE bill_date <= ?', [$asOf]);
        $gstNet = round($outTax - $inTax, 2);
        $assets = [];
        foreach ($accts as $a) $assets[] = [($a['type'] === 'cash' ? 'Cash – ' : 'Bank – ') . $a['name'], $a['balance']];
        $assets[] = ['Sundry debtors (to collect)', max(0, $recv)];
        $assets[] = ['Closing stock (at cost, current)', $stockVal];
        if ($gstNet < 0) $assets[] = ['GST input credit receivable', -$gstNet];
        if ($pay < 0) $assets[] = ['Advances paid to suppliers', -$pay];
        $liab = [['Sundry creditors (to pay)', max(0, $pay)]];
        if ($gstNet > 0) $liab[] = ['GST payable', $gstNet];
        if ($recv < 0) $liab[] = ['Advances received from customers', -$recv];
        $ta = array_sum(array_column($assets, 1)); $tl = array_sum(array_column($liab, 1));
        $liab[] = ["Owner's capital & profit (balancing figure)", $ta - $tl];
        $table = ['head' => ['Assets', 'Amount'], 'rows' => $assets, 'money' => [1], 'foot' => ['Total assets', $ta]];
        $blocks[] = ['title' => 'Liabilities & capital', 'head' => ['Liabilities', 'Amount'], 'money' => [1], 'rows' => array_merge($liab, [['Total', $ta]])];
        $blocks[] = ['note' => 'Balance sheet as of ' . fmt_date($asOf) . '. Stock is valued at today\'s quantity × cost price. Owner\'s capital is the balancing figure (assets − liabilities), which includes profit earned so far.'];
        break;

    case 'cashbank':
        $accName = [];
        foreach (cash_bank_accounts(false) as $a) $accName[$a['id']] = $a['name'];
        $acc = (int) get('account');
        $rows = [];
        foreach (q_all("SELECT payment_date d, direction, amount, party_name, receipt_no, reference, mode, account_id FROM payments WHERE payment_date BETWEEN ? AND ?", $R) as $r) {
            $rows[] = [$r['d'], $r['direction'] === 'in' ? 'Payment in' : 'Payment out', $r['party_name'], $r['direction'] === 'in' ? $r['receipt_no'] : $r['reference'], payment_modes()[$r['mode']] ?? $r['mode'], (int) $r['account_id'], $r['direction'] === 'in' ? (float) $r['amount'] : 0, $r['direction'] === 'out' ? (float) $r['amount'] : 0];
        }
        foreach (q_all('SELECT expense_date d, category, paid_to, description, mode, account_id, amount FROM expenses WHERE expense_date BETWEEN ? AND ?', $R) as $r) {
            $rows[] = [$r['d'], 'Expense – ' . $r['category'], $r['paid_to'], $r['description'], payment_modes()[$r['mode']] ?? $r['mode'], (int) $r['account_id'], 0, (float) $r['amount']];
        }
        foreach (q_all('SELECT txn_date d, type, note, account_id, amount FROM account_txns WHERE txn_date BETWEEN ? AND ?', $R) as $r) {
            $plus = in_array($r['type'], ['add', 'transfer_in'], true);
            $rows[] = [$r['d'], ['add' => 'Money added', 'reduce' => 'Money withdrawn', 'transfer_in' => 'Transfer in', 'transfer_out' => 'Transfer out'][$r['type']], '', $r['note'], '', (int) $r['account_id'], $plus ? (float) $r['amount'] : 0, $plus ? 0 : (float) $r['amount']];
        }
        if ($acc) $rows = array_values(array_filter($rows, fn($r) => $r[5] === $acc));
        usort($rows, fn($a, $b) => strcmp($a[0], $b[0]));
        $table = ['head' => ['Date', 'Type', 'Party', 'Reference', 'Mode', 'Account', 'Money in', 'Money out'],
            'rows' => array_map(fn($r) => [fmt_date($r[0]), $r[1], $r[2], $r[3], $r[4], $accName[$r[5]] ?? '—', $r[6] ?: '', $r[7] ?: ''], $rows), 'money' => [6, 7]];
        $in = array_sum(array_column($rows, 6)); $outAmt = array_sum(array_column($rows, 7));
        $table['foot'] = ['Total', '', '', '', '', 'Net ' . money($in - $outAmt), $in, $outAmt];
        $blocks[] = ['title' => 'Balances as of ' . fmt_date($to), 'head' => ['Account', 'Balance'], 'money' => [1],
            'rows' => array_map(fn($a) => [$a['name'], $a['balance']], array_values(account_balances($to)))];
        break;

    case 'party':
        $rows = [];
        foreach (q_all("SELECT COALESCE(NULLIF(i.customer_org,''), i.customer_name) party, i.customer_phone phone, COUNT(*) n, SUM(i.grand_total) total, SUM(i.amount_paid) paid
            FROM invoices i WHERE i.status <> 'cancelled' AND i.invoice_date BETWEEN ? AND ? GROUP BY party, phone", $R) as $r) {
            $rows[] = [$r['party'], 'Customer', $r['phone'], (int) $r['n'], (float) $r['total'], 0.0, (float) $r['paid'], (float) $r['total'] - (float) $r['paid']];
        }
        foreach (q_all('SELECT p.supplier_name party, s.phone, COUNT(*) n, SUM(p.grand_total) total, SUM(p.amount_paid) paid FROM purchases p LEFT JOIN suppliers s ON s.id = p.supplier_id
            WHERE p.bill_date BETWEEN ? AND ? GROUP BY party, s.phone', $R) as $r) {
            $rows[] = [$r['party'], 'Supplier', $r['phone'], (int) $r['n'], 0.0, (float) $r['total'], (float) $r['paid'], (float) $r['total'] - (float) $r['paid']];
        }
        usort($rows, fn($a, $b) => ($b[4] + $b[5]) <=> ($a[4] + $a[5]));
        $table = ['head' => ['Party', 'Type', 'Phone', 'Bills', 'Sales', 'Purchases', 'Received / paid', 'Balance (of these bills)'], 'rows' => $rows, 'money' => [4, 5, 6, 7]];
        $table['foot'] = ['Total', '', '', array_sum(array_column($rows, 3)), array_sum(array_column($rows, 4)), array_sum(array_column($rows, 5)), array_sum(array_column($rows, 6)), array_sum(array_column($rows, 7))];
        break;

    case 'item':
        $sold = [];
        foreach (q_all("SELECT ii.product_id, SUM(ii.qty) q, SUM(ii.taxable) amt FROM invoice_items ii JOIN invoices i ON i.id = ii.invoice_id
            WHERE i.status <> 'cancelled' AND i.invoice_date BETWEEN ? AND ? AND ii.product_id IS NOT NULL GROUP BY ii.product_id", $R) as $r) $sold[$r['product_id']] = $r;
        $bought = [];
        foreach (q_all('SELECT pi.product_id, SUM(pi.qty) q, SUM(pi.line_total) amt FROM purchase_items pi JOIN purchases p ON p.id = pi.purchase_id
            WHERE p.bill_date BETWEEN ? AND ? AND pi.product_id IS NOT NULL GROUP BY pi.product_id', $R) as $r) $bought[$r['product_id']] = $r;
        $rows = [];
        foreach (q_all('SELECT id, name, unit, stock_qty, purchase_price FROM products ORDER BY name') as $p) {
            $s = $sold[$p['id']] ?? null; $b = $bought[$p['id']] ?? null;
            if (!$s && !$b && get('all') !== '1') continue;
            $sq = (float) ($s['q'] ?? 0); $sa = (float) ($s['amt'] ?? 0);
            $rows[] = [$p['name'], $sq, $sa, (float) ($b['q'] ?? 0), (float) ($b['amt'] ?? 0), (int) $p['stock_qty'] . ' ' . $p['unit'], $sa - $sq * (float) ($p['purchase_price'] ?? 0)];
        }
        usort($rows, fn($a, $b) => $b[2] <=> $a[2]);
        $table = ['head' => ['Item', 'Qty sold', 'Sales (excl. GST)', 'Qty purchased', 'Purchase value', 'Current stock', 'Profit on sales'], 'rows' => $rows, 'money' => [2, 4, 6]];
        $table['foot'] = ['Total', array_sum(array_column($rows, 1)), array_sum(array_column($rows, 2)), array_sum(array_column($rows, 3)), array_sum(array_column($rows, 4)), '', array_sum(array_column($rows, 6))];
        break;

    case 'gstr1':
        $b2b = q_all("SELECT i.customer_gstin, COALESCE(NULLIF(i.customer_org,''), i.customer_name) party, i.invoice_no, i.invoice_date, i.grand_total, i.place_of_supply,
            ii.gst_rate, SUM(ii.taxable) taxable, SUM(CASE WHEN i.is_igst = 1 THEN ii.tax_amount ELSE 0 END) ig, SUM(CASE WHEN i.is_igst = 0 THEN ii.tax_amount ELSE 0 END) cs
            FROM invoices i JOIN invoice_items ii ON ii.invoice_id = i.id WHERE i.status <> 'cancelled' AND i.customer_gstin <> '' AND i.invoice_date BETWEEN ? AND ?
            GROUP BY i.id, i.customer_gstin, party, i.invoice_no, i.invoice_date, i.grand_total, i.place_of_supply, ii.gst_rate ORDER BY i.invoice_date, i.invoice_no", $R);
        $table = ['head' => ['GSTIN of recipient', 'Receiver name', 'Invoice no.', 'Date', 'Invoice value', 'Place of supply', 'Rate', 'Taxable value', 'IGST', 'CGST', 'SGST'],
            'rows' => array_map(fn($r) => [$r['customer_gstin'], $r['party'], $r['invoice_no'], fmt_date($r['invoice_date']), $r['grand_total'], state_code($r['place_of_supply']) . '-' . $r['place_of_supply'], (float) $r['gst_rate'] . '%', $r['taxable'], $r['ig'], round($r['cs'] / 2, 2), $r['cs'] - round($r['cs'] / 2, 2)], $b2b),
            'money' => [4, 7, 8, 9, 10]];
        $b2c = q_all("SELECT i.place_of_supply, ii.gst_rate, SUM(ii.taxable) taxable, SUM(CASE WHEN i.is_igst = 1 THEN ii.tax_amount ELSE 0 END) ig, SUM(CASE WHEN i.is_igst = 0 THEN ii.tax_amount ELSE 0 END) cs
            FROM invoices i JOIN invoice_items ii ON ii.invoice_id = i.id WHERE i.status <> 'cancelled' AND i.customer_gstin = '' AND i.invoice_date BETWEEN ? AND ?
            GROUP BY i.place_of_supply, ii.gst_rate ORDER BY i.place_of_supply, ii.gst_rate", $R);
        $blocks[] = ['title' => 'B2C (customers without GSTIN) – by place of supply & rate', 'head' => ['Place of supply', 'Rate', 'Taxable value', 'IGST', 'CGST', 'SGST'], 'money' => [2, 3, 4, 5],
            'rows' => array_map(fn($r) => [($r['place_of_supply'] ? state_code($r['place_of_supply']) . '-' . $r['place_of_supply'] : '—'), (float) $r['gst_rate'] . '%', $r['taxable'], $r['ig'], round($r['cs'] / 2, 2), $r['cs'] - round($r['cs'] / 2, 2)], $b2c)];
        $blocks[] = ['note' => 'B2B section lists invoices to customers with a GSTIN (one row per tax rate). Use Export CSV to share with your CA or to fill GSTR-1 on the GST portal. HSN summary is in the HSN Summary tab.'];
        break;

    case 'transactions':
        $types = ['sale' => 'Sales invoice', 'receipt' => 'Payment in', 'purchase' => 'Purchase bill', 'payout' => 'Payment out', 'expense' => 'Expense', 'quotation' => 'Quotation'];
        $want = array_key_exists(get('type'), $types) ? get('type') : '';
        $party = mb_strtolower(get('party'));
        $rows = [];
        if (!$want || $want === 'sale') foreach (q_all("SELECT invoice_date d, invoice_no ref, COALESCE(NULLIF(customer_org,''), customer_name) party, grand_total amt, status FROM invoices WHERE invoice_date BETWEEN ? AND ?", $R) as $r) $rows[] = [$r['d'], 'Sales invoice', $r['ref'], $r['party'], $r['amt'], ucfirst($r['status'])];
        if (!$want || $want === 'receipt') foreach (q_all("SELECT payment_date d, receipt_no ref, party_name party, amount amt, mode FROM payments WHERE direction = 'in' AND payment_date BETWEEN ? AND ?", $R) as $r) $rows[] = [$r['d'], 'Payment in', $r['ref'], $r['party'], $r['amt'], payment_modes()[$r['mode']] ?? $r['mode']];
        if (!$want || $want === 'purchase') foreach (q_all('SELECT bill_date d, bill_no ref, supplier_name party, grand_total amt, status FROM purchases WHERE bill_date BETWEEN ? AND ?', $R) as $r) $rows[] = [$r['d'], 'Purchase bill', $r['ref'], $r['party'], $r['amt'], ucfirst($r['status'])];
        if (!$want || $want === 'payout') foreach (q_all("SELECT payment_date d, reference ref, party_name party, amount amt, mode FROM payments WHERE direction = 'out' AND payment_date BETWEEN ? AND ?", $R) as $r) $rows[] = [$r['d'], 'Payment out', $r['ref'], $r['party'], $r['amt'], payment_modes()[$r['mode']] ?? $r['mode']];
        if (!$want || $want === 'expense') foreach (q_all('SELECT expense_date d, category, description, paid_to, amount FROM expenses WHERE expense_date BETWEEN ? AND ?', $R) as $r) $rows[] = [$r['d'], 'Expense', $r['category'] . ($r['description'] ? ' – ' . $r['description'] : ''), $r['paid_to'], $r['amount'], ''];
        if (!$want || $want === 'quotation') foreach (q_all("SELECT quote_date d, quote_no ref, COALESCE(NULLIF(customer_org,''), customer_name) party, grand_total amt, status FROM quotations WHERE quote_date BETWEEN ? AND ?", $R) as $r) $rows[] = [$r['d'], 'Quotation', $r['ref'], $r['party'], $r['amt'], ucfirst($r['status'])];
        if ($party !== '') $rows = array_values(array_filter($rows, fn($r) => str_contains(mb_strtolower((string) $r[3]), $party)));
        usort($rows, fn($a, $b) => strcmp($b[0], $a[0]));
        $table = ['head' => ['Date', 'Type', 'Reference', 'Party', 'Amount', 'Status / mode'], 'rows' => array_map(fn($r) => [fmt_date($r[0]), $r[1], $r[2], $r[3], (float) $r[4], $r[5]], $rows), 'money' => [4]];
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
        $strong = isset($row[0]) && in_array($row[0], ['Gross profit', 'Net profit', 'Net GST payable', 'Excess credit carried forward', 'Total'], true);
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

$qs = fn(array $o) => 'billing-reports.php?' . http_build_query(array_filter(array_merge(['tab' => $tab, 'from' => $from, 'to' => $to, 'type' => get('type'), 'party' => get('party'), 'group' => get('group'), 'account' => get('account'), 'all' => get('all')], $o), fn($v) => $v !== '' && $v !== null));
$pageTitle = $tabs[$tab];
$activeNav = 'reports-hub';
$breadcrumbs = ['Reports' => 'reports-hub.php', $tabs[$tab] => null];
$pageActions = '<a href="' . e($qs(['csv' => 1])) . '" class="btn btn-light-brand"><i class="feather-download me-2"></i>Export CSV</a><button onclick="window.print()" class="btn btn-primary"><i class="feather-printer me-2"></i>Print</button>';
require __DIR__ . '/partials/header.php';
?>
<div class="card no-print">
    <div class="card-body">
        <div class="d-flex gap-2 mb-3 flex-wrap">
            <a href="reports-hub.php" class="btn btn-sm btn-light-brand"><i class="feather-grid me-1"></i>All reports</a>
            <select class="form-select form-select-sm" style="max-width:280px" onchange="location.href=this.value" aria-label="Switch report">
                <?php foreach ($tabs as $k => $v): ?><option value="<?= e($qs(['tab' => $k])) ?>" <?= $tab === $k ? 'selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?>
            </select>
        </div>
        <form method="get" class="d-flex gap-2 flex-wrap align-items-center">
            <input type="hidden" name="tab" value="<?= e($tab) ?>">
            <?php if ($tab === 'stock'): ?>
                <span class="fs-12 text-muted">Current stock of all active items, valued at cost price and at selling price.</span>
            <?php elseif ($tab === 'balancesheet'): ?>
                <input type="hidden" name="from" value="<?= e($from) ?>">
                <span class="text-muted">As of</span><input type="date" name="to" class="form-control form-control-sm" style="width:auto" value="<?= e($to) ?>">
                <button class="btn btn-sm btn-primary">Apply</button>
            <?php elseif ($tab !== 'outstanding'): ?>
                <?php if ($tab === 'transactions'): ?>
                    <select name="type" class="form-select form-select-sm" style="width:auto"><option value="">All types</option>
                        <?php foreach (['sale' => 'Sales invoices', 'receipt' => 'Payments in', 'purchase' => 'Purchase bills', 'payout' => 'Payments out', 'expense' => 'Expenses', 'quotation' => 'Quotations'] as $k => $v): ?><option value="<?= $k ?>" <?= get('type') === $k ? 'selected' : '' ?>><?= $v ?></option><?php endforeach; ?></select>
                    <input type="search" name="party" class="form-control form-control-sm" style="width:auto" placeholder="Party name" value="<?= e(get('party')) ?>">
                <?php elseif ($tab === 'salessummary'): ?>
                    <select name="group" class="form-select form-select-sm" style="width:auto"><option value="day">Day-wise</option><option value="month" <?= get('group') === 'month' ? 'selected' : '' ?>>Month-wise</option></select>
                <?php elseif ($tab === 'cashbank'): ?>
                    <select name="account" class="form-select form-select-sm" style="width:auto"><option value="">All accounts</option><?php foreach (cash_bank_accounts(false) as $a): ?><option value="<?= (int) $a['id'] ?>" <?= (int) get('account') === (int) $a['id'] ? 'selected' : '' ?>><?= e($a['name']) ?></option><?php endforeach; ?></select>
                <?php elseif ($tab === 'item'): ?>
                    <label class="fs-12 d-flex align-items-center gap-1"><input type="checkbox" name="all" value="1" <?= get('all') === '1' ? 'checked' : '' ?>> all items</label>
                <?php endif; ?>
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
