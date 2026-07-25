<?php

namespace YesWiki\Core\Service;

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Writer\SvgWriter;

class QrCodeService
{
    /**
     * generate an SVG qr code for $data and write it to $path (ticket 14, formerly
     * yeswiki-extension-qrcode's wiki.php global $GLOBALS['qrcode'] singleton, a thin
     * Laravel-facade wrapper -- replaced with a direct dependency on endroid/qr-code,
     * which needs neither the facade nor its transitive illuminate/* packages).
     */
    public function generateToFile(string $data, string $path): void
    {
        $result = (new Builder(
            writer: new SvgWriter(),
            data: $data,
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::High,
        ))->build();

        $result->saveToFile($path);
    }
}
