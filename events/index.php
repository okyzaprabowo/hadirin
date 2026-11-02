<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/bootstrap.php';
require_role('admin');

$db = get_db();
$pageTitle = 'Event';

$search = input_str($_GET, 'q');
$statusFilter = input_str($_GET, 'status');
$page = max(1, input_int($_GET, 'page', 1));

$where = [];
$params = [];

if ($search !== '') {
    $where[] = '(ev.event_name LIKE :search OR ev.location LIKE :search)';
    $params['search'] = '%' . $search . '%';
}
if (in_array($statusFilter, ['active', 'inactive'], true)) {
    $where[] = 'ev.status = :status';
    $params['status'] = $statusFilter;
}
$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$countStmt = $db->prepare("SELECT COUNT(*) FROM events ev {$whereSql}");
$countStmt->execute($params);
$total = (int) $countStmt->fetchColumn();

$pagination = paginate_calc($total, $page, 10);

$sql = "SELECT ev.*,
               COUNT(DISTINCT gg.id) AS total_groups,
               COUNT(gu.id) AS total_guests,
               SUM(CASE WHEN gu.checkin_at IS NOT NULL THEN 1 ELSE 0 END) AS total_present
        FROM events ev
        LEFT JOIN guest_groups gg ON gg.event_id = ev.id
        LEFT JOIN guests gu ON gu.group_id = gg.id
        {$whereSql}
        GROUP BY ev.id
        ORDER BY ev.created_at DESC
        LIMIT :limit OFFSET :offset";

$stmt = $db->prepare($sql);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->bindValue('limit', $pagination['per_page'], PDO::PARAM_INT);
$stmt->bindValue('offset', $pagination['offset'], PDO::PARAM_INT);
$stmt->execute();
$events = $stmt->fetchAll();

require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/sidebar.php';
?>
<div class="container-fluid p-4">
  <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <h3 class="page-title mb-0"><i class="bi bi-calendar-event"></i> Event</h3>
    <a href="<?= e(base_url('events/create.php')) ?>" class="btn btn-primary"><i class="bi bi-plus-circle"></i> Tambah Event</a>
  </div>

  <div class="card mb-3">
    <div class="card-body">
      <form method="get" class="row g-2">
        <div class="col-md-5">
          <input type="text" name="q" class="form-control" placeholder="Cari nama event atau lokasi..." value="<?= e($search) ?>">
        </div>
        <div class="col-md-3">
          <select name="status" class="form-select">
            <option value="">Semua Status</option>
            <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Aktif</option>
            <option value="inactive" <?= $statusFilter === 'inactive' ? 'selected' : '' ?>>Nonaktif</option>
          </select>
        </div>
        <div class="col-md-2">
          <button type="submit" class="btn btn-outline-primary w-100"><i class="bi bi-search"></i> Cari</button>
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
              <th>Nama Event</th>
              <th>Tanggal</th>
              <th>Lokasi</th>
              <th>Grup</th>
              <th>Hadir / Total</th>
              <th>Status</th>
              <th class="text-end">Aksi</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($events as $ev): ?>
            <tr>
              <td><?= e($ev['event_name']) ?></td>
              <td><?= $ev['event_date'] ? e(format_datetime($ev['event_date'], 'd M Y')) : '-' ?></td>
              <td><?= e($ev['location'] ?? '-') ?></td>
              <td><?= (int) $ev['total_groups'] ?></td>
              <td><?= (int) $ev['total_present'] ?> / <?= (int) $ev['total_guests'] ?></td>
              <td>
                <?php if ($ev['status'] === 'active'): ?>
                  <span class="badge text-bg-success">Aktif</span>
                <?php else: ?>
                  <span class="badge text-bg-secondary">Nonaktif</span>
                <?php endif; ?>
              </td>
              <td class="text-end">
                <a href="<?= e(base_url('events/detail.php?id=' . $ev['id'])) ?>" class="btn btn-sm btn-outline-primary" title="Detail"><i class="bi bi-eye"></i></a>
                <a href="<?= e(base_url('events/edit.php?id=' . $ev['id'])) ?>" class="btn btn-sm btn-outline-secondary" title="Edit"><i class="bi bi-pencil"></i></a>
                <?php if ((int) $ev['total_groups'] === 0): ?>
                  <button type="button" class="btn btn-sm btn-outline-danger btn-delete-event"
                          data-id="<?= (int) $ev['id'] ?>" data-name="<?= e($ev['event_name']) ?>" title="Hapus">
                    <i class="bi bi-trash"></i>
                  </button>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$events): ?>
            <tr><td colspan="7" class="text-center text-muted py-4">Belum ada data event.</td></tr>
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

<form id="deleteEventForm" method="post" action="<?= e(base_url('events/delete.php')) ?>" class="d-none">
  <?= csrf_field() ?>
  <input type="hidden" name="id" id="deleteEventId">
</form>

<script>
document.querySelectorAll('.btn-delete-event').forEach(function (btn) {
  btn.addEventListener('click', function () {
    if (confirm('Hapus event "' + btn.dataset.name + '"? Tindakan ini tidak dapat dibatalkan.')) {
      document.getElementById('deleteEventId').value = btn.dataset.id;
      document.getElementById('deleteEventForm').submit();
    }
  });
});
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
