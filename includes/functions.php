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
    return base_url('assets/' . ltrim($path, '/'));
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
