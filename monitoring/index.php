<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/bootstrap.php';
require_role('admin');

$db = get_db();
$pageTitle = 'Monitoring Kehadiran';

$eventId = input_int($_GET, 'event_id', 0);
$groupName = input_str($_GET, 'group_name');
$guestName = input_str($_GET, 'guest_name');
$status = input_str($_GET, 'status');
$dateFrom = input_str($_GET, 'date_from');
$dateTo = input_str($_GET, 'date_to');

$allEvents = $db->query('SELECT id, event_name FROM events ORDER BY event_name ASC')->fetchAll();

$officerActivity = $db->query(
    "SELECT u.full_name, COUNT(cl.id) AS total_checkins
     FROM users u
     LEFT JOIN checkin_logs cl ON cl.scanned_by = u.id AND cl.action = 'checkin'
     WHERE u.role = 'petugas'
     GROUP BY u.id, u.full_name
     ORDER BY total_checkins DESC"
)->fetchAll();

require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/sidebar.php';
?>
<div class="container-fluid p-4">
  <h3 class="page-title mb-4"><i class="bi bi-activity"></i> Monitoring Kehadiran</h3>

  <div class="card mb-3">
    <div class="card-body">
      <form id="filterForm" class="row g-2">
        <div class="col-md-2">
          <select name="event_id" class="form-select">
            <option value="0">Semua Event</option>
            <?php foreach ($allEvents as $ev): ?>
              <option value="<?= (int) $ev['id'] ?>" <?= $eventId === (int) $ev['id'] ? 'selected' : '' ?>><?= e($ev['event_name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-2">
          <input type="text" name="group_name" class="form-control" placeholder="Nama grup..." value="<?= e($groupName) ?>">
        </div>
        <div class="col-md-2">
          <input type="text" name="guest_name" class="form-control" placeholder="Nama tamu..." value="<?= e($guestName) ?>">
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
      </form>
    </div>
  </div>

  <div class="row g-3 mb-3">
    <div class="col-md-4">
      <div class="stat-card"><div class="stat-value" id="statTotal">-</div><div class="stat-label">Total Tamu</div></div>
    </div>
    <div class="col-md-4">
      <div class="stat-card"><div class="stat-value text-success" id="statPresent">-</div><div class="stat-label">Sudah Hadir</div></div>
    </div>
    <div class="col-md-4">
      <div class="stat-card"><div class="stat-value text-secondary" id="statAbsent">-</div><div class="stat-label">Belum Hadir</div></div>
    </div>
  </div>

  <div class="row g-3">
    <div class="col-lg-8">
      <div class="card">
        <div class="card-header d-flex justify-content-between">
          <span>Data Kehadiran</span>
          <small class="text-muted" id="lastUpdated">Memuat...</small>
        </div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-sm mb-0">
              <thead><tr><th>Nama Tamu</th><th>Event</th><th>Grup</th><th>Status</th><th>Waktu Check-in</th></tr></thead>
              <tbody id="monitorTableBody">
                <tr><td colspan="5" class="text-center text-muted py-4">Memuat data...</td></tr>
              </tbody>
            </table>
          </div>
        </div>
        <div class="card-footer d-flex flex-wrap justify-content-between align-items-center gap-2 bg-white">
          <small class="text-muted" id="monitorPageInfo"></small>
          <nav><ul class="pagination pagination-sm mb-0" id="monitorPagination"></ul></nav>
        </div>
      </div>
    </div>
    <div class="col-lg-4">
      <div class="card">
        <div class="card-header">Aktivitas Petugas</div>
        <div class="card-body p-0">
          <table class="table table-sm mb-0">
            <thead><tr><th>Petugas</th><th>Total Check-in</th></tr></thead>
            <tbody>
            <?php foreach ($officerActivity as $o): ?>
              <tr><td><?= e($o['full_name']) ?></td><td><?= (int) $o['total_checkins'] ?></td></tr>
            <?php endforeach; ?>
            <?php if (!$officerActivity): ?>
              <tr><td colspan="2" class="text-center text-muted py-3">Belum ada petugas.</td></tr>
            <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
function escapeHtml(str) {
  const div = document.createElement('div');
  div.textContent = str ?? '';
  return div.innerHTML;
}

let monitorPage = 1;

function renderPagination(pagination) {
  const info = document.getElementById('monitorPageInfo');
  const nav = document.getElementById('monitorPagination');

  if (!pagination || pagination.total === 0) {
    info.textContent = '';
    nav.innerHTML = '';
    return;
  }

  const start = (pagination.page - 1) * pagination.per_page + 1;
  const end = Math.min(pagination.page * pagination.per_page, pagination.total);
  info.textContent = `Menampilkan ${start}-${end} dari ${pagination.total} data`;

  let html = '';
  html += `<li class="page-item ${pagination.page <= 1 ? 'disabled' : ''}">
    <button type="button" class="page-link" data-page="${pagination.page - 1}">&laquo;</button>
  </li>`;
  for (let i = 1; i <= pagination.total_pages; i++) {
    html += `<li class="page-item ${i === pagination.page ? 'active' : ''}">
      <button type="button" class="page-link" data-page="${i}">${i}</button>
    </li>`;
  }
  html += `<li class="page-item ${pagination.page >= pagination.total_pages ? 'disabled' : ''}">
    <button type="button" class="page-link" data-page="${pagination.page + 1}">&raquo;</button>
  </li>`;
  nav.innerHTML = html;

  nav.querySelectorAll('.page-link').forEach(btn => {
    btn.addEventListener('click', () => {
      const target = parseInt(btn.dataset.page, 10);
      if (!target || target < 1 || target > pagination.total_pages || target === pagination.page) return;
      monitorPage = target;
      loadMonitoringData();
    });
  });
}

function loadMonitoringData() {
  const params = new URLSearchParams(new FormData(document.getElementById('filterForm')));
  params.set('page', monitorPage);
  fetch('<?= e(base_url('monitoring/data.php')) ?>?' + params.toString())
    .then(r => r.json())
    .then(data => {
      if (!data.success) return;
      document.getElementById('statTotal').textContent = data.stats.total_guests;
      document.getElementById('statPresent').textContent = data.stats.total_present;
      document.getElementById('statAbsent').textContent = data.stats.total_absent;

      const tbody = document.getElementById('monitorTableBody');
      if (data.rows.length === 0) {
        tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-4">Tidak ada data.</td></tr>';
      } else {
        tbody.innerHTML = data.rows.map(r => `
          <tr>
            <td>${escapeHtml(r.full_name)}</td>
            <td>${escapeHtml(r.event_name)}</td>
            <td>${escapeHtml(r.group_name)}</td>
            <td>${r.status === 'Sudah Hadir' ? '<span class="badge text-bg-success">Sudah Hadir</span>' : '<span class="badge text-bg-secondary">Belum Hadir</span>'}</td>
            <td>${escapeHtml(r.checkin_at ?? '-')}</td>
          </tr>
        `).join('');
      }
      renderPagination(data.pagination);
      document.getElementById('lastUpdated').textContent = 'Diperbarui ' + new Date().toLocaleTimeString('id-ID');
    })
    .catch(() => {
      document.getElementById('lastUpdated').textContent = 'Gagal memuat data.';
    });
}

document.getElementById('filterForm').addEventListener('input', function () {
  monitorPage = 1;
  loadMonitoringData();
});
loadMonitoringData();
setInterval(loadMonitoringData, 30000);
</script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
