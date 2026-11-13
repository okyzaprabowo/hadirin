<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/bootstrap.php';
require_role('admin');

$db = get_db();
$pageTitle = 'Kelola Sesi Presensi';

$eventFilter = input_int($_GET, 'event_id', 0);
$allEvents = $db->query('SELECT id, event_name FROM events ORDER BY event_name ASC')->fetchAll();

$where = '';
$params = [];
if ($eventFilter > 0) {
    $where = 'WHERE ps.event_id = :event_id';
    $params['event_id'] = $eventFilter;
}

$stmt = $db->prepare(
    "SELECT ps.*, ev.event_name,
            (SELECT COUNT(*) FROM session_attendance sa WHERE sa.presensi_session_id = ps.id) AS total_records,
            (SELECT COUNT(*) FROM session_attendance sa WHERE sa.presensi_session_id = ps.id AND sa.checked_in_at IS NOT NULL) AS total_checked_in
     FROM presensi_sessions ps
     JOIN events ev ON ev.id = ps.event_id
     {$where}
     ORDER BY ev.event_name ASC, ps.sort_order ASC, ps.id ASC"
);
$stmt->execute($params);
$sessions = $stmt->fetchAll();

require __DIR__ . '/../../includes/header.php';
require __DIR__ . '/../../includes/sidebar.php';
?>
<div class="container-fluid p-4">
  <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <h3 class="page-title mb-0"><i class="bi bi-qr-code"></i> Kelola Sesi Presensi</h3>
    <a href="<?= e(base_url('presensi/sessions/create.php')) ?>" class="btn btn-primary"><i class="bi bi-plus-circle"></i> Tambah Sesi</a>
  </div>

  <p class="text-muted">
    Sesi presensi adalah area/titik presensi mandiri (contoh: Absen Masuk, Absen Snack, Absen Makan
    Siang, atau nama lain sesuai kebutuhan acara). Setiap sesi aktif otomatis punya layar QR
    sendiri di menu <a href="<?= e(base_url('presensi/index.php')) ?>">Layar Presensi</a>, dan setiap
    grup/mahasiswa baru otomatis mendapat baris presensi untuk semua sesi aktif pada event-nya.
  </p>

  <div class="card mb-3">
    <div class="card-body">
      <form method="get" class="row g-2">
        <div class="col-md-4">
          <select name="event_id" class="form-select" onchange="this.form.submit()">
            <option value="0">Semua Event</option>
            <?php foreach ($allEvents as $ev): ?>
              <option value="<?= (int) $ev['id'] ?>" <?= $eventFilter === (int) $ev['id'] ? 'selected' : '' ?>><?= e($ev['event_name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </form>
    </div>
  </div>

  <div class="card">
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle">
          <thead>
            <tr>
              <th>Urutan</th>
              <th>Nama Sesi</th>
              <th>Event</th>
              <th>Status</th>
              <th>Presensi</th>
              <th class="text-end">Aksi</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($sessions as $s): ?>
            <tr>
              <td><?= (int) $s['sort_order'] ?></td>
              <td><?= e($s['name']) ?></td>
              <td><?= e($s['event_name']) ?></td>
              <td>
                <?php if ($s['status'] === 'active'): ?>
                  <span class="badge text-bg-success">Aktif</span>
                <?php else: ?>
                  <span class="badge text-bg-secondary">Nonaktif</span>
                <?php endif; ?>
              </td>
              <td><?= (int) $s['total_checked_in'] ?> / <?= (int) $s['total_records'] ?> grup</td>
              <td class="text-end">
                <?php if ($s['status'] === 'active'): ?>
                  <a href="<?= e(base_url('presensi/display.php?session_id=' . $s['id'])) ?>" target="_blank" class="btn btn-sm btn-outline-dark" title="Buka Layar QR"><i class="bi bi-display"></i></a>
                <?php endif; ?>
                <a href="<?= e(base_url('presensi/sessions/edit.php?id=' . $s['id'])) ?>" class="btn btn-sm btn-outline-secondary" title="Edit"><i class="bi bi-pencil"></i></a>
                <?php if ((int) $s['total_checked_in'] === 0): ?>
                  <button type="button" class="btn btn-sm btn-outline-danger btn-delete-session"
                          data-id="<?= (int) $s['id'] ?>" data-name="<?= e($s['name']) ?>" title="Hapus">
                    <i class="bi bi-trash"></i>
                  </button>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$sessions): ?>
            <tr><td colspan="6" class="text-center text-muted py-4">Belum ada sesi presensi.</td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<form id="deleteSessionForm" method="post" action="<?= e(base_url('presensi/sessions/delete.php')) ?>" class="d-none">
  <?= csrf_field() ?>
  <input type="hidden" name="id" id="deleteSessionId">
</form>
<script>
document.querySelectorAll('.btn-delete-session').forEach(function (btn) {
  btn.addEventListener('click', function () {
    if (confirm('Hapus sesi presensi "' + btn.dataset.name + '"? Tindakan ini tidak dapat dibatalkan.')) {
      document.getElementById('deleteSessionId').value = btn.dataset.id;
      document.getElementById('deleteSessionForm').submit();
    }
  });
});
</script>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
