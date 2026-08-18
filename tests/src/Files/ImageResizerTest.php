<?php

namespace YesWiki\Test\Files;

use YesWiki\Files\Service\ImageResizer;
use YesWiki\Files\Service\Storage;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** Ticket 41: Zebra_Image is handed a leased path and nothing else, in both of the modes that reach it. */
class ImageResizerTest extends YesWikiTestCase
{
    public function testAResizeGoesThroughTheLeaseInBothModes(): void
    {
        $services = self::getWiki()->services;
        $storage = $services->get(Storage::class);
        $resizer = $services->get(ImageResizer::class);

        $source = 'files/storage-smoke.png';
        $image = imagecreatetruecolor(400, 300);
        imagefilledrectangle($image, 0, 0, 400, 300, (int)imagecolorallocate($image, 10, 200, 90));
        ob_start();
        imagepng($image);
        $storage->write($source, (string)ob_get_clean());

        foreach (['fit', 'crop'] as $mode) {
            $destination = $resizer->resizedFilename($source, '120', '80', $mode);
            if ($storage->exists($destination)) {
                $storage->delete($destination);
            }
            $this->assertSame($destination, $resizer->resize($source, $destination, 120, 80, $mode), "resize $mode");
            $this->assertTrue($storage->fileExists($destination), "$mode wrote $destination");
            $size = $storage->imageSize($destination);
            $this->assertIsArray($size);
            $this->assertLessThanOrEqual(120, $size[0], "$mode width");
            $this->assertLessThanOrEqual(80, $size[1], "$mode height");
            if ($mode === 'crop') {
                $this->assertSame([120, 80], [$size[0], $size[1]], 'crop fills the box');
            }
            $storage->delete($destination);
        }

        $storage->delete($source);
    }
}
