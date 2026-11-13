<?php
declare(strict_types=1);
require_once __DIR__ . '/config/bootstrap.php';
require_login();

$db = get_db();
$user = current_user();
$pageTitle = 'Dashboard';

if ($user['role'] === 'admin') {
    $totalEvents = (int) $db->query('SELECT COUNT(*) FROM events')->fetchColumn();
    $totalGroups = (int) $db->query('SELECT COUNT(*) FROM guest_groups')->fetchColumn();
    $totalGuests = (int) $db->query('SELECT COUNT(*) FROM guests')->fetchColumn();
    $totalActiveGroups = (int) $db->query("SELECT COUNT(*) FROM guest_groups WHERE status = 'active'")->fetchColumn();

    $sessionTotals = $db->query(
        "SELECT COUNT(*) AS total, SUM(CASE WHEN checked_in_at IS NOT NULL THEN 1 ELSE 0 END) AS checked_in
         FROM session_attendance"
    )->fetch();
    $totalSessionSlots = (int) $sessionTotals['total'];
    $totalCheckedIn = (int) $sessionTotals['checked_in'];
    $totalNotCheckedIn = $totalSessionSlots - $totalCheckedIn;
    $attendancePercentage = $totalSessionSlots > 0 ? round(($totalCheckedIn / $totalSessionSlots) * 100, 1) : 0.0;

    $recentCheckins = $db->query(
        "SELECT sa.checked_in_at, gg.group_name, ps.name AS session_name
         FROM session_attendance sa
         JOIN guest_groups gg ON gg.id = sa.group_id
         JOIN presensi_sessions ps ON ps.id = sa.presensi_session_id
         WHERE sa.checked_in_at IS NOT NULL
         ORDER BY sa.checked_in_at DESC
         LIMIT 10"
    )->fetchAll();

    $groupStats = $db->query(
        "SELECT gg.id, gg.group_name, gg.quota,
                COUNT(sa.id) AS total_sessions,
                SUM(CASE WHEN sa.checked_in_at IS NOT NULL THEN 1 ELSE 0 END) AS total_present
         FROM guest_groups gg
         LEFT JOIN session_attendance sa ON sa.group_id = gg.id
         GROUP BY gg.id, gg.group_name, gg.quota
         ORDER BY gg.created_at DESC
         LIMIT 8"
    )->fetchAll();
} elseif ($user['role'] === 'mahasiswa') {
    $groupStmt = $db->prepare(
        'SELECT gg.*, ev.event_name FROM guest_groups gg JOIN events ev ON ev.id = gg.event_id WHERE gg.id = :id'
    );
    $groupStmt->execute(['id' => $user['group_id']]);
    $myGroup = $groupStmt->fetch();

    $myGuestsStmt = $db->prepare('SELECT * FROM guests WHERE group_id = :id ORDER BY id ASC');
    $myGuestsStmt->execute(['id' => $user['group_id']]);
    $myGuests = $myGuestsStmt->fetchAll();

    $mySessions = [];
    if ($myGroup) {
        $mySessionsStmt = $db->prepare(
            "SELECT ps.id, ps.name, ps.sort_order, sa.checked_in_at
             FROM presensi_sessions ps
             LEFT JOIN session_attendance sa ON sa.presensi_session_id = ps.id AND sa.group_id = :group_id
             WHERE ps.event_id = :event_id AND ps.status = 'active'
             ORDER BY ps.sort_order ASC, ps.id ASC"
        );
        $mySessionsStmt->execute(['group_id' => $user['group_id'], 'event_id' => $myGroup['event_id']]);
        $mySessions = $mySessionsStmt->fetchAll();
    }
} else {
    // petugas: tidak lagi scan apa pun (presensi dilakukan mahasiswa lewat
    // login mereka sendiri) — dashboard menampilkan ringkasan sesi aktif.
    $activeSessions = $db->query(
        "SELECT ps.id, ps.name, ev.event_name,
                COUNT(sa.id) AS total,
                SUM(CASE WHEN sa.checked_in_at IS NOT NULL THEN 1 ELSE 0 END) AS checked_in
         FROM presensi_sessions ps
         JOIN events ev ON ev.id = ps.event_id
         LEFT JOIN session_attendance sa ON sa.presensi_session_id = ps.id
         WHERE ps.status = 'active' AND ev.status = 'active'
         GROUP BY ps.id, ps.name, ev.event_name
         ORDER BY ev.event_name ASC, ps.sort_order ASC, ps.id ASC"
    )->fetchAll();
}

require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/sidebar.php';
?>
<div class="container-fluid p-4">
  <h3 class="page-title mb-4"><i class="bi bi-speedometer2"></i> <?= e($pageTitle) ?></h3>

  <?php if ($user['role'] === 'admin'): ?>
    <div class="row g-3 mb-4">
      <div class="col-6 col-md-4 col-lg-2">
        <div class="stat-card">
          <div class="stat-icon bg-primary-subtle text-primary"><i class="bi bi-calendar-event"></i></div>
          <div class="stat-value"><?= (int) $totalEvents ?></div>
          <div class="stat-label">Event</div>
        </div>
      </div>
      <div class="col-6 col-md-4 col-lg-2">
        <div class="stat-card">
          <div class="stat-icon bg-primary-subtle text-primary"><i class="bi bi-people"></i></div>
          <div class="stat-value"><?= (int) $totalGroups ?></div>
          <div class="stat-label">Grup Undangan</div>
        </div>
      </div>
      <div class="col-6 col-md-4 col-lg-2">
        <div class="stat-card">
          <div class="stat-icon bg-info-subtle text-info"><i class="bi bi-person-lines-fill"></i></div>
          <div class="stat-value"><?= (int) $totalGuests ?></div>
          <div class="stat-label">Tamu Terdaftar</div>
        </div>
      </div>
      <div class="col-6 col-md-4 col-lg-2">
        <div class="stat-card">
          <div class="stat-icon bg-primary-subtle text-primary"><i class="bi bi-check2-circle"></i></div>
          <div class="stat-value"><?= (int) $totalActiveGroups ?></div>
          <div class="stat-label">Grup Aktif</div>
        </div>
      </div>
      <div class="col-6 col-md-4 col-lg-2">
        <div class="stat-card">
          <div class="stat-icon bg-success-subtle text-success"><i class="bi bi-check-circle"></i></div>
          <div class="stat-value"><?= (int) $totalCheckedIn ?></div>
          <div class="stat-label">Presensi Tercatat</div>
        </div>
      </div>
      <div class="col-6 col-md-4 col-lg-2">
        <div class="stat-card">
          <div class="stat-icon bg-warning-subtle text-warning"><i class="bi bi-clock-history"></i></div>
          <div class="stat-value"><?= (int) $totalNotCheckedIn ?></div>
          <div class="stat-label">Belum Presensi</div>
        </div>
      </div>
      <div class="col-6 col-md-4 col-lg-2">
        <div class="stat-card">
          <div class="stat-icon bg-success-subtle text-success"><i class="bi bi-percent"></i></div>
          <div class="stat-value"><?= e((string) $attendancePercentage) ?>%</div>
          <div class="stat-label">Persentase Presensi</div>
        </div>
      </div>
    </div>

    <div class="mb-4 d-flex gap-2 flex-wrap">
      <a href="<?= e(base_url('events/create.php')) ?>" class="btn btn-primary"><i class="bi bi-calendar-plus"></i> Tambah Event</a>
      <a href="<?= e(base_url('groups/create.php')) ?>" class="btn btn-outline-primary"><i class="bi bi-plus-circle"></i> Tambah Grup</a>
      <a href="<?= e(base_url('presensi/index.php')) ?>" class="btn btn-outline-primary"><i class="bi bi-qr-code"></i> Layar Presensi</a>
    </div>

    <div class="row g-3">
      <div class="col-lg-5">
        <div class="card h-100">
          <div class="card-header">Grafik Presensi</div>
          <div class="card-body">
            <canvas id="attendanceChart" height="220"></canvas>
          </div>
        </div>
      </div>
      <div class="col-lg-7">
        <div class="card h-100">
          <div class="card-header">Presensi per Grup</div>
          <div class="card-body p-0">
            <div class="table-responsive">
              <table class="table table-sm mb-0">
                <thead><tr><th>Grup</th><th>Kuota</th><th>Sesi Hadir</th><th>Sesi Belum</th></tr></thead>
                <tbody>
                <?php foreach ($groupStats as $g): ?>
                  <tr>
                    <td><?= e($g['group_name']) ?></td>
                    <td><?= (int) $g['quota'] ?></td>
                    <td><span class="badge text-bg-success"><?= (int) $g['total_present'] ?></span></td>
                    <td><span class="badge text-bg-secondary"><?= (int) ($g['total_sessions'] - $g['total_present']) ?></span></td>
                  </tr>
                <?php endforeach; ?>
                <?php if (!$groupStats): ?>
                  <tr><td colspan="4" class="text-center text-muted py-3">Belum ada data grup.</td></tr>
                <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="card mt-3">
      <div class="card-header">Presensi Terbaru</div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-sm mb-0">
            <thead><tr><th>Waktu</th><th>Grup</th><th>Sesi</th></tr></thead>
            <tbody>
            <?php foreach ($recentCheckins as $row): ?>
              <tr>
                <td><?= e(format_datetime($row['checked_in_at'])) ?></td>
                <td><?= e($row['group_name']) ?></td>
                <td><span class="badge text-bg-success"><?= e($row['session_name']) ?></span></td>
              </tr>
            <?php endforeach; ?>
            <?php if (!$recentCheckins): ?>
              <tr><td colspan="3" class="text-center text-muted py-3">Belum ada aktivitas presensi.</td></tr>
            <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
    <script>
      new Chart(document.getElementById('attendanceChart'), {
        type: 'doughnut',
        data: {
          labels: ['Sudah Hadir', 'Belum Hadir'],
          datasets: [{
            data: [<?= (int) $totalCheckedIn ?>, <?= (int) $totalNotCheckedIn ?>],
            backgroundColor: ['#16A34A', '#64748B']
          }]
        },
        options: { plugins: { legend: { position: 'bottom' } } }
      });
    </script>

  <?php elseif ($user['role'] === 'petugas'): ?>
    <div class="alert alert-light border mb-4">
      <i class="bi bi-info-circle"></i> Presensi sekarang dilakukan mandiri oleh peserta lewat akun
      login mereka sendiri (menu Scan QR). Tugas Anda: buka layar QR di lokasi masing-masing sesi
      dan pantau jumlah yang sudah presensi.
    </div>

    <a href="<?= e(base_url('presensi/index.php')) ?>" class="btn btn-primary btn-lg mb-4"><i class="bi bi-qr-code"></i> Buka Layar Presensi</a>

    <div class="row g-3">
      <?php foreach ($activeSessions as $s): ?>
        <div class="col-md-4">
          <div class="stat-card">
            <div class="stat-icon bg-success-subtle text-success"><i class="bi bi-qr-code"></i></div>
            <div class="stat-value"><?= (int) $s['checked_in'] ?> / <?= (int) $s['total'] ?></div>
            <div class="stat-label"><?= e($s['name']) ?> &middot; <?= e($s['event_name']) ?></div>
          </div>
        </div>
      <?php endforeach; ?>
      <?php if (!$activeSessions): ?>
        <div class="col-12 text-center text-muted">Belum ada sesi presensi aktif.</div>
      <?php endif; ?>
    </div>

  <?php else: // mahasiswa ?>
    <?php if (!$myGroup): ?>
      <div class="alert alert-danger">Data grup Anda tidak ditemukan. Hubungi panitia.</div>
    <?php else: ?>
      <div class="card mb-3">
        <div class="card-header">Informasi Saya</div>
        <div class="card-body">
          <table class="table table-borderless table-sm mb-0">
            <tr><th width="180">Event</th><td><?= e($myGroup['event_name']) ?></td></tr>
            <tr><th>Nama Mahasiswa</th><td><?= e($myGroup['contact_name'] ?? '-') ?></td></tr>
            <tr><th>Nomor HP</th><td><?= e($myGroup['contact_phone'] ?? '-') ?></td></tr>
            <tr><th>Status Undangan</th><td><?= $myGroup['status'] === 'active' ? '<span class="badge text-bg-success">Aktif</span>' : '<span class="badge text-bg-secondary">Nonaktif</span>' ?></td></tr>
          </table>
        </div>
      </div>

      <div class="card mb-3">
        <div class="card-header">Data Mahasiswa &amp; Orang Tua (<?= count($myGuests) ?> / <?= (int) $myGroup['quota'] ?> kuota)</div>
        <div class="card-body p-0">
          <table class="table table-sm mb-0">
            <thead><tr><th>Nama</th></tr></thead>
            <tbody>
            <?php foreach ($myGuests as $g): ?>
              <tr><td><?= e($g['full_name']) ?></td></tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

      <div class="card mb-3">
        <div class="card-header">Status Presensi</div>
        <div class="card-body">
          <div class="row g-3">
            <?php foreach ($mySessions as $sess): ?>
              <?php $checked = $sess['checked_in_at'] !== null; ?>
              <div class="col-md-4">
                <div class="stat-card text-center">
                  <div class="stat-icon mx-auto <?= $checked ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary' ?>">
                    <i class="bi <?= $checked ? 'bi-check-circle' : 'bi-clock-history' ?>"></i>
                  </div>
                  <div class="fw-semibold mt-2"><?= e($sess['name']) ?></div>
                  <div class="text-muted small"><?= $checked ? e(format_datetime($sess['checked_in_at'])) : 'Belum presensi' ?></div>
                </div>
              </div>
            <?php endforeach; ?>
            <?php if (!$mySessions): ?>
              <div class="col-12 text-center text-muted">Belum ada sesi presensi aktif untuk event ini.</div>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <a href="<?= e(base_url('presensi/scan.php')) ?>" class="btn btn-primary btn-lg w-100"><i class="bi bi-qr-code-scan"></i> Scan QR Presensi</a>
    <?php endif; ?>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
