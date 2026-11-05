<?php
/**
 * Role-based sidebar navigation.
 */
declare(strict_types=1);
if (!defined('HADIRIN_APP')) {
    http_response_code(403);
    exit('Direct access is not allowed.');
}
$user = current_user();
if (!$user) {
    return;
}
$scriptPath = str_replace('\\', '/', $_SERVER['SCRIPT_NAME']);

function nav_active(string $scriptPath, string $needle): string
{
    return str_contains($scriptPath, $needle) ? 'active' : '';
}
?>
<aside class="sidebar" id="appSidebar">
  <nav class="nav flex-column p-2">
    <?php if ($user['role'] === 'admin'): ?>
      <a class="nav-link <?= nav_active($scriptPath, '/dashboard.php') ?>" href="<?= e(base_url('dashboard.php')) ?>">
        <i class="bi bi-speedometer2"></i> Dashboard
      </a>
      <a class="nav-link <?= nav_active($scriptPath, '/events/') ?>" href="<?= e(base_url('events/index.php')) ?>">
        <i class="bi bi-calendar-event"></i> Event
      </a>
      <a class="nav-link <?= nav_active($scriptPath, '/groups/') ?>" href="<?= e(base_url('groups/index.php')) ?>">
        <i class="bi bi-people"></i> Grup Undangan
      </a>
      <a class="nav-link <?= nav_active($scriptPath, '/guests/') ?>" href="<?= e(base_url('guests/index.php')) ?>">
        <i class="bi bi-person-lines-fill"></i> Data Tamu
      </a>
      <a class="nav-link <?= nav_active($scriptPath, '/groups/barcode.php') ?>" href="<?= e(base_url('groups/index.php')) ?>">
        <i class="bi bi-upc"></i> Barcode
      </a>
      <a class="nav-link <?= nav_active($scriptPath, '/monitoring/') ?>" href="<?= e(base_url('monitoring/index.php')) ?>">
        <i class="bi bi-activity"></i> Monitoring Kehadiran
      </a>
      <a class="nav-link <?= nav_active($scriptPath, '/reports/') ?>" href="<?= e(base_url('reports/index.php')) ?>">
        <i class="bi bi-file-earmark-bar-graph"></i> Laporan
      </a>
      <a class="nav-link <?= nav_active($scriptPath, '/users/') ?>" href="<?= e(base_url('users/index.php')) ?>">
        <i class="bi bi-person-badge"></i> Manajemen Petugas
      </a>
      <a class="nav-link <?= nav_active($scriptPath, '/account/') ?>" href="<?= e(base_url('account/index.php')) ?>">
        <i class="bi bi-gear"></i> Pengaturan Akun
      </a>
      <hr class="text-white-50">
      <a class="nav-link text-danger-emphasis" href="<?= e(base_url('logout.php')) ?>">
        <i class="bi bi-box-arrow-right"></i> Logout
      </a>
    <?php else: ?>
      <a class="nav-link <?= nav_active($scriptPath, '/dashboard.php') ?>" href="<?= e(base_url('dashboard.php')) ?>">
        <i class="bi bi-speedometer2"></i> Dashboard Petugas
      </a>
      <a class="nav-link <?= nav_active($scriptPath, '/scanner/index.php') ?>" href="<?= e(base_url('scanner/index.php')) ?>">
        <i class="bi bi-upc-scan"></i> Scan Barcode
      </a>
      <a class="nav-link <?= nav_active($scriptPath, '/scanner/history.php') ?>" href="<?= e(base_url('scanner/history.php')) ?>">
        <i class="bi bi-clock-history"></i> Riwayat Scan
      </a>
      <a class="nav-link <?= nav_active($scriptPath, '/account/') ?>" href="<?= e(base_url('account/index.php')) ?>">
        <i class="bi bi-gear"></i> Pengaturan Akun
      </a>
      <hr class="text-white-50">
      <a class="nav-link text-danger-emphasis" href="<?= e(base_url('logout.php')) ?>">
        <i class="bi bi-box-arrow-right"></i> Logout
      </a>
    <?php endif; ?>
  </nav>
</aside>
<main class="app-main">
  <?php foreach (flash_get_all() as $flash): ?>
    <div class="alert alert-<?= e($flash['type']) ?> alert-dismissible fade show m-3 mb-0" role="alert">
      <?= e($flash['message']) ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  <?php endforeach; ?>
