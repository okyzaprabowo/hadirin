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

$eventCheck = $db->prepare("SELECT COUNT(*) FROM events WHERE id = :id AND status = 'active'");
$eventCheck->execute(['id' => $eventId]);
if ((int) $eventCheck->fetchColumn() === 0) {
    http_response_code(404);
    exit('Event tidak ditemukan atau tidak aktif.');
}

$content = session_qr_build_content($sessionType, $eventId);
$png = generate_session_qr_png($content, 420);

header('Content-Type: image/png');
header('Content-Length: ' . strlen($png));
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
echo $png;
