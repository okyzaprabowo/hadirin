<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/bootstrap.php';
require_role(['admin', 'petugas']);

$db = get_db();
$pageTitle = 'Monitoring Kehadiran';

$allEvents = $db->query("SELECT id, event_name FROM events ORDER BY status = 'active' DESC, event_date DESC")->fetchAll();

require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/sidebar.php';
?>
<div class="container-fluid p-4">
  <h3 class="page-title mb-4"><i class="bi bi-activity"></i> Monitoring Kehadiran</h3>

  <div class="card mb-3">
    <div class="card-body">
      <form id="filterForm" class="row g-2">
        <div class="col-md-3">
          <select name="event_id" id="filterEvent" class="form-select">
            <?php foreach ($allEvents as $ev): ?>
              <option value="<?= (int) $ev['id'] ?>"><?= e($ev['event_name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-3">
          <input type="text" name="search" class="form-control" placeholder="Cari nama grup / NIM / penanggung jawab...">
        </div>
        <div class="col-md-3">
          <select name="session_id" id="filterSession" class="form-select">
            <option value="0">Semua Sesi</option>
          </select>
        </div>
        <div class="col-md-3">
          <select name="status" class="form-select">
            <option value="">Semua Status</option>
            <option value="present">Sudah Hadir (di sesi terpilih)</option>
            <option value="absent">Belum Hadir (di sesi terpilih)</option>
          </select>
        </div>
      </form>
    </div>
  </div>

  <div class="row g-3 mb-3">
    <div class="col-md-3">
      <div class="stat-card"><div class="stat-value" id="statGroups">-</div><div class="stat-label">Total Grup</div></div>
    </div>
    <div class="col-md-3">
      <div class="stat-card"><div class="stat-value text-success" id="statChecked">-</div><div class="stat-label">Slot Presensi Terisi</div></div>
    </div>
    <div class="col-md-3">
      <div class="stat-card"><div class="stat-value text-secondary" id="statNotChecked">-</div><div class="stat-label">Slot Presensi Kosong</div></div>
    </div>
    <div class="col-md-3">
      <div class="stat-card"><div class="stat-value" id="statPercentage">-</div><div class="stat-label">Persentase Presensi</div></div>
    </div>
  </div>

  <div class="row g-3">
    <div class="col-lg-9">
      <div class="card">
        <div class="card-header d-flex justify-content-between">
          <span>Status Presensi per Grup</span>
          <small class="text-muted" id="lastUpdated">Memuat...</small>
        </div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-sm mb-0 align-middle">
              <thead id="monitorTableHead"></thead>
              <tbody id="monitorTableBody">
                <tr><td class="text-center text-muted py-4">Memuat data...</td></tr>
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
    <div class="col-lg-3">
      <div class="card">
        <div class="card-header">Ringkasan per Sesi</div>
        <div class="card-body p-0">
          <table class="table table-sm mb-0">
            <thead><tr><th>Sesi</th><th>Hadir</th></tr></thead>
            <tbody id="sessionSummaryBody">
              <tr><td colspan="2" class="text-center text-muted py-3">Memuat...</td></tr>
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
let currentSessions = [];

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
  info.textContent = `Menampilkan ${start}-${end} dari ${pagination.total} grup`;

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

function renderSessionFilterOptions(sessions) {
  const select = document.getElementById('filterSession');
  const current = select.value;
  select.innerHTML = '<option value="0">Semua Sesi</option>' +
    sessions.map(s => `<option value="${s.id}">${escapeHtml(s.name)}</option>`).join('');
  if (sessions.some(s => String(s.id) === current)) {
    select.value = current;
  }
}

function renderTableHead(sessions) {
  const thead = document.getElementById('monitorTableHead');
  let html = '<tr><th>Nama Grup</th><th>Penanggung Jawab</th>';
  sessions.forEach(s => { html += `<th class="text-center">${escapeHtml(s.name)}</th>`; });
  html += '<th class="text-center">Total</th></tr>';
  thead.innerHTML = html;
}

function sessionBadge(checkedInAt) {
  if (checkedInAt) {
    return `<span class="badge text-bg-success" title="${escapeHtml(checkedInAt)}">Hadir</span><div class="small text-muted">${escapeHtml(checkedInAt)}</div>`;
  }
  return '<span class="badge text-bg-secondary">Belum</span>';
}

function renderRows(rows, sessions) {
  const tbody = document.getElementById('monitorTableBody');
  if (rows.length === 0) {
    tbody.innerHTML = `<tr><td colspan="${sessions.length + 3}" class="text-center text-muted py-4">Tidak ada data.</td></tr>`;
    return;
  }
  tbody.innerHTML = rows.map(r => {
    const sessionCells = r.sessions.map(s => `<td class="text-center">${sessionBadge(s.checked_in_at)}</td>`).join('');
    return `<tr>
      <td>${escapeHtml(r.group_name)}</td>
      <td>${escapeHtml(r.contact_name ?? '-')}</td>
      ${sessionCells}
      <td class="text-center fw-semibold">${r.total_present} / ${r.total_sessions}</td>
    </tr>`;
  }).join('');
}

function renderSessionSummary(stats) {
  const tbody = document.getElementById('sessionSummaryBody');
  if (!stats.length) {
    tbody.innerHTML = '<tr><td colspan="2" class="text-center text-muted py-3">Belum ada sesi.</td></tr>';
    return;
  }
  tbody.innerHTML = stats.map(s => `
    <tr><td>${escapeHtml(s.name)}</td><td>${s.checked_in} / ${s.total}</td></tr>
  `).join('');
}

function loadMonitoringData() {
  const params = new URLSearchParams(new FormData(document.getElementById('filterForm')));
  params.set('page', monitorPage);
  fetch('<?= e(base_url('monitoring/data.php')) ?>?' + params.toString())
    .then(r => r.json())
    .then(data => {
      if (!data.success) return;
      currentSessions = data.sessions;

      document.getElementById('statGroups').textContent = data.stats.total_groups;
      document.getElementById('statChecked').textContent = data.stats.total_checked;
      document.getElementById('statNotChecked').textContent = data.stats.total_not_checked;
      const pct = data.stats.total_slots > 0 ? Math.round((data.stats.total_checked / data.stats.total_slots) * 1000) / 10 : 0;
      document.getElementById('statPercentage').textContent = pct + '%';

      renderSessionFilterOptions(data.sessions);
      renderTableHead(data.sessions);
      renderRows(data.rows, data.sessions);
      renderSessionSummary(data.per_session_stats);
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
