<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/bootstrap.php';
require_role('admin');

$db = get_db();

$allGroups = $db->query(
    'SELECT gg.id, gg.group_name, gg.quota, ev.event_name,
            (SELECT COUNT(*) FROM guests WHERE group_id = gg.id) AS current_count
     FROM guest_groups gg
     JOIN events ev ON ev.id = gg.event_id
     ORDER BY ev.event_name ASC, gg.group_name ASC'
)->fetchAll();

$pageTitle = 'Tambah Tamu';
$errors = [];
$form = [
    'group_id'  => input_int($_GET, 'group_id', 0),
    'full_name' => '',
    'phone'     => '',
    'notes'     => '',
];

$selectedGroup = null;
foreach ($allGroups as $grp) {
    if ((int) $grp['id'] === $form['group_id']) {
        $selectedGroup = $grp;
        break;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Token keamanan tidak valid. Silakan coba lagi.';
    }

    $form['group_id']  = input_int($_POST, 'group_id', 0);
    $form['full_name'] = input_str($_POST, 'full_name');
    $form['phone']     = input_str($_POST, 'phone');
    $form['notes']     = input_str($_POST, 'notes');

    $selectedGroup = null;
    foreach ($allGroups as $grp) {
        if ((int) $grp['id'] === $form['group_id']) {
            $selectedGroup = $grp;
            break;
        }
    }

    if ($form['group_id'] <= 0 || !$selectedGroup) {
        $errors[] = 'Grup wajib dipilih. Buat event dan grup terlebih dahulu jika belum ada.';
    }
    if ($form['full_name'] === '') {
        $errors[] = 'Nama tamu wajib diisi.';
    }
    if ($selectedGroup && (int) $selectedGroup['current_count'] >= (int) $selectedGroup['quota']) {
        $errors[] = 'Kuota grup sudah penuh (' . $selectedGroup['quota'] . ' orang).';
    }

    if (!$errors) {
        $now = date('Y-m-d H:i:s');
        $insert = $db->prepare(
            'INSERT INTO guests (group_id, full_name, phone, notes, created_at, updated_at)
             VALUES (:group_id, :full_name, :phone, :notes, :created_at, :updated_at)'
        );
        $insert->execute([
            'group_id'   => $form['group_id'],
            'full_name'  => $form['full_name'],
            'phone'      => $form['phone'] !== '' ? $form['phone'] : null,
            'notes'      => $form['notes'] !== '' ? $form['notes'] : null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        audit_log($db, (int) current_user()['id'], 'create_guest', 'guest', (int) $db->lastInsertId(),
            'Menambahkan tamu "' . $form['full_name'] . '" ke grup "' . ($selectedGroup['group_name'] ?? '') . '"');

        flash_set('success', 'Tamu berhasil ditambahkan.');
        redirect(base_url('groups/detail.php?id=' . $form['group_id']));
    }
}

require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/sidebar.php';
?>
<div class="container-fluid p-4">
  <h3 class="page-title mb-4"><i class="bi bi-person-plus"></i> Tambah Tamu</h3>

  <?php if (!$allGroups): ?>
    <div class="alert alert-warning">
      Belum ada grup undangan. <a href="<?= e(base_url('groups/create.php')) ?>" class="alert-link">Buat grup terlebih dahulu</a> sebelum menambahkan tamu.
    </div>
  <?php endif; ?>

  <?php if ($errors): ?>
    <div class="alert alert-danger"><?php foreach ($errors as $error): ?><div><?= e($error) ?></div><?php endforeach; ?></div>
  <?php endif; ?>

  <form method="post" action="<?= e(base_url('guests/create.php')) ?>">
    <?= csrf_field() ?>
    <div class="card">
      <div class="card-body">
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">Grup Undangan <span class="text-danger">*</span></label>
            <select name="group_id" class="form-select" required <?= !$allGroups ? 'disabled' : '' ?>>
              <option value="">-- Pilih Grup --</option>
              <?php foreach ($allGroups as $grp): ?>
                <option value="<?= (int) $grp['id'] ?>" <?= $form['group_id'] === (int) $grp['id'] ? 'selected' : '' ?>>
                  <?= e($grp['event_name'] . ' — ' . $grp['group_name']) ?> (<?= (int) $grp['current_count'] ?>/<?= (int) $grp['quota'] ?>)
                </option>
              <?php endforeach; ?>
            </select>
            <div class="form-text">Alur: Event &rarr; Grup Undangan &rarr; Tamu.</div>
          </div>
          <div class="col-md-6">
            <label class="form-label">Nama Lengkap <span class="text-danger">*</span></label>
            <input type="text" name="full_name" class="form-control" required value="<?= e($form['full_name']) ?>">
          </div>
          <div class="col-md-6">
            <label class="form-label">Nomor HP</label>
            <input type="text" name="phone" class="form-control" value="<?= e($form['phone']) ?>">
          </div>
          <div class="col-md-12">
            <label class="form-label">Catatan</label>
            <textarea name="notes" class="form-control" rows="2"><?= e($form['notes']) ?></textarea>
          </div>
        </div>
      </div>
    </div>
    <div class="mt-3">
      <button type="submit" class="btn btn-primary btn-lg" <?= !$allGroups ? 'disabled' : '' ?>><i class="bi bi-save"></i> Simpan</button>
      <?php if ($form['group_id'] > 0): ?>
        <a href="<?= e(base_url('groups/detail.php?id=' . $form['group_id'])) ?>" class="btn btn-outline-secondary btn-lg">Batal</a>
      <?php else: ?>
        <a href="<?= e(base_url('guests/index.php')) ?>" class="btn btn-outline-secondary btn-lg">Batal</a>
      <?php endif; ?>
    </div>
  </form>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
