<?php
declare(strict_types=1);
require_once __DIR__ . '/config/bootstrap.php';

redirect(is_logged_in() ? base_url('dashboard.php') : base_url('login.php'));
