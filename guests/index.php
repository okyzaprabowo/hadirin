<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/bootstrap.php';
require_role('admin');

$db = get_db();
$pageTitle = 'Data Tamu';

$search = input_str($_GET, 'q');
$groupFilter = input_int($_GET, 'group_id', 0);
$eventFilter = input_int($_GET, 'event_id', 0);
$statusFilter = input_str($_GET, 'status');
$page = max(1, input_int($_GET, 'page', 1));

$where = [];
$params = [];

if ($search !== '') {
    $where[] = 'g.full_name LIKE :search';
    $params['search'] = '%' . $search . '%';
}
if ($groupFilter > 0) {
    $where[] = 'g.group_id = :group_id';
    $params['group_id'] = $groupFilter;
}
if ($eventFilter > 0) {
    $where[] = 'ev.id = :event_id';
    $params['event_id'] = $eventFilter;
}
if ($statusFilter === 'present') {
    $where[] = 'g.checkin_at IS NOT NULL';
} elseif ($statusFilter === 'absent') {
    $where[] = 'g.checkin_at IS NULL';
}
$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$countStmt = $db->prepare("SELECT COUNT(*) FROM guests g JOIN guest_groups gg ON gg.id = g.group_id JOIN events ev ON ev.id = gg.event_id {$whereSql}");
$countStmt->execute($params);
$total = (int) $countStmt->fetchColumn();
$pagination = paginate_calc($total, $page, 15);

$sql = "SELECT g.*, gg.group_name, ev.event_name
        FROM guests g
        JOIN guest_groups gg ON gg.id = g.group_id
        JOIN events ev ON ev.id = gg.event_id
        {$whereSql}
        ORDER BY g.created_at DESC
        LIMIT :limit OFFSET :offset";
$stmt = $db->prepare($sql);
foreach ($params as $k => $v) {
    $stmt->bindValue($k, $v);
}
$stmt->bindValue('limit', $pagination['per_page'], PDO::PARAM_INT);
$stmt->bindValue('offset', $pagination['offset'], PDO::PARAM_INT);
$stmt->execute();
$guests = $stmt->fetchAll();

$groupsList = $db->query('SELECT id, group_name FROM guest_groups ORDER BY group_name ASC')->fetchAll();
$eventsList = $db->query('SELECT id, event_name FROM events ORDER BY event_name ASC')->fetchAll();

require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/sidebar.php';
?>
<div class="container-fluid p-4">
  <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <h3 class="page-title mb-0"><i class="bi bi-person-lines-fill"></i> Data Tamu</h3>
    <a href="<?= e(base_url('guests/create.php')) ?>" class="btn btn-primary"><i class="bi bi-person-plus"></i> Tambah Tamu</a>
  </div>

  <div class="card mb-3">
    <div class="card-body">
      <form method="get" class="row g-2">
        <div class="col-md-3">
          <input type="text" name="q" class="form-control" placeholder="Cari nama tamu..." value="<?= e($search) ?>">
        </div>
        <div class="col-md-3">
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
              <option value="<?= (int) $grp['id'] ?>" <?= $groupFilter === (int) $grp['id'] ? 'selected' : '' ?>><?= e($grp['group_name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-2">
          <select name="status" class="form-select">
            <option value="">Semua Status</option>
            <option value="present" <?= $statusFilter === 'present' ? 'selected' : '' ?>>Sudah Hadir</option>
            <option value="absent" <?= $statusFilter === 'absent' ? 'selected' : '' ?>>Belum Hadir</option>
          </select>
        </div>
        <div class="col-md-2">
          <button type="submit" class="btn btn-outline-primary w-100"><i class="bi bi-search"></i> Filter</button>
        </div>
      </form>
    </div>
  </div>

  <div class="card">
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle">
          <thead><tr><th>Nama</th><th>Event</th><th>Grup</th><th>No. HP</th><th>Status</th><th>Check-in</th><th class="text-end">Aksi</th></tr></thead>
          <tbody>
          <?php foreach ($guests as $g): ?>
            <tr>
              <td><?= e($g['full_name']) ?></td>
              <td><?= e($g['event_name']) ?></td>
              <td><?= e($g['group_name']) ?></td>
              <td><?= e($g['phone'] ?? '-') ?></td>
              <td><?= $g['checkin_at'] ? '<span class="badge text-bg-success">Sudah Hadir</span>' : '<span class="badge text-bg-secondary">Belum Hadir</span>' ?></td>
              <td><?= e(format_datetime($g['checkin_at'])) ?></td>
              <td class="text-end">
                <a href="<?= e(base_url('guests/edit.php?id=' . $g['id'])) ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil"></i></a>
                <?php if (!$g['checkin_at']): ?>
                  <button type="button" class="btn btn-sm btn-outline-danger btn-delete-guest" data-id="<?= (int) $g['id'] ?>" data-name="<?= e($g['full_name']) ?>"><i class="bi bi-trash"></i></button>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$guests): ?>
            <tr><td colspan="7" class="text-center text-muted py-4">Tidak ada data tamu.</td></tr>
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

<form id="deleteGuestForm" method="post" action="<?= e(base_url('guests/delete.php')) ?>" class="d-none">
  <?= csrf_field() ?>
  <input type="hidden" name="id" id="deleteGuestId">
</form>
<script>
document.querySelectorAll('.btn-delete-guest').forEach(function (btn) {
  btn.addEventListener('click', function () {
    if (confirm('Hapus tamu "' + btn.dataset.name + '"?')) {
      document.getElementById('deleteGuestId').value = btn.dataset.id;
      document.getElementById('deleteGuestForm').submit();
    }
  });
});
</script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
