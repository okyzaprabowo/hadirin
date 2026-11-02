<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/bootstrap.php';
require_role('admin');

$db = get_db();
$id = input_int($_GET, 'id', 0) ?: input_int($_POST, 'id', 0);

$stmt = $db->prepare('SELECT * FROM users WHERE id = :id');
$stmt->execute(['id' => $id]);
$targetUser = $stmt->fetch();

if (!$targetUser || $targetUser['role'] !== 'petugas') {
    flash_set('danger', 'Akun petugas tidak ditemukan.');
    redirect(base_url('users/index.php'));
}

$pageTitle = 'Kelola Petugas';
$errors = [];
$form = ['full_name' => $targetUser['full_name']];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Token keamanan tidak valid. Silakan coba lagi.';
    }

    $action = input_str($_POST, 'form_action', 'update');

    if ($action === 'toggle_active') {
        $newStatus = (int) $targetUser['is_active'] === 1 ? 0 : 1;
        $db->prepare('UPDATE users SET is_active = :is_active, updated_at = :updated_at WHERE id = :id')
            ->execute(['is_active' => $newStatus, 'updated_at' => date('Y-m-d H:i:s'), 'id' => $id]);

        audit_log($db, (int) current_user()['id'], $newStatus ? 'activate_user' : 'deactivate_user', 'user', $id,
            ($newStatus ? 'Mengaktifkan' : 'Menonaktifkan') . ' akun petugas "' . $targetUser['username'] . '"');

        flash_set('success', 'Status akun berhasil diperbarui.');
        redirect(base_url('users/edit.php?id=' . $id));
    }

    if ($action === 'reset_password') {
        $password = (string) ($_POST['password'] ?? '');
        $passwordConfirm = (string) ($_POST['password_confirm'] ?? '');

        if (strlen($password) < 8) {
            $errors[] = 'Password minimal 8 karakter.';
        } elseif ($password !== $passwordConfirm) {
            $errors[] = 'Konfirmasi password tidak sama.';
        }

        if (!$errors) {
            $db->prepare('UPDATE users SET password_hash = :hash, updated_at = :updated_at WHERE id = :id')
                ->execute(['hash' => password_hash($password, PASSWORD_DEFAULT), 'updated_at' => date('Y-m-d H:i:s'), 'id' => $id]);

            audit_log($db, (int) current_user()['id'], 'reset_password', 'user', $id, 'Reset password akun petugas "' . $targetUser['username'] . '"');
            flash_set('success', 'Password berhasil direset.');
            redirect(base_url('users/edit.php?id=' . $id));
        }
    }

    if ($action === 'update') {
        $form['full_name'] = input_str($_POST, 'full_name');
        if ($form['full_name'] === '') {
            $errors[] = 'Nama lengkap wajib diisi.';
        }

        if (!$errors) {
            $db->prepare('UPDATE users SET full_name = :full_name, updated_at = :updated_at WHERE id = :id')
                ->execute(['full_name' => $form['full_name'], 'updated_at' => date('Y-m-d H:i:s'), 'id' => $id]);

            audit_log($db, (int) current_user()['id'], 'update_user', 'user', $id, 'Memperbarui data akun petugas "' . $targetUser['username'] . '"');
            flash_set('success', 'Data petugas berhasil diperbarui.');
            redirect(base_url('users/edit.php?id=' . $id));
        }
    }
}

require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/sidebar.php';
?>
<div class="container-fluid p-4">
  <h3 class="page-title mb-4"><i class="bi bi-person-badge"></i> Kelola Petugas: <?= e($targetUser['username']) ?></h3>

  <?php if ($errors): ?>
    <div class="alert alert-danger"><?php foreach ($errors as $error): ?><div><?= e($error) ?></div><?php endforeach; ?></div>
  <?php endif; ?>

  <div class="row g-3">
    <div class="col-md-6">
      <div class="card mb-3">
        <div class="card-header">Informasi Akun</div>
        <div class="card-body">
          <form method="post" action="<?= e(base_url('users/edit.php?id=' . $id)) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= (int) $id ?>">
            <input type="hidden" name="form_action" value="update">
            <div class="mb-3">
              <label class="form-label">Username</label>
              <input type="text" class="form-control" value="<?= e($targetUser['username']) ?>" disabled>
            </div>
            <div class="mb-3">
              <label class="form-label">Nama Lengkap</label>
              <input type="text" name="full_name" class="form-control" required value="<?= e($form['full_name']) ?>">
            </div>
            <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> Simpan</button>
          </form>
        </div>
      </div>

      <div class="card">
        <div class="card-header">Status Akun</div>
        <div class="card-body d-flex justify-content-between align-items-center">
          <span>Status saat ini:
            <?= (int) $targetUser['is_active'] === 1 ? '<span class="badge text-bg-success">Aktif</span>' : '<span class="badge text-bg-secondary">Nonaktif</span>' ?>
          </span>
          <form method="post" action="<?= e(base_url('users/edit.php?id=' . $id)) ?>" onsubmit="return confirm('Ubah status akun ini?');">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= (int) $id ?>">
            <input type="hidden" name="form_action" value="toggle_active">
            <button type="submit" class="btn btn-sm <?= (int) $targetUser['is_active'] === 1 ? 'btn-outline-danger' : 'btn-outline-success' ?>">
              <?= (int) $targetUser['is_active'] === 1 ? 'Nonaktifkan' : 'Aktifkan' ?>
            </button>
          </form>
        </div>
      </div>
    </div>

    <div class="col-md-6">
      <div class="card">
        <div class="card-header">Reset Password</div>
        <div class="card-body">
          <form method="post" action="<?= e(base_url('users/edit.php?id=' . $id)) ?>" autocomplete="off">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= (int) $id ?>">
            <input type="hidden" name="form_action" value="reset_password">
            <div class="mb-3">
              <label class="form-label">Password Baru</label>
              <input type="password" name="password" class="form-control" required minlength="8">
            </div>
            <div class="mb-3">
              <label class="form-label">Konfirmasi Password Baru</label>
              <input type="password" name="password_confirm" class="form-control" required minlength="8">
            </div>
            <button type="submit" class="btn btn-warning"><i class="bi bi-key"></i> Reset Password</button>
          </form>
        </div>
      </div>
    </div>
  </div>

  <div class="mt-3">
    <a href="<?= e(base_url('users/index.php')) ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Kembali</a>
  </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
