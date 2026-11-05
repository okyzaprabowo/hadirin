<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/bootstrap.php';
require_role('admin');

$db = get_db();
$id = input_int($_GET, 'id', 0);

$stmt = $db->prepare('SELECT * FROM events WHERE id = :id');
$stmt->execute(['id' => $id]);
$event = $stmt->fetch();

if (!$event) {
    flash_set('danger', 'Event tidak ditemukan.');
    redirect(base_url('events/index.php'));
}

$groupsStmt = $db->prepare(
    'SELECT gg.*,
            COUNT(gu.id) AS total_guests,
            SUM(CASE WHEN gu.checkin_at IS NOT NULL THEN 1 ELSE 0 END) AS total_present
     FROM guest_groups gg
     LEFT JOIN guests gu ON gu.group_id = gg.id
     WHERE gg.event_id = :id
     GROUP BY gg.id
     ORDER BY gg.group_name ASC'
);
$groupsStmt->execute(['id' => $id]);
$groups = $groupsStmt->fetchAll();

$totalGroups = count($groups);
$totalGuests = array_sum(array_map(fn($g) => (int) $g['total_guests'], $groups));
$totalPresent = array_sum(array_map(fn($g) => (int) $g['total_present'], $groups));

$pageTitle = 'Detail Event: ' . $event['event_name'];
require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/sidebar.php';
?>
<div class="container-fluid p-4">
  <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <h3 class="page-title mb-0"><i class="bi bi-calendar-event"></i> <?= e($event['event_name']) ?></h3>
    <div>
      <a href="<?= e(base_url('events/edit.php?id=' . $id)) ?>" class="btn btn-outline-secondary"><i class="bi bi-pencil"></i> Edit</a>
      <a href="<?= e(base_url('events/index.php')) ?>" class="btn btn-outline-primary"><i class="bi bi-arrow-left"></i> Kembali</a>
    </div>
  </div>

  <div class="card mb-3">
    <div class="card-header">Informasi Event</div>
    <div class="card-body">
      <table class="table table-borderless table-sm mb-0">
        <tr><th width="200">Tanggal</th><td><?= $event['event_date'] ? e(format_datetime($event['event_date'], 'd M Y')) : '-' ?></td></tr>
        <tr><th>Lokasi</th><td><?= e($event['location'] ?? '-') ?></td></tr>
        <tr><th>Status</th><td><?= $event['status'] === 'active' ? '<span class="badge text-bg-success">Aktif</span>' : '<span class="badge text-bg-secondary">Nonaktif</span>' ?></td></tr>
        <tr><th>Jumlah Grup</th><td><?= $totalGroups ?></td></tr>
        <tr><th>Kehadiran</th><td><?= $totalPresent ?> / <?= $totalGuests ?> tamu hadir</td></tr>
        <tr><th>Deskripsi</th><td><?= e($event['description'] ?? '-') ?></td></tr>
        <tr><th>Dibuat</th><td><?= e(format_datetime($event['created_at'])) ?></td></tr>
      </table>
    </div>
  </div>

  <div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
      <span>Grup Undangan pada Event Ini</span>
      <a href="<?= e(base_url('groups/create.php?event_id=' . $id)) ?>" class="btn btn-sm btn-primary"><i class="bi bi-plus-lg"></i> Tambah Grup</a>
    </div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-sm mb-0 align-middle">
          <thead><tr><th>Nama Grup</th><th>Penanggung Jawab</th><th>Kuota</th><th>Hadir / Total</th><th>Status</th><th class="text-end">Aksi</th></tr></thead>
          <tbody>
          <?php foreach ($groups as $g): ?>
            <tr>
              <td><?= e($g['group_name']) ?></td>
              <td><?= e($g['contact_name'] ?? '-') ?></td>
              <td><?= (int) $g['quota'] ?></td>
              <td><?= (int) $g['total_present'] ?> / <?= (int) $g['total_guests'] ?></td>
              <td><?= $g['status'] === 'active' ? '<span class="badge text-bg-success">Aktif</span>' : '<span class="badge text-bg-secondary">Nonaktif</span>' ?></td>
              <td class="text-end">
                <a href="<?= e(base_url('groups/detail.php?id=' . $g['id'])) ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye"></i></a>
                <a href="<?= e(base_url('groups/barcode.php?id=' . $g['id'])) ?>" class="btn btn-sm btn-outline-dark"><i class="bi bi-upc"></i></a>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$groups): ?>
            <tr><td colspan="6" class="text-center text-muted py-3">Belum ada grup pada event ini. Tambahkan grup terlebih dahulu.</td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
