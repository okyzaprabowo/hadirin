<?php
declare(strict_types=1);
define('HADIRIN_AJAX', true);
require_once __DIR__ . '/../config/bootstrap.php';
require_role(['admin', 'petugas']);

$db = get_db();
$sessionId = input_int($_GET, 'session_id', 0);

$existsStmt = $db->prepare('SELECT COUNT(*) FROM presensi_sessions WHERE id = :id');
$existsStmt->execute(['id' => $sessionId]);
if ((int) $existsStmt->fetchColumn() === 0) {
    json_response(['success' => false, 'message' => 'Sesi tidak ditemukan.'], 404);
}

$stmt = $db->prepare(
    "SELECT COUNT(*) AS total, SUM(CASE WHEN checked_in_at IS NOT NULL THEN 1 ELSE 0 END) AS checked_in
     FROM session_attendance WHERE presensi_session_id = :id"
);
$stmt->execute(['id' => $sessionId]);
$row = $stmt->fetch();

json_response([
    'success'    => true,
    'total'      => (int) $row['total'],
    'checked_in' => (int) $row['checked_in'],
]);
