<?php
/**
 * QR code generation helper built on top of endroid/qr-code.
 */

declare(strict_types=1);

if (!defined('HADIRIN_APP')) {
    http_response_code(403);
    exit('Direct access is not allowed.');
}

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Writer\PngWriter;

/**
 * Renders a QR code PNG (binary string) for the given payload.
 * The payload must only ever be the opaque QR token, never guest data.
 */
function generate_qr_png(string $data, int $size = 360): string
{
    $result = Builder::create()
        ->writer(new PngWriter())
        ->data($data)
        ->errorCorrectionLevel(ErrorCorrectionLevel::High)
        ->size($size)
        ->margin(12)
        ->build();

    return $result->getString();
}
