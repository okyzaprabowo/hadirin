<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/bootstrap.php';
require_role('admin');

$db = get_db();
$pageTitle = 'Grup Undangan';

$search = input_str($_GET, 'q');
$statusFilter = input_str($_GET, 'status');
$eventFilter = input_int($_GET, 'event_id', 0);
$page = max(1, input_int($_GET, 'page', 1));

$where = [];
$params = [];

if ($search !== '') {
    $where[] = '(gg.group_name LIKE :search OR gg.contact_name LIKE :search)';
    $params['search'] = '%' . $search . '%';
}
if (in_array($statusFilter, ['active', 'inactive'], true)) {
    $where[] = 'gg.status = :status';
    $params['status'] = $statusFilter;
}
if ($eventFilter > 0) {
    $where[] = 'gg.event_id = :event_id';
    $params['event_id'] = $eventFilter;
}
$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$countStmt = $db->prepare("SELECT COUNT(*) FROM guest_groups gg {$whereSql}");
$countStmt->execute($params);
$total = (int) $countStmt->fetchColumn();

$pagination = paginate_calc($total, $page, 10);

$sql = "SELECT gg.*, ev.event_name,
               COUNT(gu.id) AS total_guests,
               SUM(CASE WHEN gu.checkin_at IS NOT NULL THEN 1 ELSE 0 END) AS total_present
        FROM guest_groups gg
        JOIN events ev ON ev.id = gg.event_id
        LEFT JOIN guests gu ON gu.group_id = gg.id
        {$whereSql}
        GROUP BY gg.id
        ORDER BY gg.created_at DESC
        LIMIT :limit OFFSET :offset";

$stmt = $db->prepare($sql);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->bindValue('limit', $pagination['per_page'], PDO::PARAM_INT);
$stmt->bindValue('offset', $pagination['offset'], PDO::PARAM_INT);
$stmt->execute();
$groups = $stmt->fetchAll();

$eventsList = $db->query('SELECT id, event_name FROM events ORDER BY event_name ASC')->fetchAll();

require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/sidebar.php';
?>
<div class="container-fluid p-4">
  <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <h3 class="page-title mb-0"><i class="bi bi-people"></i> Grup Undangan</h3>
    <a href="<?= e(base_url('groups/create.php')) ?>" class="btn btn-primary"><i class="bi bi-plus-circle"></i> Tambah Grup</a>
  </div>

  <div class="card mb-3">
    <div class="card-body">
      <form method="get" class="row g-2">
        <div class="col-md-4">
          <input type="text" name="q" class="form-control" placeholder="Cari nama grup atau penanggung jawab..." value="<?= e($search) ?>">
        </div>
        <div class="col-md-3">
          <select name="event_id" class="form-select">
            <option value="0">Semua Event</option>
            <?php foreach ($eventsList as $ev): ?>
              <option value="<?= (int) $ev['id'] ?>" <?= $eventFilter === (int) $ev['id'] ? 'selected' : '' ?>><?= e($ev['event_name']) ?></option>
            <?php endforeach; ?>
          </select>
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
              <th>Nama Grup</th>
              <th>Event</th>
              <th>Penanggung Jawab</th>
              <th>Kuota</th>
              <th>Hadir / Total</th>
              <th>Status</th>
              <th class="text-end">Aksi</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($groups as $g): ?>
            <tr>
              <td><?= e($g['group_name']) ?></td>
              <td><?= e($g['event_name']) ?></td>
              <td><?= e($g['contact_name'] ?? '-') ?></td>
              <td><?= (int) $g['quota'] ?></td>
              <td><?= (int) $g['total_present'] ?> / <?= (int) $g['total_guests'] ?></td>
              <td>
                <?php if ($g['status'] === 'active'): ?>
                  <span class="badge text-bg-success">Aktif</span>
                <?php else: ?>
                  <span class="badge text-bg-secondary">Nonaktif</span>
                <?php endif; ?>
              </td>
              <td class="text-end">
                <a href="<?= e(base_url('groups/detail.php?id=' . $g['id'])) ?>" class="btn btn-sm btn-outline-primary" title="Detail"><i class="bi bi-eye"></i></a>
                <a href="<?= e(base_url('groups/edit.php?id=' . $g['id'])) ?>" class="btn btn-sm btn-outline-secondary" title="Edit"><i class="bi bi-pencil"></i></a>
                <a href="<?= e(base_url('groups/barcode.php?id=' . $g['id'])) ?>" class="btn btn-sm btn-outline-dark" title="Barcode"><i class="bi bi-upc"></i></a>
                <?php if ((int) $g['total_present'] === 0): ?>
                  <button type="button" class="btn btn-sm btn-outline-danger btn-delete-group"
                          data-id="<?= (int) $g['id'] ?>" data-name="<?= e($g['group_name']) ?>" title="Hapus">
                    <i class="bi bi-trash"></i>
                  </button>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$groups): ?>
            <tr><td colspan="7" class="text-center text-muted py-4">Tidak ada data grup.</td></tr>
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

<form id="deleteGroupForm" method="post" action="<?= e(base_url('groups/delete.php')) ?>" class="d-none">
  <?= csrf_field() ?>
  <input type="hidden" name="id" id="deleteGroupId">
</form>

<script>
document.querySelectorAll('.btn-delete-group').forEach(function (btn) {
  btn.addEventListener('click', function () {
    if (confirm('Hapus grup "' + btn.dataset.name + '"? Tindakan ini tidak dapat dibatalkan.')) {
      document.getElementById('deleteGroupId').value = btn.dataset.id;
      document.getElementById('deleteGroupForm').submit();
    }
  });
});
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
