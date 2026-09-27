<?php
/**
 * Vivora Healthcare - application bootstrap.
 * Loads config, opens the database connection, starts the session and
 * provides the small helper library used by the website and the dashboard.
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
