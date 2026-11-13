<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/bootstrap.php';
require_role(['admin', 'petugas']);

$db = get_db();
$sessionId = input_int($_GET, 'session_id', 0);

$stmt = $db->prepare(
    "SELECT ps.id, ps.event_id, ps.status, ev.status AS event_status
     FROM presensi_sessions ps
     JOIN events ev ON ev.id = ps.event_id
     WHERE ps.id = :id"
);
$stmt->execute(['id' => $sessionId]);
$session = $stmt->fetch();

if (!$session) {
    http_response_code(404);
    exit('Sesi presensi tidak ditemukan.');
}
if ($session['status'] !== 'active' || $session['event_status'] !== 'active') {
    http_response_code(403);
    exit('Sesi presensi ini sedang tidak aktif.');
}

$content = session_qr_build_content((int) $session['id'], (int) $session['event_id']);
$png = generate_session_qr_png($content, 420);

header('Content-Type: image/png');
header('Content-Length: ' . strlen($png));
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
echo $png;
