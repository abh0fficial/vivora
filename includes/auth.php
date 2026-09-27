<?php
/**
 * Dashboard authentication: session login, idle timeout, brute-force throttle.
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

const LOGIN_MAX_ATTEMPTS   = 5;     // failed attempts allowed ...
const LOGIN_WINDOW_MINUTES = 15;    // ... within this many minutes per IP
const SESSION_IDLE_SECONDS = 7200;  // auto logout after 2 hours of inactivity

function current_admin(): ?array
{
    static $admin = false;
    if ($admin !== false) {
        return $admin;
    }
    $admin = null;
    if (!empty($_SESSION['admin_id'])) {
        if (time() - (int) ($_SESSION['last_seen'] ?? 0) > SESSION_IDLE_SECONDS) {
            logout_admin();
            return null;
        }
        $_SESSION['last_seen'] = time();
        try {
            $admin = q_row('SELECT id, username, full_name, email, last_login_at, notifications_seen_at FROM admins WHERE id = ?', [(int) $_SESSION['admin_id']]);
        } catch (PDOException $e) {
            // Database not upgraded yet (System Check → Repair database fixes it).
            $admin = q_row('SELECT id, username, full_name, email, last_login_at, NULL AS notifications_seen_at FROM admins WHERE id = ?', [(int) $_SESSION['admin_id']]);
        }
    }
    return $admin;
}

function require_admin(): array
{
    $admin = current_admin();
    if (!$admin) {
        $back = $_SERVER['REQUEST_URI'] ?? '';
        redirect('admin/login.php' . ($back !== '' ? '?next=' . rawurlencode($back) : ''));
    }
    header('X-Robots-Tag: noindex, nofollow');
    header('Cache-Control: no-store');
    return $admin;
}

function login_throttled(): bool
{
    $count = (int) q_val(
        'SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND attempted_at > (NOW() - INTERVAL ' . LOGIN_WINDOW_MINUTES . ' MINUTE)',
        [client_ip()]
    );
    return $count >= LOGIN_MAX_ATTEMPTS;
}

function attempt_login(string $username, string $password): bool
{
    $row = q_row('SELECT id, password_hash FROM admins WHERE username = ?', [$username]);
    if (!$row || !password_verify($password, $row['password_hash'])) {
        q('INSERT INTO login_attempts (ip, username) VALUES (?, ?)', [client_ip(), mb_substr($username, 0, 60)]);
        return false;
    }
    if (password_needs_rehash($row['password_hash'], PASSWORD_DEFAULT)) {
        q('UPDATE admins SET password_hash = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), $row['id']]);
    }
    session_regenerate_id(true);
    $_SESSION['admin_id']  = (int) $row['id'];
    $_SESSION['last_seen'] = time();
    q('UPDATE admins SET last_login_at = NOW() WHERE id = ?', [$row['id']]);
    q('DELETE FROM login_attempts WHERE ip = ?', [client_ip()]);
    // Housekeeping: drop stale attempts.
    q('DELETE FROM login_attempts WHERE attempted_at < (NOW() - INTERVAL 1 DAY)');
    log_activity('login', 'Signed in', (int) $row['id']);
    return true;
}

function logout_admin(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

function log_activity(string $action, string $details = '', ?int $adminId = null): void
{
    $adminId = $adminId ?? (int) ($_SESSION['admin_id'] ?? 0);
    q('INSERT INTO activity_log (admin_id, action, details, ip) VALUES (?, ?, ?, ?)', [
        $adminId ?: null, $action, mb_substr($details, 0, 400), client_ip(),
    ]);
}

/** Only accept POST for state-changing admin endpoints. */
function require_post(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        http_response_code(405);
        exit('Method not allowed');
    }
    csrf_check();
}

/**
 * Live notifications for the header bell: new enquiries, open service tickets,
 * low-stock products and quotations about to expire. Items newer than the
 * admin's "mark all as read" time count as unread.
 */
function admin_notifications(array $admin): array
{
    $items = [];
    foreach (q_all("SELECT e.id, e.name, e.organization, e.subject, e.created_at, p.name product FROM enquiries e
        LEFT JOIN products p ON p.id = e.product_id WHERE e.status = 'new' ORDER BY e.created_at DESC LIMIT 10") as $r) {
        $items[] = ['icon' => 'inbox', 'color' => 'primary', 'time' => $r['created_at'], 'url' => 'enquiry-view.php?id=' . $r['id'],
            'title' => 'New enquiry from ' . $r['name'], 'text' => trim(($r['product'] ?: $r['subject']) . ($r['organization'] ? ' · ' . $r['organization'] : ''), ' ·')];
    }
    $types = service_types();
    foreach (q_all("SELECT id, ticket_no, request_type, priority, equipment, organization, name, created_at FROM service_requests
        WHERE status = 'open' ORDER BY created_at DESC LIMIT 10") as $r) {
        $items[] = ['icon' => 'tool', 'color' => in_array($r['priority'], ['high', 'urgent'], true) ? 'danger' : 'warning', 'time' => $r['created_at'],
            'url' => 'service-view.php?id=' . $r['id'], 'title' => $r['ticket_no'] . ' · ' . ($types[$r['request_type']] ?? $r['request_type']) . ($r['priority'] === 'urgent' ? ' (urgent)' : ''),
            'text' => trim(($r['equipment'] ?: 'Equipment') . ' · ' . ($r['organization'] ?: $r['name']), ' ·')];
    }
    foreach (q_all('SELECT p.id, p.name, p.stock_qty, p.min_stock,
            COALESCE((SELECT MAX(m.created_at) FROM stock_movements m WHERE m.product_id = p.id), p.updated_at) changed_at
        FROM products p WHERE p.is_active = 1 AND p.min_stock > 0 AND p.stock_qty <= p.min_stock ORDER BY changed_at DESC LIMIT 10') as $r) {
        $items[] = ['icon' => 'alert-triangle', 'color' => 'danger', 'time' => $r['changed_at'], 'url' => 'product-form.php?id=' . $r['id'],
            'title' => ($r['stock_qty'] <= 0 ? 'Out of stock: ' : 'Low stock: ') . $r['name'], 'text' => (int) $r['stock_qty'] . ' left · minimum ' . (int) $r['min_stock']];
    }
    foreach (q_all("SELECT id, quote_no, customer_org, customer_name, valid_until, updated_at FROM quotations
        WHERE status = 'sent' AND valid_until BETWEEN CURDATE() AND (CURDATE() + INTERVAL 3 DAY) ORDER BY valid_until LIMIT 10") as $r) {
        $items[] = ['icon' => 'clock', 'color' => 'info', 'time' => $r['updated_at'], 'url' => 'quotation-view.php?id=' . $r['id'],
            'title' => 'Quotation ' . $r['quote_no'] . ' expires ' . fmt_date($r['valid_until']), 'text' => 'Follow up with ' . ($r['customer_org'] ?: $r['customer_name'])];
    }
    usort($items, fn($a, $b) => strcmp((string) $b['time'], (string) $a['time']));
    $seen = $admin['notifications_seen_at'] ?? null;
    $unread = 0;
    foreach ($items as &$it) {
        $it['unread'] = !$seen || (string) $it['time'] > (string) $seen;
        $unread += $it['unread'] ? 1 : 0;
    }
    unset($it);
    return ['items' => array_slice($items, 0, 15), 'unread' => $unread, 'total' => count($items)];
}
