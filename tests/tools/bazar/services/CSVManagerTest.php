<?php

namespace YesWiki\Test\Bazar\Service;

use PHPUnit\Framework\Attributes\DataProvider;
use YesWiki\Bazar\Service\CSVManager;
use YesWiki\Bazar\Service\EntryManager;
use YesWiki\Bazar\Service\FormManager;
use YesWiki\Bazar\Service\ListManager;
use YesWiki\Core\Service\PageManager;
use YesWiki\Core\Service\TripleStore;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

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

    private $wiki;
    private $csvManager;
    private $formManager;
    private $entryManager;
    private $tags = [];

    protected function setUp(): void
    {
        $this->wiki = $this->getWiki();
        $this->csvManager = $this->wiki->services->get(CSVManager::class);
        $this->formManager = $this->wiki->services->get(FormManager::class);
        $this->entryManager = $this->wiki->services->get(EntryManager::class);
        $GLOBALS['wiki'] = $this->wiki;

        $this->wiki->services->get(ListManager::class)->create(
            'CSVManager test list',
            array_map(function ($id) {
                return ['id' => $id, 'label' => self::OPTIONS[$id]];
            }, array_keys(self::OPTIONS)),
            self::LIST_ID,
        );

        $this->formManager->create([
            'bn_id_nature' => self::FORM_ID,
            'bn_label_nature' => 'CSVManager test form',
            'bn_template' => implode("\n", [
                'texte***bf_titre***Titre***60***255*** *** ***text***1*** *** *** * *** * *** *** *** ***',
                'checkbox***' . self::LIST_ID . '***Couleurs*** *** *** ***bf_couleurs*** ***0*** *** *** * *** * *** *** *** ***',
                'liste***' . self::LIST_ID . '***Couleur*** *** *** ***bf_couleur*** ***0*** *** *** * *** * *** *** *** ***',
                'tags***bf_motscles***Mots cles*** *** *** *** *** ***0*** *** *** * *** * *** *** *** ***',
            ]),
            'bn_condition' => '',
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
        unset($GLOBALS['wiki']);
    }

    public static function trickyValuesProvider(): array
    {
        return [
            'quote' => ['dit "bonjour"'],
            'backslash before quote' => ['chemin\\"suite'],
            'trailing backslash' => ['C:\\dossier\\'],
            'json with escaped quotes' => [json_encode(['name' => 'Café "Chez Léon"'])],
            'separator and newline' => ["un, deux\ntrois"],
        ];
    }

    #[DataProvider('trickyValuesProvider')]
    public function testExportedValueSurvivesAStandardCsvParser(string $value)
    {
        $line = rtrim($this->csvManager->arrayToCSV([['avant', $value, 'apres']]), "\n");

        $this->assertSame(['avant', $value, 'apres'], str_getcsv($line, ',', '"', ''));
    }

    public function testExportedEntryIsReimportedIdentically()
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

    public function testPlainMultipleValuesAreReimportedAsSeveralOptions()
    {
        $this->createEntry([
            'bf_titre' => 'Sans virgule ni guillemet',
            'bf_couleurs' => 'k1,k4',
        ]);

        $imported = $this->reimport($this->export());

        $this->assertSame('k1,k4', $imported[0]['entry']['bf_couleurs'] ?? null);
        $this->assertSame([], $imported[0]['errormsg']);
    }

    public function testEmptyTemplateIsReimportedWithItsMultipleValues()
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

    public function testUnknownOptionIsSkippedAndReported()
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

    private function createEntry(array $values): void
    {
        $entry = $this->entryManager->create(self::FORM_ID, array_merge(
            ['antispam' => 1, 'id_fiche' => self::ENTRY_TAG],
            $values,
        ));
        $this->tags[] = $entry['id_fiche'];
    }

    private function export(): string
    {
        return $this->csvManager->arrayToCSV($this->csvManager->getCSVfromFormId(self::FORM_ID, []));
    }

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
