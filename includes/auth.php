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
        $admin = q_row('SELECT id, username, full_name, email, last_login_at FROM admins WHERE id = ?', [(int) $_SESSION['admin_id']]);
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
