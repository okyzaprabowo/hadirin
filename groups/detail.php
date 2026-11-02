<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/bootstrap.php';
require_role('admin');

$db = get_db();
$id = input_int($_GET, 'id', 0);

$stmt = $db->prepare(
    'SELECT gg.*, ev.event_name
     FROM guest_groups gg
     JOIN events ev ON ev.id = gg.event_id
     WHERE gg.id = :id'
);
$stmt->execute(['id' => $id]);
$group = $stmt->fetch();

if (!$group) {
    flash_set('danger', 'Grup tidak ditemukan.');
    redirect(base_url('groups/index.php'));
}

$guestsStmt = $db->prepare('SELECT * FROM guests WHERE group_id = :id ORDER BY full_name ASC');
$guestsStmt->execute(['id' => $id]);
$guests = $guestsStmt->fetchAll();

$totalGuests = count($guests);
$totalPresent = count(array_filter($guests, fn($g) => $g['checkin_at'] !== null));

$pageTitle = 'Detail Grup: ' . $group['group_name'];
require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/sidebar.php';
?>
<div class="container-fluid p-4">
  <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <h3 class="page-title mb-0"><i class="bi bi-people"></i> <?= e($group['group_name']) ?></h3>
    <div>
      <a href="<?= e(base_url('groups/edit.php?id=' . $id)) ?>" class="btn btn-outline-secondary"><i class="bi bi-pencil"></i> Edit</a>
      <a href="<?= e(base_url('groups/qr.php?id=' . $id)) ?>" class="btn btn-outline-dark"><i class="bi bi-qr-code"></i> Lihat QR</a>
      <a href="<?= e(base_url('groups/index.php')) ?>" class="btn btn-outline-primary"><i class="bi bi-arrow-left"></i> Kembali</a>
    </div>
  </div>

  <div class="row g-3 mb-3">
    <div class="col-md-8">
      <div class="card h-100">
        <div class="card-header">Informasi Grup</div>
        <div class="card-body">
          <table class="table table-borderless table-sm mb-0">
            <tr><th width="200">Event</th><td><a href="<?= e(base_url('events/detail.php?id=' . (int) $group['event_id'])) ?>"><?= e($group['event_name']) ?></a></td></tr>
            <tr><th>Penanggung Jawab</th><td><?= e($group['contact_name'] ?? '-') ?></td></tr>
            <tr><th>Nomor HP</th><td><?= e($group['contact_phone'] ?? '-') ?></td></tr>
            <tr><th>Kuota</th><td><?= (int) $group['quota'] ?> orang</td></tr>
            <tr><th>Kehadiran</th><td><?= $totalPresent ?> / <?= $totalGuests ?> tamu hadir</td></tr>
            <tr><th>Status</th><td><?= $group['status'] === 'active' ? '<span class="badge text-bg-success">Aktif</span>' : '<span class="badge text-bg-secondary">Nonaktif</span>' ?></td></tr>
            <tr><th>Catatan</th><td><?= e($group['notes'] ?? '-') ?></td></tr>
            <tr><th>Dibuat</th><td><?= e(format_datetime($group['created_at'])) ?></td></tr>
          </table>
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card h-100 text-center">
        <div class="card-header">QR Code</div>
        <div class="card-body d-flex flex-column justify-content-center align-items-center">
          <img src="<?= e(base_url('groups/qr.php?id=' . $id . '&action=view')) ?>" alt="QR Code" class="img-fluid mb-2" style="max-width:180px;">
          <div class="d-flex gap-2">
            <a href="<?= e(base_url('groups/qr.php?id=' . $id . '&action=download')) ?>" class="btn btn-sm btn-primary"><i class="bi bi-download"></i> PNG</a>
            <a href="<?= e(base_url('groups/qr.php?id=' . $id)) ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-printer"></i> Detail</a>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
      <span>Daftar Tamu</span>
      <a href="<?= e(base_url('guests/create.php?group_id=' . $id)) ?>" class="btn btn-sm btn-primary"><i class="bi bi-plus-lg"></i> Tambah Tamu</a>
    </div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-sm mb-0 align-middle">
          <thead><tr><th>Nama</th><th>No. HP</th><th>Status</th><th>Waktu Check-in</th><th class="text-end">Aksi</th></tr></thead>
          <tbody>
          <?php foreach ($guests as $guest): ?>
            <tr>
              <td><?= e($guest['full_name']) ?></td>
              <td><?= e($guest['phone'] ?? '-') ?></td>
              <td><?= $guest['checkin_at'] ? '<span class="badge text-bg-success">Sudah Hadir</span>' : '<span class="badge text-bg-secondary">Belum Hadir</span>' ?></td>
              <td><?= e(format_datetime($guest['checkin_at'])) ?></td>
              <td class="text-end">
                <a href="<?= e(base_url('guests/edit.php?id=' . $guest['id'])) ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil"></i></a>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$guests): ?>
            <tr><td colspan="5" class="text-center text-muted py-3">Belum ada tamu pada grup ini.</td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
