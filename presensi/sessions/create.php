<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/bootstrap.php';
require_role('admin');

$db = get_db();
$pageTitle = 'Tambah Sesi Presensi';

$allEvents = $db->query('SELECT id, event_name FROM events ORDER BY event_name ASC')->fetchAll();

$errors = [];
$form = [
    'event_id'   => input_int($_GET, 'event_id', 0),
    'name'       => '',
    'sort_order' => 0,
    'status'     => 'active',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Token keamanan tidak valid. Silakan coba lagi.';
    }

    $form['event_id']   = input_int($_POST, 'event_id', 0);
    $form['name']       = input_str($_POST, 'name');
    $form['sort_order'] = input_int($_POST, 'sort_order', 0);
    $form['status']     = input_str($_POST, 'status', 'active');

    if ($form['event_id'] <= 0) {
        $errors[] = 'Event wajib dipilih.';
    } else {
        $eventCheck = $db->prepare('SELECT COUNT(*) FROM events WHERE id = :id');
        $eventCheck->execute(['id' => $form['event_id']]);
        if ((int) $eventCheck->fetchColumn() === 0) {
            $errors[] = 'Event yang dipilih tidak valid.';
        }
    }
    if ($form['name'] === '') {
        $errors[] = 'Nama sesi wajib diisi.';
    }
    if (!in_array($form['status'], ['active', 'inactive'], true)) {
        $errors[] = 'Status tidak valid.';
    }

    if (!$errors) {
        try {
            $db->beginTransaction();
            $now = date('Y-m-d H:i:s');

            $insert = $db->prepare(
                'INSERT INTO presensi_sessions (event_id, name, sort_order, status, created_at, updated_at)
                 VALUES (:event_id, :name, :sort_order, :status, :created_at, :updated_at)'
            );
            $insert->execute([
                'event_id'   => $form['event_id'],
                'name'       => $form['name'],
                'sort_order' => $form['sort_order'],
                'status'     => $form['status'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $sessionId = (int) $db->lastInsertId();

            // Beri setiap grup yang sudah ada di event ini baris presensi untuk sesi baru
            $backfill = $db->prepare(
                'INSERT INTO session_attendance (group_id, presensi_session_id, created_at, updated_at)
                 SELECT id, :session_id, :created_at, :updated_at FROM guest_groups WHERE event_id = :event_id'
            );
            $backfill->execute([
                'session_id' => $sessionId,
                'event_id'   => $form['event_id'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $db->commit();

            audit_log($db, (int) current_user()['id'], 'create_presensi_session', 'presensi_session', $sessionId,
                'Membuat sesi presensi "' . $form['name'] . '" pada event #' . $form['event_id']);

            flash_set('success', 'Sesi presensi berhasil dibuat.');
            redirect(base_url('presensi/sessions/index.php'));
        } catch (Throwable $e) {
            $db->rollBack();
            error_log('[Hadirin] Failed to create presensi session: ' . $e->getMessage());
            $errors[] = 'Terjadi kesalahan saat menyimpan data. Silakan coba lagi.';
        }
    }
}

require __DIR__ . '/../../includes/header.php';
require __DIR__ . '/../../includes/sidebar.php';
?>
<div class="container-fluid p-4">
  <h3 class="page-title mb-4"><i class="bi bi-plus-circle"></i> Tambah Sesi Presensi</h3>

  <?php if ($errors): ?>
    <div class="alert alert-danger"><?php foreach ($errors as $error): ?><div><?= e($error) ?></div><?php endforeach; ?></div>
  <?php endif; ?>

  <?php if (!$allEvents): ?>
    <div class="alert alert-warning">Belum ada event. <a href="<?= e(base_url('events/create.php')) ?>" class="alert-link">Buat event terlebih dahulu</a>.</div>
  <?php else: ?>
    <form method="post" action="<?= e(base_url('presensi/sessions/create.php')) ?>">
      <?= csrf_field() ?>
      <div class="card">
        <div class="card-body">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Event <span class="text-danger">*</span></label>
              <select name="event_id" class="form-select" required>
                <option value="">-- Pilih Event --</option>
                <?php foreach ($allEvents as $ev): ?>
                  <option value="<?= (int) $ev['id'] ?>" <?= $form['event_id'] === (int) $ev['id'] ? 'selected' : '' ?>><?= e($ev['event_name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-3">
              <label class="form-label">Urutan Tampil</label>
              <input type="number" name="sort_order" class="form-control" value="<?= e((string) $form['sort_order']) ?>">
            </div>
            <div class="col-md-3">
              <label class="form-label">Status</label>
              <select name="status" class="form-select">
                <option value="active" <?= $form['status'] === 'active' ? 'selected' : '' ?>>Aktif</option>
                <option value="inactive" <?= $form['status'] === 'inactive' ? 'selected' : '' ?>>Nonaktif</option>
              </select>
            </div>
            <div class="col-md-12">
              <label class="form-label">Nama Sesi <span class="text-danger">*</span></label>
              <input type="text" name="name" class="form-control" required placeholder="Contoh: Absen Masuk, Absen Snack, Pengambilan Toga" value="<?= e($form['name']) ?>">
            </div>
          </div>
        </div>
      </div>
      <div class="mt-3">
        <button type="submit" class="btn btn-primary btn-lg"><i class="bi bi-save"></i> Simpan</button>
        <a href="<?= e(base_url('presensi/sessions/index.php')) ?>" class="btn btn-outline-secondary btn-lg">Batal</a>
      </div>
    </form>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
