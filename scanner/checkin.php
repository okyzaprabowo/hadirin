<?php
declare(strict_types=1);
define('HADIRIN_AJAX', true);
require_once __DIR__ . '/../config/bootstrap.php';
require_role(['admin', 'petugas']);
require_post();

$db = get_db();
$user = current_user();

// 3. Validate CSRF
if (!csrf_verify($_POST['csrf_token'] ?? '')) {
    json_response(['success' => false, 'message' => 'Token CSRF tidak valid. Muat ulang halaman.'], 403);
}

$token = input_str($_POST, 'token');
$eventId = input_int($_POST, 'event_id', 0);
$guestIdsRaw = $_POST['guest_ids'] ?? [];
$guestIds = array_values(array_unique(array_map('intval', is_array($guestIdsRaw) ? $guestIdsRaw : [])));
$guestIds = array_filter($guestIds, fn($v) => $v > 0);

if ($token === '') {
    json_response(['success' => false, 'message' => 'Token QR tidak valid.'], 422);
}
if (count($guestIds) === 0) {
    json_response(['success' => false, 'message' => 'Pilih minimal satu tamu untuk check-in.'], 422);
}

// Petugas wajib memilih event aktif sebelum check-in
if ($eventId <= 0) {
    json_response(['success' => false, 'message' => 'Pilih event aktif terlebih dahulu sebelum check-in.'], 422);
}

$eventStmt = $db->prepare("SELECT id, event_name FROM events WHERE id = :id AND status = 'active' LIMIT 1");
$eventStmt->execute(['id' => $eventId]);
$event = $eventStmt->fetch();

if (!$event) {
    json_response(['success' => false, 'message' => 'Event tidak aktif atau tidak ditemukan.'], 403);
}

try {
    $db->beginTransaction();

    // 4. Validate QR token + lock group row
    $groupStmt = $db->prepare('SELECT * FROM guest_groups WHERE qr_token = :token LIMIT 1 FOR UPDATE');
    $groupStmt->execute(['token' => $token]);
    $group = $groupStmt->fetch();

    if (!$group) {
        $db->rollBack();
        $log = $db->prepare(
            'INSERT INTO checkin_logs (guest_id, group_id, scanned_by, action, message, scanned_at)
             VALUES (NULL, NULL, :scanned_by, :action, :message, :scanned_at)'
        );
        $log->execute([
            'scanned_by' => $user['id'], 'action' => 'rejected',
            'message' => 'Token QR tidak ditemukan saat check-in.', 'scanned_at' => date('Y-m-d H:i:s'),
        ]);
        json_response(['success' => false, 'message' => 'QR Code tidak valid atau tidak terdaftar.'], 404);
    }

    // 5. Pastikan QR milik event aktif yang dipilih petugas
    if ((int) $group['event_id'] !== $eventId) {
        $db->rollBack();
        $log = $db->prepare(
            'INSERT INTO checkin_logs (guest_id, group_id, scanned_by, action, message, scanned_at)
             VALUES (NULL, :group_id, :scanned_by, :action, :message, :scanned_at)'
        );
        $log->execute([
            'group_id' => $group['id'], 'scanned_by' => $user['id'], 'action' => 'rejected',
            'message' => 'QR milik event lain (' . $event['event_name'] . ') saat check-in.', 'scanned_at' => date('Y-m-d H:i:s'),
        ]);
        json_response(['success' => false, 'message' => 'QR ini bukan milik event "' . $event['event_name'] . '". Periksa kembali event yang dipilih.'], 403);
    }

    // 6. Check group status
    if ($group['status'] !== 'active') {
        $db->rollBack();
        $log = $db->prepare(
            'INSERT INTO checkin_logs (guest_id, group_id, scanned_by, action, message, scanned_at)
             VALUES (NULL, :group_id, :scanned_by, :action, :message, :scanned_at)'
        );
        $log->execute([
            'group_id' => $group['id'], 'scanned_by' => $user['id'], 'action' => 'rejected',
            'message' => 'Undangan nonaktif saat check-in.', 'scanned_at' => date('Y-m-d H:i:s'),
        ]);
        json_response(['success' => false, 'message' => 'Undangan ini tidak aktif.'], 403);
    }

    // 9. Lock the candidate guest rows for update
    $placeholders = implode(',', array_fill(0, count($guestIds), '?'));
    $lockStmt = $db->prepare(
        "SELECT id, full_name, group_id, checkin_at FROM guests WHERE id IN ({$placeholders}) FOR UPDATE"
    );
    $lockStmt->execute($guestIds);
    $lockedGuests = $lockStmt->fetchAll();

    // 6. Ensure every guest id belongs to this group
    if (count($lockedGuests) !== count($guestIds)) {
        $db->rollBack();
        json_response(['success' => false, 'message' => 'Data tamu tidak valid. Muat ulang status grup.'], 422);
    }
    foreach ($lockedGuests as $g) {
        if ((int) $g['group_id'] !== (int) $group['id']) {
            $db->rollBack();
            json_response(['success' => false, 'message' => 'Tamu tidak termasuk dalam grup ini. Muat ulang status grup.'], 422);
        }
    }

    // 7. Ensure none are already checked in (handles concurrent check-in race)
    $alreadyIn = array_filter($lockedGuests, fn($g) => $g['checkin_at'] !== null);
    if ($alreadyIn) {
        $db->rollBack();
        $names = implode(', ', array_map(fn($g) => $g['full_name'], $alreadyIn));
        json_response([
            'success' => false,
            'message' => "Konflik: tamu berikut sudah check-in sebelumnya ({$names}). Muat ulang status grup.",
        ], 409);
    }

    // 10. Update checkin_at + 11. insert checkin_logs
    $now = date('Y-m-d H:i:s');
    $updateStmt = $db->prepare('UPDATE guests SET checkin_at = :checkin_at, updated_at = :updated_at WHERE id = :id');
    $logStmt = $db->prepare(
        'INSERT INTO checkin_logs (guest_id, group_id, scanned_by, action, message, scanned_at)
         VALUES (:guest_id, :group_id, :scanned_by, :action, :message, :scanned_at)'
    );

    $checkedInGuests = [];
    foreach ($lockedGuests as $g) {
        $updateStmt->execute(['checkin_at' => $now, 'updated_at' => $now, 'id' => $g['id']]);
        $logStmt->execute([
            'guest_id'   => $g['id'],
            'group_id'   => $group['id'],
            'scanned_by' => $user['id'],
            'action'     => 'checkin',
            'message'    => 'Check-in berhasil',
            'scanned_at' => $now,
        ]);
        $checkedInGuests[] = ['id' => (int) $g['id'], 'full_name' => $g['full_name'], 'checkin_at' => $now];
    }

    // 12. Commit
    $db->commit();

    json_response([
        'success'          => true,
        'message'          => 'Check-in berhasil',
        'checked_in_count' => count($checkedInGuests),
        'guests'           => $checkedInGuests,
        'group_name'       => $group['group_name'],
    ]);
} catch (Throwable $e) {
    if ($db->inTransaction()) {
        // 13. Rollback on failure
        $db->rollBack();
    }
    error_log('[Hadirin] Check-in failed: ' . $e->getMessage());
    json_response(['success' => false, 'message' => 'Terjadi kesalahan pada server saat memproses check-in.'], 500);
}
