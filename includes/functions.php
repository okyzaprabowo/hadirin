<?php
/**
 * General-purpose helper functions shared across the application.
 */

declare(strict_types=1);

if (!defined('HADIRIN_APP')) {
    http_response_code(403);
    exit('Direct access is not allowed.');
}

/**
 * Escapes a value for safe HTML output.
 */
function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Computes the base URL path of the application, so links work whether the
 * app lives at the document root or in a sub-folder (e.g. /hadirin).
 */
function base_path(): string
{
    static $basePath = null;

    if ($basePath !== null) {
        return $basePath;
    }

    $docRoot = isset($_SERVER['DOCUMENT_ROOT']) ? realpath($_SERVER['DOCUMENT_ROOT']) : false;
    $appRoot = realpath(APP_ROOT);

    if ($docRoot && $appRoot && str_starts_with($appRoot, $docRoot)) {
        $relative = substr($appRoot, strlen($docRoot));
        $relative = str_replace('\\', '/', $relative);
        $basePath = rtrim($relative, '/');
    } else {
        $basePath = '';
    }

    return $basePath;
}

function base_url(string $path = ''): string
{
    $path = '/' . ltrim($path, '/');
    return base_path() . $path;
}

/**
 * Redirects the browser and terminates the script.
 */
function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

function flash_set(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

/**
 * Returns and clears all queued flash messages.
 *
 * @return array<int, array{type: string, message: string}>
 */
function flash_get_all(): array
{
    $messages = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $messages;
}

function old_set(array $data): void
{
    $_SESSION['old_input'] = $data;
}

function old_get_all(): array
{
    $data = $_SESSION['old_input'] ?? [];
    unset($_SESSION['old_input']);
    return $data;
}

/**
 * Generates a cryptographically secure, URL-safe QR token.
 */
function generate_qr_token(): string
{
    return bin2hex(random_bytes(32));
}

function format_datetime(?string $value, string $format = 'd M Y H:i'): string
{
    if (empty($value)) {
        return '-';
    }
    try {
        return (new DateTime($value))->format($format);
    } catch (Exception $e) {
        return '-';
    }
}

/**
 * Computes pagination metadata.
 *
 * @return array{page:int, per_page:int, offset:int, total:int, total_pages:int}
 */
function paginate_calc(int $total, int $page, int $perPage = 10): array
{
    $page = max(1, $page);
    $perPage = max(1, $perPage);
    $totalPages = max(1, (int) ceil($total / $perPage));
    $page = min($page, $totalPages);
    $offset = ($page - 1) * $perPage;

    return [
        'page'        => $page,
        'per_page'    => $perPage,
        'offset'      => $offset,
        'total'       => $total,
        'total_pages' => $totalPages,
    ];
}

/**
 * Builds a query string preserving existing GET params, overriding given ones.
 */
function build_query(array $overrides): string
{
    $params = array_merge($_GET, $overrides);
    return '?' . http_build_query($params);
}

/**
 * Neutralizes CSV formula injection by prefixing risky leading characters.
 */
function csv_safe_field(?string $value): string
{
    $value = $value ?? '';
    if ($value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
        $value = "'" . $value;
    }
    return $value;
}

/**
 * Sends a JSON response with the given HTTP status code and exits.
 */
function json_response(array $data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
}

/**
 * Ensures the current request is POST, otherwise responds with 405.
 */
function require_post(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        if (defined('HADIRIN_AJAX') && HADIRIN_AJAX) {
            json_response(['success' => false, 'message' => 'Metode tidak diizinkan.'], 405);
        }
        http_response_code(405);
        exit('Metode tidak diizinkan.');
    }
}

/**
 * Records an entry into the audit_logs table.
 */
function audit_log(PDO $db, ?int $userId, string $action, ?string $entityType = null, ?int $entityId = null, ?string $description = null): void
{
    try {
        $stmt = $db->prepare(
            'INSERT INTO audit_logs (user_id, action, entity_type, entity_id, description, created_at)
             VALUES (:user_id, :action, :entity_type, :entity_id, :description, :created_at)'
        );
        $stmt->execute([
            'user_id'     => $userId,
            'action'      => $action,
            'entity_type' => $entityType,
            'entity_id'   => $entityId,
            'description' => $description,
            'created_at'  => date('Y-m-d H:i:s'),
        ]);
    } catch (Throwable $e) {
        error_log('[Hadirin] Failed to write audit log: ' . $e->getMessage());
    }
}

/**
 * Minimal input trimming helper.
 */
function input_str(array $source, string $key, string $default = ''): string
{
    return trim((string) ($source[$key] ?? $default));
}

function input_int(array $source, string $key, int $default = 0): int
{
    return isset($source[$key]) && $source[$key] !== '' ? (int) $source[$key] : $default;
}
