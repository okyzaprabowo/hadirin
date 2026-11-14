<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/bootstrap.php';
require_role(['admin', 'petugas']);

$db = get_db();
$pageTitle = 'Layar Presensi';

$sessions = $db->query(
    "SELECT ps.*, ev.event_name
     FROM presensi_sessions ps
     JOIN events ev ON ev.id = ps.event_id
     WHERE ps.status = 'active' AND ev.status = 'active'
     ORDER BY ev.event_name ASC, ps.sort_order ASC, ps.id ASC"
)->fetchAll();

$byEvent = [];
foreach ($sessions as $s) {
    $byEvent[$s['event_name']][] = $s;
}

require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/sidebar.php';
?>
<div class="container-fluid p-4">
  <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <h3 class="page-title mb-0"><i class="bi bi-qr-code"></i> Layar Presensi</h3>
    <?php if (is_admin()): ?>
      <a href="<?= e(base_url('presensi/sessions/index.php')) ?>" class="btn btn-outline-primary"><i class="bi bi-gear"></i> Kelola Sesi Presensi</a>
    <?php endif; ?>
  </div>

  <?php if (!$byEvent): ?>
    <div class="alert alert-warning">Belum ada sesi presensi aktif. <?= is_admin() ? '<a href="' . e(base_url('presensi/sessions/create.php')) . '" class="alert-link">Tambah sesi presensi</a>.' : '' ?></div>
  <?php else: ?>
    <p class="text-muted">
      Buka salah satu layar di bawah pada laptop/proyektor, atau cetak QR-nya dan tempel di lokasi
      masing-masing. QR ini statis (tidak berganti) selama sesi presensi masih ada; mahasiswa
      memindainya lewat menu <strong>Scan QR</strong> di akun mereka sendiri.
    </p>

    <?php foreach ($byEvent as $eventName => $eventSessions): ?>
      <div class="card mb-3">
        <div class="card-header"><?= e($eventName) ?></div>
        <div class="card-body d-flex flex-wrap gap-2">
          <?php foreach ($eventSessions as $s): ?>
            <a href="<?= e(base_url('presensi/display.php?session_id=' . (int) $s['id'])) ?>"
               target="_blank" class="btn btn-outline-primary">
              <i class="bi bi-display"></i> Buka Layar: <?= e($s['name']) ?>
            </a>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
