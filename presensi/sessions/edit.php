<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/bootstrap.php';
require_role('admin');

$db = get_db();
$id = input_int($_GET, 'id', 0) ?: input_int($_POST, 'id', 0);

$stmt = $db->prepare(
    'SELECT ps.*, ev.event_name FROM presensi_sessions ps JOIN events ev ON ev.id = ps.event_id WHERE ps.id = :id'
);
$stmt->execute(['id' => $id]);
$session = $stmt->fetch();

if (!$session) {
    flash_set('danger', 'Sesi presensi tidak ditemukan.');
    redirect(base_url('presensi/sessions/index.php'));
}

$pageTitle = 'Edit Sesi Presensi';
$errors = [];
$form = [
    'name'       => $session['name'],
    'sort_order' => (int) $session['sort_order'],
    'status'     => $session['status'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Token keamanan tidak valid. Silakan coba lagi.';
    }

    $form['name']       = input_str($_POST, 'name');
    $form['sort_order'] = input_int($_POST, 'sort_order', 0);
    $form['status']     = input_str($_POST, 'status', 'active');

    if ($form['name'] === '') {
        $errors[] = 'Nama sesi wajib diisi.';
    }
    if (!in_array($form['status'], ['active', 'inactive'], true)) {
        $errors[] = 'Status tidak valid.';
    }

    if (!$errors) {
        $db->prepare(
            'UPDATE presensi_sessions SET name = :name, sort_order = :sort_order, status = :status, updated_at = :updated_at WHERE id = :id'
        )->execute([
            'name'       => $form['name'],
            'sort_order' => $form['sort_order'],
            'status'     => $form['status'],
            'updated_at' => date('Y-m-d H:i:s'),
            'id'         => $id,
        ]);

        audit_log($db, (int) current_user()['id'], 'update_presensi_session', 'presensi_session', $id,
            'Memperbarui sesi presensi "' . $form['name'] . '"');

        flash_set('success', 'Sesi presensi berhasil diperbarui.');
        redirect(base_url('presensi/sessions/index.php'));
    }
}

require __DIR__ . '/../../includes/header.php';
require __DIR__ . '/../../includes/sidebar.php';
?>
<div class="container-fluid p-4">
  <h3 class="page-title mb-4"><i class="bi bi-pencil"></i> Edit Sesi Presensi</h3>

  <?php if ($errors): ?>
    <div class="alert alert-danger"><?php foreach ($errors as $error): ?><div><?= e($error) ?></div><?php endforeach; ?></div>
  <?php endif; ?>

  <form method="post" action="<?= e(base_url('presensi/sessions/edit.php?id=' . $id)) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= (int) $id ?>">
    <div class="card">
      <div class="card-body">
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">Event</label>
            <input type="text" class="form-control" value="<?= e($session['event_name']) ?>" disabled>
            <div class="form-text">Event tidak dapat diubah setelah sesi dibuat.</div>
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
            <div class="form-text">Nonaktif = layar QR &amp; scan untuk sesi ini ditutup.</div>
          </div>
          <div class="col-md-12">
            <label class="form-label">Nama Sesi <span class="text-danger">*</span></label>
            <input type="text" name="name" class="form-control" required value="<?= e($form['name']) ?>">
          </div>
        </div>
      </div>
    </div>
    <div class="mt-3">
      <button type="submit" class="btn btn-primary btn-lg"><i class="bi bi-save"></i> Simpan Perubahan</button>
      <a href="<?= e(base_url('presensi/sessions/index.php')) ?>" class="btn btn-outline-secondary btn-lg">Batal</a>
    </div>
  </form>
</div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
