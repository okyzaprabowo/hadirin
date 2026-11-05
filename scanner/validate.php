<?php
declare(strict_types=1);
define('HADIRIN_AJAX', true);
require_once __DIR__ . '/../config/bootstrap.php';
require_role(['admin', 'petugas']);
require_post();

$db = get_db();
$user = current_user();

if (!csrf_verify($_POST['csrf_token'] ?? '')) {
    json_response(['success' => false, 'message' => 'Token CSRF tidak valid. Muat ulang halaman.'], 403);
}

$token = input_str($_POST, 'token');
$eventId = input_int($_POST, 'event_id', 0);

// 1. Petugas wajib memilih event aktif sebelum melakukan scan
if ($eventId <= 0) {
    json_response(['success' => false, 'message' => 'Pilih event aktif terlebih dahulu sebelum scan.'], 422);
}

$eventStmt = $db->prepare("SELECT id, event_name FROM events WHERE id = :id AND status = 'active' LIMIT 1");
$eventStmt->execute(['id' => $eventId]);
$event = $eventStmt->fetch();

if (!$event) {
    json_response(['success' => false, 'message' => 'Event tidak aktif atau tidak ditemukan.'], 403);
}

if ($token === '' || !preg_match('/^[a-f0-9]{16,100}$/i', $token)) {
    json_response(['success' => false, 'message' => 'Barcode tidak valid atau tidak terdaftar.'], 404);
}

$stmt = $db->prepare('SELECT * FROM guest_groups WHERE qr_token = :token LIMIT 1');
$stmt->execute(['token' => $token]);
$group = $stmt->fetch();

if (!$group) {
    $log = $db->prepare(
        'INSERT INTO checkin_logs (guest_id, group_id, scanned_by, action, message, scanned_at)
         VALUES (NULL, NULL, :scanned_by, :action, :message, :scanned_at)'
    );
    $log->execute([
        'scanned_by' => $user['id'],
        'action'     => 'rejected',
        'message'    => 'Token barcode tidak ditemukan.',
        'scanned_at' => date('Y-m-d H:i:s'),
    ]);
    json_response(['success' => false, 'message' => 'Barcode tidak valid atau tidak terdaftar.'], 404);
}

// 2. Barcode harus milik event aktif yang dipilih petugas
if ((int) $group['event_id'] !== $eventId) {
    $log = $db->prepare(
        'INSERT INTO checkin_logs (guest_id, group_id, scanned_by, action, message, scanned_at)
         VALUES (NULL, :group_id, :scanned_by, :action, :message, :scanned_at)'
    );
    $log->execute([
        'group_id'   => $group['id'],
        'scanned_by' => $user['id'],
        'action'     => 'rejected',
        'message'    => 'Barcode milik event lain (' . $event['event_name'] . ').',
        'scanned_at' => date('Y-m-d H:i:s'),
    ]);
    json_response(['success' => false, 'message' => 'Barcode ini bukan milik event "' . $event['event_name'] . '". Periksa kembali event yang dipilih.'], 403);
}

if ($group['status'] !== 'active') {
    $log = $db->prepare(
        'INSERT INTO checkin_logs (guest_id, group_id, scanned_by, action, message, scanned_at)
         VALUES (NULL, :group_id, :scanned_by, :action, :message, :scanned_at)'
    );
    $log->execute([
        'group_id'   => $group['id'],
        'scanned_by' => $user['id'],
        'action'     => 'rejected',
        'message'    => 'Undangan nonaktif.',
        'scanned_at' => date('Y-m-d H:i:s'),
    ]);
    json_response(['success' => false, 'message' => 'Undangan ini tidak aktif.'], 403);
}

$guestsStmt = $db->prepare('SELECT id, full_name, checkin_at FROM guests WHERE group_id = :id ORDER BY full_name ASC');
$guestsStmt->execute(['id' => $group['id']]);
$guests = $guestsStmt->fetchAll();

$present = 0;
$guestsOut = [];
foreach ($guests as $g) {
    $isPresent = $g['checkin_at'] !== null;
    if ($isPresent) {
        $present++;
    }
    $guestsOut[] = [
        'id'         => (int) $g['id'],
        'full_name'  => $g['full_name'],
        'checked_in' => $isPresent,
        'checkin_at' => $g['checkin_at'],
    ];
}

json_response([
    'success' => true,
    'message' => 'Barcode Valid',
    'event'   => [
        'id'         => (int) $event['id'],
        'event_name' => $event['event_name'],
    ],
    'group'   => [
        'id'         => (int) $group['id'],
        'group_name' => $group['group_name'],
        'quota'      => (int) $group['quota'],
        'qr_token'   => $group['qr_token'],
        'total'      => count($guests),
        'present'    => $present,
        'absent'     => count($guests) - $present,
    ],
    'guests' => $guestsOut,
]);
