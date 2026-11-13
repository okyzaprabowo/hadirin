<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/bootstrap.php';
require_role(['admin', 'petugas']);

$db = get_db();
$sessionType = input_str($_GET, 'session');
$eventId = input_int($_GET, 'event_id', 0);

if (!array_key_exists($sessionType, session_types())) {
    http_response_code(422);
    exit('Sesi tidak valid.');
}

$eventStmt = $db->prepare("SELECT id, event_name FROM events WHERE id = :id AND status = 'active'");
$eventStmt->execute(['id' => $eventId]);
$event = $eventStmt->fetch();

if (!$event) {
    http_response_code(404);
    exit('Event tidak ditemukan atau tidak aktif.');
}

$label = session_type_label($sessionType);
$qrUrl = base_url('presensi/qr.php?session=' . $sessionType . '&event_id=' . $eventId);
$statsUrl = base_url('presensi/stats.php?session=' . $sessionType . '&event_id=' . $eventId);
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($label) ?> &middot; <?= e(APP_NAME) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@600;700&display=swap" rel="stylesheet">
<style>
  * { font-family: 'Poppins', sans-serif; box-sizing: border-box; }
  body {
    margin: 0; min-height: 100vh; background: #1E3A8A;
    display: flex; flex-direction: column; align-items: center; justify-content: center;
    color: #fff; text-align: center; padding: 24px;
  }
  .brand { letter-spacing: 4px; font-size: 1rem; opacity: .8; }
  h1 { font-size: 2.4rem; margin: 8px 0 24px; }
  .qr-box { background: #fff; border-radius: 20px; padding: 24px; box-shadow: 0 20px 60px rgba(0,0,0,.3); }
  .qr-box img { width: min(60vw, 420px); height: min(60vw, 420px); display: block; }
  .event-name { margin-top: 20px; opacity: .85; font-size: 1.1rem; }
  .stats { margin-top: 24px; font-size: 1.2rem; }
  .stats strong { font-size: 1.6rem; }
</style>
</head>
<body>
  <div class="brand">HADIRIN</div>
  <h1><?= e($label) ?></h1>
  <div class="qr-box">
    <img id="qrImg" src="" alt="QR Presensi">
  </div>
  <div class="event-name"><?= e($event['event_name']) ?></div>
  <div class="stats">Sudah presensi: <strong id="statChecked">-</strong> / <span id="statTotal">-</span></div>

  <script>
    var qrUrl = <?= json_encode($qrUrl) ?>;
    var statsUrl = <?= json_encode($statsUrl) ?>;

    function refreshQr() {
      document.getElementById('qrImg').src = qrUrl + '&t=' + Date.now();
    }

    function refreshStats() {
      fetch(statsUrl + '&t=' + Date.now())
        .then(function (r) { return r.json(); })
        .then(function (data) {
          if (!data.success) return;
          document.getElementById('statChecked').textContent = data.checked_in;
          document.getElementById('statTotal').textContent = data.total;
        })
        .catch(function () {});
    }

    refreshQr();
    refreshStats();
    setInterval(refreshQr, 5000);
    setInterval(refreshStats, 5000);
  </script>
</body>
</html>
