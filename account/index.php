<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/bootstrap.php';
require_login();

$db = get_db();
$user = current_user();
$pageTitle = 'Pengaturan Akun';
$errors = [];
$success = null;

$stmt = $db->prepare('SELECT * FROM users WHERE id = :id');
$stmt->execute(['id' => $user['id']]);
$me = $stmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Token keamanan tidak valid. Silakan coba lagi.';
    }

    $action = input_str($_POST, 'form_action');

    if ($action === 'update_profile') {
        $fullName = input_str($_POST, 'full_name');
        if ($fullName === '') {
            $errors[] = 'Nama lengkap wajib diisi.';
        }
        if (!$errors) {
            $db->prepare('UPDATE users SET full_name = :full_name, updated_at = :updated_at WHERE id = :id')
                ->execute(['full_name' => $fullName, 'updated_at' => date('Y-m-d H:i:s'), 'id' => $user['id']]);
            $_SESSION['full_name'] = $fullName;
            audit_log($db, (int) $user['id'], 'update_profile', 'user', (int) $user['id'], 'Memperbarui nama profil');
            flash_set('success', 'Profil berhasil diperbarui.');
            redirect(base_url('account/index.php'));
        }
    }

    if ($action === 'change_password') {
        $currentPassword = (string) ($_POST['current_password'] ?? '');
        $newPassword = (string) ($_POST['new_password'] ?? '');
        $newPasswordConfirm = (string) ($_POST['new_password_confirm'] ?? '');

        if (!password_verify($currentPassword, $me['password_hash'])) {
            $errors[] = 'Password saat ini tidak sesuai.';
        }
        if (strlen($newPassword) < 8) {
            $errors[] = 'Password baru minimal 8 karakter.';
        } elseif ($newPassword !== $newPasswordConfirm) {
            $errors[] = 'Konfirmasi password baru tidak sama.';
        }

        if (!$errors) {
            $db->prepare('UPDATE users SET password_hash = :hash, updated_at = :updated_at WHERE id = :id')
                ->execute(['hash' => password_hash($newPassword, PASSWORD_DEFAULT), 'updated_at' => date('Y-m-d H:i:s'), 'id' => $user['id']]);
            audit_log($db, (int) $user['id'], 'change_password', 'user', (int) $user['id'], 'Mengubah password sendiri');
            flash_set('success', 'Password berhasil diubah. Silakan login kembali.');
            logout_user();
            redirect(base_url('login.php'));
        }
    }
}

require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/sidebar.php';
?>
<div class="container-fluid p-4">
  <h3 class="page-title mb-4"><i class="bi bi-gear"></i> Pengaturan Akun</h3>

  <?php if ($errors): ?>
    <div class="alert alert-danger"><?php foreach ($errors as $error): ?><div><?= e($error) ?></div><?php endforeach; ?></div>
  <?php endif; ?>

  <div class="row g-3">
    <div class="col-md-6">
      <div class="card">
        <div class="card-header">Profil</div>
        <div class="card-body">
          <form method="post" action="<?= e(base_url('account/index.php')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="form_action" value="update_profile">
            <div class="mb-3">
              <label class="form-label">Username</label>
              <input type="text" class="form-control" value="<?= e($me['username']) ?>" disabled>
            </div>
            <div class="mb-3">
              <label class="form-label">Nama Lengkap</label>
              <input type="text" name="full_name" class="form-control" required value="<?= e($me['full_name']) ?>">
            </div>
            <div class="mb-3">
              <label class="form-label">Role</label>
              <input type="text" class="form-control" value="<?= e(ucfirst($me['role'])) ?>" disabled>
            </div>
            <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> Simpan Profil</button>
          </form>
        </div>
      </div>
    </div>

    <div class="col-md-6">
      <div class="card">
        <div class="card-header">Ubah Password</div>
        <div class="card-body">
          <form method="post" action="<?= e(base_url('account/index.php')) ?>" autocomplete="off">
            <?= csrf_field() ?>
            <input type="hidden" name="form_action" value="change_password">
            <div class="mb-3">
              <label class="form-label">Password Saat Ini</label>
              <input type="password" name="current_password" class="form-control" required>
            </div>
            <div class="mb-3">
              <label class="form-label">Password Baru</label>
              <input type="password" name="new_password" class="form-control" required minlength="8">
            </div>
            <div class="mb-3">
              <label class="form-label">Konfirmasi Password Baru</label>
              <input type="password" name="new_password_confirm" class="form-control" required minlength="8">
            </div>
            <button type="submit" class="btn btn-warning"><i class="bi bi-key"></i> Ubah Password</button>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
