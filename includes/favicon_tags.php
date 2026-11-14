<?php
/**
 * Shared <head> favicon / home-screen icon tags. Included from every
 * standalone head section (header.php, login.php, daftar-wisuda.php).
 */
declare(strict_types=1);
if (!defined('HADIRIN_APP')) {
    http_response_code(403);
    exit('Direct access is not allowed.');
}
?>
<link rel="icon" type="image/svg+xml" href="<?= e(base_url('assets/img/icons/favicon.svg')) ?>">
<link rel="icon" type="image/png" sizes="32x32" href="<?= e(base_url('assets/img/icons/favicon-32x32.png')) ?>">
<link rel="icon" type="image/png" sizes="16x16" href="<?= e(base_url('assets/img/icons/favicon-16x16.png')) ?>">
<link rel="apple-touch-icon" sizes="180x180" href="<?= e(base_url('assets/img/icons/apple-touch-icon.png')) ?>">
<link rel="manifest" href="<?= e(base_url('site.webmanifest')) ?>">
<meta name="theme-color" content="#1E3A8A">
