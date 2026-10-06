<?php

namespace YesWiki\Test\Files;

use YesWiki\Files\Service\ImageShrinker;
use YesWiki\Files\Service\Storage;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** A picture already rewritten as WebP once is taken from YESWIKI_WEBP_CACHE instead of being converted again. */
class ImageShrinkerTest extends YesWikiTestCase
{
    private string $cache;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cache = sys_get_temp_dir() . '/webp-cache-' . bin2hex(random_bytes(4));
        putenv(ImageShrinker::CACHE_ENV . '=' . $this->cache);
    }

    protected function tearDown(): void
    {
        putenv(ImageShrinker::CACHE_ENV);
        if (is_dir($this->cache)) {
            exec('rm -rf ' . escapeshellarg($this->cache));
        }
        parent::tearDown();
    }

    public function testAConversionIsKeptAndTheNextOneIsTakenFromTheCache(): void
    {
        $services = self::getWiki()->services;
        $storage = $services->get(Storage::class);
        $shrinker = $services->get(ImageShrinker::class);

        $source = 'files/shrinker-cache.png';
        $storage->write($source, self::png());
        $cached = $storage->withLocalCopy($source, static fn (string $local) => $shrinker->cachedPath($local, 200, 200, 80));
        $this->assertIsString($cached);

        $this->assertTrue($shrinker->shrink($source, 'files/shrinker-cache-1.webp', 200, 200, 80));
        $this->assertFileExists($cached, 'the first conversion is kept');
        $this->assertSame((string)file_get_contents($cached), $storage->read('files/shrinker-cache-1.webp'));

        file_put_contents($cached, 'planted');
        $this->assertTrue($shrinker->shrink($source, 'files/shrinker-cache-2.webp', 200, 200, 80));
        $this->assertSame('planted', $storage->read('files/shrinker-cache-2.webp'), 'the second one is not converted');

        $this->assertTrue($shrinker->shrink($source, 'files/shrinker-cache-3.webp', 100, 100, 80));
        $this->assertNotSame('planted', $storage->read('files/shrinker-cache-3.webp'), 'other bounds are another conversion');

        foreach ([$source, 'files/shrinker-cache-1.webp', 'files/shrinker-cache-2.webp', 'files/shrinker-cache-3.webp'] as $path) {
            $storage->delete($path);
        }
    }

    public function testWithoutTheVariableNothingIsKept(): void
    {
        putenv(ImageShrinker::CACHE_ENV);
        $local = tempnam(sys_get_temp_dir(), 'shrinker');
        file_put_contents($local, self::png());

        $this->assertNull(self::getWiki()->services->get(ImageShrinker::class)->cachedPath($local, 200, 200, 80));
        unlink($local);
    }

    private static function png(): string
    {
        $image = imagecreatetruecolor(400, 300);
        imagefilledrectangle($image, 0, 0, 400, 300, (int)imagecolorallocate($image, 10, 200, 90));
        ob_start();
        imagepng($image);

        return (string)ob_get_clean();
    }
}
