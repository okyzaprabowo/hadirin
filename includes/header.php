<?php
/**
 * Shared HTML head + top navbar. Expects $pageTitle to be set by the caller.
 */
declare(strict_types=1);
if (!defined('HADIRIN_APP')) {
    http_response_code(403);
    exit('Direct access is not allowed.');
}
$pageTitle = $pageTitle ?? APP_NAME;
$user = current_user();
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($pageTitle) ?> &middot; <?= e(APP_NAME) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<link rel="stylesheet" href="<?= e(base_url('assets/css/style.css')) ?>?v=<?= @filemtime(APP_ROOT . '/assets/css/style.css') ?: '1' ?>">
</head>
<body>
<?php if ($user): ?>
<nav class="navbar navbar-expand topbar">
  <div class="container-fluid">
    <button class="btn btn-link text-white d-lg-none" type="button" id="sidebarToggle">
      <i class="bi bi-list fs-3"></i>
    </button>
    <a class="navbar-brand topbar-brand" href="<?= e(base_url('dashboard.php')) ?>">
      <i class="bi bi-qr-code-scan"></i> <?= e(APP_NAME) ?>
    </a>
    <div class="ms-auto d-flex align-items-center gap-3">
      <span class="text-white-50 d-none d-md-inline"><?= e(ucfirst($user['role'])) ?></span>
      <div class="dropdown">
        <button class="btn btn-sm btn-outline-light dropdown-toggle" type="button" data-bs-toggle="dropdown">
          <i class="bi bi-person-circle"></i> <?= e($user['full_name']) ?>
        </button>
        <ul class="dropdown-menu dropdown-menu-end">
          <li><a class="dropdown-item" href="<?= e(base_url('account/index.php')) ?>"><i class="bi bi-gear"></i> Pengaturan Akun</a></li>
          <li><hr class="dropdown-divider"></li>
          <li><a class="dropdown-item text-danger" href="<?= e(base_url('logout.php')) ?>"><i class="bi bi-box-arrow-right"></i> Logout</a></li>
        </ul>
      </div>
    </div>
  </div>
</nav>
<?php endif; ?>
<?php if ($user): ?>
<div class="app-wrapper">
<?php endif; ?>
