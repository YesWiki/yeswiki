<?php

namespace YesWiki\Test\Services;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Files\Service\ImageResizer;
use YesWiki\Files\Service\RemoteImageCache;
use YesWiki\Files\Service\Storage;
use YesWiki\Kernel\Service\RuntimeConfig;
use YesWiki\Kernel\Service\UrlFormatter;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** `fetch()` answered from a fixture instead of the network. */
class FetchlessRemoteImageCache extends RemoteImageCache
{
    /**
     * @var list<string> every url it was asked for, in order
     */
    public array $fetched = [];

    private ?string $answer;

    public function __construct(
        ParameterBagInterface $params,
        RuntimeConfig $config,
        UrlFormatter $urlFormatter,
        ImageResizer $resizer,
        Storage $storage,
        ?string $answer
    ) {
        parent::__construct($params, $config, $urlFormatter, $resizer, $storage);
        $this->answer = $answer;
    }

    protected function fetch(string $url): ?string
    {
        $this->fetched[] = $url;

        return $this->answer;
    }
}

/** A picture from a feed is fetched once, shrunk, and served from this wiki. */
class RemoteImageCacheTest extends YesWikiTestCase
{
    private const REMOTE = 'https://news.example/photo.png';

    /** A real PNG of these dimensions, as bytes. */
    private function png(int $width, int $height): string
    {
        $image = imagecreatetruecolor(max(1, $width), max(1, $height));
        imagefilledrectangle($image, 0, 0, $width, $height, (int)imagecolorallocate($image, 20, 120, 200));
        ob_start();
        imagepng($image);

        return (string)ob_get_clean();
    }

    /** The same picture as a JPEG, to check what comes out does not depend on what went in. */
    private function jpeg(int $width, int $height): string
    {
        $image = imagecreatetruecolor(max(1, $width), max(1, $height));
        imagefilledrectangle($image, 0, 0, $width, $height, (int)imagecolorallocate($image, 20, 120, 200));
        ob_start();
        imagejpeg($image);

        return (string)ob_get_clean();
    }

    /** The service, answering every fetch with these bytes (or nothing at all, for null). */
    private function cache(?string $answer): FetchlessRemoteImageCache
    {
        $services = $this->getWiki()->services;

        return new FetchlessRemoteImageCache(
            $services->get(ParameterBagInterface::class),
            $services->get(RuntimeConfig::class),
            $services->get(UrlFormatter::class),
            $services->get(ImageResizer::class),
            $services->get(Storage::class),
            $answer
        );
    }

    private function clearCache(): void
    {
        foreach (glob('cache/remote/*') ?: [] as $file) {
            @unlink($file);
        }
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->clearCache();
    }

    protected function tearDown(): void
    {
        $this->clearCache();
        parent::tearDown();
    }

    /** The path a returned local url points at, relative to the wiki root. */
    private function pathOf(string $url): string
    {
        return (string)preg_replace('~^.*/(cache/remote/)~', '$1', $url);
    }

    public function testAnOversizedFeedImageIsShrunkAndServedFromHere(): void
    {
        $cache = $this->cache($this->png(3000, 1500));

        $local = $cache->localUrl(self::REMOTE);

        $this->assertNotSame(self::REMOTE, $local, 'the reader must not be sent to the publisher');
        $path = $this->pathOf($local);
        $this->assertFileExists($path);

        $size = (array)getimagesize($path);
        $this->assertSame(1920, $size[0], 'shrunk to the render cap');
        $this->assertSame(960, $size[1], 'and not squashed while doing it');
    }

    /** Cached means cached: a second render does not go back out to the network. */
    public function testTheSecondCallDoesNotFetchAgain(): void
    {
        $cache = $this->cache($this->png(3000, 1500));

        $first = $cache->localUrl(self::REMOTE);
        $second = $cache->localUrl(self::REMOTE);

        $this->assertSame($first, $second);
        $this->assertSame([self::REMOTE], $cache->fetched, 'fetched once, for two renders');
    }

    /** A picture already smaller than the cap keeps its pixels, but still becomes WebP. */
    public function testASmallImageIsConvertedButNotEnlarged(): void
    {
        $cache = $this->cache($this->png(300, 200));

        $path = $this->pathOf($cache->localUrl(self::REMOTE));

        $size = (array)getimagesize($path);
        $this->assertSame(300, $size[0], 'kept at its own size');
        $this->assertSame(200, $size[1]);
        $this->assertSame(IMAGETYPE_WEBP, $size[2], 'and served as WebP');
    }

    /** Whatever the publisher stores it as, what this wiki serves is WebP. */
    public function testEveryCachedCopyIsWebpWhateverCameIn(): void
    {
        foreach ([$this->png(3000, 1500), $this->jpeg(3000, 1500)] as $index => $bytes) {
            $cache = $this->cache($bytes);

            $path = $this->pathOf($cache->localUrl(self::REMOTE . '?' . $index));

            $this->assertStringEndsWith('.webp', $path);
            $size = getimagesize($path);
            $this->assertIsArray($size);
            $this->assertSame(IMAGETYPE_WEBP, $size[2]);
        }
    }

    /** A picture that cannot be had leaves the page exactly as it was. */
    public function testAFailedFetchFallsBackToTheRemoteUrlAndIsNotRetried(): void
    {
        $cache = $this->cache(null);

        $this->assertSame(self::REMOTE, $cache->localUrl(self::REMOTE));
        $this->assertSame(self::REMOTE, $cache->localUrl(self::REMOTE));
        $this->assertSame([self::REMOTE], $cache->fetched, 'the miss is remembered');
    }

    /** Whatever the publisher called it, only an actual image is written into a public directory. */
    public function testSomethingThatIsNotAnImageIsNeverStored(): void
    {
        $cache = $this->cache('<?php echo "not a picture";');

        $this->assertSame(self::REMOTE, $cache->localUrl(self::REMOTE));
        $this->assertSame([], glob('cache/remote/*.webp') ?: []);
    }

    /**
     * A Presentation asks for the size it will draw, and gets a local copy of that size.
     *
     * A syndicated Item carries the publisher's own URL; the shared image macro reaches it
     * through `image_at`, which is where the fetching and the shrinking happen. A four-column
     * card wall must not download the banner a one-column list would.
     */
    public function testAPresentationGetsTheSizeItAsksFor(): void
    {
        $cache = $this->cache($this->png(3000, 1500));

        $wide = $cache->localUrl(self::REMOTE, 1170, 780);
        $narrow = $cache->localUrl(self::REMOTE, 390, 260);

        $this->assertNotSame($wide, $narrow, 'one cached copy per size asked for');

        $wideSize = (array)getimagesize($this->pathOf($wide));
        $narrowSize = (array)getimagesize($this->pathOf($narrow));

        $this->assertSame(1170, $wideSize[0]);
        $this->assertSame(390, $narrowSize[0]);
        $this->assertLessThan(filesize($this->pathOf($wide)), filesize($this->pathOf($narrow)));
    }

    /** An address this server should not be making a request to is not one it makes. */
    public function testOnlyANamedRemoteHostIsEverFetched(): void
    {
        $cache = $this->cache($this->png(100, 100));
        $own = $this->getWiki()->services->get(UrlFormatter::class)->getBaseUrl();

        foreach ([
            'file:///etc/passwd',
            'http://192.168.1.1/photo.png',
            'https://127.0.0.1/photo.png',
            'not a url at all',
            $own . '/api/files/SomeFile/download',
        ] as $refused) {
            $this->assertSame($refused, $cache->localUrl($refused), $refused . ' must be left alone');
        }

        $this->assertSame([], $cache->fetched, 'and none of them reached the network');
    }
}
