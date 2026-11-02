<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/bootstrap.php';
require_role('admin');
require_post();
csrf_require_valid();

$db = get_db();
$id = input_int($_POST, 'id', 0);

$stmt = $db->prepare('SELECT * FROM guests WHERE id = :id');
$stmt->execute(['id' => $id]);
$guest = $stmt->fetch();

if (!$guest) {
    flash_set('danger', 'Tamu tidak ditemukan.');
    redirect(base_url('guests/index.php'));
}

if ($guest['checkin_at'] !== null) {
    flash_set('danger', 'Tamu yang sudah check-in tidak dapat dihapus.');
    redirect(base_url('groups/detail.php?id=' . $guest['group_id']));
}

try {
    $db->prepare('DELETE FROM guests WHERE id = :id')->execute(['id' => $id]);
    audit_log($db, (int) current_user()['id'], 'delete_guest', 'guest', $id, 'Menghapus tamu "' . $guest['full_name'] . '"');
    flash_set('success', 'Tamu berhasil dihapus.');
} catch (Throwable $e) {
    error_log('[Hadirin] Failed to delete guest: ' . $e->getMessage());
    flash_set('danger', 'Tamu tidak dapat dihapus karena masih memiliki riwayat terkait.');
}

redirect(base_url('guests/index.php'));
