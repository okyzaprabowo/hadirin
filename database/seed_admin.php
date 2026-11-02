<?php
/**
 * CLI seeder: creates the first administrator account for Hadirin.
 *
 * Usage (from the project root):
 *   php database/seed_admin.php
 *
 * This script must be run from the command line. It will never run over
 * HTTP, and no default admin credentials are ever stored in the repository
 * or in database.sql.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('This script can only be run from the command line.');
}

define('HADIRIN_APP', true);

require __DIR__ . '/../config/database.php';

function prompt(string $label): string
{
    echo $label;
    $value = fgets(STDIN);
    return $value === false ? '' : trim($value);
}

function prompt_password(string $label): string
{
    echo $label;
    if (stripos(PHP_OS, 'WIN') === 0) {
        // Windows CLI has no stty; input will be visible.
        $value = fgets(STDIN);
        return $value === false ? '' : trim($value);
    }
    system('stty -echo');
    $value = fgets(STDIN);
    system('stty echo');
    echo PHP_EOL;
    return $value === false ? '' : trim($value);
}

echo "=====================================\n";
echo " Hadirin - Seeder Akun Admin Pertama \n";
echo "=====================================\n\n";

try {
    $db = get_db();
} catch (Throwable $e) {
    fwrite(STDERR, "Gagal terhubung ke database. Periksa konfigurasi di config/database.php.\n");
    exit(1);
}

$stmt = $db->query("SELECT COUNT(*) FROM users WHERE role = 'admin'");
$existingAdmins = (int) $stmt->fetchColumn();
if ($existingAdmins > 0) {
    echo "Peringatan: sudah terdapat {$existingAdmins} akun admin di database.\n";
    $continue = prompt('Tetap lanjutkan membuat admin baru? (y/N): ');
    if (strtolower($continue) !== 'y') {
        echo "Dibatalkan.\n";
        exit(0);
    }
}

$username = '';
while ($username === '') {
    $username = prompt('Username admin: ');
    if ($username !== '' && !preg_match('/^[a-zA-Z0-9_.]{3,50}$/', $username)) {
        echo "Username harus 3-50 karakter, hanya huruf, angka, titik, dan underscore.\n";
        $username = '';
    }
}

$checkStmt = $db->prepare('SELECT COUNT(*) FROM users WHERE username = :username');
$checkStmt->execute(['username' => $username]);
if ((int) $checkStmt->fetchColumn() > 0) {
    fwrite(STDERR, "Username '{$username}' sudah digunakan.\n");
    exit(1);
}

$fullName = '';
while ($fullName === '') {
    $fullName = prompt('Nama lengkap: ');
}

$password = '';
while (true) {
    $password = prompt_password('Password (minimal 8 karakter): ');
    if (strlen($password) < 8) {
        echo "Password terlalu pendek.\n";
        continue;
    }
    $confirm = prompt_password('Ulangi password: ');
    if ($password !== $confirm) {
        echo "Password tidak sama, silakan ulangi.\n";
        continue;
    }
    break;
}

$hash = password_hash($password, PASSWORD_DEFAULT);
$now = date('Y-m-d H:i:s');

$insert = $db->prepare(
    'INSERT INTO users (username, password_hash, full_name, role, is_active, created_at, updated_at)
     VALUES (:username, :password_hash, :full_name, :role, 1, :created_at, :updated_at)'
);
$insert->execute([
    'username'      => $username,
    'password_hash' => $hash,
    'full_name'     => $fullName,
    'role'          => 'admin',
    'created_at'    => $now,
    'updated_at'    => $now,
]);

echo "\nAkun admin '{$username}' berhasil dibuat. Silakan login melalui halaman web.\n";
