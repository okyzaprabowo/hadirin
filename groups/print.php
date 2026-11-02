<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/bootstrap.php';
require_role('admin');

$db = get_db();
$id = input_int($_GET, 'id', 0);

$stmt = $db->prepare('SELECT * FROM guest_groups WHERE id = :id');
$stmt->execute(['id' => $id]);
$group = $stmt->fetch();

if (!$group) {
    http_response_code(404);
    exit('Grup tidak ditemukan.');
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Cetak QR - <?= e($group['group_name']) ?></title>
<style>
  body { font-family: 'Segoe UI', Arial, sans-serif; text-align: center; padding: 40px; color: #0f172a; }
  .brand { font-size: 14px; letter-spacing: 3px; font-weight: 700; color: #1E3A8A; }
  h1 { font-size: 22px; margin: 8px 0 4px; }
  .quota { color: #64748B; margin-bottom: 20px; }
  img { width: 280px; height: 280px; }
  @media print { .no-print { display: none; } }
</style>
</head>
<body onload="window.print()">
  <div class="brand">HADIRIN</div>
  <h1><?= e($group['group_name']) ?></h1>
  <div class="quota">Kuota: <?= (int) $group['quota'] ?> Orang</div>
  <img src="<?= e(base_url('groups/qr.php?id=' . $id . '&action=view')) ?>" alt="QR Code">
  <p class="no-print"><button onclick="window.print()">Print</button></p>
</body>
</html>
