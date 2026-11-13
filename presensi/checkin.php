<?php
declare(strict_types=1);
define('HADIRIN_AJAX', true);
require_once __DIR__ . '/../config/bootstrap.php';
require_role('mahasiswa');
require_post();

$db = get_db();
$user = current_user();

if (!csrf_verify($_POST['csrf_token'] ?? '')) {
    json_response(['success' => false, 'message' => 'Token CSRF tidak valid. Muat ulang halaman.'], 403);
}

if (!$user['group_id']) {
    json_response(['success' => false, 'message' => 'Akun Anda tidak terhubung ke grup manapun. Hubungi panitia.'], 404);
}

$content = input_str($_POST, 'content');
$parsed = session_qr_parse($content);

if (!$parsed) {
    json_response(['success' => false, 'message' => 'QR tidak dikenali. Pastikan Anda scan layar presensi panitia.'], 422);
}

if (!session_qr_is_valid($parsed['presensi_session_id'], $parsed['event_id'], $parsed['bucket'], $parsed['token'])) {
    json_response(['success' => false, 'message' => 'QR sudah kedaluwarsa (kode berganti tiap 5 detik). Coba scan ulang.'], 403);
}

$sessionStmt = $db->prepare(
    "SELECT ps.*, ev.status AS event_status
     FROM presensi_sessions ps JOIN events ev ON ev.id = ps.event_id
     WHERE ps.id = :id"
);
$sessionStmt->execute(['id' => $parsed['presensi_session_id']]);
$presensiSession = $sessionStmt->fetch();

if (!$presensiSession || $presensiSession['status'] !== 'active' || $presensiSession['event_status'] !== 'active') {
    json_response(['success' => false, 'message' => 'Sesi presensi ini sedang tidak aktif.'], 403);
}

$groupStmt = $db->prepare('SELECT * FROM guest_groups WHERE id = :id');
$groupStmt->execute(['id' => $user['group_id']]);
$group = $groupStmt->fetch();

if (!$group) {
    json_response(['success' => false, 'message' => 'Data grup Anda tidak ditemukan. Hubungi panitia.'], 404);
}

if ((int) $group['event_id'] !== (int) $presensiSession['event_id']) {
    json_response(['success' => false, 'message' => 'QR ini bukan untuk event Anda.'], 403);
}

if ($group['status'] !== 'active') {
    json_response(['success' => false, 'message' => 'Undangan Anda sedang tidak aktif. Hubungi panitia.'], 403);
}

try {
    $db->beginTransaction();

    $attendanceStmt = $db->prepare(
        'SELECT * FROM session_attendance WHERE group_id = :group_id AND presensi_session_id = :presensi_session_id FOR UPDATE'
    );
    $attendanceStmt->execute(['group_id' => $group['id'], 'presensi_session_id' => $presensiSession['id']]);
    $attendance = $attendanceStmt->fetch();

    if (!$attendance) {
        // Grup belum punya baris untuk sesi ini (mis. sesi dibuat setelah grup terdaftar) — buat sekarang.
        $now = date('Y-m-d H:i:s');
        $db->prepare(
            'INSERT INTO session_attendance (group_id, presensi_session_id, created_at, updated_at) VALUES (:group_id, :presensi_session_id, :now, :now)'
        )->execute(['group_id' => $group['id'], 'presensi_session_id' => $presensiSession['id'], 'now' => $now]);
        $attendance = ['id' => (int) $db->lastInsertId(), 'checked_in_at' => null];
    }

    if ($attendance['checked_in_at'] !== null) {
        $db->rollBack();
        json_response([
            'success' => false,
            'message' => 'Anda sudah presensi ' . $presensiSession['name'] . ' pada ' . format_datetime($attendance['checked_in_at']) . '.',
        ], 409);
    }

    $now = date('Y-m-d H:i:s');
    $db->prepare('UPDATE session_attendance SET checked_in_at = :checked_in_at, updated_at = :updated_at WHERE id = :id')
        ->execute(['checked_in_at' => $now, 'updated_at' => $now, 'id' => $attendance['id']]);

    $db->commit();

    audit_log($db, (int) $user['id'], 'self_checkin_session', 'session_attendance', (int) $attendance['id'],
        'Presensi mandiri "' . $presensiSession['name'] . '" oleh ' . $user['full_name']);

    json_response([
        'success'       => true,
        'message'       => 'Presensi berhasil',
        'session_label' => $presensiSession['name'],
        'checked_in_at' => format_datetime($now),
    ]);
} catch (Throwable $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    error_log('[Hadirin] Session self-checkin failed: ' . $e->getMessage());
    json_response(['success' => false, 'message' => 'Terjadi kesalahan pada server.'], 500);
}
