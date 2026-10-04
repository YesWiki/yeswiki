<?php

namespace YesWiki\Test\Content;

use YesWiki\Content\Service\CSVManager;
use YesWiki\Content\Service\EntryManager;
use YesWiki\Content\Service\FormManager;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** Importing remote files as URLs, map cells, and the two formats the import form may post. */
class CSVImportOptionsTest extends YesWikiTestCase
{
    private const FORM_ID = '999908';

    private CSVManager $csvManager;
    private FormManager $formManager;
    private EntryManager $entryManager;
    /** @var list<string> */
    private array $tags = [];

    protected function setUp(): void
    {
        $wiki = $this->getWiki();
        $this->csvManager = $wiki->services->get(CSVManager::class);
        $this->formManager = $wiki->services->get(FormManager::class);
        $this->entryManager = $wiki->services->get(EntryManager::class);
        $this->formManager->create([
            'id' => self::FORM_ID,
            'label' => 'CSV import options test form',
            'entry_title_template' => '{{bf_titre}}',
            'template' => json_encode([
                ['type' => 'texte', 'name' => 'bf_titre', 'label' => 'Titre'],
                ['type' => 'image', 'name' => 'bf_photo', 'label' => 'Photo'],
                ['type' => 'fichier', 'name' => 'bf_doc', 'label' => 'Document'],
                ['type' => 'map', 'name' => 'bf_latitude', 'label' => 'bf_longitude'],
            ]),
        ]);
    }

    protected function tearDown(): void
    {
        foreach ($this->tags as $tag) {
            $this->entryManager->delete($tag, true);
        }
        $this->formManager->delete(self::FORM_ID);
    }

    public function testRemoteFilesAreKeptAsUrlsWhenAsked(): void
    {
        $row = $this->importRow([
            'Titre' => 'Avec URL',
            'Photo' => 'https://exemple.invalid/photos/été.jpg',
            'Document' => 'https://exemple.invalid/docs/compte-rendu.pdf',
        ], true);

        $this->assertSame([], $row['errormsg']);
        $this->assertSame('https://exemple.invalid/photos/été.jpg', $row['entry']['imagebf_photo']);
        $this->assertSame('https://exemple.invalid/docs/compte-rendu.pdf', $row['entry']['fichierbf_doc']);
    }

    public function testAMapCellIsReadBackIntoItsStructure(): void
    {
        $row = $this->importRow(['Titre' => 'Carte', 'bf_longitude' => '{"latitude":45.18,"longitude":5.72}']);

        $maps = array_values(array_filter($row['entry'], fn ($value) => is_array($value) && isset($value['latitude'])));
        $this->assertSame([], $row['errormsg']);
        $this->assertSame(['latitude' => '45.18', 'longitude' => '5.72', 'geometries' => ''], $maps[0] ?? null);
    }

    public function testAnUnreadableMapCellIsReported(): void
    {
        $row = $this->importRow(['Titre' => 'Carte', 'bf_longitude' => 'pas une carte']);

        $this->assertCount(1, $row['errormsg']);
        $this->assertStringContainsString(_t('BAZ_UNREADABLE_MAP_VALUE'), $row['errormsg'][0]);
    }

    public function testBothPostedFormatsAreRead(): void
    {
        $decode = new \ReflectionMethod($this->csvManager, 'decodeImportedEntry');
        $map = ['latitude' => '45.18', 'longitude' => '5.72', 'geometries' => ''];

        $this->assertSame(
            ['bf_titre' => 'Import JSON', 'bf_latitude' => $map],
            $decode->invoke($this->csvManager, json_encode(['bf_titre' => 'Import JSON', 'bf_latitude' => $map]))
        );
        $this->assertSame(
            ['bf_titre' => 'Import ancien', 'status' => '1'],
            $decode->invoke($this->csvManager, base64_encode(serialize(['bf_titre' => 'Import ancien', 'status' => 1])))
        );
        $this->assertNull($decode->invoke($this->csvManager, 'illisible'));
    }

    /**
     * @param array<string, string> $cellsByHeader header fragment => cell
     *
     * @return array{entry: array<string, mixed>, errormsg: list<string>}
     */
    private function importRow(array $cellsByHeader, bool $keepRemoteFilesAsUrl = false): array
    {
        $csv = $this->csvManager->getCSVfromFormId(self::FORM_ID, [], ['fakeMode' => true]);
        if (!isset($csv[0])) {
            $this->fail('the form has no CSV header row');
        }
        $headers = $csv[0];
        $row = array_fill(0, count($headers), '');
        foreach ($cellsByHeader as $fragment => $cell) {
            foreach ($headers as $index => $header) {
                if (stripos((string)$header, $fragment) !== false) {
                    $row[$index] = $cell;
                    break;
                }
            }
        }
        $path = tempnam(sys_get_temp_dir(), 'csvimportoptions') . '.csv';
        file_put_contents($path, $this->csvManager->arrayToCSV([$headers, $row]));

        try {
            $extracted = $this->csvManager->extractCSVfromCSVFile(
                self::FORM_ID,
                ['name' => basename($path), 'tmp_name' => $path, 'error' => 0],
                true,
                $this->formManager->getOne(self::FORM_ID),
                $keepRemoteFilesAsUrl
            ) ?? [];
        } finally {
            unlink($path);
        }

        return $extracted[0];
    }
}
