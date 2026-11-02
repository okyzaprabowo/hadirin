<?php
/**
 * Session bootstrap, authentication and role-based access control.
 */

declare(strict_types=1);

if (!defined('HADIRIN_APP')) {
    http_response_code(403);
    exit('Direct access is not allowed.');
}

const SESSION_TIMEOUT_SECONDS = 1800; // 30 minutes
const LOGIN_MAX_ATTEMPTS = 5;
const LOGIN_LOCKOUT_WINDOW_SECONDS = 900; // 15 minutes

/**
 * Starts the PHP session with hardened cookie parameters.
 * Must be called before any output is sent.
 */
function hadirin_session_start(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['SERVER_PORT'] ?? '') === '443')
        || ((strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https');

    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_name('hadirin_session');
    session_start();
}

/**
 * Enforces the 30-minute inactivity timeout. Destroys the session if expired.
 */
function hadirin_enforce_session_timeout(): void
{
    if (isset($_SESSION['user_id'])) {
        if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > SESSION_TIMEOUT_SECONDS) {
            hadirin_destroy_session();
            return;
        }
    }
    $_SESSION['last_activity'] = time();
}

function hadirin_destroy_session(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }
    session_destroy();
}

function get_client_ip(): string
{
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

/**
 * Checks whether the username/IP combination is allowed to attempt login.
 */
function login_rate_limit_check(PDO $db, string $username, string $ip): bool
{
    $since = (new DateTime())->modify('-' . LOGIN_LOCKOUT_WINDOW_SECONDS . ' seconds')->format('Y-m-d H:i:s');

    $stmt = $db->prepare(
        'SELECT COUNT(*) FROM login_attempts
         WHERE ((username = :username) OR (ip_address = :ip))
           AND successful = 0 AND attempted_at >= :since'
    );
    $stmt->execute(['username' => $username, 'ip' => $ip, 'since' => $since]);

    return (int) $stmt->fetchColumn() < LOGIN_MAX_ATTEMPTS;
}

function login_record_attempt(PDO $db, string $username, string $ip, bool $successful): void
{
    $stmt = $db->prepare(
        'INSERT INTO login_attempts (username, ip_address, attempted_at, successful)
         VALUES (:username, :ip, :attempted_at, :successful)'
    );
    $stmt->execute([
        'username'     => $username,
        'ip'           => $ip,
        'attempted_at' => date('Y-m-d H:i:s'),
        'successful'   => $successful ? 1 : 0,
    ]);
}

/**
 * Attempts to authenticate a user. Returns the user row on success, or null.
 */
function attempt_login(PDO $db, string $username, string $password): ?array
{
    $stmt = $db->prepare(
        'SELECT id, username, password_hash, full_name, role, is_active
         FROM users WHERE username = :username LIMIT 1'
    );
    $stmt->execute(['username' => $username]);
    $user = $stmt->fetch();

    if (!$user || (int) $user['is_active'] !== 1) {
        return null;
    }

    if (!password_verify($password, $user['password_hash'])) {
        return null;
    }

    return $user;
}

/**
 * Regenerates the session id and stores the authenticated user's data.
 */
function login_user(array $user): void
{
    session_regenerate_id(true);

    $_SESSION['user_id']       = (int) $user['id'];
    $_SESSION['username']      = $user['username'];
    $_SESSION['full_name']     = $user['full_name'];
    $_SESSION['role']          = $user['role'];
    $_SESSION['last_activity'] = time();
}

function logout_user(): void
{
    hadirin_destroy_session();
}

function is_logged_in(): bool
{
    return isset($_SESSION['user_id']);
}

function current_user(): ?array
{
    if (!is_logged_in()) {
        return null;
    }
    return [
        'id'        => $_SESSION['user_id'],
        'username'  => $_SESSION['username'],
        'full_name' => $_SESSION['full_name'],
        'role'      => $_SESSION['role'],
    ];
}

function is_admin(): bool
{
    return is_logged_in() && $_SESSION['role'] === 'admin';
}

function is_petugas(): bool
{
    return is_logged_in() && $_SESSION['role'] === 'petugas';
}

/**
 * Redirects unauthenticated requests to the login page.
 */
function require_login(): void
{
    if (!is_logged_in()) {
        $target = defined('HADIRIN_AJAX') && HADIRIN_AJAX;
        if ($target) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Sesi tidak valid, silakan login kembali.']);
            exit;
        }
        redirect(base_url('login.php'));
    }
}

/**
 * Restricts access to one or more roles. Call require_login() first.
 *
 * @param string|string[] $roles
 */
function require_role($roles): void
{
    require_login();
    $roles = is_array($roles) ? $roles : [$roles];

    if (!in_array($_SESSION['role'], $roles, true)) {
        if (defined('HADIRIN_AJAX') && HADIRIN_AJAX) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Anda tidak memiliki izin untuk mengakses fitur ini.']);
            exit;
        }
        http_response_code(403);
        require __DIR__ . '/../includes/403.php';
        exit;
    }
}
