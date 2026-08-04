<?php

namespace YesWiki\Test\Content;

use Symfony\Component\HttpFoundation\Request;
use YesWiki\Content\Api\FileApiController;
use YesWiki\Content\Field\BazarField;
use YesWiki\Content\Service\FieldFactory;
use YesWiki\Content\Service\FileManager;
use YesWiki\Content\Service\PageManager;
use YesWiki\Kernel\Service\UrlFormatter;
use YesWiki\Test\Core\YesWikiTestCase;
use YesWiki\YesWikiRuntime;

require_once 'tests/YesWikiTestCase.php';

/**
 * A picture attached from the file rail is still the field's picture.
 *
 * The bazar image field stores a picked file as its own download address, which lands in
 * the URL branch the field has always had -- and that branch handed the image straight to
 * the browser at whatever size it was uploaded. A field configured to show 200x150
 * thumbnails would have served a 3 MB photograph in a 200px box, on every card of every
 * list, which is the whole reason the sizes exist.
 *
 * The resizing happens behind `/api/files/<tag>/download`, not in a public cache
 * directory: that route is the one place that asks whether this reader may have the file,
 * and a thumbnail sitting in `cache/` would be a readable copy of a file whose whole point
 * is that reading it is checked.
 */
class ImageFieldOwnFilesTest extends YesWikiTestCase
{
    private const FILE_TAG = 'ImageFieldOwnFilesTestPicture';

    /** An image field carrying the sizes a webmaster configured. */
    private function imageField(YesWikiRuntime $wiki, string $thumbWidth = '200', string $thumbHeight = '150'): BazarField
    {
        // positional, as forms have always stored a field: type, name, label, then
        // ImageField's own thumbnail height/width and full height/width
        $values = array_fill(0, 16, '');
        $values[0] = 'image';
        $values[1] = 'bf_image';
        $values[3] = $thumbHeight;
        $values[4] = $thumbWidth;

        $field = $wiki->services->get(FieldFactory::class)->create($values);
        $this->assertInstanceOf(BazarField::class, $field, 'the image field type must be registered');

        return $field;
    }

    private function renderStatic(BazarField $field, string $value): string
    {
        $render = (new \ReflectionClass($field))->getMethod('renderStatic');

        // an image field's stored key is its type + its name (`imagebf_image`), which is
        // what the entry array is read by
        return (string)$render->invoke($field, [$field->getPropertyName() => $value, 'tag' => 'SomeEntry']);
    }

    public function testOurOwnFileIsAskedForAtTheFieldsSize(): void
    {
        $wiki = $this->getWiki();
        $GLOBALS['yeswikiServices'] = $wiki->services;
        $url = $wiki->services->get(UrlFormatter::class)->href('', 'api/files/some-picture/download');

        $rendered = $this->renderStatic($this->imageField($wiki), $url);
        $this->assertStringContainsString('width=200', $rendered);
        $this->assertStringContainsString('height=150', $rendered);

        // a field with no sizes configured asks for none: the picture as it was uploaded
        $plain = $this->renderStatic($this->imageField($wiki, '', ''), $url);
        $this->assertStringNotContainsString('width=', $plain);
    }

    public function testSomebodyElsesPictureIsLeftAlone(): void
    {
        $wiki = $this->getWiki();
        $GLOBALS['yeswikiServices'] = $wiki->services;

        // an address on another site -- resizing it would mean fetching it, and this wiki
        // does not host it
        $rendered = $this->renderStatic($this->imageField($wiki), 'https://example.org/api/files/x/download');
        $this->assertStringContainsString('src="https://example.org/api/files/x/download"', $rendered);
        $this->assertStringNotContainsString('width=', $rendered);
    }

    /**
     * ...and the route really does resize, into a cache nobody can reach directly.
     */
    public function testTheDownloadRouteServesAResizedCopyFromAPrivateCache(): void
    {
        if (!function_exists('imagecreatetruecolor')) {
            $this->markTestSkipped('resizing needs GD');
        }

        $wiki = $this->getWiki();
        $GLOBALS['yeswikiServices'] = $wiki->services;
        $fileManager = $wiki->services->get(FileManager::class);

        $stored = 'image-field-own-files-test.png';
        $path = FileManager::STORAGE_DIR . '/' . $stored;
        $image = imagecreatetruecolor(600, 400);
        imagepng($image, $path);
        $entry = $fileManager->create('picture.png', $stored, 'SomeEntry', (int)filesize($path), 'image/png');
        $tag = $entry['tag'] ?? self::FILE_TAG;

        $cached = FileManager::STORAGE_DIR . '/cache/' . pathinfo($stored, PATHINFO_FILENAME) . '_fit_120_90.png';

        try {
            $response = $wiki->services->get(FileApiController::class)->downloadFile(
                Request::create('/', 'GET', ['width' => '120', 'height' => '90']),
                $tag
            );
            ob_start();
            $response->sendContent();
            $body = (string)ob_get_clean();

            $size = getimagesizefromstring($body);
            $this->assertNotFalse($size, 'the route answered with an image');
            $this->assertLessThan(600, $size[0], 'and a smaller one than was uploaded');

            $this->assertFileExists($cached, 'the resized copy is cached');
            $this->assertStringStartsWith(
                'private/',
                $cached,
                'under private/, where the bytes are -- a public thumbnail would be a way around the ACL'
            );
        } finally {
            $fileManager->delete($tag);
            $wiki->services->get(PageManager::class)->deleteOrphaned($tag);
            foreach ([$path, $cached] as $leftover) {
                if (file_exists($leftover)) {
                    unlink($leftover);
                }
            }
        }
    }
}
