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
    $totalCheckedIn = (int) $db->query('SELECT COUNT(*) FROM guests WHERE checkin_at IS NOT NULL')->fetchColumn();
    $totalNotCheckedIn = $totalGuests - $totalCheckedIn;
    $totalActiveQr = (int) $db->query("SELECT COUNT(*) FROM guest_groups WHERE status = 'active'")->fetchColumn();
    $attendancePercentage = $totalGuests > 0 ? round(($totalCheckedIn / $totalGuests) * 100, 1) : 0.0;

    $recentCheckins = $db->query(
        "SELECT cl.scanned_at, g.full_name AS guest_name, gg.group_name, u.full_name AS officer_name, cl.action
         FROM checkin_logs cl
         LEFT JOIN guests g ON g.id = cl.guest_id
         LEFT JOIN guest_groups gg ON gg.id = cl.group_id
         JOIN users u ON u.id = cl.scanned_by
         ORDER BY cl.scanned_at DESC
         LIMIT 10"
    )->fetchAll();

    $groupStats = $db->query(
        "SELECT gg.id, gg.group_name, gg.quota,
                COUNT(gu.id) AS total_guests,
                SUM(CASE WHEN gu.checkin_at IS NOT NULL THEN 1 ELSE 0 END) AS total_present
         FROM guest_groups gg
         LEFT JOIN guests gu ON gu.group_id = gg.id
         GROUP BY gg.id, gg.group_name, gg.quota
         ORDER BY gg.created_at DESC
         LIMIT 8"
    )->fetchAll();
} else {
    $todayStart = date('Y-m-d 00:00:00');
    $stmt = $db->prepare(
        "SELECT COUNT(*) FROM checkin_logs WHERE scanned_by = :uid AND action = 'checkin' AND scanned_at >= :today"
    );
    $stmt->execute(['uid' => $user['id'], 'today' => $todayStart]);
    $myScansToday = (int) $stmt->fetchColumn();

    $stmt2 = $db->prepare(
        "SELECT COUNT(*) FROM checkin_logs WHERE scanned_by = :uid"
    );
    $stmt2->execute(['uid' => $user['id']]);
    $myScansTotal = (int) $stmt2->fetchColumn();

    $myRecentScans = $db->prepare(
        "SELECT cl.scanned_at, g.full_name AS guest_name, gg.group_name, cl.action, cl.message
         FROM checkin_logs cl
         LEFT JOIN guests g ON g.id = cl.guest_id
         LEFT JOIN guest_groups gg ON gg.id = cl.group_id
         WHERE cl.scanned_by = :uid
         ORDER BY cl.scanned_at DESC LIMIT 10"
    );
    $myRecentScans->execute(['uid' => $user['id']]);
    $myRecentScans = $myRecentScans->fetchAll();
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
          <div class="stat-icon bg-success-subtle text-success"><i class="bi bi-check-circle"></i></div>
          <div class="stat-value"><?= (int) $totalCheckedIn ?></div>
          <div class="stat-label">Tamu Hadir</div>
        </div>
      </div>
      <div class="col-6 col-md-4 col-lg-2">
        <div class="stat-card">
          <div class="stat-icon bg-warning-subtle text-warning"><i class="bi bi-clock-history"></i></div>
          <div class="stat-value"><?= (int) $totalNotCheckedIn ?></div>
          <div class="stat-label">Belum Hadir</div>
        </div>
      </div>
      <div class="col-6 col-md-4 col-lg-2">
        <div class="stat-card">
          <div class="stat-icon bg-primary-subtle text-primary"><i class="bi bi-upc"></i></div>
          <div class="stat-value"><?= (int) $totalActiveQr ?></div>
          <div class="stat-label">Barcode Aktif</div>
        </div>
      </div>
      <div class="col-6 col-md-4 col-lg-2">
        <div class="stat-card">
          <div class="stat-icon bg-success-subtle text-success"><i class="bi bi-percent"></i></div>
          <div class="stat-value"><?= e((string) $attendancePercentage) ?>%</div>
          <div class="stat-label">Persentase Hadir</div>
        </div>
      </div>
    </div>

    <div class="mb-4 d-flex gap-2 flex-wrap">
      <a href="<?= e(base_url('events/create.php')) ?>" class="btn btn-primary"><i class="bi bi-calendar-plus"></i> Tambah Event</a>
      <a href="<?= e(base_url('groups/create.php')) ?>" class="btn btn-outline-primary"><i class="bi bi-plus-circle"></i> Tambah Grup</a>
      <a href="<?= e(base_url('scanner/index.php')) ?>" class="btn btn-outline-primary"><i class="bi bi-upc-scan"></i> Scan Barcode</a>
    </div>

    <div class="row g-3">
      <div class="col-lg-5">
        <div class="card h-100">
          <div class="card-header">Grafik Kehadiran</div>
          <div class="card-body">
            <canvas id="attendanceChart" height="220"></canvas>
          </div>
        </div>
      </div>
      <div class="col-lg-7">
        <div class="card h-100">
          <div class="card-header">Kehadiran per Grup</div>
          <div class="card-body p-0">
            <div class="table-responsive">
              <table class="table table-sm mb-0">
                <thead><tr><th>Grup</th><th>Kuota</th><th>Hadir</th><th>Belum Hadir</th></tr></thead>
                <tbody>
                <?php foreach ($groupStats as $g): ?>
                  <tr>
                    <td><?= e($g['group_name']) ?></td>
                    <td><?= (int) $g['quota'] ?></td>
                    <td><span class="badge text-bg-success"><?= (int) $g['total_present'] ?></span></td>
                    <td><span class="badge text-bg-secondary"><?= (int) ($g['total_guests'] - $g['total_present']) ?></span></td>
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
      <div class="card-header">Check-in Terbaru</div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-sm mb-0">
            <thead><tr><th>Waktu</th><th>Tamu</th><th>Grup</th><th>Petugas</th><th>Status</th></tr></thead>
            <tbody>
            <?php foreach ($recentCheckins as $row): ?>
              <tr>
                <td><?= e(format_datetime($row['scanned_at'])) ?></td>
                <td><?= e($row['guest_name'] ?? '-') ?></td>
                <td><?= e($row['group_name'] ?? '-') ?></td>
                <td><?= e($row['officer_name']) ?></td>
                <td><?= $row['action'] === 'checkin' ? '<span class="badge text-bg-success">Check-in</span>' : '<span class="badge text-bg-danger">Ditolak</span>' ?></td>
              </tr>
            <?php endforeach; ?>
            <?php if (!$recentCheckins): ?>
              <tr><td colspan="5" class="text-center text-muted py-3">Belum ada aktivitas check-in.</td></tr>
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

  <?php else: ?>
    <div class="row g-3 mb-4">
      <div class="col-6 col-md-6">
        <div class="stat-card">
          <div class="stat-icon bg-success-subtle text-success"><i class="bi bi-check-circle"></i></div>
          <div class="stat-value"><?= (int) $myScansToday ?></div>
          <div class="stat-label">Check-in Hari Ini</div>
        </div>
      </div>
      <div class="col-6 col-md-6">
        <div class="stat-card">
          <div class="stat-icon bg-primary-subtle text-primary"><i class="bi bi-upc-scan"></i></div>
          <div class="stat-value"><?= (int) $myScansTotal ?></div>
          <div class="stat-label">Total Check-in Saya</div>
        </div>
      </div>
    </div>

    <a href="<?= e(base_url('scanner/index.php')) ?>" class="btn btn-primary btn-lg mb-4"><i class="bi bi-upc-scan"></i> Mulai Scan Barcode</a>

    <div class="card">
      <div class="card-header">Riwayat Scan Terbaru</div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-sm mb-0">
            <thead><tr><th>Waktu</th><th>Tamu</th><th>Grup</th><th>Status</th></tr></thead>
            <tbody>
            <?php foreach ($myRecentScans as $row): ?>
              <tr>
                <td><?= e(format_datetime($row['scanned_at'])) ?></td>
                <td><?= e($row['guest_name'] ?? '-') ?></td>
                <td><?= e($row['group_name'] ?? '-') ?></td>
                <td><?= $row['action'] === 'checkin' ? '<span class="badge text-bg-success">Check-in</span>' : '<span class="badge text-bg-danger">Ditolak</span>' ?></td>
              </tr>
            <?php endforeach; ?>
            <?php if (!$myRecentScans): ?>
              <tr><td colspan="4" class="text-center text-muted py-3">Belum ada riwayat scan.</td></tr>
            <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
