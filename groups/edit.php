<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/bootstrap.php';
require_role('admin');

$db = get_db();
$id = input_int($_GET, 'id', 0) ?: input_int($_POST, 'id', 0);

$stmt = $db->prepare('SELECT * FROM guest_groups WHERE id = :id');
$stmt->execute(['id' => $id]);
$group = $stmt->fetch();

if (!$group) {
    flash_set('danger', 'Grup tidak ditemukan.');
    redirect(base_url('groups/index.php'));
}

$guestCountStmt = $db->prepare('SELECT COUNT(*) FROM guests WHERE group_id = :id');
$guestCountStmt->execute(['id' => $id]);
$guestCount = (int) $guestCountStmt->fetchColumn();

$allEvents = $db->query('SELECT id, event_name FROM events ORDER BY event_name ASC')->fetchAll();

$pageTitle = 'Edit Grup Undangan';
$errors = [];
$form = [
    'event_id'      => (int) $group['event_id'],
    'group_name'    => $group['group_name'],
    'contact_name'  => $group['contact_name'] ?? '',
    'contact_phone' => $group['contact_phone'] ?? '',
    'quota'         => (int) $group['quota'],
    'notes'         => $group['notes'] ?? '',
    'status'        => $group['status'],
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
    $form['status']        = input_str($_POST, 'status', 'active');

    if ($form['event_id'] <= 0) {
        $errors[] = 'Event wajib dipilih.';
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
    } elseif ($form['quota'] < $guestCount) {
        $errors[] = "Kuota tidak boleh lebih kecil dari jumlah tamu terdaftar ({$guestCount}).";
    }
    if (!in_array($form['status'], ['active', 'inactive'], true)) {
        $errors[] = 'Status tidak valid.';
    }
    if ($form['contact_phone'] !== '' && !preg_match('/^[0-9+\-\s()]{6,30}$/', $form['contact_phone'])) {
        $errors[] = 'Format nomor HP tidak valid.';
    }

    if (!$errors) {
        $update = $db->prepare(
            'UPDATE guest_groups SET event_id = :event_id, group_name = :group_name, contact_name = :contact_name,
             contact_phone = :contact_phone, quota = :quota, notes = :notes, status = :status, updated_at = :updated_at
             WHERE id = :id'
        );
        $update->execute([
            'event_id'      => $form['event_id'],
            'group_name'    => $form['group_name'],
            'contact_name'  => $form['contact_name'] !== '' ? $form['contact_name'] : null,
            'contact_phone' => $form['contact_phone'] !== '' ? $form['contact_phone'] : null,
            'quota'         => $form['quota'],
            'notes'         => $form['notes'] !== '' ? $form['notes'] : null,
            'status'        => $form['status'],
            'updated_at'    => date('Y-m-d H:i:s'),
            'id'            => $id,
        ]);

        audit_log($db, (int) current_user()['id'], 'update_group', 'guest_group', $id, 'Memperbarui grup "' . $form['group_name'] . '"');

        flash_set('success', 'Grup undangan berhasil diperbarui.');
        redirect(base_url('groups/detail.php?id=' . $id));
    }
}

require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/sidebar.php';
?>
<div class="container-fluid p-4">
  <h3 class="page-title mb-4"><i class="bi bi-pencil"></i> Edit Grup Undangan</h3>

  <?php if ($errors): ?>
    <div class="alert alert-danger"><?php foreach ($errors as $error): ?><div><?= e($error) ?></div><?php endforeach; ?></div>
  <?php endif; ?>

  <form method="post" action="<?= e(base_url('groups/edit.php?id=' . $id)) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= (int) $id ?>">
    <div class="card">
      <div class="card-body">
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">Event <span class="text-danger">*</span></label>
            <select name="event_id" class="form-select" required>
              <?php foreach ($allEvents as $ev): ?>
                <option value="<?= (int) $ev['id'] ?>" <?= $form['event_id'] === (int) $ev['id'] ? 'selected' : '' ?>><?= e($ev['event_name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label">Nama Grup <span class="text-danger">*</span></label>
            <input type="text" name="group_name" class="form-control" required value="<?= e($form['group_name']) ?>">
          </div>
          <div class="col-md-3">
            <label class="form-label">Jumlah Kuota <span class="text-danger">*</span></label>
            <input type="number" min="<?= (int) $guestCount ?>" name="quota" class="form-control" required value="<?= e((string) $form['quota']) ?>">
            <div class="form-text">Tamu terdaftar saat ini: <?= (int) $guestCount ?></div>
          </div>
          <div class="col-md-3">
            <label class="form-label">Status</label>
            <select name="status" class="form-select">
              <option value="active" <?= $form['status'] === 'active' ? 'selected' : '' ?>>Aktif</option>
              <option value="inactive" <?= $form['status'] === 'inactive' ? 'selected' : '' ?>>Nonaktif</option>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label">Nama Penanggung Jawab</label>
            <input type="text" name="contact_name" class="form-control" value="<?= e($form['contact_name']) ?>">
          </div>
          <div class="col-md-6">
            <label class="form-label">Nomor HP Penanggung Jawab</label>
            <input type="text" name="contact_phone" class="form-control" value="<?= e($form['contact_phone']) ?>">
          </div>
          <div class="col-md-12">
            <label class="form-label">Catatan</label>
            <textarea name="notes" class="form-control" rows="2"><?= e($form['notes']) ?></textarea>
          </div>
        </div>
      </div>
    </div>
    <div class="mt-3">
      <button type="submit" class="btn btn-primary btn-lg"><i class="bi bi-save"></i> Simpan Perubahan</button>
      <a href="<?= e(base_url('groups/detail.php?id=' . $id)) ?>" class="btn btn-outline-secondary btn-lg">Batal</a>
    </div>
  </form>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
