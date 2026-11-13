<?php
declare(strict_types=1);
define('HADIRIN_AJAX', true);
require_once __DIR__ . '/../config/bootstrap.php';
require_role(['admin', 'petugas']);

$db = get_db();
$sessionType = input_str($_GET, 'session');
$eventId = input_int($_GET, 'event_id', 0);

if (!array_key_exists($sessionType, session_types())) {
    json_response(['success' => false, 'message' => 'Sesi tidak valid.'], 422);
}

$stmt = $db->prepare(
    "SELECT COUNT(*) AS total, SUM(CASE WHEN sa.checked_in_at IS NOT NULL THEN 1 ELSE 0 END) AS checked_in
     FROM session_attendance sa
     JOIN guest_groups gg ON gg.id = sa.group_id
     WHERE sa.session_type = :session_type AND gg.event_id = :event_id"
);
$stmt->execute(['session_type' => $sessionType, 'event_id' => $eventId]);
$row = $stmt->fetch();

json_response([
    'success'    => true,
    'total'      => (int) $row['total'],
    'checked_in' => (int) $row['checked_in'],
]);
