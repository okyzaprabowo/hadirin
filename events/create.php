<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/bootstrap.php';
require_role('admin');

$db = get_db();
$pageTitle = 'Tambah Event';

$errors = [];
$form = [
    'event_name'  => '',
    'event_date'  => '',
    'location'    => '',
    'description' => '',
    'status'      => 'active',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Token keamanan tidak valid. Silakan coba lagi.';
    }

    $form['event_name']  = input_str($_POST, 'event_name');
    $form['event_date']  = input_str($_POST, 'event_date');
    $form['location']    = input_str($_POST, 'location');
    $form['description'] = input_str($_POST, 'description');
    $form['status']      = input_str($_POST, 'status', 'active');

    if ($form['event_name'] === '') {
        $errors[] = 'Nama event wajib diisi.';
    }
    if ($form['event_date'] !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $form['event_date'])) {
        $errors[] = 'Format tanggal event tidak valid.';
    }
    if (!in_array($form['status'], ['active', 'inactive'], true)) {
        $errors[] = 'Status tidak valid.';
    }

    if (!$errors) {
        $now = date('Y-m-d H:i:s');
        $insert = $db->prepare(
            'INSERT INTO events (event_name, event_date, location, description, status, created_at, updated_at)
             VALUES (:event_name, :event_date, :location, :description, :status, :created_at, :updated_at)'
        );
        $insert->execute([
            'event_name'  => $form['event_name'],
            'event_date'  => $form['event_date'] !== '' ? $form['event_date'] : null,
            'location'    => $form['location'] !== '' ? $form['location'] : null,
            'description' => $form['description'] !== '' ? $form['description'] : null,
            'status'      => $form['status'],
            'created_at'  => $now,
            'updated_at'  => $now,
        ]);
        $eventId = (int) $db->lastInsertId();

        audit_log($db, (int) current_user()['id'], 'create_event', 'event', $eventId,
            'Membuat event "' . $form['event_name'] . '"');

        flash_set('success', 'Event berhasil dibuat. Selanjutnya tambahkan grup undangan pada event ini.');
        redirect(base_url('events/detail.php?id=' . $eventId));
    }
}

require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/sidebar.php';
?>
<div class="container-fluid p-4">
  <h3 class="page-title mb-4"><i class="bi bi-calendar-plus"></i> Tambah Event</h3>

  <?php if ($errors): ?>
    <div class="alert alert-danger"><?php foreach ($errors as $error): ?><div><?= e($error) ?></div><?php endforeach; ?></div>
  <?php endif; ?>

  <form method="post" action="<?= e(base_url('events/create.php')) ?>">
    <?= csrf_field() ?>
    <div class="card">
      <div class="card-body">
        <div class="row g-3">
          <div class="col-md-8">
            <label class="form-label">Nama Event <span class="text-danger">*</span></label>
            <input type="text" name="event_name" class="form-control" required value="<?= e($form['event_name']) ?>">
          </div>
          <div class="col-md-4">
            <label class="form-label">Tanggal</label>
            <input type="date" name="event_date" class="form-control" value="<?= e($form['event_date']) ?>">
          </div>
          <div class="col-md-8">
            <label class="form-label">Lokasi</label>
            <input type="text" name="location" class="form-control" value="<?= e($form['location']) ?>">
          </div>
          <div class="col-md-4">
            <label class="form-label">Status</label>
            <select name="status" class="form-select">
              <option value="active" <?= $form['status'] === 'active' ? 'selected' : '' ?>>Aktif</option>
              <option value="inactive" <?= $form['status'] === 'inactive' ? 'selected' : '' ?>>Nonaktif</option>
            </select>
          </div>
          <div class="col-md-12">
            <label class="form-label">Deskripsi</label>
            <textarea name="description" class="form-control" rows="3"><?= e($form['description']) ?></textarea>
          </div>
        </div>
      </div>
    </div>
    <div class="mt-3">
      <button type="submit" class="btn btn-primary btn-lg"><i class="bi bi-save"></i> Simpan Event</button>
      <a href="<?= e(base_url('events/index.php')) ?>" class="btn btn-outline-secondary btn-lg">Batal</a>
    </div>
  </form>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
