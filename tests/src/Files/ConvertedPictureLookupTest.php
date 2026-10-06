<?php

namespace YesWiki\Test\Files;

use YesWiki\Files\Service\AttachedFilePaths;
use YesWiki\Files\Service\Storage;
use YesWiki\Kernel\Service\PageContext;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** A page still naming the JPEG or PNG the WebP migration converted finds the WebP, and an unconverted file still wins. */
class ConvertedPictureLookupTest extends YesWikiTestCase
{
    public function testAConvertedPictureIsFoundUnderItsOldExtension(): void
    {
        $services = $this->getWiki()->services;
        $paths = $services->get(AttachedFilePaths::class);
        if (!$paths->isSafeMode()) {
            $this->markTestSkipped('the dev wiki keeps uploads in per-page directories');
        }
        $storage = $services->get(Storage::class);
        $context = $services->get(PageContext::class);
        $base = rtrim((string)$paths->config()['upload_path'], '/');
        $page = 'CplPage' . uniqid();
        $webp = "{$base}/{$page}_photo_20240101000000_20240101000000.webp";
        $png = "{$base}/{$page}_photo_20240101000000_20240101000001.png";
        $other = "{$base}/{$page}_notes_20240101000000_20240101000000.webp";
        $storage->write($webp, 'webp');
        $storage->write($other, 'webp');
        $previousTag = $context->getTag();
        $context->setTag($page);

        try {
            $this->assertSame($webp, $paths->fullFilename('photo.png'), 'the old PNG name finds the WebP');
            $this->assertSame($webp, $paths->fullFilename("{$page}/photo.jpg"), 'a cross-page JPEG name finds it too');
            $this->assertSame('', $paths->fullFilename('notes.gif'), 'only the formats the migration converted fall back');

            $storage->write($png, 'png');
            $this->assertSame($png, $paths->fullFilename('photo.png'), 'a file still under its own extension wins');
        } finally {
            $context->setTag($previousTag);
            foreach ([$webp, $png, $other] as $file) {
                if ($storage->exists($file)) {
                    $storage->delete($file);
                }
            }
        }
    }
}
