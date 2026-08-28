<?php

namespace YesWiki\Test\Content;

use PHPUnit\Framework\Attributes\DataProvider;
use YesWiki\Content\Service\CSVManager;
use YesWiki\Content\Service\EntryManager;
use YesWiki\Content\Service\FormManager;
use YesWiki\Content\Service\ListManager;
use YesWiki\Content\Service\PageManager;
use YesWiki\Kernel\Service\TripleStore;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** A CSV this wiki writes has to be one every spreadsheet and every standard parser reads back, and one this wiki reimports unchanged. Ported from doryphore-dev, with ectoplasme's JSON form template. */
class CSVManagerTest extends YesWikiTestCase
{
    private const LIST_ID = 'ListeCsvManagerTest';
    private const FORM_ID = '999905';
    private const ENTRY_TAG = 'CsvManagerTestFiche';

    private const OPTIONS = [
        'k1' => 'Rouge',
        'k2' => 'Vert, tendre',
        'k3' => 'Bleu "roi"',
        'k4' => 'Jaune',
    ];

    private CSVManager $csvManager;
    private FormManager $formManager;
    private EntryManager $entryManager;
    /** @var list<string> */
    private array $tags = [];

    private \YesWiki\Core\YesWikiRuntime $wiki;

    protected function setUp(): void
    {
        parent::setUp();
        $this->wiki = $this->getWiki();
        $this->csvManager = $this->wiki->services->get(CSVManager::class);
        $this->formManager = $this->wiki->services->get(FormManager::class);
        $this->entryManager = $this->wiki->services->get(EntryManager::class);

        $this->wiki->services->get(ListManager::class)->create(
            'CSVManager test list',
            array_map(
                fn (string $id): array => ['id' => $id, 'label' => self::OPTIONS[$id]],
                array_keys(self::OPTIONS)
            ),
            self::LIST_ID,
        );

        $this->formManager->create([
            'id' => self::FORM_ID,
            'label' => 'CSVManager test form',
            'template' => json_encode([
                ['type' => 'texte', 'name' => 'bf_titre', 'label' => 'Titre', 'required' => '1'],
                ['type' => 'checkbox', 'linked_object' => self::LIST_ID, 'name' => 'bf_couleurs', 'label' => 'Couleurs'],
                ['type' => 'liste', 'linked_object' => self::LIST_ID, 'name' => 'bf_couleur', 'label' => 'Couleur'],
                ['type' => 'tags', 'name' => 'bf_motscles', 'label' => 'Mots cles'],
            ]),
            'condition' => '',
        ]);
    }

    protected function tearDown(): void
    {
        foreach ($this->tags as $tag) {
            $this->entryManager->delete($tag, true);
        }
        $this->formManager->delete(self::FORM_ID);
        $this->wiki->services->get(PageManager::class)->deleteOrphaned(self::LIST_ID);
        $this->wiki->services->get(TripleStore::class)->delete(self::LIST_ID, TripleStore::TYPE_URI, null, '', '');
        parent::tearDown();
    }

    /** @return array<string, array{0: string}> */
    public static function trickyValuesProvider(): array
    {
        return [
            'quote' => ['dit "bonjour"'],
            'backslash before quote' => ['chemin\\"suite'],
            'trailing backslash' => ['C:\\dossier\\'],
            'json with escaped quotes' => [(string)json_encode(['name' => 'Café "Chez Léon"'])],
            'separator and newline' => ["un, deux\ntrois"],
        ];
    }

    #[DataProvider('trickyValuesProvider')]
    public function testExportedValueSurvivesAStandardCsvParser(string $value): void
    {
        $line = rtrim($this->csvManager->arrayToCSV([['avant', $value, 'apres']]), "\n");

        $this->assertSame(['avant', $value, 'apres'], str_getcsv($line, ',', '"', ''));
    }

    public function testExportedEntryIsReimportedIdentically(): void
    {
        $original = [
            'bf_titre' => 'Fiche "essai", une',
            'bf_couleurs' => 'k1,k2,k3',
            'bf_couleur' => 'k2',
            'bf_motscles' => 'alpha,beta',
        ];
        $this->createEntry($original);

        $imported = $this->reimport($this->export());

        $this->assertCount(1, $imported);
        foreach ($original as $propertyName => $value) {
            $this->assertSame($value, $imported[0]['entry'][$propertyName] ?? null, $propertyName);
        }
        $this->assertSame([], $imported[0]['errormsg']);
    }

    public function testPlainMultipleValuesAreReimportedAsSeveralOptions(): void
    {
        $this->createEntry([
            'bf_titre' => 'Sans virgule ni guillemet',
            'bf_couleurs' => 'k1,k4',
        ]);

        $imported = $this->reimport($this->export());

        $this->assertSame('k1,k4', $imported[0]['entry']['bf_couleurs'] ?? null);
        $this->assertSame([], $imported[0]['errormsg']);
    }

    public function testEmptyTemplateIsReimportedWithItsMultipleValues(): void
    {
        $csv = $this->csvManager->arrayToCSV(
            $this->csvManager->getCSVfromFormId(self::FORM_ID, [], ['fakeMode' => true]),
        );

        $imported = $this->reimport($csv);

        $this->assertCount(3, $imported);
        $this->assertSame('k1,k2,k3', $imported[0]['entry']['bf_couleurs']);
        $this->assertSame(
            'ligne 1 - champ 4 - tag 1,ligne 1 - champ 4 - tag 2,ligne 1 - champ 4 - tag 3',
            $imported[0]['entry']['bf_motscles'],
        );
    }

    public function testUnknownOptionIsSkippedAndReported(): void
    {
        $csv = $this->csvManager->arrayToCSV([
            ['datetime_create', 'datetime_latest', 'Titre *', 'Couleurs', 'Couleur', 'Mots cles'],
            ['', '', 'Fiche', 'Rouge,Mauve,Bleu "roi"', '', ''],
        ]);

        $imported = $this->reimport($csv);

        $this->assertSame('k1,k3', $imported[0]['entry']['bf_couleurs']);
        $this->assertCount(1, $imported[0]['errormsg']);
        $this->assertStringContainsString('Mauve', $imported[0]['errormsg'][0]);
    }

    /** @param array<string, string> $values */
    private function createEntry(array $values): void
    {
        $entry = $this->entryManager->create(self::FORM_ID, array_merge(
            ['antispam' => 1, 'tag' => self::ENTRY_TAG],
            $values,
        ));
        $this->tags[] = $entry['tag'];
    }

    private function export(): string
    {
        return $this->csvManager->arrayToCSV($this->csvManager->getCSVfromFormId(self::FORM_ID, []));
    }

    /** @return list<array<string, mixed>> */
    private function reimport(string $csv): array
    {
        $path = tempnam(sys_get_temp_dir(), 'csvmanagertest') . '.csv';
        file_put_contents($path, $csv);

        try {
            return $this->csvManager->extractCSVfromCSVFile(
                self::FORM_ID,
                ['name' => basename($path), 'tmp_name' => $path, 'error' => 0],
                true,
                $this->formManager->getOne(self::FORM_ID),
            ) ?? [];
        } finally {
            unlink($path);
        }
    }
}
