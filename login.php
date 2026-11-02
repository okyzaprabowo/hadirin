<?php
declare(strict_types=1);
require_once __DIR__ . '/config/bootstrap.php';

if (is_logged_in()) {
    redirect(base_url('dashboard.php'));
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Token keamanan tidak valid. Silakan coba lagi.';
    } else {
        $username = input_str($_POST, 'username');
        $password = (string) ($_POST['password'] ?? '');
        $ip = get_client_ip();

        if ($username === '' || $password === '') {
            $errors[] = 'Username dan password wajib diisi.';
        } else {
            $db = get_db();

            if (!login_rate_limit_check($db, $username, $ip)) {
                $errors[] = 'Terlalu banyak percobaan login. Silakan coba lagi dalam 15 menit.';
            } else {
                $user = attempt_login($db, $username, $password);

                if ($user) {
                    login_record_attempt($db, $username, $ip, true);
                    login_user($user);
                    audit_log($db, (int) $user['id'], 'login', 'user', (int) $user['id'], 'Login berhasil');
                    redirect(base_url('dashboard.php'));
                } else {
                    login_record_attempt($db, $username, $ip, false);
                    $errors[] = 'Username atau password salah.';
                }
            }
        }
    }
}

$pageTitle = 'Login';
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($pageTitle) ?> &middot; <?= e(APP_NAME) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<link rel="stylesheet" href="<?= e(base_url('assets/css/style.css')) ?>?v=<?= @filemtime(APP_ROOT . '/assets/css/style.css') ?: '1' ?>">
</head>
<body class="login-page">
  <div class="login-card">
    <div class="text-center mb-4">
      <i class="bi bi-qr-code-scan login-logo"></i>
      <h1 class="login-title"><?= e(APP_NAME) ?></h1>
      <p class="login-tagline"><?= e(APP_TAGLINE) ?></p>
    </div>

    <?php if ($errors): ?>
      <div class="alert alert-danger">
        <?php foreach ($errors as $error): ?>
          <div><?= e($error) ?></div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <form method="post" action="<?= e(base_url('login.php')) ?>" autocomplete="off">
      <?= csrf_field() ?>
      <div class="mb-3">
        <label class="form-label">Username</label>
        <input type="text" name="username" class="form-control form-control-lg" required autofocus value="<?= e($_POST['username'] ?? '') ?>">
      </div>
      <div class="mb-3">
        <label class="form-label">Password</label>
        <input type="password" name="password" class="form-control form-control-lg" required>
      </div>
      <button type="submit" class="btn btn-primary btn-lg w-100">Login</button>
    </form>
  </div>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
