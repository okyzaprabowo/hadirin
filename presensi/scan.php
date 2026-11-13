<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/bootstrap.php';
require_role('mahasiswa');

$pageTitle = 'Scan QR Presensi';
require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/sidebar.php';
?>
<div class="container-fluid p-3 scanner-page">
  <h4 class="page-title text-center mb-3"><i class="bi bi-qr-code-scan"></i> Scan QR Presensi</h4>

  <div id="scanSection">
    <div class="alert alert-light border text-center small mb-3">
      Arahkan kamera ke layar QR yang ditampilkan panitia di lokasi
      (Absen Masuk / Snack / Makan Siang). Kode berganti tiap 5 detik,
      jadi cukup arahkan kamera sampai berhasil terbaca.
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
  </div>

  <div id="resultSection" class="d-none">
    <div id="resultContent"></div>
  </div>

  <div id="scanAlert"></div>
</div>

<script src="https://cdn.jsdelivr.net/npm/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
<script>window.HADIRIN_CSRF = <?= json_encode(csrf_token()) ?>;</script>
<script src="<?= e(base_url('assets/js/presensi-scan.js')) ?>?v=<?= @filemtime(APP_ROOT . '/assets/js/presensi-scan.js') ?: '1' ?>"></script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
