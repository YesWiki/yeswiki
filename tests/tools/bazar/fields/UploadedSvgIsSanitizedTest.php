<?php

namespace YesWiki\Test\Bazar\Field;

use PHPUnit\Framework\Attributes\DataProvider;
use YesWiki\Bazar\Field\FileField;
use YesWiki\Bazar\Field\ImageField;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * An svg uploaded through a bazar file or image field is stored without its scripts.
 */
class UploadedSvgIsSanitizedTest extends YesWikiTestCase
{
    private const ENTRY_TAG = 'UploadedSvgIsSanitizedEntry';
    private const PAYLOAD = '<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"><script>alert(2)</script><rect width="10" height="10"/></svg>';

    private $wiki;
    private $storedFile;

    protected function setUp(): void
    {
        $this->wiki = $this->getWiki();
        $GLOBALS['wiki'] = $this->wiki;
        $this->storedFile = null;
    }

    protected function tearDown(): void
    {
        if ($this->storedFile && file_exists($this->storedFile)) {
            unlink($this->storedFile);
        }
        $_FILES = [];
    }

    public static function fieldProvider(): array
    {
        return [
            'image field' => [ImageField::class, 'image'],
            'file field' => [FileField::class, 'fichier'],
        ];
    }

    #[DataProvider('fieldProvider')]
    public function testTheStoredSvgCarriesNoScript(string $fieldClass, string $type)
    {
        $field = $this->field($fieldClass, $type);
        $tmp = tempnam(sys_get_temp_dir(), 'svg');
        file_put_contents($tmp, self::PAYLOAD);
        $_FILES[$field->getPropertyName()] = [
            'name' => 'payload.svg',
            'tmp_name' => $tmp,
            'size' => filesize($tmp),
            'error' => 0,
        ];

        $saved = $field->formatValuesBeforeSave(['id_fiche' => self::ENTRY_TAG, 'id_typeannonce' => '1']);

        $this->storedFile = $this->findStored($saved[$field->getPropertyName()]);
        $this->assertFileExists($this->storedFile);
        $stored = file_get_contents($this->storedFile);
        $this->assertStringContainsString('<rect', $stored);
        $this->assertStringNotContainsString('<script', $stored);
        $this->assertStringNotContainsString('onload', $stored);
    }

    private function findStored(string $name): ?string
    {
        $found = glob('files/{,*/}' . $name, GLOB_BRACE);

        return $found[0] ?? null;
    }

    private function field(string $fieldClass, string $type): FileField
    {
        $values = array_replace(array_fill(0, 20, ''), [0 => $type, 1 => 'bf_upload_svg_test']);
        $subclass = $fieldClass === ImageField::class
            ? new class($values, $this->wiki->services) extends ImageField {
                protected function moveUploadedFile(string $source, string $destination): bool
                {
                    return rename($source, $destination);
                }
            }
            : new class($values, $this->wiki->services) extends FileField {
                protected function moveUploadedFile(string $source, string $destination): bool
                {
                    return rename($source, $destination);
                }
            };

        return $subclass;
    }
}
