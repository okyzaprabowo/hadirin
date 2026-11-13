<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/bootstrap.php';
require_role('admin');
require_post();
csrf_require_valid();

$db = get_db();
$id = input_int($_POST, 'id', 0);

$stmt = $db->prepare('SELECT * FROM presensi_sessions WHERE id = :id');
$stmt->execute(['id' => $id]);
$session = $stmt->fetch();

if (!$session) {
    flash_set('danger', 'Sesi presensi tidak ditemukan.');
    redirect(base_url('presensi/sessions/index.php'));
}

$checkedInCountStmt = $db->prepare('SELECT COUNT(*) FROM session_attendance WHERE presensi_session_id = :id AND checked_in_at IS NOT NULL');
$checkedInCountStmt->execute(['id' => $id]);
$checkedInCount = (int) $checkedInCountStmt->fetchColumn();

if ($checkedInCount > 0) {
    flash_set('danger', 'Sesi tidak dapat dihapus karena sudah memiliki riwayat presensi. Nonaktifkan saja jika ingin menutupnya.');
    redirect(base_url('presensi/sessions/index.php'));
}

try {
    $db->beginTransaction();
    $db->prepare('DELETE FROM session_attendance WHERE presensi_session_id = :id')->execute(['id' => $id]);
    $db->prepare('DELETE FROM presensi_sessions WHERE id = :id')->execute(['id' => $id]);
    $db->commit();

    audit_log($db, (int) current_user()['id'], 'delete_presensi_session', 'presensi_session', $id,
        'Menghapus sesi presensi "' . $session['name'] . '"');
    flash_set('success', 'Sesi presensi berhasil dihapus.');
} catch (Throwable $e) {
    $db->rollBack();
    error_log('[Hadirin] Failed to delete presensi session: ' . $e->getMessage());
    flash_set('danger', 'Sesi presensi tidak dapat dihapus karena masih memiliki data terkait.');
}

redirect(base_url('presensi/sessions/index.php'));
