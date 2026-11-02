<?php
declare(strict_types=1);
require_once __DIR__ . '/config/bootstrap.php';

if (is_logged_in()) {
    $userId = (int) $_SESSION['user_id'];
    try {
        audit_log(get_db(), $userId, 'logout', 'user', $userId, 'Logout');
    } catch (Throwable $e) {
        // ignore audit failures on logout
    }
}

logout_user();
redirect(base_url('login.php'));
