<?php
/**
 * Database configuration and PDO connection factory.
 *
 * For production (shared hosting), override these values via environment
 * variables (DB_HOST, DB_NAME, DB_USER, DB_PASS, DB_PORT) set in cPanel,
 * or edit the defaults below directly on the server (never commit real
 * production credentials to a public repository).
 */

declare(strict_types=1);

if (!defined('HADIRIN_APP')) {
    http_response_code(403);
    exit('Direct access is not allowed.');
}

function hadirin_env(string $key, string $default = ''): string
{
    $value = getenv($key);
    return $value !== false ? $value : $default;
}

define('DB_HOST', hadirin_env('DB_HOST', 'localhost'));
define('DB_PORT', hadirin_env('DB_PORT', '3306'));
define('DB_NAME', hadirin_env('DB_NAME', 'hadirin_db'));
define('DB_USER', hadirin_env('DB_USER', 'root'));
define('DB_PASS', hadirin_env('DB_PASS', ''));
define('DB_CHARSET', 'utf8mb4');

/**
 * Returns a shared PDO instance (one connection per request).
 */
function get_db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=%s',
        DB_HOST,
        DB_PORT,
        DB_NAME,
        DB_CHARSET
    );

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    } catch (PDOException $e) {
        error_log('[Hadirin] Database connection failed: ' . $e->getMessage());
        http_response_code(500);
        if (defined('HADIRIN_AJAX') && HADIRIN_AJAX) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Terjadi kesalahan pada server.']);
        } else {
            echo 'Terjadi kesalahan pada server. Silakan coba lagi nanti.';
        }
        exit;
    }

    return $pdo;
}
