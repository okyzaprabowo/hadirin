<?php
declare(strict_types=1);
if (!defined('HADIRIN_APP')) {
    http_response_code(403);
    exit('Direct access is not allowed.');
}
$user = current_user();
?>
<?php if ($user): ?>
</main>
</div>
<?php endif; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= e(base_url('assets/js/app.js')) ?>?v=<?= @filemtime(APP_ROOT . '/assets/js/app.js') ?: '1' ?>"></script>
</body>
</html>
