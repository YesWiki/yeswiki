<?php

namespace YesWiki\Test\Services;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Content\Action\SyndicationAction;
use YesWiki\Files\Service\ImageResizer;
use YesWiki\Files\Service\RemoteImageCache;
use YesWiki\Kernel\Service\RuntimeConfig;
use YesWiki\Kernel\Service\UrlFormatter;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * `fetch()` answered from a fixture instead of the network.
 *
 * The seam is deliberate and is the only way any of this can be asserted: against a live URL,
 * a cache that silently refetches, one that stores the wrong bytes and one that works are all
 * "the picture appeared".
 */
class FetchlessRemoteImageCache extends RemoteImageCache
{
    /** @var list<string> every url it was asked for, in order */
    public array $fetched = [];

    private ?string $answer;

    public function __construct(
        ParameterBagInterface $params,
        RuntimeConfig $config,
        UrlFormatter $urlFormatter,
        ImageResizer $resizer,
        ?string $answer
    ) {
        parent::__construct($params, $config, $urlFormatter, $resizer);
        $this->answer = $answer;
    }

    protected function fetch(string $url): ?string
    {
        $this->fetched[] = $url;

        return $this->answer;
    }
}

/**
 * A picture from a feed is fetched once, shrunk, and served from this wiki.
 *
 * A feed hands out its image as a URL on the publisher's own server, so every card drawn from
 * one sent the reader there -- at whatever size that server stores, and with a hole in the
 * card whenever it is down.
 */
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
        // no imagedestroy(): a no-op since PHP 8.0 and deprecated in 8.5, so calling it only
        // adds a deprecation to the suite's output

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

    /**
     * Cached means cached: a second render does not go back out to the network.
     *
     * The whole point is one download per picture rather than one per page view -- and a
     * cache that silently refetches looks exactly like one that works.
     */
    public function testTheSecondCallDoesNotFetchAgain(): void
    {
        $cache = $this->cache($this->png(3000, 1500));

        $first = $cache->localUrl(self::REMOTE);
        $second = $cache->localUrl(self::REMOTE);

        $this->assertSame($first, $second);
        $this->assertSame([self::REMOTE], $cache->fetched, 'fetched once, for two renders');
    }

    /**
     * A picture already smaller than the cap keeps its pixels, but still becomes WebP.
     *
     * Both halves matter: nothing this wiki serves is a PNG a WebP could have been, and a
     * feed thumbnail must not be blown up to the cap on its way through -- the resizer
     * enlarges smaller images by default, so a 300px picture asked for at 1920 comes out
     * bigger and no sharper.
     */
    public function testASmallImageIsConvertedButNotEnlarged(): void
    {
        $cache = $this->cache($this->png(300, 200));

        $path = $this->pathOf($cache->localUrl(self::REMOTE));

        $size = (array)getimagesize($path);
        $this->assertSame(300, $size[0], 'kept at its own size');
        $this->assertSame(200, $size[1]);
        $this->assertSame(IMAGETYPE_WEBP, $size[2], 'and served as WebP');
    }

    /**
     * Whatever the publisher stores it as, what this wiki serves is WebP.
     *
     * A feed's PNG is routinely two or three times the size of the WebP that looks identical,
     * and the copy is being re-encoded anyway -- this is the same rule the browser applies to
     * an upload (javascripts/image-upload.js), and the two must not disagree.
     */
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

    /**
     * A picture that cannot be had leaves the page exactly as it was.
     *
     * And is not asked for again on the next render: a feed carrying one dead image URL
     * would otherwise spend ten seconds of timeout in front of every page showing it.
     */
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
     * The feed reader really does route its images through here.
     *
     * The service is only worth having if something calls it, and "the card shows a picture"
     * looks the same either way -- the difference is whose server the reader fetched it from.
     * `itemsFrom()` is the seam every presentation goes through (ticket 37), reached by
     * reflection because reaching it any other way means reading a feed over the network.
     */
    public function testSyndicationTurnsAFeedImageIntoALocalOne(): void
    {
        $wiki = $this->getWiki();
        $wiki->services->set(RemoteImageCache::class, $this->cache($this->png(3000, 1500)));

        $action = new SyndicationAction();
        $action->setServices($wiki->services);
        $itemsFrom = new \ReflectionMethod($action, 'itemsFrom');

        $items = $itemsFrom->invoke($action, [[
            'title' => 'An article',
            'url' => 'https://news.example/article',
            'image' => self::REMOTE,
        ]]);

        $this->assertCount(1, $items);
        $this->assertNotSame(self::REMOTE, $items[0]->image);
        $this->assertStringContainsString('cache/remote/', (string)$items[0]->image);
    }

    /**
     * An address this server should not be making a request to is not one it makes.
     *
     * A feed is configured by an admin but written by a stranger, so an item's image URL is
     * somebody else's string that this server is about to fetch. Nothing is even attempted
     * for a scheme that is not http(s), for a bare IP -- the shape of somebody mapping the
     * network this server sits in -- or for an address already on this wiki.
     */
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
