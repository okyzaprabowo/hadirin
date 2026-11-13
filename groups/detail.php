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

$sessionsStmt = $db->prepare(
    "SELECT ps.id, ps.name, ps.sort_order, sa.id AS attendance_id, sa.checked_in_at
     FROM presensi_sessions ps
     LEFT JOIN session_attendance sa ON sa.presensi_session_id = ps.id AND sa.group_id = :group_id
     WHERE ps.event_id = :event_id
     ORDER BY ps.sort_order ASC, ps.id ASC"
);
$sessionsStmt->execute(['group_id' => $id, 'event_id' => $group['event_id']]);
$sessions = $sessionsStmt->fetchAll();

$pageTitle = 'Detail Grup: ' . $group['group_name'];
require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/sidebar.php';
?>
<div class="container-fluid p-4">
  <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <h3 class="page-title mb-0"><i class="bi bi-people"></i> <?= e($group['group_name']) ?></h3>
    <div>
      <a href="<?= e(base_url('groups/edit.php?id=' . $id)) ?>" class="btn btn-outline-secondary"><i class="bi bi-pencil"></i> Edit</a>
      <a href="<?= e(base_url('groups/index.php')) ?>" class="btn btn-outline-primary"><i class="bi bi-arrow-left"></i> Kembali</a>
    </div>
  </div>

  <div class="card mb-3">
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

  <div class="card mb-3">
    <div class="card-header d-flex justify-content-between align-items-center">
      <span>Presensi Sesi</span>
      <a href="<?= e(base_url('presensi/sessions/index.php?event_id=' . (int) $group['event_id'])) ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-gear"></i> Kelola Sesi</a>
    </div>
    <div class="card-body">
      <div class="row g-3">
        <?php foreach ($sessions as $sess): ?>
          <?php $checked = $sess['checked_in_at'] !== null; ?>
          <div class="col-md-4">
            <div class="border rounded p-3 text-center h-100 d-flex flex-column justify-content-between">
              <div>
                <div class="fw-semibold"><?= e($sess['name']) ?></div>
                <div class="small text-muted mb-2">
                  <?= $checked ? e(format_datetime($sess['checked_in_at'])) : 'Belum presensi' ?>
                </div>
                <span class="badge <?= $checked ? 'text-bg-success' : 'text-bg-secondary' ?>"><?= $checked ? 'Hadir' : 'Belum Hadir' ?></span>
              </div>
              <form method="post" action="<?= e(base_url('groups/mark_session.php')) ?>" class="mt-2">
                <?= csrf_field() ?>
                <input type="hidden" name="group_id" value="<?= (int) $id ?>">
                <input type="hidden" name="presensi_session_id" value="<?= (int) $sess['id'] ?>">
                <input type="hidden" name="action" value="<?= $checked ? 'unmark' : 'mark' ?>">
                <button type="submit" class="btn btn-sm w-100 <?= $checked ? 'btn-outline-secondary' : 'btn-outline-success' ?>">
                  <?= $checked ? 'Batalkan' : 'Tandai Hadir' ?>
                </button>
              </form>
            </div>
          </div>
        <?php endforeach; ?>
        <?php if (!$sessions): ?>
          <div class="col-12 text-center text-muted">Belum ada sesi presensi untuk event ini.</div>
        <?php endif; ?>
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
