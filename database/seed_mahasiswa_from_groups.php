<?php
/**
 * CLI backfill: creates a 'mahasiswa' login account (username = password = NPM)
 * for every existing guest_groups row that doesn't have one yet.
 *
 * The NPM is the leading digit sequence in `group_name` (e.g.
 * "2042015 - AGUS SAPUTRA - ..." -> NPM "2042015"), matching the same
 * username/password convention used by the self-registration flow at
 * /daftar-wisuda.
 *
 * Idempotent: a group that is already linked to a 'mahasiswa' user (via
 * users.group_id), or whose NPM username already exists, is left alone.
 *
 * When two or more existing groups share the same NPM (duplicate import),
 * only the most recently created one (highest id) gets a login account;
 * the older duplicate is reported but left untouched for manual review.
 *
 * Usage (from the project root):
 *   php database/seed_mahasiswa_from_groups.php
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('This script can only be run from the command line.');
}

define('HADIRIN_APP', true);

require __DIR__ . '/../config/database.php';

try {
    $db = get_db();
} catch (Throwable $e) {
    fwrite(STDERR, "Gagal terhubung ke database.\n");
    exit(1);
}

$groups = $db->query('SELECT id, group_name, contact_name FROM guest_groups ORDER BY id ASC')->fetchAll();

// Keep only the highest-id group per NPM (handles duplicate imports).
$byNpm = [];
$unmatched = [];
foreach ($groups as $group) {
    if (!preg_match('/^(\d+)/', trim($group['group_name']), $m)) {
        $unmatched[] = $group;
        continue;
    }
    $npm = $m[1];
    if (!isset($byNpm[$npm]) || $group['id'] > $byNpm[$npm]['id']) {
        $byNpm[$npm] = $group;
    }
}

$alreadyLinkedStmt = $db->prepare('SELECT COUNT(*) FROM users WHERE group_id = :group_id');
$usernameExistsStmt = $db->prepare('SELECT COUNT(*) FROM users WHERE username = :username');
$insertStmt = $db->prepare(
    'INSERT INTO users (username, password_hash, full_name, role, group_id, is_active, created_at, updated_at)
     VALUES (:username, :password_hash, :full_name, \'mahasiswa\', :group_id, 1, :created_at, :updated_at)'
);

$created = 0;
$skipped = 0;
$duplicateGroups = [];

foreach ($groups as $group) {
    if (!preg_match('/^(\d+)/', trim($group['group_name']), $m)) {
        continue;
    }
    $npm = $m[1];
    $winner = $byNpm[$npm];
    if ((int) $group['id'] !== (int) $winner['id']) {
        $duplicateGroups[] = $group;
        continue;
    }

    $alreadyLinkedStmt->execute(['group_id' => $group['id']]);
    if ((int) $alreadyLinkedStmt->fetchColumn() > 0) {
        $skipped++;
        continue;
    }

    $usernameExistsStmt->execute(['username' => $npm]);
    if ((int) $usernameExistsStmt->fetchColumn() > 0) {
        echo "SKIP  NPM {$npm}: username sudah dipakai oleh akun lain.\n";
        $skipped++;
        continue;
    }

    $fullName = $group['contact_name'] !== null && $group['contact_name'] !== ''
        ? $group['contact_name']
        : preg_replace('/^\d+\s*-\s*/', '', $group['group_name']);

    $now = date('Y-m-d H:i:s');
    $insertStmt->execute([
        'username'      => $npm,
        'password_hash' => password_hash($npm, PASSWORD_DEFAULT),
        'full_name'     => $fullName,
        'group_id'      => $group['id'],
        'created_at'    => $now,
        'updated_at'    => $now,
    ]);
    $created++;
}

echo "\nSelesai.\n";
echo "Akun mahasiswa dibuat : {$created}\n";
echo "Dilewati (sudah ada)  : {$skipped}\n";

if ($unmatched) {
    echo "\nGrup tanpa NPM terdeteksi di awal nama (dilewati):\n";
    foreach ($unmatched as $g) {
        echo "  - #{$g['id']} {$g['group_name']}\n";
    }
}

if ($duplicateGroups) {
    echo "\nGrup duplikat (NPM sama dengan grup lain, tidak dibuatkan akun -- cek manual):\n";
    foreach ($duplicateGroups as $g) {
        echo "  - #{$g['id']} {$g['group_name']} (akun dibuat untuk #{$byNpm[preg_replace('/^(\d+).*/', '$1', $g['group_name'])]['id']} saja)\n";
    }
}
