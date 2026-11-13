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

if (!session_qr_is_valid($parsed['session_type'], $parsed['event_id'], $parsed['bucket'], $parsed['token'])) {
    json_response(['success' => false, 'message' => 'QR sudah kedaluwarsa (kode berganti tiap 5 detik). Coba scan ulang.'], 403);
}

$groupStmt = $db->prepare('SELECT * FROM guest_groups WHERE id = :id');
$groupStmt->execute(['id' => $user['group_id']]);
$group = $groupStmt->fetch();

if (!$group) {
    json_response(['success' => false, 'message' => 'Data grup Anda tidak ditemukan. Hubungi panitia.'], 404);
}

if ((int) $group['event_id'] !== $parsed['event_id']) {
    json_response(['success' => false, 'message' => 'QR ini bukan untuk event Anda.'], 403);
}

if ($group['status'] !== 'active') {
    json_response(['success' => false, 'message' => 'Undangan Anda sedang tidak aktif. Hubungi panitia.'], 403);
}

try {
    $db->beginTransaction();

    $sessionStmt = $db->prepare(
        'SELECT * FROM session_attendance WHERE group_id = :group_id AND session_type = :session_type FOR UPDATE'
    );
    $sessionStmt->execute(['group_id' => $group['id'], 'session_type' => $parsed['session_type']]);
    $session = $sessionStmt->fetch();

    if (!$session) {
        $db->rollBack();
        json_response(['success' => false, 'message' => 'Sesi presensi tidak ditemukan. Hubungi panitia.'], 404);
    }

    if ($session['checked_in_at'] !== null) {
        $db->rollBack();
        json_response([
            'success' => false,
            'message' => 'Anda sudah presensi ' . session_type_label($parsed['session_type']) . ' pada ' . format_datetime($session['checked_in_at']) . '.',
        ], 409);
    }

    $now = date('Y-m-d H:i:s');
    $db->prepare('UPDATE session_attendance SET checked_in_at = :checked_in_at, updated_at = :updated_at WHERE id = :id')
        ->execute(['checked_in_at' => $now, 'updated_at' => $now, 'id' => $session['id']]);

    $db->commit();

    audit_log($db, (int) $user['id'], 'self_checkin_session', 'session_attendance', (int) $session['id'],
        'Presensi mandiri "' . session_type_label($parsed['session_type']) . '" oleh ' . $user['full_name']);

    json_response([
        'success'       => true,
        'message'       => 'Presensi berhasil',
        'session_type'  => $parsed['session_type'],
        'session_label' => session_type_label($parsed['session_type']),
        'checked_in_at' => format_datetime($now),
    ]);
} catch (Throwable $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    error_log('[Hadirin] Session self-checkin failed: ' . $e->getMessage());
    json_response(['success' => false, 'message' => 'Terjadi kesalahan pada server.'], 500);
}
