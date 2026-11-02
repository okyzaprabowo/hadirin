<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/bootstrap.php';
require_role('admin');

$db = get_db();
$id = input_int($_GET, 'id', 0) ?: input_int($_POST, 'id', 0);

$stmt = $db->prepare('SELECT * FROM guests WHERE id = :id');
$stmt->execute(['id' => $id]);
$guest = $stmt->fetch();

if (!$guest) {
    flash_set('danger', 'Tamu tidak ditemukan.');
    redirect(base_url('guests/index.php'));
}

$isCheckedIn = $guest['checkin_at'] !== null;
$pageTitle = 'Edit Tamu';
$errors = [];
$form = [
    'full_name' => $guest['full_name'],
    'phone'     => $guest['phone'] ?? '',
    'notes'     => $guest['notes'] ?? '',
    'group_id'  => (int) $guest['group_id'],
];

$allGroups = $db->query('SELECT id, group_name, quota FROM guest_groups ORDER BY group_name ASC')->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Token keamanan tidak valid. Silakan coba lagi.';
    }

    $form['full_name'] = input_str($_POST, 'full_name');
    $form['phone'] = input_str($_POST, 'phone');
    $form['notes'] = input_str($_POST, 'notes');
    $form['group_id'] = $isCheckedIn ? (int) $guest['group_id'] : input_int($_POST, 'group_id', (int) $guest['group_id']);

    if ($form['full_name'] === '') {
        $errors[] = 'Nama tamu wajib diisi.';
    }

    if (!$isCheckedIn && $form['group_id'] !== (int) $guest['group_id']) {
        $targetStmt = $db->prepare('SELECT quota, (SELECT COUNT(*) FROM guests WHERE group_id = guest_groups.id) AS current_count FROM guest_groups WHERE id = :id');
        $targetStmt->execute(['id' => $form['group_id']]);
        $target = $targetStmt->fetch();
        if (!$target) {
            $errors[] = 'Grup tujuan tidak valid.';
        } elseif ((int) $target['current_count'] >= (int) $target['quota']) {
            $errors[] = 'Kuota grup tujuan sudah penuh.';
        }
    }

    if (!$errors) {
        $update = $db->prepare(
            'UPDATE guests SET full_name = :full_name, phone = :phone, notes = :notes, group_id = :group_id, updated_at = :updated_at WHERE id = :id'
        );
        $update->execute([
            'full_name'  => $form['full_name'],
            'phone'      => $form['phone'] !== '' ? $form['phone'] : null,
            'notes'      => $form['notes'] !== '' ? $form['notes'] : null,
            'group_id'   => $form['group_id'],
            'updated_at' => date('Y-m-d H:i:s'),
            'id'         => $id,
        ]);

        audit_log($db, (int) current_user()['id'], 'update_guest', 'guest', $id, 'Memperbarui data tamu "' . $form['full_name'] . '"');

        flash_set('success', 'Data tamu berhasil diperbarui.');
        redirect(base_url('groups/detail.php?id=' . $form['group_id']));
    }
}

require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/sidebar.php';
?>
<div class="container-fluid p-4">
  <h3 class="page-title mb-4"><i class="bi bi-pencil"></i> Edit Tamu</h3>

  <?php if ($errors): ?>
    <div class="alert alert-danger"><?php foreach ($errors as $error): ?><div><?= e($error) ?></div><?php endforeach; ?></div>
  <?php endif; ?>
  <?php if ($isCheckedIn): ?>
    <div class="alert alert-info">Tamu ini sudah check-in, sehingga tidak dapat dipindahkan ke grup lain.</div>
  <?php endif; ?>

  <form method="post" action="<?= e(base_url('guests/edit.php?id=' . $id)) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= (int) $id ?>">
    <div class="card">
      <div class="card-body">
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">Nama Lengkap <span class="text-danger">*</span></label>
            <input type="text" name="full_name" class="form-control" required value="<?= e($form['full_name']) ?>">
          </div>
          <div class="col-md-6">
            <label class="form-label">Nomor HP</label>
            <input type="text" name="phone" class="form-control" value="<?= e($form['phone']) ?>">
          </div>
          <div class="col-md-6">
            <label class="form-label">Grup</label>
            <select name="group_id" class="form-select" <?= $isCheckedIn ? 'disabled' : '' ?>>
              <?php foreach ($allGroups as $grp): ?>
                <option value="<?= (int) $grp['id'] ?>" <?= $form['group_id'] === (int) $grp['id'] ? 'selected' : '' ?>><?= e($grp['group_name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-12">
            <label class="form-label">Catatan</label>
            <textarea name="notes" class="form-control" rows="2"><?= e($form['notes']) ?></textarea>
          </div>
        </div>
      </div>
    </div>
    <div class="mt-3">
      <button type="submit" class="btn btn-primary btn-lg"><i class="bi bi-save"></i> Simpan</button>
      <a href="<?= e(base_url('groups/detail.php?id=' . $guest['group_id'])) ?>" class="btn btn-outline-secondary btn-lg">Batal</a>
    </div>
  </form>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
