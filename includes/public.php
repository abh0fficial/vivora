<?php
/**
 * Public website helpers: layout data, spam protection and form handling
 * for enquiries / quote requests / service requests.
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');

/** Active categories for menus, with product counts. */
function site_categories(): array
{
    static $cats = null;
    if ($cats === null) {
        $cats = q_all('SELECT c.*, COUNT(p.id) product_count FROM categories c
            LEFT JOIN products p ON p.category_id = c.id AND p.is_active = 1
            WHERE c.is_active = 1 GROUP BY c.id ORDER BY c.sort_order, c.name');
    }
    return $cats;
}

/** Placeholder tile or real image for a product card. */
function product_visual(array $p, string $class = ''): string
{
    $img = product_image_url($p);
    if ($img) {
        return '<img src="' . e($img) . '" alt="' . e($p['name']) . '" class="' . e($class) . '" loading="lazy">';
    }
    return '<div class="vh-placeholder ' . e($class) . '"><i class="feather-' . e(category_icon($p['icon'] ?? '')) . '"></i></div>';
}

/** Price label respecting the "show price" switch. */
function product_price_label(array $p): string
{
    if ($p['show_price'] && $p['price'] !== null) {
        return money($p['price']) . ' <small class="text-muted fw-normal">+ GST</small>';
    }
    return '<span class="text-muted fw-semibold">Price on request</span>';
}

/**
 * Validate the shared anti-spam fields. Returns an error message or ''.
 *  - CSRF token
 *  - honeypot field "website" must stay empty
 *  - form must not be submitted faster than 3 seconds after rendering
 *  - max 5 submissions per IP per 10 minutes (per table)
 */
function spam_check(string $table): string
{
    $token = $_POST['_csrf'] ?? '';
    if (!is_string($token) || !hash_equals(csrf_token(), $token)) {
        return 'Your session expired. Please submit the form again.';
    }
    if (post('website') !== '') {
        return 'Submission blocked.';
    }
    $ts = (int) post('_ts');
    if ($ts && time() - $ts < 3) {
        return 'Please take a moment to fill in the form and try again.';
    }
    $recent = (int) q_val("SELECT COUNT(*) FROM `$table` WHERE ip = ? AND created_at > (NOW() - INTERVAL 10 MINUTE)", [client_ip()]);
    if ($recent >= 5) {
        return 'Too many requests from your network. Please call or WhatsApp us instead.';
    }
    return '';
}

function spam_fields(): string
{
    return csrf_field()
        . '<input type="hidden" name="_ts" value="' . time() . '">'
        . '<div class="vh-hp" aria-hidden="true"><label>Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>';
}

function valid_phone(string $phone): bool
{
    return (bool) preg_match('/^\+?[\d\s\-()]{8,20}$/', $phone);
}

/** Send a plain-text notification to the configured address (Hostinger supports PHP mail()). */
function notify_admin(string $subject, array $lines): void
{
    $to = setting('notify_email');
    if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return;
    }
    $host = preg_replace('/[^a-z0-9.-]/i', '', $_SERVER['HTTP_HOST'] ?? 'localhost');
    $host = preg_replace('/^www\./i', '', $host);
    $body = '';
    foreach ($lines as $k => $v) {
        $body .= $k . ': ' . str_replace(["\r", "\n"], ' ', (string) $v) . "\n";
    }
    $body .= "\nOpen your dashboard to respond.\n";
    $headers = 'From: ' . setting('company_name', 'Vivora Healthcare') . ' Website <no-reply@' . $host . ">\r\n"
        . "Content-Type: text/plain; charset=UTF-8\r\n";
    @mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $body, $headers);
}

/**
 * Handle an enquiry / quote form post. Returns [errors[], old input[]].
 * On success it flashes a message and redirects (never returns).
 */
function handle_enquiry_post(string $source, ?int $productId, string $redirectTo): array
{
    $old = [
        'name' => post('name'), 'organization' => post('organization'), 'email' => post('email'), 'phone' => post('phone'),
        'city' => post('city'), 'quantity' => post('quantity'), 'subject' => post('subject'), 'message' => post('message'),
        'product_id' => post('product_id'),
    ];
    $errors = [];
    if ($err = spam_check('enquiries')) {
        return [[$err], $old];
    }
    if ($old['name'] === '' || mb_strlen($old['name']) > 160) $errors[] = 'Please enter your name.';
    if (!valid_phone($old['phone'])) $errors[] = 'Please enter a valid phone number.';
    if ($old['email'] !== '' && !filter_var($old['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Please enter a valid email address.';
    if (mb_strlen($old['message']) > 5000) $errors[] = 'Message is too long.';
    if ($productId === null && (int) $old['product_id'] > 0) {
        $productId = (int) q_val('SELECT id FROM products WHERE id = ? AND is_active = 1', [(int) $old['product_id']]) ?: null;
    }
    if ($errors) {
        return [$errors, $old];
    }
    $qty = (int) $old['quantity'];
    q('INSERT INTO enquiries (product_id, source, name, organization, email, phone, city, quantity, subject, message, ip) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', [
        $productId, $source, mb_substr($old['name'], 0, 160), mb_substr($old['organization'], 0, 200), mb_substr($old['email'], 0, 160),
        mb_substr($old['phone'], 0, 30), mb_substr($old['city'], 0, 80), $qty > 0 ? min($qty, 100000) : null,
        mb_substr($old['subject'], 0, 200), $old['message'], client_ip(),
    ]);
    $productName = $productId ? (string) q_val('SELECT name FROM products WHERE id = ?', [$productId]) : '';
    notify_admin('New ' . ($source === 'quote' ? 'quote request' : 'enquiry') . ' from ' . $old['name'], [
        'Name' => $old['name'], 'Organisation' => $old['organization'], 'Phone' => $old['phone'], 'Email' => $old['email'],
        'City' => $old['city'], 'Product' => $productName ?: $old['subject'], 'Quantity' => $old['quantity'], 'Message' => $old['message'],
    ]);
    flash('success', 'Thank you, ' . $old['name'] . '! Your ' . ($source === 'quote' ? 'quote request' : 'enquiry') . ' has been received. Our team will contact you shortly.');
    redirect($redirectTo);
}

function page_meta(string $title = '', string $description = ''): array
{
    $company = setting('company_name', 'Vivora Healthcare');
    return [
        'title' => $title !== '' ? $title . ' | ' . $company : $company . ' – ' . setting('tagline', 'Better Equipment. Healthier Tomorrows.'),
        'description' => $description !== '' ? $description : setting('meta_description'),
    ];
}
