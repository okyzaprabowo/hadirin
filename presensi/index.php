<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/bootstrap.php';
require_role(['admin', 'petugas']);

$db = get_db();
$pageTitle = 'Layar Presensi';

$activeEvents = $db->query(
    "SELECT id, event_name FROM events WHERE status = 'active' ORDER BY event_name ASC"
)->fetchAll();

require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/sidebar.php';
?>
<div class="container-fluid p-4">
  <h3 class="page-title mb-4"><i class="bi bi-qr-code"></i> Layar Presensi (Masuk / Snack / Makan Siang)</h3>

  <?php if (!$activeEvents): ?>
    <div class="alert alert-warning">Belum ada event aktif.</div>
  <?php else: ?>
    <p class="text-muted">
      Buka salah satu layar di bawah pada laptop/proyektor yang ditempatkan di lokasi masing-masing.
      QR di layar akan otomatis berganti tiap 5 detik; mahasiswa memindainya lewat menu
      <strong>Scan QR</strong> di akun mereka sendiri.
    </p>

    <?php foreach ($activeEvents as $ev): ?>
      <div class="card mb-3">
        <div class="card-header"><?= e($ev['event_name']) ?></div>
        <div class="card-body d-flex flex-wrap gap-2">
          <?php foreach (session_types() as $type => $label): ?>
            <a href="<?= e(base_url('presensi/display.php?session=' . $type . '&event_id=' . (int) $ev['id'])) ?>"
               target="_blank" class="btn btn-outline-primary">
              <i class="bi bi-display"></i> Buka Layar: <?= e($label) ?>
            </a>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
