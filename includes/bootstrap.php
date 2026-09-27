<?php
/**
 * Vivora Healthcare - application bootstrap.
 * Loads config, opens the database connection, starts the session and
 * provides the small helper library used by the dashboard.
 */

declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__));
define('APP_VERSION', '1.0.0');

error_reporting(E_ALL);
ini_set('display_errors', '0');
date_default_timezone_set('Asia/Kolkata');

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/schema.php';

$configFile = APP_ROOT . '/config.php';
if (!is_file($configFile)) {
    // Not installed yet: send the visitor to the installer.
    header('Location: ' . base_url('install.php'));
    exit;
}
require $configFile;

if (defined('APP_DEBUG') && APP_DEBUG) {
    ini_set('display_errors', '1');
}

start_session();

/** Show a readable error (and log it) instead of a blank page when something fails. */
set_exception_handler(function (Throwable $e): void {
    error_log('Vivora error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    if (!headers_sent()) {
        http_response_code(500);
    }
    $detail = (defined('APP_DEBUG') && APP_DEBUG) || !empty($_SESSION['admin_id']) ? $e->getMessage() : '';
    echo '<div style="font-family:system-ui,sans-serif;max-width:640px;margin:60px auto;padding:24px;border:1px solid #f1c0c0;border-radius:12px;background:#fff5f5">'
        . '<h2 style="margin-top:0;color:#b42318">Sorry, that could not be saved.</h2>'
        . ($detail !== '' ? '<p><strong>Details:</strong> ' . htmlspecialchars($detail, ENT_QUOTES, 'UTF-8') . '</p>' : '')
        . '<p>Go back and try again. If it keeps happening, open <a href="' . htmlspecialchars(base_url('admin/system.php'), ENT_QUOTES, 'UTF-8') . '">System Check</a> and click <em>Repair database</em>.</p></div>';
});
