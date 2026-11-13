<?php
/**
 * Rotating presensi-session QR codes.
 *
 * The QR shown on the committee's display screen is NOT tied to any guest —
 * it only proves "someone scanned this exact screen, for this exact
 * presensi_sessions row, within the last few seconds". The student's own
 * login (already verified via session cookie) is what identifies WHO is
 * checking in. The token is a stateless HMAC over a 5-second time bucket,
 * so nothing needs to be stored or polled server-side to keep it rotating.
 *
 * Which sessions exist (names, how many, per event) is fully admin-managed
 * via the presensi_sessions table / presensi/sessions CRUD — this file only
 * deals with the rotating-token math and QR rendering, keyed by the
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

const SESSION_QR_WINDOW_SECONDS = 5;
const SESSION_QR_TOLERANCE_BUCKETS = 2; // accept current bucket +/- this many

function session_qr_current_bucket(): int
{
    return intdiv(time(), SESSION_QR_WINDOW_SECONDS);
}

function session_qr_token(int $presensiSessionId, int $eventId, int $bucket): string
{
    $payload = $presensiSessionId . '|' . $eventId . '|' . $bucket;
    return substr(hash_hmac('sha256', $payload, SESSION_QR_SECRET), 0, 16);
}

/**
 * Builds the current QR payload string for a display screen.
 */
function session_qr_build_content(int $presensiSessionId, int $eventId): string
{
    $bucket = session_qr_current_bucket();
    $token = session_qr_token($presensiSessionId, $eventId, $bucket);
    return "HADIRIN|{$presensiSessionId}|{$eventId}|{$bucket}|{$token}";
}

/**
 * Parses a scanned QR payload string. Returns null if malformed.
 *
 * @return array{presensi_session_id:int, event_id:int, bucket:int, token:string}|null
 */
function session_qr_parse(string $content): ?array
{
    $parts = explode('|', trim($content));
    if (count($parts) !== 5 || $parts[0] !== 'HADIRIN') {
        return null;
    }
    [, $presensiSessionId, $eventId, $bucket, $token] = $parts;

    if (!ctype_digit($presensiSessionId) || !ctype_digit($eventId) || !ctype_digit($bucket)) {
        return null;
    }

    return [
        'presensi_session_id' => (int) $presensiSessionId,
        'event_id'            => (int) $eventId,
        'bucket'              => (int) $bucket,
        'token'               => $token,
    ];
}

/**
 * Validates a parsed payload: correct HMAC and within the allowed time window.
 */
function session_qr_is_valid(int $presensiSessionId, int $eventId, int $bucket, string $token): bool
{
    $currentBucket = session_qr_current_bucket();
    if (abs($currentBucket - $bucket) > SESSION_QR_TOLERANCE_BUCKETS) {
        return false;
    }

    $expected = session_qr_token($presensiSessionId, $eventId, $bucket);
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
