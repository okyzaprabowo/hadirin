<?php
declare(strict_types=1);
require_once __DIR__ . '/config/bootstrap.php';

if (is_logged_in()) {
    redirect(base_url('dashboard.php'));
}

$db = get_db();
$activeEvents = $db->query(
    "SELECT id, event_name, event_date, location FROM events WHERE status = 'active' ORDER BY event_date ASC, event_name ASC"
)->fetchAll();

$errors = [];
$success = null;
$form = [
    'event_id'  => count($activeEvents) === 1 ? (int) $activeEvents[0]['id'] : 0,
    'nim'       => '',
    'full_name' => '',
    'phone'     => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Token keamanan tidak valid. Silakan muat ulang halaman dan coba lagi.';
    }

    $form['event_id']  = input_int($_POST, 'event_id', 0);
    $form['nim']       = input_str($_POST, 'nim');
    $form['full_name'] = input_str($_POST, 'full_name');
    $form['phone']     = input_str($_POST, 'phone');

    if ($form['event_id'] <= 0) {
        $errors[] = 'Event wajib dipilih.';
    } else {
        $eventCheck = $db->prepare("SELECT COUNT(*) FROM events WHERE id = :id AND status = 'active'");
        $eventCheck->execute(['id' => $form['event_id']]);
        if ((int) $eventCheck->fetchColumn() === 0) {
            $errors[] = 'Event yang dipilih tidak aktif atau tidak valid.';
        }
    }
    if (!preg_match('/^[A-Za-z0-9._-]{3,30}$/', $form['nim'])) {
        $errors[] = 'NIM harus 3-30 karakter, hanya huruf, angka, titik, strip, atau underscore.';
    }
    if ($form['full_name'] === '') {
        $errors[] = 'Nama lengkap mahasiswa wajib diisi.';
    }
    if ($form['phone'] !== '' && !preg_match('/^[0-9+\-\s()]{6,30}$/', $form['phone'])) {
        $errors[] = 'Format nomor HP tidak valid.';
    }

    if (!$errors) {
        $check = $db->prepare('SELECT COUNT(*) FROM users WHERE username = :username');
        $check->execute(['username' => $form['nim']]);
        if ((int) $check->fetchColumn() > 0) {
            $errors[] = 'NIM ini sudah terdaftar. Silakan login, atau hubungi panitia jika ini bukan Anda.';
        }
    }

    if (!$errors) {
        try {
            $db->beginTransaction();
            $now = date('Y-m-d H:i:s');

            $groupStmt = $db->prepare(
                'INSERT INTO guest_groups (event_id, group_name, contact_name, contact_phone, quota, status, notes, created_at, updated_at)
                 VALUES (:event_id, :group_name, :contact_name, :contact_phone, 3, \'active\', :notes, :created_at, :updated_at)'
            );
            $groupStmt->execute([
                'event_id'      => $form['event_id'],
                'group_name'    => $form['nim'] . ' - ' . $form['full_name'],
                'contact_name'  => $form['full_name'],
                'contact_phone' => $form['phone'] !== '' ? $form['phone'] : null,
                'notes'         => 'Pendaftaran mandiri via /daftar-wisuda',
                'created_at'    => $now,
                'updated_at'    => $now,
            ]);
            $groupId = (int) $db->lastInsertId();

            $guestStmt = $db->prepare(
                'INSERT INTO guests (group_id, full_name, created_at, updated_at) VALUES (:group_id, :full_name, :created_at, :updated_at)'
            );
            foreach ([$form['full_name'], 'Ayah ' . $form['full_name'], 'Ibu ' . $form['full_name']] as $guestName) {
                $guestStmt->execute([
                    'group_id'   => $groupId,
                    'full_name'  => $guestName,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            $activeSessions = $db->prepare("SELECT id FROM presensi_sessions WHERE event_id = :event_id AND status = 'active'");
            $activeSessions->execute(['event_id' => $form['event_id']]);
            $sessionStmt = $db->prepare(
                'INSERT INTO session_attendance (group_id, presensi_session_id) VALUES (:group_id, :presensi_session_id)'
            );
            foreach ($activeSessions->fetchAll(PDO::FETCH_COLUMN) as $presensiSessionId) {
                $sessionStmt->execute(['group_id' => $groupId, 'presensi_session_id' => $presensiSessionId]);
            }

            $userStmt = $db->prepare(
                'INSERT INTO users (username, password_hash, full_name, role, group_id, is_active, created_at, updated_at)
                 VALUES (:username, :password_hash, :full_name, \'mahasiswa\', :group_id, 1, :created_at, :updated_at)'
            );
            $userStmt->execute([
                'username'      => $form['nim'],
                'password_hash' => password_hash($form['nim'], PASSWORD_DEFAULT),
                'full_name'     => $form['full_name'],
                'group_id'      => $groupId,
                'created_at'    => $now,
                'updated_at'    => $now,
            ]);

            $db->commit();

            audit_log($db, null, 'self_register', 'guest_group', $groupId,
                'Pendaftaran mandiri mahasiswa "' . $form['full_name'] . '" (NIM ' . $form['nim'] . ') via /daftar-wisuda');

            $success = ['nim' => $form['nim']];
        } catch (Throwable $e) {
            $db->rollBack();
            error_log('[Hadirin] Self-registration failed: ' . $e->getMessage());
            $errors[] = 'Terjadi kesalahan saat menyimpan pendaftaran. Silakan coba lagi.';
        }
    }
}

$pageTitle = 'Daftar Wisuda';
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($pageTitle) ?> &middot; <?= e(APP_NAME) ?></title>
<?php require __DIR__ . '/includes/favicon_tags.php'; ?>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<link rel="stylesheet" href="<?= e(base_url('assets/css/style.css')) ?>?v=<?= @filemtime(APP_ROOT . '/assets/css/style.css') ?: '1' ?>">
</head>
<body class="login-page">
  <div class="login-card" style="max-width:480px;">
    <div class="text-center mb-4">
      <i class="bi bi-mortarboard login-logo"></i>
      <h1 class="login-title">Daftar Wisuda</h1>
      <p class="login-tagline"><?= e(APP_NAME) ?> &middot; <?= e(APP_TAGLINE) ?></p>
    </div>

    <?php if ($success): ?>
      <div class="text-center">
        <i class="bi bi-check-circle-fill text-success" style="font-size:3rem;"></i>
        <h4 class="mt-2 text-success">Pendaftaran Berhasil</h4>
        <p class="text-muted">Akun login Anda sudah aktif. Gunakan untuk masuk ke dashboard dan melakukan presensi saat acara.</p>
        <div class="alert alert-primary text-start">
          <div><strong>Username:</strong> <?= e($success['nim']) ?></div>
          <div><strong>Password:</strong> <?= e($success['nim']) ?> <span class="text-muted">(sama dengan NIM)</span></div>
        </div>
        <p class="text-muted small">Anda bisa mengganti password ini nanti lewat menu Pengaturan Akun, tapi tidak wajib.</p>
        <a href="<?= e(base_url('login.php')) ?>" class="btn btn-primary btn-lg w-100">Login Sekarang</a>
      </div>
    <?php else: ?>

      <?php if ($errors): ?>
        <div class="alert alert-danger">
          <?php foreach ($errors as $error): ?><div><?= e($error) ?></div><?php endforeach; ?>
        </div>
      <?php endif; ?>

      <?php if (!$activeEvents): ?>
        <div class="alert alert-warning text-center">Pendaftaran belum dibuka. Silakan hubungi panitia.</div>
      <?php else: ?>
        <form method="post" action="<?= e(base_url('daftar-wisuda.php')) ?>" autocomplete="off">
          <?= csrf_field() ?>

          <?php if (count($activeEvents) > 1): ?>
            <div class="mb-3">
              <label class="form-label">Event</label>
              <select name="event_id" class="form-select" required>
                <option value="">-- Pilih Event --</option>
                <?php foreach ($activeEvents as $ev): ?>
                  <option value="<?= (int) $ev['id'] ?>" <?= $form['event_id'] === (int) $ev['id'] ? 'selected' : '' ?>><?= e($ev['event_name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          <?php else: ?>
            <input type="hidden" name="event_id" value="<?= (int) $activeEvents[0]['id'] ?>">
            <div class="alert alert-light border text-center small mb-3">
              Mendaftar untuk: <strong><?= e($activeEvents[0]['event_name']) ?></strong>
            </div>
          <?php endif; ?>

          <div class="mb-3">
            <label class="form-label">NIM <span class="text-danger">*</span></label>
            <input type="text" name="nim" class="form-control form-control-lg" required value="<?= e($form['nim']) ?>">
            <div class="form-text">NIM ini akan menjadi username &amp; password login Anda.</div>
          </div>
          <div class="mb-3">
            <label class="form-label">Nama Lengkap Mahasiswa <span class="text-danger">*</span></label>
            <input type="text" name="full_name" class="form-control form-control-lg" required value="<?= e($form['full_name']) ?>">
          </div>
          <div class="mb-3">
            <label class="form-label">Nomor HP (opsional)</label>
            <input type="text" name="phone" class="form-control form-control-lg" value="<?= e($form['phone']) ?>">
          </div>

          <div class="alert alert-light border small">
            <i class="bi bi-info-circle"></i> Sistem otomatis mendaftarkan <strong>3 orang</strong>: Anda,
            Ayah, dan Ibu — sesuai kuota undangan wisuda.
          </div>

          <button type="submit" class="btn btn-primary btn-lg w-100">Daftar</button>
        </form>
        <p class="text-center text-muted small mt-3">Sudah daftar? <a href="<?= e(base_url('login.php')) ?>">Login di sini</a></p>
      <?php endif; ?>
    <?php endif; ?>
  </div>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
