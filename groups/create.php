<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/bootstrap.php';
require_role('admin');

$db = get_db();
$pageTitle = 'Tambah Grup Undangan';

$allEvents = $db->query('SELECT id, event_name FROM events ORDER BY event_name ASC')->fetchAll();

$errors = [];
$form = [
    'event_id'      => input_int($_GET, 'event_id', 0),
    'group_name'    => '',
    'contact_name'  => '',
    'contact_phone' => '',
    'quota'         => 1,
    'notes'         => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Token keamanan tidak valid. Silakan coba lagi.';
    }

    $form['event_id']      = input_int($_POST, 'event_id', 0);
    $form['group_name']    = input_str($_POST, 'group_name');
    $form['contact_name']  = input_str($_POST, 'contact_name');
    $form['contact_phone'] = input_str($_POST, 'contact_phone');
    $form['quota']         = input_int($_POST, 'quota', 0);
    $form['notes']         = input_str($_POST, 'notes');

    if ($form['event_id'] <= 0) {
        $errors[] = 'Event wajib dipilih. Buat event terlebih dahulu jika belum ada.';
    } else {
        $eventCheck = $db->prepare('SELECT COUNT(*) FROM events WHERE id = :id');
        $eventCheck->execute(['id' => $form['event_id']]);
        if ((int) $eventCheck->fetchColumn() === 0) {
            $errors[] = 'Event yang dipilih tidak valid.';
        }
    }
    if ($form['group_name'] === '') {
        $errors[] = 'Nama grup wajib diisi.';
    }
    if ($form['quota'] <= 0) {
        $errors[] = 'Kuota harus lebih dari 0.';
    }
    if ($form['contact_phone'] !== '' && !preg_match('/^[0-9+\-\s()]{6,30}$/', $form['contact_phone'])) {
        $errors[] = 'Format nomor HP tidak valid.';
    }

    if (!$errors) {
        try {
            $qrToken = generate_barcode_token();
            $now = date('Y-m-d H:i:s');

            $stmt = $db->prepare(
                'INSERT INTO guest_groups (event_id, group_name, contact_name, contact_phone, quota, qr_token, status, notes, created_at, updated_at)
                 VALUES (:event_id, :group_name, :contact_name, :contact_phone, :quota, :qr_token, :status, :notes, :created_at, :updated_at)'
            );
            $stmt->execute([
                'event_id'      => $form['event_id'],
                'group_name'    => $form['group_name'],
                'contact_name'  => $form['contact_name'] !== '' ? $form['contact_name'] : null,
                'contact_phone' => $form['contact_phone'] !== '' ? $form['contact_phone'] : null,
                'quota'         => $form['quota'],
                'qr_token'      => $qrToken,
                'status'        => 'active',
                'notes'         => $form['notes'] !== '' ? $form['notes'] : null,
                'created_at'    => $now,
                'updated_at'    => $now,
            ]);
            $groupId = (int) $db->lastInsertId();

            audit_log($db, (int) current_user()['id'], 'create_group', 'guest_group', $groupId,
                'Membuat grup "' . $form['group_name'] . '" pada event #' . $form['event_id']);

            flash_set('success', 'Grup undangan berhasil dibuat dan Barcode telah digenerate. Selanjutnya tambahkan tamu pada grup ini.');
            redirect(base_url('groups/detail.php?id=' . $groupId));
        } catch (Throwable $e) {
            error_log('[Hadirin] Failed to create group: ' . $e->getMessage());
            $errors[] = 'Terjadi kesalahan saat menyimpan data. Silakan coba lagi.';
        }
    }
}

require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/sidebar.php';
?>
<div class="container-fluid p-4">
  <h3 class="page-title mb-4"><i class="bi bi-plus-circle"></i> Tambah Grup Undangan</h3>

  <?php if (!$allEvents): ?>
    <div class="alert alert-warning">
      Belum ada event. <a href="<?= e(base_url('events/create.php')) ?>" class="alert-link">Buat event terlebih dahulu</a> sebelum menambahkan grup.
    </div>
  <?php endif; ?>

  <?php if ($errors): ?>
    <div class="alert alert-danger">
      <?php foreach ($errors as $error): ?><div><?= e($error) ?></div><?php endforeach; ?>
    </div>
  <?php endif; ?>

  <form method="post" action="<?= e(base_url('groups/create.php')) ?>">
    <?= csrf_field() ?>
    <div class="card mb-3">
      <div class="card-header">Informasi Grup</div>
      <div class="card-body">
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">Event <span class="text-danger">*</span></label>
            <select name="event_id" class="form-select" required <?= !$allEvents ? 'disabled' : '' ?>>
              <option value="">-- Pilih Event --</option>
              <?php foreach ($allEvents as $ev): ?>
                <option value="<?= (int) $ev['id'] ?>" <?= $form['event_id'] === (int) $ev['id'] ? 'selected' : '' ?>><?= e($ev['event_name']) ?></option>
              <?php endforeach; ?>
            </select>
            <div class="form-text">Alur: Event &rarr; Grup Undangan &rarr; Tamu.</div>
          </div>
          <div class="col-md-6">
            <label class="form-label">Nama Grup <span class="text-danger">*</span></label>
            <input type="text" name="group_name" class="form-control" required value="<?= e($form['group_name']) ?>">
          </div>
          <div class="col-md-3">
            <label class="form-label">Jumlah Kuota <span class="text-danger">*</span></label>
            <input type="number" min="1" name="quota" class="form-control" required value="<?= e((string) $form['quota']) ?>">
          </div>
          <div class="col-md-3">
            <label class="form-label">Nomor HP Penanggung Jawab</label>
            <input type="text" name="contact_phone" class="form-control" value="<?= e($form['contact_phone']) ?>">
          </div>
          <div class="col-md-6">
            <label class="form-label">Nama Penanggung Jawab</label>
            <input type="text" name="contact_name" class="form-control" value="<?= e($form['contact_name']) ?>">
          </div>
          <div class="col-md-12">
            <label class="form-label">Catatan</label>
            <textarea name="notes" class="form-control" rows="2"><?= e($form['notes']) ?></textarea>
          </div>
        </div>
      </div>
    </div>

    <button type="submit" class="btn btn-primary btn-lg" <?= !$allEvents ? 'disabled' : '' ?>><i class="bi bi-upc"></i> Simpan dan Generate Barcode</button>
    <a href="<?= e(base_url('groups/index.php')) ?>" class="btn btn-outline-secondary btn-lg">Batal</a>
  </form>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
