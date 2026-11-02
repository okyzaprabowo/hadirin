<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/bootstrap.php';
require_role('admin');
require_post();
csrf_require_valid();

$db = get_db();
$id = input_int($_POST, 'id', 0);

$stmt = $db->prepare('SELECT * FROM events WHERE id = :id');
$stmt->execute(['id' => $id]);
$event = $stmt->fetch();

if (!$event) {
    flash_set('danger', 'Event tidak ditemukan.');
    redirect(base_url('events/index.php'));
}

$groupCountStmt = $db->prepare('SELECT COUNT(*) FROM guest_groups WHERE event_id = :id');
$groupCountStmt->execute(['id' => $id]);
$groupCount = (int) $groupCountStmt->fetchColumn();

if ($groupCount > 0) {
    flash_set('danger', 'Event tidak dapat dihapus karena masih memiliki ' . $groupCount . ' grup undangan. Hapus grup terlebih dahulu.');
    redirect(base_url('events/detail.php?id=' . $id));
}

try {
    $db->prepare('DELETE FROM events WHERE id = :id')->execute(['id' => $id]);
    audit_log($db, (int) current_user()['id'], 'delete_event', 'event', $id, 'Menghapus event "' . $event['event_name'] . '"');
    flash_set('success', 'Event berhasil dihapus.');
} catch (Throwable $e) {
    error_log('[Hadirin] Failed to delete event: ' . $e->getMessage());
    flash_set('danger', 'Event tidak dapat dihapus karena masih memiliki data terkait.');
    redirect(base_url('events/detail.php?id=' . $id));
}

redirect(base_url('events/index.php'));
