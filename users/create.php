<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/bootstrap.php';
require_role('admin');

$db = get_db();
$pageTitle = 'Tambah Petugas';
$errors = [];
$form = ['username' => '', 'full_name' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Token keamanan tidak valid. Silakan coba lagi.';
    }

    $form['username'] = input_str($_POST, 'username');
    $form['full_name'] = input_str($_POST, 'full_name');
    $password = (string) ($_POST['password'] ?? '');
    $passwordConfirm = (string) ($_POST['password_confirm'] ?? '');

    if (!preg_match('/^[a-zA-Z0-9_.]{3,50}$/', $form['username'])) {
        $errors[] = 'Username harus 3-50 karakter, hanya huruf, angka, titik, dan underscore.';
    }
    if ($form['full_name'] === '') {
        $errors[] = 'Nama lengkap wajib diisi.';
    }
    if (strlen($password) < 8) {
        $errors[] = 'Password minimal 8 karakter.';
    } elseif ($password !== $passwordConfirm) {
        $errors[] = 'Konfirmasi password tidak sama.';
    }

    if (!$errors) {
        $check = $db->prepare('SELECT COUNT(*) FROM users WHERE username = :username');
        $check->execute(['username' => $form['username']]);
        if ((int) $check->fetchColumn() > 0) {
            $errors[] = 'Username sudah digunakan.';
        }
    }

    if (!$errors) {
        $now = date('Y-m-d H:i:s');
        $insert = $db->prepare(
            'INSERT INTO users (username, password_hash, full_name, role, is_active, created_at, updated_at)
             VALUES (:username, :password_hash, :full_name, :role, 1, :created_at, :updated_at)'
        );
        $insert->execute([
            'username'      => $form['username'],
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'full_name'     => $form['full_name'],
            'role'          => 'petugas',
            'created_at'    => $now,
            'updated_at'    => $now,
        ]);

        audit_log($db, (int) current_user()['id'], 'create_user', 'user', (int) $db->lastInsertId(),
            'Membuat akun petugas "' . $form['username'] . '"');

        flash_set('success', 'Akun petugas berhasil dibuat.');
        redirect(base_url('users/index.php'));
    }
}

require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/sidebar.php';
?>
<div class="container-fluid p-4">
  <h3 class="page-title mb-4"><i class="bi bi-person-plus"></i> Tambah Petugas</h3>

  <?php if ($errors): ?>
    <div class="alert alert-danger"><?php foreach ($errors as $error): ?><div><?= e($error) ?></div><?php endforeach; ?></div>
  <?php endif; ?>

  <form method="post" action="<?= e(base_url('users/create.php')) ?>" autocomplete="off">
    <?= csrf_field() ?>
    <div class="card">
      <div class="card-body">
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">Username <span class="text-danger">*</span></label>
            <input type="text" name="username" class="form-control" required value="<?= e($form['username']) ?>">
          </div>
          <div class="col-md-6">
            <label class="form-label">Nama Lengkap <span class="text-danger">*</span></label>
            <input type="text" name="full_name" class="form-control" required value="<?= e($form['full_name']) ?>">
          </div>
          <div class="col-md-6">
            <label class="form-label">Password <span class="text-danger">*</span></label>
            <input type="password" name="password" class="form-control" required minlength="8">
          </div>
          <div class="col-md-6">
            <label class="form-label">Konfirmasi Password <span class="text-danger">*</span></label>
            <input type="password" name="password_confirm" class="form-control" required minlength="8">
          </div>
        </div>
      </div>
    </div>
    <div class="mt-3">
      <button type="submit" class="btn btn-primary btn-lg"><i class="bi bi-save"></i> Simpan</button>
      <a href="<?= e(base_url('users/index.php')) ?>" class="btn btn-outline-secondary btn-lg">Batal</a>
    </div>
  </form>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
