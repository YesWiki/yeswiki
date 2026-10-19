<?php

namespace YesWiki\Test\Content;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Content\Api\ImageApiController;
use YesWiki\Content\Service\EntryManager;
use YesWiki\Content\Service\FormManager;
use YesWiki\Files\Service\ImageResizer;
use YesWiki\Files\Service\RemoteImageCache;
use YesWiki\Files\Service\Storage;
use YesWiki\Identity\Service\AuthenticationService;
use YesWiki\Kernel\Service\RuntimeConfig;
use YesWiki\Kernel\Service\SignedTokens;
use YesWiki\Kernel\Service\UrlFormatter;
use YesWiki\Render\Service\TemplateEngine;
use YesWiki\Test\Core\YesWikiTestCase;
use YesWiki\Test\Services\FetchlessRemoteImageCache;

require_once 'tests/YesWikiTestCase.php';
require_once 'tests/src/services/RemoteImageCacheTest.php';

/** An image field may hold a link to another site, shown everywhere from a resized copy kept here. */
class RemoteImageFieldTest extends YesWikiTestCase
{
    private const FORM_ID = '999942';
    private const REMOTE = 'https://news.example/field-photo.png';

    private string $tag = '';

    protected function setUp(): void
    {
        parent::setUp();
        $services = $this->getWiki()->services;
        if ($services->get(AuthenticationService::class)->connectFirstAdmin() === null) {
            $this->markTestSkipped('no admin account in the test wiki');
        }
        $services->get(FormManager::class)->create([
            'id' => self::FORM_ID,
            'label' => 'Remote image test form',
            'template' => json_encode([
                ['type' => 'texte', 'name' => 'bf_titre', 'label' => 'Titre'],
                ['type' => 'image', 'name' => 'bf_image', 'label' => 'Image', 'thumbnail_height' => 140, 'thumbnail_width' => 140, 'image_height' => 300, 'image_width' => 300],
            ]),
            'condition' => '',
        ]);
        $entry = $services->get(EntryManager::class)->create(self::FORM_ID, [
            'antispam' => 1,
            'bf_titre' => 'Une photo venue d’ailleurs',
            'imagebf_image' => self::REMOTE,
        ]);
        $this->tag = (string)($entry['tag'] ?? '');
        $services->get(AuthenticationService::class)->logout();
        $this->clearCache();
    }

    protected function tearDown(): void
    {
        $services = $this->getWiki()->services;
        $services->get(AuthenticationService::class)->logout();
        if ($this->tag !== '' && $services->get(EntryManager::class)->isEntry($this->tag)) {
            $services->get(EntryManager::class)->delete($this->tag, true);
        }
        $services->get(FormManager::class)->delete(self::FORM_ID);
        $this->clearCache();
        unset($_POST['csrftoken']);
        parent::tearDown();
    }

    private function clearCache(): void
    {
        foreach (glob('cache/remote/*') ?: [] as $file) {
            @unlink($file);
        }
    }

    /** Put a copy of the remote picture in the cache, as a first fetch would, and return its local URL. */
    private function cached(int $width, int $height): string
    {
        $services = $this->getWiki()->services;
        $image = imagecreatetruecolor(800, 600);
        ob_start();
        imagepng($image);
        $cache = new FetchlessRemoteImageCache(
            $services->get(ParameterBagInterface::class),
            $services->get(RuntimeConfig::class),
            $services->get(UrlFormatter::class),
            $services->get(ImageResizer::class),
            $services->get(Storage::class),
            (string)ob_get_clean()
        );
        $local = $cache->localUrl(self::REMOTE, $width, $height);
        $this->assertNotSame(self::REMOTE, $local);

        return $local;
    }

    /** @return array<string, array{mixed, bool}> */
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
    public function testItTellsLinksFromFileNames(mixed $value, bool $expected): void
    {
        $this->assertSame($expected, RemoteImageCache::isRemote($value));
    }

    public function testTheEntryKeepsTheLinkAndShowsTheLocalCopy(): void
    {
        $local = $this->cached(300, 300);
        $entry = $this->getWiki()->services->get(EntryManager::class)->getOne($this->tag);

        $this->assertSame(self::REMOTE, $entry['imagebf_image'] ?? null);
        $html = $this->getWiki()->services->get(\YesWiki\Content\Service\EntryDisplay::class)->renderEntry(false, $entry);
        $this->assertStringContainsString('src="' . htmlspecialchars($local) . '"', $html);
        $this->assertStringNotContainsString('src="' . self::REMOTE . '"', $html);
    }

    public function testTwigImageHelpersServeTheLinkFromTheCache(): void
    {
        $local = $this->cached(300, 200);

        $rendered = $this->getWiki()->services->get(TemplateEngine::class)->renderFromStringNoEscape(
            "{{ resizedImage(url, 300, 200) }}\n{{ urlImage({fileName: url, width: 300, height: 200, mode: 'fit'}) }}",
            ['url' => self::REMOTE]
        );

        $this->assertSame($local . "\n" . $local, $rendered);
    }

    public function testTheApiResizesTheLinkAnEntryHolds(): void
    {
        $local = $this->cached(300, 200);
        $_POST['csrftoken'] = $this->getWiki()->services->get(SignedTokens::class)->sign('POST api/images/cache/300/200/crop');

        $response = $this->getWiki()->services->get(ImageApiController::class)
            ->getCacheUrlRemoteImageViaPost($this->tag, 'imagebf_image', '300', '200', 'crop');

        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode((string)$response->getContent(), true);
        $this->assertSame($local, $data['url']);
        $this->assertNotEmpty($data['newToken']);
    }

    public function testTheApiRefusesAFieldWithoutALink(): void
    {
        $_POST['csrftoken'] = $this->getWiki()->services->get(SignedTokens::class)->sign('POST api/images/cache/300/200/crop');

        $response = $this->getWiki()->services->get(ImageApiController::class)
            ->getCacheUrlRemoteImageViaPost($this->tag, 'bf_titre', '300', '200', 'crop');

        $this->assertSame(400, $response->getStatusCode());
    }

    public function testTheApiWantsAToken(): void
    {
        $_POST['csrftoken'] = 'forged';

        $response = $this->getWiki()->services->get(ImageApiController::class)
            ->getCacheUrlRemoteImageViaPost($this->tag, 'imagebf_image', '300', '200', 'crop');

        $this->assertSame(401, $response->getStatusCode());
    }
}
