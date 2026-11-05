<?php
/**
 * Barcode generation helper built on top of picqer/php-barcode-generator.
 */

declare(strict_types=1);

if (!defined('HADIRIN_APP')) {
    http_response_code(403);
    exit('Direct access is not allowed.');
}

use Picqer\Barcode\BarcodeGenerator;
use Picqer\Barcode\BarcodeGeneratorPNG;

/**
 * Renders a Code128 barcode PNG (binary string) for the given payload.
 * The payload must only ever be the opaque barcode token, never guest data.
 */
function generate_barcode_png(string $data, int $widthFactor = 3, int $height = 120): string
{
    $generator = new BarcodeGeneratorPNG();

    return $generator->getBarcode($data, BarcodeGenerator::TYPE_CODE_128, $widthFactor, $height);
}
