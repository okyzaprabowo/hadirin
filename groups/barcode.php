<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/bootstrap.php';
require_role('admin');

$db = get_db();
$id = input_int($_GET, 'id', 0) ?: input_int($_POST, 'id', 0);
$action = input_str($_GET, 'action');

$stmt = $db->prepare('SELECT * FROM guest_groups WHERE id = :id');
$stmt->execute(['id' => $id]);
$group = $stmt->fetch();

if (!$group) {
    if (in_array($action, ['view', 'download'], true)) {
        http_response_code(404);
        exit('Not found');
    }
    flash_set('danger', 'Grup tidak ditemukan.');
    redirect(base_url('groups/index.php'));
}

// --- Streaming the raw PNG image (used by <img> and the download button) ---
if ($action === 'view' || $action === 'download') {
    $png = generate_barcode_png($group['qr_token']);
    header('Content-Type: image/png');
    header('Content-Length: ' . strlen($png));
    if ($action === 'download') {
        $slug = preg_replace('/[^A-Za-z0-9_\-]+/', '_', $group['group_name']);
        header('Content-Disposition: attachment; filename="BARCODE_' . $slug . '.png"');
    }
    header('Cache-Control: no-store, no-cache, must-revalidate');
    echo $png;
    exit;
}

// --- Token rotation ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && input_str($_POST, 'rotate') === '1') {
    csrf_require_valid();

    $newToken = generate_barcode_token();
    $db->prepare('UPDATE guest_groups SET qr_token = :token, updated_at = :updated_at WHERE id = :id')
        ->execute(['token' => $newToken, 'updated_at' => date('Y-m-d H:i:s'), 'id' => $id]);

    audit_log($db, (int) current_user()['id'], 'rotate_barcode_token', 'guest_group', $id,
        'Merotasi barcode token untuk grup "' . $group['group_name'] . '". Barcode sebelumnya menjadi tidak valid.');

    flash_set('success', 'Barcode berhasil dirotasi. Barcode sebelumnya sudah tidak valid lagi.');
    redirect(base_url('groups/barcode.php?id=' . $id));
}

$pageTitle = 'Barcode: ' . $group['group_name'];
require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/sidebar.php';
?>
<div class="container-fluid p-4">
  <h3 class="page-title mb-4"><i class="bi bi-upc"></i> Barcode Grup</h3>

  <div class="row justify-content-center">
    <div class="col-lg-5">
      <div class="card barcode-card text-center">
        <div class="card-body">
          <div class="barcode-brand">HADIRIN</div>
          <h4 class="mt-2 mb-1"><?= e($group['group_name']) ?></h4>
          <p class="text-muted mb-3">Kuota: <?= (int) $group['quota'] ?> Orang</p>

          <?php if ($group['status'] !== 'active'): ?>
            <div class="alert alert-warning">Undangan ini nonaktif. Barcode tidak dapat digunakan untuk check-in.</div>
          <?php endif; ?>

          <img src="<?= e(base_url('groups/barcode.php?id=' . $id . '&action=view')) ?>" alt="Barcode" class="img-fluid mb-3" style="max-width:320px;">

          <div class="d-flex justify-content-center gap-2 mb-3">
            <a href="<?= e(base_url('groups/barcode.php?id=' . $id . '&action=download')) ?>" class="btn btn-primary"><i class="bi bi-download"></i> Download PNG</a>
            <button type="button" class="btn btn-outline-secondary" onclick="window.open('<?= e(base_url('groups/print.php?id=' . $id)) ?>', '_blank')"><i class="bi bi-printer"></i> Print Barcode</button>
          </div>

          <hr>
          <form method="post" action="<?= e(base_url('groups/barcode.php?id=' . $id)) ?>" onsubmit="return confirm('Barcode sebelumnya akan menjadi tidak valid. Lanjutkan rotasi token?');">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= (int) $id ?>">
            <input type="hidden" name="rotate" value="1">
            <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-arrow-repeat"></i> Rotasi Barcode Token</button>
          </form>
        </div>
      </div>
      <div class="text-center mt-3">
        <a href="<?= e(base_url('groups/detail.php?id=' . $id)) ?>" class="btn btn-link"><i class="bi bi-arrow-left"></i> Kembali ke Detail Grup</a>
      </div>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
