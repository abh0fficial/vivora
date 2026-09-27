<?php
/** Notification bell: JSON unread count (GET ?format=count) and "mark all as read" (POST). */
require __DIR__ . '/../includes/auth.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $admin = require_admin();
    require_post();
    q('UPDATE admins SET notifications_seen_at = NOW() WHERE id = ?', [$admin['id']]);
    $back = (string) ($_SERVER['HTTP_REFERER'] ?? '');
    $path = (string) parse_url($back, PHP_URL_PATH);
    $query = (string) parse_url($back, PHP_URL_QUERY);
    // Only return to a page inside the dashboard.
    if ($path !== '' && strpos($path, base_path() . '/admin/') === 0 && basename($path) !== 'notifications.php') {
        redirect($path . ($query !== '' ? '?' . $query : ''));
    }
    redirect('admin/index.php');
}

header('Content-Type: application/json');
header('Cache-Control: no-store');
$admin = current_admin();
if (!$admin) {
    http_response_code(401);
    echo json_encode(['error' => 'login required']);
    exit;
}
$n = admin_notifications($admin);
echo json_encode(['unread' => $n['unread'], 'total' => $n['total']]);
