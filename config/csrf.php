<?php
/**
 * CSRF protection helpers.
 */

declare(strict_types=1);

if (!defined('HADIRIN_APP')) {
    http_response_code(403);
    exit('Direct access is not allowed.');
}

/**
 * Returns the current CSRF token, generating one if it does not exist yet.
 */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Outputs a hidden input field containing the CSRF token.
 */
function csrf_field(): string
{
    $token = htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8');
    return '<input type="hidden" name="csrf_token" value="' . $token . '">';
}

/**
 * Validates a submitted CSRF token using a timing-safe comparison.
 */
function csrf_verify(?string $token): bool
{
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Validates the CSRF token from $_POST and stops the request if invalid.
 * For AJAX endpoints, returns a JSON 403 response; otherwise redirects back.
 */
function csrf_require_valid(): void
{
    $token = $_POST['csrf_token'] ?? '';
    if (!csrf_verify($token)) {
        if (defined('HADIRIN_AJAX') && HADIRIN_AJAX) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Token CSRF tidak valid. Muat ulang halaman.']);
            exit;
        }
        http_response_code(403);
        exit('Token CSRF tidak valid. Silakan muat ulang halaman dan coba lagi.');
    }
}
