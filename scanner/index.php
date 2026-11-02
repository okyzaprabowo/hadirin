<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/bootstrap.php';
require_role(['admin', 'petugas']);

$db = get_db();
$activeEvents = $db->query(
    "SELECT id, event_name, event_date, location
     FROM events
     WHERE status = 'active'
     ORDER BY event_date ASC, event_name ASC"
)->fetchAll();

$pageTitle = 'Scan QR Code';
require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/sidebar.php';
?>
<div class="container-fluid p-3 scanner-page">
  <h4 class="page-title text-center mb-3"><i class="bi bi-qr-code-scan"></i> Hadirin Scanner</h4>

  <?php if (!$activeEvents): ?>
    <div class="alert alert-warning text-center">
      <i class="bi bi-exclamation-triangle"></i> Belum ada event aktif.
      <div class="small mt-1">Hubungi admin untuk mengaktifkan event sebelum memulai scan.</div>
    </div>
  <?php else: ?>
    <!-- LANGKAH 1: pilih event aktif -->
    <div class="card mb-3">
      <div class="card-body">
        <label for="eventSelect" class="form-label fw-semibold mb-2">
          <i class="bi bi-calendar-check"></i> Langkah 1 &mdash; Pilih Event Aktif
        </label>
        <select id="eventSelect" class="form-select">
          <?php if (count($activeEvents) > 1): ?>
            <option value="">-- Pilih event --</option>
          <?php endif; ?>
          <?php foreach ($activeEvents as $ev): ?>
            <option
              value="<?= (int) $ev['id'] ?>"
              data-name="<?= e($ev['event_name']) ?>"
              data-date="<?= e((string) ($ev['event_date'] ?? '')) ?>"
              data-location="<?= e((string) ($ev['location'] ?? '')) ?>"
            ><?= e($ev['event_name']) ?><?= $ev['event_date'] ? ' (' . e($ev['event_date']) . ')' : '' ?></option>
          <?php endforeach; ?>
        </select>
        <div id="eventInfo" class="text-muted small mt-2 d-none"></div>
      </div>
    </div>

    <div id="noEventNotice" class="alert alert-info text-center">
      Pilih event aktif terlebih dahulu untuk mulai scan.
    </div>

    <!-- LANGKAH 2: scan untuk event terpilih -->
    <div id="scanSection" class="d-none">
      <div class="alert alert-primary py-2 small mb-3">
        <i class="bi bi-broadcast"></i> Event aktif: <strong id="activeEventBanner">&mdash;</strong>
      </div>

      <div class="card scanner-card mb-3">
        <div class="card-body">
          <div id="qr-reader"></div>
          <div class="d-flex gap-2 mt-3 flex-wrap">
            <button id="btnStartScan" class="btn btn-success btn-lg flex-grow-1"><i class="bi bi-camera-video"></i> Mulai Scan</button>
            <button id="btnStopScan" class="btn btn-outline-danger btn-lg flex-grow-1 d-none"><i class="bi bi-stop-circle"></i> Stop Scan</button>
          </div>
          <div class="mt-2">
            <select id="cameraSelect" class="form-select d-none"></select>
          </div>
        </div>
      </div>

      <div class="card">
        <div class="card-body">
          <label class="form-label">Input Token Manual (fallback)</label>
          <div class="input-group">
            <input type="text" id="manualToken" class="form-control" placeholder="Masukkan token QR...">
            <button id="btnManualCheck" class="btn btn-primary"><i class="bi bi-search"></i> Cek</button>
          </div>
        </div>
      </div>
    </div>
  <?php endif; ?>

  <div id="resultSection" class="d-none">
    <div id="resultContent"></div>
  </div>

  <div id="scanAlert"></div>
</div>

<script src="https://cdn.jsdelivr.net/npm/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
<script>window.HADIRIN_CSRF = <?= json_encode(csrf_token()) ?>;</script>
<script src="<?= e(base_url('assets/js/scanner.js')) ?>?v=<?= @filemtime(APP_ROOT . '/assets/js/scanner.js') ?: '1' ?>"></script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
