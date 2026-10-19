<?php

namespace YesWiki\Test\Attach\Service;

use PHPUnit\Framework\Attributes\DataProvider;
use YesWiki\Attach\Service\RemoteImageCache;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * Remote images are only fetched from public addresses, and nothing is written for a refused URL.
 */
class RemoteImageCacheTest extends YesWikiTestCase
{
    public static function fieldValues(): array
    {
        return [
            'https link' => ['https://example.org/a.jpg', true],
            'http link' => ['http://example.org/a.jpg', true],
            'protocol-relative link' => ['//example.org/a.jpg', true],
            'uploaded file name' => ['MyEntry_bf_image_photo.jpg', false],
            'other scheme' => ['ftp://example.org/a.jpg', false],
            'javascript url' => ['javascript:alert(1)//', false],
            'empty' => ['', false],
            'not a string' => [['https://example.org/a.jpg'], false],
        ];
    }

    #[DataProvider('fieldValues')]
    public function testItTellsLinksFromFileNames($value, bool $expected): void
    {
        $this->getWiki();

        $this->assertSame($expected, RemoteImageCache::isRemote($value));
    }

    public static function refusedUrls(): array
    {
        return [
            'loopback' => ['http://127.0.0.1/image.jpg'],
            'cloud metadata' => ['http://169.254.169.254/latest/meta-data'],
            'private network' => ['http://192.168.1.10/image.png'],
            'ipv6 loopback' => ['http://[::1]/image.png'],
            'file scheme' => ['file:///etc/passwd'],
        ];
    }

    #[DataProvider('refusedUrls')]
    public function testItRefusesPrivateAddresses(string $url): void
    {
        $cache = $this->getWiki()->services->get(RemoteImageCache::class);
        $before = glob('cache/remote_*') ?: [];

        $this->assertNull($cache->resized($url, 300, 200, 'crop'));
        $this->assertFileDoesNotExist('cache/remote_' . sha1($url) . '.jpg');

        array_map('unlink', array_diff(glob('cache/remote_*') ?: [], $before));
    }

    public function testItRefusesInvalidSizesWithoutFetching(): void
    {
        $cache = $this->getWiki()->services->get(RemoteImageCache::class);

        $this->assertNull($cache->resized('https://example.org/a.jpg', 0, 200));
        $this->assertNull($cache->resized('https://example.org/a.jpg', 300, 200, 'stretch'));
        $this->assertSame([], glob('cache/remote_' . sha1('https://example.org/a.jpg') . '*') ?: []);
    }
}
