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
    'products' => ['SELECT p.sku, p.name, c.name category, p.brand, p.model, p.price, p.gst_rate, p.unit, p.stock_qty, p.min_stock, p.is_active, p.is_featured, p.views
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
