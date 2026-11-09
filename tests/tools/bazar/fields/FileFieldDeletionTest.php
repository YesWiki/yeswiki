<?php

namespace YesWiki\Test\Bazar\Field;

use PHPUnit\Framework\Attributes\DataProvider;
use YesWiki\Bazar\Field\FileField;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * A signed deletion link trashes the entry's file, whatever characters its name holds.
 */
class FileFieldDeletionTest extends YesWikiTestCase
{
    private const ENTRY_TAG = 'FileFieldDeletionEntry';

    private $wiki;
    private $previousGet;

    protected function setUp(): void
    {
        $this->wiki = $this->getWiki();
        $GLOBALS['wiki'] = $this->wiki;
        $this->previousGet = $_GET;
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob('files/' . self::ENTRY_TAG . '_*'));
        $_GET = $this->previousGet;
    }

    public static function fileNames(): array
    {
        return [
            'plain' => ['plain.pdf'],
            'accents' => ['été.pdf'],
            'apostrophe' => ["l'ami.pdf"],
            'ampersand' => ['a&b.pdf'],
        ];
    }

    #[DataProvider('fileNames')]
    public function testASignedLinkTrashesTheFile(string $name)
    {
        $field = $this->field();
        $value = self::ENTRY_TAG . '_' . $field->getPropertyName() . '_' . $name;
        file_put_contents('files/' . $value, 'content');
        $_GET['delete_file'] = $value;

        $field->renderIt(['id_fiche' => self::ENTRY_TAG, 'id_typeannonce' => '1', $field->getPropertyName() => $value]);

        $this->assertFileDoesNotExist('files/' . $value);
        $this->assertCount(1, glob('files/' . $value . 'trash*'));
    }

    private function field(): FileField
    {
        $values = array_replace(array_fill(0, 20, ''), [0 => 'fichier', 1 => 'doc']);

        return new class($values, $this->wiki->services) extends FileField {
            protected function deletionLinkIsSigned(): bool
            {
                return true;
            }

            protected function isAllowedToDeleteFile(array $entry, string $fileName): bool
            {
                return true;
            }

            public function renderIt(array $entry): string
            {
                return $this->renderInput($entry);
            }
        };
    }
}
