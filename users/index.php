<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/bootstrap.php';
require_role('admin');

$db = get_db();
$pageTitle = 'Manajemen Petugas';

$users = $db->query(
    "SELECT * FROM users WHERE role = 'petugas' ORDER BY created_at DESC"
)->fetchAll();

require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/sidebar.php';
?>
<div class="container-fluid p-4">
  <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <h3 class="page-title mb-0"><i class="bi bi-person-badge"></i> Manajemen Petugas</h3>
    <a href="<?= e(base_url('users/create.php')) ?>" class="btn btn-primary"><i class="bi bi-plus-circle"></i> Tambah Petugas</a>
  </div>

  <div class="card">
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle">
          <thead><tr><th>Username</th><th>Nama Lengkap</th><th>Status</th><th>Dibuat</th><th class="text-end">Aksi</th></tr></thead>
          <tbody>
          <?php foreach ($users as $u): ?>
            <tr>
              <td><?= e($u['username']) ?></td>
              <td><?= e($u['full_name']) ?></td>
              <td><?= (int) $u['is_active'] === 1 ? '<span class="badge text-bg-success">Aktif</span>' : '<span class="badge text-bg-secondary">Nonaktif</span>' ?></td>
              <td><?= e(format_datetime($u['created_at'])) ?></td>
              <td class="text-end">
                <a href="<?= e(base_url('users/edit.php?id=' . $u['id'])) ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil"></i> Kelola</a>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$users): ?>
            <tr><td colspan="5" class="text-center text-muted py-4">Belum ada akun petugas.</td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
