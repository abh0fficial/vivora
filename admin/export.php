<?php
/** CSV exports for Excel: enquiries, customers, quotations, service requests, products. */
require __DIR__ . '/../includes/auth.php';
$admin = require_admin();

$exports = [
    'enquiries' => ['SELECT e.id, e.created_at, e.source, e.status, e.name, e.organization, e.phone, e.email, e.city, p.name product, e.quantity, e.subject, e.message, e.notes
        FROM enquiries e LEFT JOIN products p ON p.id = e.product_id ORDER BY e.id DESC'],
    'customers' => ['SELECT id, name, organization, type, phone, email, gstin, address, city, state, pincode, notes, created_at FROM customers ORDER BY id DESC'],
    'quotations' => ['SELECT quote_no, quote_date, valid_until, status, customer_name, customer_org, customer_phone, customer_email, customer_gstin, subtotal, discount, tax_total, grand_total
        FROM quotations ORDER BY id DESC'],
    'service' => ['SELECT ticket_no, created_at, request_type, priority, status, name, organization, phone, email, city, equipment, brand_model, serial_no, preferred_date, engineer, description, notes
        FROM service_requests ORDER BY id DESC'],
    'invoices' => ["SELECT invoice_no, invoice_date, due_date, status, customer_name, customer_org, customer_gstin, place_of_supply, subtotal, discount, taxable_total, cgst, sgst, igst, round_off, grand_total, amount_paid, grand_total - amount_paid balance
        FROM invoices ORDER BY invoice_date DESC, id DESC"],
    'payments_in' => ["SELECT p.receipt_no, p.payment_date, p.party_name, i.invoice_no, p.mode, p.reference, p.amount, p.notes FROM payments p LEFT JOIN invoices i ON i.id = p.invoice_id WHERE p.direction = 'in' ORDER BY p.payment_date DESC, p.id DESC"],
    'payments_out' => ["SELECT p.payment_date, p.party_name, pu.bill_no, p.mode, p.reference, p.amount, p.notes FROM payments p LEFT JOIN purchases pu ON pu.id = p.purchase_id WHERE p.direction = 'out' ORDER BY p.payment_date DESC, p.id DESC"],
    'purchases' => ['SELECT bill_date, bill_no, supplier_name, subtotal, tax_total, grand_total, amount_paid, status, due_date FROM purchases ORDER BY bill_date DESC, id DESC'],
    'expenses' => ['SELECT expense_date, category, description, paid_to, mode, reference, amount FROM expenses ORDER BY expense_date DESC, id DESC'],
    'products' => ['SELECT p.sku, p.name, c.name category, p.hsn_code, p.brand, p.model, p.purchase_price, p.price, p.gst_rate, p.unit, p.stock_qty, p.min_stock, p.is_active
        FROM products p LEFT JOIN categories c ON c.id = p.category_id ORDER BY p.name'],
];
$type = get('type');
if (!isset($exports[$type])) {
    http_response_code(404);
    exit('Unknown export');
}
$rows = q_all($exports[$type][0]);
log_activity('export', 'Exported ' . $type . ' (' . count($rows) . ' rows)');

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="vivora-' . $type . '-' . date('Y-m-d') . '.csv"');
$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel shows ₹ and Hindi text correctly
if ($rows) {
    fputcsv($out, array_keys($rows[0]), ',', '"', '\\');
    foreach ($rows as $r) {
        // Neutralise spreadsheet formulas in user-supplied text.
        $r = array_map(fn($v) => is_string($v) && $v !== '' && strpbrk($v[0], '=+-@') !== false ? "'" . $v : $v, $r);
        fputcsv($out, $r, ',', '"', '\\');
    }
}
fclose($out);
