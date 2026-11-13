<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/bootstrap.php';
require_role('admin');
require_post();
csrf_require_valid();

$db = get_db();
$groupId = input_int($_POST, 'group_id', 0);
$sessionType = input_str($_POST, 'session_type');
$action = input_str($_POST, 'action', 'mark');

if (!array_key_exists($sessionType, session_types())) {
    flash_set('danger', 'Sesi tidak valid.');
    redirect(base_url('groups/detail.php?id=' . $groupId));
}

$sessionStmt = $db->prepare('SELECT * FROM session_attendance WHERE group_id = :group_id AND session_type = :session_type');
$sessionStmt->execute(['group_id' => $groupId, 'session_type' => $sessionType]);
$session = $sessionStmt->fetch();

if (!$session) {
    flash_set('danger', 'Data presensi grup tidak ditemukan.');
    redirect(base_url('groups/detail.php?id=' . $groupId));
}

$newValue = $action === 'unmark' ? null : date('Y-m-d H:i:s');

$db->prepare('UPDATE session_attendance SET checked_in_at = :value, updated_at = NOW() WHERE id = :id')
    ->execute(['value' => $newValue, 'id' => $session['id']]);

audit_log($db, (int) current_user()['id'], $action === 'unmark' ? 'unmark_session' : 'mark_session', 'session_attendance', (int) $session['id'],
    ($action === 'unmark' ? 'Membatalkan' : 'Menandai hadir') . ' presensi "' . session_type_label($sessionType) . '" untuk grup #' . $groupId . ' (manual oleh admin)');

flash_set('success', 'Status presensi berhasil diperbarui.');
redirect(base_url('groups/detail.php?id=' . $groupId));
