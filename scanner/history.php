<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/bootstrap.php';
require_role(['admin', 'petugas']);

$db = get_db();
$user = current_user();
$pageTitle = 'Riwayat Scan';
$page = max(1, input_int($_GET, 'page', 1));

$countStmt = $db->prepare('SELECT COUNT(*) FROM checkin_logs WHERE scanned_by = :uid');
$countStmt->execute(['uid' => $user['id']]);
$total = (int) $countStmt->fetchColumn();
$pagination = paginate_calc($total, $page, 15);

$stmt = $db->prepare(
    "SELECT cl.*, g.full_name AS guest_name, gg.group_name
     FROM checkin_logs cl
     LEFT JOIN guests g ON g.id = cl.guest_id
     LEFT JOIN guest_groups gg ON gg.id = cl.group_id
     WHERE cl.scanned_by = :uid
     ORDER BY cl.scanned_at DESC
     LIMIT :limit OFFSET :offset"
);
$stmt->bindValue('uid', $user['id'], PDO::PARAM_INT);
$stmt->bindValue('limit', $pagination['per_page'], PDO::PARAM_INT);
$stmt->bindValue('offset', $pagination['offset'], PDO::PARAM_INT);
$stmt->execute();
$logs = $stmt->fetchAll();

require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/sidebar.php';
?>
<div class="container-fluid p-4">
  <h3 class="page-title mb-4"><i class="bi bi-clock-history"></i> Riwayat Scan Saya</h3>

  <div class="card">
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-sm mb-0 align-middle">
          <thead><tr><th>Waktu</th><th>Grup</th><th>Tamu</th><th>Status</th><th>Keterangan</th></tr></thead>
          <tbody>
          <?php foreach ($logs as $log): ?>
            <tr>
              <td><?= e(format_datetime($log['scanned_at'])) ?></td>
              <td><?= e($log['group_name'] ?? '-') ?></td>
              <td><?= e($log['guest_name'] ?? '-') ?></td>
              <td><?= $log['action'] === 'checkin' ? '<span class="badge text-bg-success">Check-in</span>' : '<span class="badge text-bg-danger">Ditolak</span>' ?></td>
              <td><?= e($log['message'] ?? '-') ?></td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$logs): ?>
            <tr><td colspan="5" class="text-center text-muted py-4">Belum ada riwayat scan.</td></tr>
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
