<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/bootstrap.php';
require_role('admin');
require_post();
csrf_require_valid();

$db = get_db();
$groupId = input_int($_POST, 'group_id', 0);
$presensiSessionId = input_int($_POST, 'presensi_session_id', 0);
$action = input_str($_POST, 'action', 'mark');

$sessionStmt = $db->prepare('SELECT * FROM presensi_sessions WHERE id = :id');
$sessionStmt->execute(['id' => $presensiSessionId]);
$presensiSession = $sessionStmt->fetch();

if (!$presensiSession) {
    flash_set('danger', 'Sesi presensi tidak valid.');
    redirect(base_url('groups/detail.php?id=' . $groupId));
}

$attendanceStmt = $db->prepare('SELECT * FROM session_attendance WHERE group_id = :group_id AND presensi_session_id = :presensi_session_id');
$attendanceStmt->execute(['group_id' => $groupId, 'presensi_session_id' => $presensiSessionId]);
$attendance = $attendanceStmt->fetch();

$now = date('Y-m-d H:i:s');

if (!$attendance) {
    $db->prepare(
        'INSERT INTO session_attendance (group_id, presensi_session_id, created_at, updated_at) VALUES (:group_id, :presensi_session_id, :now, :now)'
    )->execute(['group_id' => $groupId, 'presensi_session_id' => $presensiSessionId, 'now' => $now]);
    $attendanceId = (int) $db->lastInsertId();
} else {
    $attendanceId = (int) $attendance['id'];
}

$newValue = $action === 'unmark' ? null : $now;

$db->prepare('UPDATE session_attendance SET checked_in_at = :value, updated_at = :updated_at WHERE id = :id')
    ->execute(['value' => $newValue, 'updated_at' => $now, 'id' => $attendanceId]);

audit_log($db, (int) current_user()['id'], $action === 'unmark' ? 'unmark_session' : 'mark_session', 'session_attendance', $attendanceId,
    ($action === 'unmark' ? 'Membatalkan' : 'Menandai hadir') . ' presensi "' . $presensiSession['name'] . '" untuk grup #' . $groupId . ' (manual oleh admin)');

flash_set('success', 'Status presensi berhasil diperbarui.');
redirect(base_url('groups/detail.php?id=' . $groupId));
