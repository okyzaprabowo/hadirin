<?php
declare(strict_types=1);
if (!defined('HADIRIN_APP')) {
    http_response_code(403);
    exit('Direct access is not allowed.');
}
$pageTitle = 'Akses Ditolak';
require APP_ROOT . '/includes/header.php';
if (is_logged_in()) {
    require APP_ROOT . '/includes/sidebar.php';
}
?>
<div class="container-fluid p-4 text-center">
  <i class="bi bi-shield-lock text-danger" style="font-size:4rem;"></i>
  <h2 class="mt-3">403 - Akses Ditolak</h2>
  <p class="text-muted">Anda tidak memiliki izin untuk mengakses halaman ini.</p>
  <a href="<?= e(base_url('dashboard.php')) ?>" class="btn btn-primary">Kembali ke Dashboard</a>
</div>
<?php require APP_ROOT . '/includes/footer.php'; ?>
