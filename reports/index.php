<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/bootstrap.php';
require_role('admin');

$db = get_db();
$pageTitle = 'Laporan';

$groupId = input_int($_GET, 'group_id', 0);
$eventFilter = input_int($_GET, 'event_id', 0);
$status = input_str($_GET, 'status');
$dateFrom = input_str($_GET, 'date_from');
$dateTo = input_str($_GET, 'date_to');
$page = max(1, input_int($_GET, 'page', 1));

$where = [];
$params = [];
if ($groupId > 0) {
    $where[] = 'g.group_id = :group_id';
    $params['group_id'] = $groupId;
}
if ($eventFilter > 0) {
    $where[] = 'ev.id = :event_id';
    $params['event_id'] = $eventFilter;
}
if ($status === 'present') {
    $where[] = 'g.checkin_at IS NOT NULL';
} elseif ($status === 'absent') {
    $where[] = 'g.checkin_at IS NULL';
}
if ($dateFrom !== '') {
    $where[] = 'g.checkin_at >= :date_from';
    $params['date_from'] = $dateFrom . ' 00:00:00';
}
if ($dateTo !== '') {
    $where[] = 'g.checkin_at <= :date_to';
    $params['date_to'] = $dateTo . ' 23:59:59';
}
$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$countStmt = $db->prepare("SELECT COUNT(*) FROM guests g JOIN guest_groups gg ON gg.id = g.group_id JOIN events ev ON ev.id = gg.event_id {$whereSql}");
$countStmt->execute($params);
$total = (int) $countStmt->fetchColumn();
$pagination = paginate_calc($total, $page, 20);

$sql = "SELECT g.full_name, g.phone, gg.group_name, ev.event_name, g.checkin_at,
               (SELECT u.full_name FROM checkin_logs cl JOIN users u ON u.id = cl.scanned_by
                WHERE cl.guest_id = g.id AND cl.action = 'checkin' ORDER BY cl.scanned_at DESC LIMIT 1) AS officer_name
        FROM guests g
        JOIN guest_groups gg ON gg.id = g.group_id
        JOIN events ev ON ev.id = gg.event_id
        {$whereSql}
        ORDER BY ev.event_name ASC, gg.group_name ASC, g.full_name ASC
        LIMIT :limit OFFSET :offset";
$stmt = $db->prepare($sql);
foreach ($params as $k => $v) {
    $stmt->bindValue($k, $v);
}
$stmt->bindValue('limit', $pagination['per_page'], PDO::PARAM_INT);
$stmt->bindValue('offset', $pagination['offset'], PDO::PARAM_INT);
$stmt->execute();
$rows = $stmt->fetchAll();

$totalAttendanceStmt = $db->prepare("SELECT COUNT(*) FROM guests g JOIN guest_groups gg ON gg.id = g.group_id JOIN events ev ON ev.id = gg.event_id {$whereSql}" . ($where ? ' AND' : 'WHERE') . ' g.checkin_at IS NOT NULL');
$totalAttendanceStmt->execute($params);
$totalAttendance = (int) $totalAttendanceStmt->fetchColumn();

$groupsList = $db->query('SELECT id, group_name FROM guest_groups ORDER BY group_name ASC')->fetchAll();
$eventsList = $db->query('SELECT id, event_name FROM events ORDER BY event_name ASC')->fetchAll();

require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/sidebar.php';
?>
<div class="container-fluid p-4">
  <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <h3 class="page-title mb-0"><i class="bi bi-file-earmark-bar-graph"></i> Laporan Kehadiran</h3>
    <a href="<?= e(base_url('reports/export.php' . build_query([]))) ?>" class="btn btn-success"><i class="bi bi-file-earmark-spreadsheet"></i> Export CSV</a>
  </div>

  <div class="card mb-3">
    <div class="card-body">
      <form method="get" class="row g-2">
        <div class="col-md-2">
          <select name="event_id" class="form-select">
            <option value="0">Semua Event</option>
            <?php foreach ($eventsList as $ev): ?>
              <option value="<?= (int) $ev['id'] ?>" <?= $eventFilter === (int) $ev['id'] ? 'selected' : '' ?>><?= e($ev['event_name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-2">
          <select name="group_id" class="form-select">
            <option value="0">Semua Grup</option>
            <?php foreach ($groupsList as $grp): ?>
              <option value="<?= (int) $grp['id'] ?>" <?= $groupId === (int) $grp['id'] ? 'selected' : '' ?>><?= e($grp['group_name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-2">
          <select name="status" class="form-select">
            <option value="">Semua Status</option>
            <option value="present" <?= $status === 'present' ? 'selected' : '' ?>>Sudah Hadir</option>
            <option value="absent" <?= $status === 'absent' ? 'selected' : '' ?>>Belum Hadir</option>
          </select>
        </div>
        <div class="col-md-2">
          <input type="date" name="date_from" class="form-control" value="<?= e($dateFrom) ?>">
        </div>
        <div class="col-md-2">
          <input type="date" name="date_to" class="form-control" value="<?= e($dateTo) ?>">
        </div>
        <div class="col-md-2">
          <button type="submit" class="btn btn-outline-primary w-100"><i class="bi bi-filter"></i> Filter</button>
        </div>
      </form>
    </div>
  </div>

  <p class="text-muted">Total kehadiran sesuai filter: <strong><?= $totalAttendance ?></strong> dari <strong><?= $total ?></strong> tamu.</p>

  <div class="card">
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-sm mb-0">
          <thead><tr><th>Nama Tamu</th><th>No. HP</th><th>Event</th><th>Grup</th><th>Status</th><th>Waktu Check-in</th><th>Petugas</th></tr></thead>
          <tbody>
          <?php foreach ($rows as $r): ?>
            <tr>
              <td><?= e($r['full_name']) ?></td>
              <td><?= e($r['phone'] ?? '-') ?></td>
              <td><?= e($r['event_name']) ?></td>
              <td><?= e($r['group_name']) ?></td>
              <td><?= $r['checkin_at'] ? '<span class="badge text-bg-success">Sudah Hadir</span>' : '<span class="badge text-bg-secondary">Belum Hadir</span>' ?></td>
              <td><?= e(format_datetime($r['checkin_at'])) ?></td>
              <td><?= e($r['officer_name'] ?? '-') ?></td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$rows): ?>
            <tr><td colspan="7" class="text-center text-muted py-4">Tidak ada data.</td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <?php if ($pagination['total_pages'] > 1): ?>
    <nav class="mt-3">
      <ul class="pagination">
        <?php for ($i = 1; $i <= $pagination['total_pages']; $i++): ?>
          <li class="page-item <?= $i === $pagination['page'] ? 'active' : '' ?>">
            <a class="page-link" href="<?= e(build_query(['page' => $i])) ?>"><?= $i ?></a>
          </li>
        <?php endfor; ?>
      </ul>
    </nav>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
