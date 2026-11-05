<?php
/**
 * Application bootstrap. Include this file (via require_once) at the very
 * top of every entry-point script before any output is produced.
 */

declare(strict_types=1);

define('HADIRIN_APP', true);

error_reporting(E_ALL);
ini_set('display_errors', '0'); // never leak errors to the browser in production
ini_set('log_errors', '1');

define('APP_ROOT', dirname(__DIR__));
define('APP_NAME', 'Hadirin');
define('APP_TAGLINE', 'Smart Guest Management & Barcode Check-in System');

if (file_exists(APP_ROOT . '/vendor/autoload.php')) {
    require_once APP_ROOT . '/vendor/autoload.php';
}

require_once APP_ROOT . '/config/database.php';
require_once APP_ROOT . '/config/csrf.php';
require_once APP_ROOT . '/config/auth.php';
require_once APP_ROOT . '/includes/functions.php';
require_once APP_ROOT . '/includes/barcode_helper.php';

hadirin_session_start();
hadirin_enforce_session_timeout();
