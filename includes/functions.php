<?php
/**
 * Vivora Healthcare - helper functions shared by the dashboard and installer.
 */

declare(strict_types=1);

/* ------------------------------------------------------------------ */
/* URLs                                                                */
/* ------------------------------------------------------------------ */

/** Web path of the application root (works in a sub-folder too), e.g. "" or "/vivora". */
function base_path(): string
{
    static $base = null;
    if ($base !== null) {
        return $base;
    }
    $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    $file   = realpath($_SERVER['SCRIPT_FILENAME'] ?? '') ?: '';
    $root   = realpath(APP_ROOT) ?: APP_ROOT;
    $rel    = str_replace('\\', '/', substr($file, strlen($root)));
    if ($rel !== '' && substr($script, -strlen($rel)) === $rel) {
        $base = rtrim(substr($script, 0, -strlen($rel)), '/');
    } else {
        $base = rtrim(dirname($script), '/');
        if (substr($base, -6) === '/admin') {
            $base = substr($base, 0, -6);
        }
    }
    return $base;
}

function base_url(string $path = ''): string
{
    return base_path() . '/' . ltrim($path, '/');
}

function asset(string $path): string
{
    $path = ltrim($path, '/');
    $url = base_url('assets/' . $path);
    // Cache-busting: the file's modified time changes on every upload, so browsers fetch the new version.
    if (preg_match('/\.(css|js)$/', $path) && defined('APP_ROOT') && is_file(APP_ROOT . '/assets/' . $path)) {
        $url .= '?v=' . filemtime(APP_ROOT . '/assets/' . $path);
    }
    return $url;
}

function redirect(string $path): void
{
    if (!preg_match('~^(https?:)?//~', $path) && $path[0] !== '/') {
        $path = base_url($path);
    }
    header('Location: ' . $path);
    exit;
}

/* ------------------------------------------------------------------ */
/* Database                                                            */
/* ------------------------------------------------------------------ */

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        if (defined('DB_PORT') && DB_PORT) {
            $dsn .= ';port=' . DB_PORT;
        }
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
            $pdo->exec("SET time_zone = '+05:30'");
            // Same behaviour on every host (Hostinger MySQL 8 / MariaDB): no ONLY_FULL_GROUP_BY
            // surprises, and over-long text is trimmed instead of making a save fail.
            $pdo->exec("SET SESSION sql_mode = 'ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'");
            if (function_exists('vivora_auto_upgrade')) {
                vivora_auto_upgrade($pdo);
            }
        } catch (PDOException $e) {
            http_response_code(500);
            error_log('Vivora DB connection failed: ' . $e->getMessage());
            exit('<h2 style="font-family:sans-serif">Database connection failed.</h2>'
                . '<p style="font-family:sans-serif">Please check the database details in <code>config.php</code>.</p>');
        }
    }
    return $pdo;
}

/** Run a prepared statement and return it. */
function q(string $sql, array $params = []): PDOStatement
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}

function q_all(string $sql, array $params = []): array
{
    return q($sql, $params)->fetchAll();
}

function q_row(string $sql, array $params = []): ?array
{
    $row = q($sql, $params)->fetch();
    return $row === false ? null : $row;
}

function q_val(string $sql, array $params = [])
{
    $val = q($sql, $params)->fetchColumn();
    return $val === false ? null : $val;
}

/* ------------------------------------------------------------------ */
/* Session, CSRF & flash messages                                      */
/* ------------------------------------------------------------------ */

function start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    session_name('VIVORASESSID');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => base_path() . '/',
        'secure'   => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function csrf_token(): string
{
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function csrf_check(): void
{
    $token = $_POST['_csrf'] ?? '';
    if (!is_string($token) || !hash_equals(csrf_token(), $token)) {
        http_response_code(419);
        exit('Your session has expired. Please go back, refresh the page and try again.');
    }
}

function flash(string $type, string $message): void
{
    $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
}

function flashes(): array
{
    $list = $_SESSION['_flash'] ?? [];
    unset($_SESSION['_flash']);
    return $list;
}

function render_flashes(): string
{
    $html = '';
    foreach (flashes() as $f) {
        $type = $f['type'] === 'error' ? 'danger' : $f['type'];
        $html .= '<div class="alert alert-' . e($type) . ' alert-dismissible fade show" role="alert">'
            . e($f['message'])
            . '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
    }
    return $html;
}

/* ------------------------------------------------------------------ */
/* Output & formatting helpers                                         */
/* ------------------------------------------------------------------ */

function e($value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function post(string $key, string $default = ''): string
{
    $v = $_POST[$key] ?? $default;
    return is_string($v) ? trim($v) : $default;
}

function get(string $key, string $default = ''): string
{
    $v = $_GET[$key] ?? $default;
    return is_string($v) ? trim($v) : $default;
}

function money($amount): string
{
    if ($amount === null || $amount === '') {
        return '—';
    }
    return '₹' . inr_format((float) $amount);
}

/** 1234567.5 -> "12,34,567.50" (Indian digit grouping). */
function inr_format(float $amount, int $decimals = 2): string
{
    $neg = $amount < 0;
    $parts = explode('.', number_format(abs($amount), $decimals, '.', ''));
    $int = $parts[0];
    if (strlen($int) > 3) {
        $last3 = substr($int, -3);
        $rest = substr($int, 0, -3);
        $int = preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', $rest) . ',' . $last3;
    }
    return ($neg ? '-' : '') . $int . (isset($parts[1]) ? '.' . $parts[1] : '');
}

function fmt_date(?string $date, string $format = 'd M Y'): string
{
    if (!$date) {
        return '—';
    }
    $ts = strtotime($date);
    return $ts ? date($format, $ts) : '—';
}

function time_ago(?string $datetime): string
{
    if (!$datetime) {
        return '';
    }
    $diff = time() - strtotime($datetime);
    if ($diff < 60) {
        return 'just now';
    }
    $units = [31536000 => 'year', 2592000 => 'month', 604800 => 'week', 86400 => 'day', 3600 => 'hour', 60 => 'minute'];
    foreach ($units as $secs => $name) {
        if ($diff >= $secs) {
            $n = (int) floor($diff / $secs);
            return $n . ' ' . $name . ($n > 1 ? 's' : '') . ' ago';
        }
    }
    return '';
}

function slugify(string $text): string
{
    $text = strtolower(trim($text));
    $text = preg_replace('~[^a-z0-9]+~', '-', $text);
    return trim((string) $text, '-') ?: 'item';
}

/** Make a slug unique within $table (optionally ignoring the row being edited). */
function unique_slug(string $table, string $text, ?int $ignoreId = null): string
{
    $base = slugify($text);
    $slug = $base;
    $i = 2;
    while (true) {
        $sql = "SELECT COUNT(*) FROM `$table` WHERE slug = ?" . ($ignoreId ? ' AND id <> ?' : '');
        $params = $ignoreId ? [$slug, $ignoreId] : [$slug];
        if ((int) q_val($sql, $params) === 0) {
            return $slug;
        }
        $slug = $base . '-' . $i++;
    }
}

function excerpt(?string $text, int $length = 120): string
{
    $text = trim(preg_replace('/\s+/', ' ', strip_tags((string) $text)));
    if (mb_strlen($text) <= $length) {
        return $text;
    }
    return rtrim(mb_substr($text, 0, $length)) . '…';
}


/* ------------------------------------------------------------------ */
/* Settings                                                            */
/* ------------------------------------------------------------------ */

function setting(string $key, string $default = ''): string
{
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        foreach (q_all('SELECT skey, svalue FROM settings') as $row) {
            $cache[$row['skey']] = (string) $row['svalue'];
        }
    }
    if (array_key_exists($key, $cache)) {
        // A value the user saved (even an empty one) wins; $default only fills blanks for display.
        return $cache[$key] !== '' ? $cache[$key] : $default;
    }
    if ($default === '' && function_exists('vivora_default_settings')) {
        return vivora_default_settings()[$key] ?? '';
    }
    return $default;
}

function save_setting(string $key, string $value): void
{
    q('INSERT INTO settings (skey, svalue) VALUES (?, ?) ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)', [$key, $value]);
}


function tel_link(string $phone): string
{
    return 'tel:' . preg_replace('/[^\d+]/', '', $phone);
}

/* ------------------------------------------------------------------ */
/* Products                                                            */
/* ------------------------------------------------------------------ */

function product_image_url(?array $product): string
{
    if ($product && !empty($product['image']) && is_file(APP_ROOT . '/uploads/products/' . $product['image'])) {
        return base_url('uploads/products/' . rawurlencode($product['image']));
    }
    return '';
}

/** Feather icon for a category (falls back to "package"). */
function category_icon(?string $icon): string
{
    $icon = preg_replace('/[^a-z0-9-]/', '', (string) $icon);
    return $icon !== '' ? $icon : 'package';
}

/**
 * Handle an uploaded file. Returns the stored file name, null when nothing
 * was uploaded, or throws RuntimeException with a user-facing message.
 */
function handle_upload(string $field, string $dir, string $kind = 'image'): ?string
{
    if (empty($_FILES[$field]) || ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    $f = $_FILES[$field];
    if ($f['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Upload failed (error code ' . (int) $f['error'] . '). The file may be too large.');
    }
    $max = $kind === 'image' ? 5 * 1024 * 1024 : 15 * 1024 * 1024;
    if ($f['size'] > $max) {
        throw new RuntimeException('File is too large. Maximum is ' . ($max / 1024 / 1024) . ' MB.');
    }
    $mime = '';
    if (function_exists('finfo_open')) {
        $fi = finfo_open(FILEINFO_MIME_TYPE);
        $mime = (string) finfo_file($fi, $f['tmp_name']);
        finfo_close($fi);
    }
    if ($kind === 'image') {
        $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
        $info = @getimagesize($f['tmp_name']);
        if (!$info || !isset($allowed[$info['mime']])) {
            throw new RuntimeException('Please upload a JPG, PNG, WEBP or GIF image.');
        }
        $ext = $allowed[$info['mime']];
    } else {
        $head = (string) file_get_contents($f['tmp_name'], false, null, 0, 5);
        if ($head !== '%PDF-' || ($mime !== '' && $mime !== 'application/pdf')) {
            throw new RuntimeException('Please upload a PDF file.');
        }
        $ext = 'pdf';
    }
    $target = APP_ROOT . '/uploads/' . $dir;
    if (!is_dir($target) && !mkdir($target, 0755, true)) {
        throw new RuntimeException('Upload folder is not writable.');
    }
    $name = date('Ymd') . '-' . bin2hex(random_bytes(8)) . '.' . $ext;
    if (!move_uploaded_file($f['tmp_name'], $target . '/' . $name)) {
        throw new RuntimeException('Could not save the uploaded file. Check folder permissions for uploads/.');
    }
    return $name;
}

function delete_upload(string $dir, ?string $name): void
{
    if ($name && strpos($name, '/') === false && strpos($name, '\\') === false) {
        $path = APP_ROOT . '/uploads/' . $dir . '/' . $name;
        if (is_file($path)) {
            @unlink($path);
        }
    }
}

/* ------------------------------------------------------------------ */
/* Labels                                                              */
/* ------------------------------------------------------------------ */

function status_badge(string $status): string
{
    $map = [
        'new' => 'primary', 'contacted' => 'info', 'quoted' => 'warning', 'won' => 'success', 'lost' => 'danger',
        'open' => 'danger', 'scheduled' => 'info', 'in_progress' => 'warning', 'resolved' => 'success', 'closed' => 'secondary',
        'draft' => 'secondary', 'sent' => 'info', 'accepted' => 'success', 'rejected' => 'danger',
        'unpaid' => 'danger', 'partial' => 'warning', 'paid' => 'success', 'cancelled' => 'secondary', 'overdue' => 'danger',
    ];
    $color = $map[$status] ?? 'secondary';
    return '<span class="badge bg-soft-' . $color . ' text-' . $color . '">' . e(ucwords(str_replace('_', ' ', $status))) . '</span>';
}

function client_ip(): string
{
    return substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
}


function customer_types(): array
{
    return [
        'hospital' => 'Hospital', 'clinic' => 'Clinic', 'diagnostic_centre' => 'Diagnostic Centre',
        'nursing_home' => 'Nursing Home', 'dealer' => 'Dealer / Distributor', 'doctor' => 'Doctor', 'other' => 'Other',
    ];
}

function service_types(): array
{
    return [
        'installation' => 'Installation & Demo', 'repair' => 'Repair / Breakdown', 'amc' => 'AMC / CMC',
        'calibration' => 'Calibration', 'training' => 'User Training', 'other' => 'Other',
    ];
}

/** Next quotation number, e.g. VH-Q-2026-0007. */
function next_quote_no(): string
{
    $prefix = setting('quote_prefix', 'VH-Q-') . date('Y') . '-';
    $last = q_val('SELECT quote_no FROM quotations WHERE quote_no LIKE ? ORDER BY id DESC LIMIT 1', [$prefix . '%']);
    $n = $last ? (int) substr((string) $last, strlen($prefix)) + 1 : 1;
    return $prefix . str_pad((string) $n, 4, '0', STR_PAD_LEFT);
}

/** Amount in words (Indian numbering) for quotations. */
function amount_in_words(float $amount): string
{
    $ones = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine', 'Ten', 'Eleven', 'Twelve', 'Thirteen',
        'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen'];
    $tens = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];
    $two = function (int $n) use ($ones, $tens): string {
        return $n < 20 ? $ones[$n] : trim($tens[intdiv($n, 10)] . ' ' . $ones[$n % 10]);
    };
    $words = function (int $n) use ($two): string {
        if ($n === 0) return 'Zero';
        $out = [];
        foreach ([[10000000, 'Crore'], [100000, 'Lakh'], [1000, 'Thousand'], [100, 'Hundred']] as [$div, $name]) {
            if ($n >= $div) {
                $chunk = intdiv($n, $div);
                $out[] = ($chunk >= 100 && $div === 10000000 ? amount_in_words((float) $chunk) : $two($chunk)) . ' ' . $name;
                $n %= $div;
            }
        }
        if ($n > 0) $out[] = $two($n);
        return implode(' ', $out);
    };
    $rupees = (int) floor($amount);
    $paise = (int) round(($amount - $rupees) * 100);
    $text = 'Rupees ' . $words($rupees);
    if ($paise > 0) $text .= ' and ' . $two($paise) . ' Paise';
    return $text . ' Only';
}

/** How an enquiry reached us (manual entry in the dashboard). */
function enquiry_sources(): array
{
    return [
        'phone' => 'Phone call', 'whatsapp' => 'WhatsApp', 'email' => 'Email', 'walk_in' => 'Walk-in / Visit',
        'referral' => 'Referral', 'tender' => 'Tender', 'dealer' => 'Dealer', 'other' => 'Other',
        'contact' => 'Contact form', 'quote' => 'Quote request', 'product' => 'Product enquiry',
    ];
}

/* ------------------------------------------------------------------ */
/* Billing                                                             */
/* ------------------------------------------------------------------ */

/** Indian states / UTs with GST state codes. */
function indian_states(): array
{
    return [
        '01' => 'Jammu and Kashmir', '02' => 'Himachal Pradesh', '03' => 'Punjab', '04' => 'Chandigarh', '05' => 'Uttarakhand',
        '06' => 'Haryana', '07' => 'Delhi', '08' => 'Rajasthan', '09' => 'Uttar Pradesh', '10' => 'Bihar', '11' => 'Sikkim',
        '12' => 'Arunachal Pradesh', '13' => 'Nagaland', '14' => 'Manipur', '15' => 'Mizoram', '16' => 'Tripura', '17' => 'Meghalaya',
        '18' => 'Assam', '19' => 'West Bengal', '20' => 'Jharkhand', '21' => 'Odisha', '22' => 'Chhattisgarh', '23' => 'Madhya Pradesh',
        '24' => 'Gujarat', '26' => 'Dadra and Nagar Haveli and Daman and Diu', '27' => 'Maharashtra', '29' => 'Karnataka', '30' => 'Goa',
        '31' => 'Lakshadweep', '32' => 'Kerala', '33' => 'Tamil Nadu', '34' => 'Puducherry', '35' => 'Andaman and Nicobar Islands',
        '36' => 'Telangana', '37' => 'Andhra Pradesh', '38' => 'Ladakh',
    ];
}

function state_code(string $state): string
{
    $code = array_search(strtolower(trim($state)), array_map('strtolower', indian_states()), true);
    return $code === false ? '' : (string) $code;
}

/** State name from the first two digits of a GSTIN ('' if unknown). */
function state_from_gstin(string $gstin): string
{
    return indian_states()[substr(trim($gstin), 0, 2)] ?? '';
}

/** Indian financial year for a date, e.g. "2026-27". */
function financial_year(?string $date = null): string
{
    $ts = $date ? strtotime($date) : time();
    $y = (int) date('Y', $ts);
    $start = (int) date('n', $ts) >= 4 ? $y : $y - 1;
    return $start . '-' . substr((string) ($start + 1), -2);
}

/** Next number in a series, restarting every financial year: PREFIX2026-27/0001. */
function next_doc_no(string $table, string $column, string $prefix, ?string $date = null): string
{
    $base = $prefix . financial_year($date) . '/';
    $last = q_val("SELECT `$column` FROM `$table` WHERE `$column` LIKE ? ORDER BY id DESC LIMIT 1", [$base . '%']);
    $n = $last ? (int) substr((string) $last, strlen($base)) + 1 : 1;
    do {
        $no = $base . str_pad((string) $n++, 4, '0', STR_PAD_LEFT);
    } while (q_val("SELECT COUNT(*) FROM `$table` WHERE `$column` = ?", [$no]));
    return $no;
}

function payment_modes(): array
{
    return ['cash' => 'Cash', 'upi' => 'UPI', 'bank_transfer' => 'Bank transfer / NEFT', 'cheque' => 'Cheque', 'card' => 'Card', 'other' => 'Other'];
}

function expense_categories(): array
{
    $list = array_values(array_filter(array_map('trim', preg_split('/\R/', setting('expense_categories')))));
    return $list ?: ['Other'];
}

/**
 * GST totals for invoice lines. $items: [['qty','unit_price','gst_rate'], ...].
 * A flat discount is spread across lines in proportion to their value (before tax).
 * Returns items with line_total/taxable/tax_amount plus the document totals.
 */
function calc_gst_totals(array $items, float $discount, bool $igst): array
{
    $subtotal = 0.0;
    foreach ($items as &$it) {
        $it['line_total'] = round((float) $it['qty'] * (float) $it['unit_price'], 2);
        $subtotal += $it['line_total'];
    }
    unset($it);
    $discount = max(0.0, min($discount, $subtotal));
    $taxableTotal = $tax = 0.0;
    foreach ($items as &$it) {
        $share = $subtotal > 0 ? $it['line_total'] / $subtotal : 0;
        $it['taxable'] = round($it['line_total'] - $discount * $share, 2);
        $it['tax_amount'] = round($it['taxable'] * (float) $it['gst_rate'] / 100, 2);
        $taxableTotal += $it['taxable'];
        $tax += $it['tax_amount'];
    }
    unset($it);
    $taxableTotal = round($taxableTotal, 2);
    $tax = round($tax, 2);
    $cgst = $sgst = $igstAmt = 0.0;
    if ($igst) {
        $igstAmt = $tax;
    } else {
        $cgst = round($tax / 2, 2);
        $sgst = round($tax - $cgst, 2);
    }
    $exact = round($taxableTotal + $tax, 2);
    $grand = round($exact);
    return [
        'items' => $items, 'subtotal' => round($subtotal, 2), 'discount' => round($discount, 2), 'taxable_total' => $taxableTotal,
        'cgst' => $cgst, 'sgst' => $sgst, 'igst' => $igstAmt, 'tax' => $tax, 'round_off' => round($grand - $exact, 2), 'grand_total' => $grand,
    ];
}

/** Change a product's stock and log the movement. */
function adjust_stock(?int $productId, float $change, string $reason, ?int $adminId = null): void
{
    $change = (int) round($change);
    if (!$productId || $change === 0) {
        return;
    }
    q('UPDATE products SET stock_qty = stock_qty + ? WHERE id = ?', [$change, $productId]);
    $bal = q_val('SELECT stock_qty FROM products WHERE id = ?', [$productId]);
    if ($bal !== null) {
        q('INSERT INTO stock_movements (product_id, change_qty, balance_after, reason, admin_id) VALUES (?, ?, ?, ?, ?)',
            [$productId, $change, (int) $bal, mb_substr($reason, 0, 200), $adminId ?: ($_SESSION['admin_id'] ?? null)]);
    }
}

/** Recalculate amount paid and status of an invoice from its payments. */
function refresh_invoice_payment(int $invoiceId): void
{
    $inv = q_row('SELECT grand_total, status FROM invoices WHERE id = ?', [$invoiceId]);
    if (!$inv) {
        return;
    }
    $paid = (float) q_val("SELECT COALESCE(SUM(amount),0) FROM payments WHERE invoice_id = ? AND direction = 'in'", [$invoiceId]);
    $status = $inv['status'] === 'cancelled' ? 'cancelled'
        : ($paid <= 0 ? 'unpaid' : ($paid + 0.009 >= (float) $inv['grand_total'] ? 'paid' : 'partial'));
    q('UPDATE invoices SET amount_paid = ?, status = ? WHERE id = ?', [$paid, $status, $invoiceId]);
}

function refresh_purchase_payment(int $purchaseId): void
{
    $pur = q_row('SELECT grand_total FROM purchases WHERE id = ?', [$purchaseId]);
    if (!$pur) {
        return;
    }
    $paid = (float) q_val("SELECT COALESCE(SUM(amount),0) FROM payments WHERE purchase_id = ? AND direction = 'out'", [$purchaseId]);
    $status = $paid <= 0 ? 'unpaid' : ($paid + 0.009 >= (float) $pur['grand_total'] ? 'paid' : 'partial');
    q('UPDATE purchases SET amount_paid = ?, status = ? WHERE id = ?', [$paid, $status, $purchaseId]);
}

/** Outstanding (receivable) for a customer: invoices minus payments received. */
function customer_balance(int $customerId): float
{
    $billed = (float) q_val("SELECT COALESCE(SUM(grand_total),0) FROM invoices WHERE customer_id = ? AND status <> 'cancelled'", [$customerId]);
    $paid = (float) q_val("SELECT COALESCE(SUM(amount),0) FROM payments WHERE customer_id = ? AND direction = 'in'", [$customerId]);
    return round($billed - $paid, 2);
}
