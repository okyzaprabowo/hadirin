<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/bootstrap.php';
require_role('admin');
require_post();
csrf_require_valid();

$db = get_db();
$id = input_int($_POST, 'id', 0);

$stmt = $db->prepare('SELECT * FROM guest_groups WHERE id = :id');
$stmt->execute(['id' => $id]);
$group = $stmt->fetch();

if (!$group) {
    flash_set('danger', 'Grup tidak ditemukan.');
    redirect(base_url('groups/index.php'));
}

$checkinCountStmt = $db->prepare('SELECT COUNT(*) FROM guests WHERE group_id = :id AND checkin_at IS NOT NULL');
$checkinCountStmt->execute(['id' => $id]);
$checkinCount = (int) $checkinCountStmt->fetchColumn();

if ($checkinCount > 0) {
    flash_set('danger', 'Grup tidak dapat dihapus karena sudah memiliki riwayat kehadiran.');
    redirect(base_url('groups/detail.php?id=' . $id));
}

try {
    $db->beginTransaction();
    $db->prepare('DELETE FROM guests WHERE group_id = :id')->execute(['id' => $id]);
    $db->prepare('DELETE FROM guest_groups WHERE id = :id')->execute(['id' => $id]);
    $db->commit();

    audit_log($db, (int) current_user()['id'], 'delete_group', 'guest_group', $id, 'Menghapus grup "' . $group['group_name'] . '"');
    flash_set('success', 'Grup undangan berhasil dihapus.');
    redirect(base_url('groups/index.php'));
} catch (Throwable $e) {
    $db->rollBack();
    error_log('[Hadirin] Failed to delete group: ' . $e->getMessage());
    flash_set('danger', 'Grup tidak dapat dihapus karena masih memiliki riwayat terkait (log check-in).');
    redirect(base_url('groups/detail.php?id=' . $id));
}
