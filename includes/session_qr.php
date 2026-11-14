<?php
/**
 * Static presensi-session QR codes.
 *
 * The QR shown on the committee's display screen (or printed on paper at
 * the presensi point) is NOT tied to any guest — it only proves "someone
 * scanned the code for this exact presensi_sessions row". The student's
 * own login (already verified via session cookie) is what identifies WHO
 * is checking in. The token is a stateless HMAC keyed by the session +
 * event id only, so it never changes for as long as that presensi session
 * exists — safe to print and reuse across the whole event.
 *
 * Which sessions exist (names, how many, per event) is fully admin-managed
 * via the presensi_sessions table / presensi/sessions CRUD — this file only
 * deals with the token math and QR rendering, keyed by the
 * presensi_sessions.id (never a hardcoded session name).
 */

declare(strict_types=1);

if (!defined('HADIRIN_APP')) {
    http_response_code(403);
    exit('Direct access is not allowed.');
}

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Writer\PngWriter;

function session_qr_token(int $presensiSessionId, int $eventId): string
{
    $payload = $presensiSessionId . '|' . $eventId;
    return substr(hash_hmac('sha256', $payload, SESSION_QR_SECRET), 0, 16);
}

/**
 * Builds the (static) QR payload string for a presensi session.
 */
function session_qr_build_content(int $presensiSessionId, int $eventId): string
{
    $token = session_qr_token($presensiSessionId, $eventId);
    return "HADIRIN|{$presensiSessionId}|{$eventId}|{$token}";
}

/**
 * Parses a scanned QR payload string. Returns null if malformed.
 *
 * @return array{presensi_session_id:int, event_id:int, token:string}|null
 */
function session_qr_parse(string $content): ?array
{
    $parts = explode('|', trim($content));
    if (count($parts) !== 4 || $parts[0] !== 'HADIRIN') {
        return null;
    }
    [, $presensiSessionId, $eventId, $token] = $parts;

    if (!ctype_digit($presensiSessionId) || !ctype_digit($eventId)) {
        return null;
    }

    return [
        'presensi_session_id' => (int) $presensiSessionId,
        'event_id'            => (int) $eventId,
        'token'               => $token,
    ];
}

/**
 * Validates a parsed payload against the expected static HMAC.
 */
function session_qr_is_valid(int $presensiSessionId, int $eventId, string $token): bool
{
    $expected = session_qr_token($presensiSessionId, $eventId);
    return hash_equals($expected, $token);
}

/**
 * Renders the rotating session QR as a PNG (binary string).
 */
function generate_session_qr_png(string $content, int $size = 320): string
{
    $result = Builder::create()
        ->writer(new PngWriter())
        ->data($content)
        ->errorCorrectionLevel(ErrorCorrectionLevel::High)
        ->size($size)
        ->margin(12)
        ->build();

    return $result->getString();
}
