<?php

namespace YesWiki\Test\Content;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use YesWiki\Content\Field\FileField;
use YesWiki\Content\Field\ImageField;
use YesWiki\Content\Service\FileManager;
use YesWiki\Core\YesWikiRuntime;
use YesWiki\Files\Service\Storage;
use YesWiki\Kernel\Service\HtmlPurifierService;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** Uploaded svg files are sanitized. */
class UploadedSvgIsSanitizedTest extends YesWikiTestCase
{
    private const ENTRY_TAG = 'UploadedSvgIsSanitizedEntry';
    private const PAYLOAD = '<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"><script>alert(2)</script><rect width="10" height="10"/></svg>';

    private YesWikiRuntime $wiki;
    private Storage $storage;

    /** @var list<string> */
    private array $stored = [];

    protected function setUp(): void
    {
        $this->wiki = $this->getWiki();
        $GLOBALS['wiki'] = $this->wiki;
        $GLOBALS['yeswikiServices'] = $this->wiki->services;
        $this->storage = $this->wiki->services->get(Storage::class);
        $this->stored = [];
    }

    protected function tearDown(): void
    {
        foreach ($this->stored as $path) {
            if ($this->storage->exists($path)) {
                $this->storage->delete($path);
            }
        }
        $_FILES = [];
    }

    /** @return array<string, array{class-string, string}> */
    public static function fieldProvider(): array
    {
        return [
            'image field' => [ImageField::class, 'image'],
            'file field' => [FileField::class, 'fichier'],
        ];
    }

    #[DataProvider('fieldProvider')]
    public function testAFieldStoresTheSvgWithoutItsScripts(string $fieldClass, string $type): void
    {
        $field = $this->field($fieldClass, $type);
        $tmp = $this->temporaryFile(self::PAYLOAD);
        $_FILES[$field->getPropertyName()] = [
            'name' => 'payload.svg',
            'tmp_name' => $tmp,
            'size' => filesize($tmp),
            'error' => 0,
        ];

        $saved = $field->formatValuesBeforeSave(['tag' => self::ENTRY_TAG, 'form_id' => '1']);

        $name = (string)$saved[$field->getPropertyName()];
        $this->assertNotSame('', $name, 'the svg was accepted');
        $path = $this->findStored($name);
        $this->assertNotNull($path, "the stored file $name is in the upload folder");
        $this->assertCleanSvg($this->storage->read($path));
    }

    public function testTheFileRailStoresTheSvgWithoutItsScripts(): void
    {
        $upload = new UploadedFile($this->temporaryFile(self::PAYLOAD), 'payload.svg', 'image/svg+xml', null, true);

        $stored = $this->wiki->services->get(FileManager::class)->storeUpload($upload);

        $path = FileManager::STORAGE_DIR . '/' . $stored['stored_filename'];
        $this->stored[] = $path;
        $this->assertCleanSvg($this->storage->read($path));
    }

    public function testAnSvgThatCannotBeParsedIsNotKept(): void
    {
        $path = 'files/' . self::ENTRY_TAG . '_broken.svg';
        $this->stored[] = $path;
        $this->storage->write($path, '<svg onload="alert(1)"><script>alert(2)');

        $kept = $this->wiki->services->get(HtmlPurifierService::class)->cleanStoredFile($path);

        $this->assertFalse($kept);
        $this->assertFalse($this->storage->exists($path));
    }

    private function assertCleanSvg(string $stored): void
    {
        $this->assertStringContainsString('<rect', $stored);
        $this->assertStringNotContainsString('<script', $stored);
        $this->assertStringNotContainsString('onload', $stored);
    }

    private function temporaryFile(string $content): string
    {
        $tmp = (string)tempnam(sys_get_temp_dir(), 'svg');
        file_put_contents($tmp, $content);

        return $tmp;
    }

    private function findStored(string $name): ?string
    {
        foreach (glob('files/{,*/}' . $name, GLOB_BRACE) ?: [] as $found) {
            $this->stored[] = $found;

            return $found;
        }

        return null;
    }

    private function field(string $fieldClass, string $type): FileField
    {
        $values = array_replace(array_fill(0, 20, ''), [0 => $type, 1 => 'bf_upload_svg_test']);

        return $fieldClass === ImageField::class
            ? new class($values, $this->wiki->services) extends ImageField {
                protected function isUploadedFile(string $source): bool
                {
                    return is_file($source);
                }
            }
        : new class($values, $this->wiki->services) extends FileField {
            protected function isUploadedFile(string $source): bool
            {
                return is_file($source);
            }
        };
    }
}
