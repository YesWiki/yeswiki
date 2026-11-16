<?php

namespace YesWiki\Test\Content;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use YesWiki\Content\Entity\CsvDialect;
use YesWiki\Content\Service\CsvDateReader;
use YesWiki\Content\Service\CSVManager;
use YesWiki\Content\Service\EntryManager;
use YesWiki\Content\Service\FormManager;
use YesWiki\Kernel\Service\RuntimeConfig;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** The import reads a file as spreadsheets save it, whatever its delimiter and encoding (end-users 10), and reads its dates day before month (end-users 28). */
#[CoversClass(CSVManager::class)]
#[CoversClass(CsvDialect::class)]
#[CoversClass(CsvDateReader::class)]
class CSVImportFromSpreadsheetsTest extends YesWikiTestCase
{
    private const FORM_ID = '999911';
    private const HEADERS = ['Titre *', 'Thème', 'Œuvre', 'Date'];

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
            'label' => 'CSV import from spreadsheets test form',
            'entry_title_template' => '{{bf_titre}}',
            'template' => json_encode([
                ['type' => 'texte', 'name' => 'bf_titre', 'label' => 'Titre', 'required' => '1'],
                ['type' => 'texte', 'name' => 'bf_theme', 'label' => 'Thème'],
                ['type' => 'texte', 'name' => 'bf_oeuvre', 'label' => 'Œuvre'],
                ['type' => 'jour', 'name' => 'bf_date', 'label' => 'Date'],
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

    /** @return array<string, array{0: string, 1: string, 2: string, 3: bool}> file bytes, delimiter, encoding, BOM */
    public static function spreadsheetFilesProvider(): array
    {
        $rows = [self::HEADERS, ['Fiche une', 'Thème libre', 'Œuvre ouverte', '01/02/2026'], ['Fiche deux', 'Thème; fermé', 'Œuvre', '2026-02-13']];
        $write = static function (array $rows, string $delimiter, string $newline): string {
            $stream = fopen('php://temp', 'r+');
            if ($stream === false) {
                throw new \RuntimeException('no temporary stream');
            }
            foreach ($rows as $row) {
                fputcsv($stream, $row, $delimiter, '"', '', $newline);
            }
            rewind($stream);

            return (string)stream_get_contents($stream);
        };
        $semicolon = $write($rows, ';', "\r\n");

        return [
            'Excel, CSV (point-virgule), Windows-1252' => [mb_convert_encoding($semicolon, 'Windows-1252', 'UTF-8'), ';', CsvDialect::WINDOWS_1252, false],
            'Excel, CSV UTF-8, with a BOM' => [CSVManager::UTF8_BOM . $semicolon, ';', CsvDialect::UTF8, true],
            'LibreOffice, comma, UTF-8' => [$write($rows, ',', "\n"), ',', CsvDialect::UTF8, false],
            'LibreOffice, semicolon, Windows-1252' => [mb_convert_encoding($write($rows, ';', "\n"), 'Windows-1252', 'UTF-8'), ';', CsvDialect::WINDOWS_1252, false],
            'tab separated' => [$write($rows, "\t", "\r\n"), "\t", CsvDialect::UTF8, false],
            'vertical bar' => [$write($rows, '|', "\n"), '|', CsvDialect::UTF8, false],
        ];
    }

    #[DataProvider('spreadsheetFilesProvider')]
    public function testAFileAsASpreadsheetSavesItImportsWithItsAccents(string $bytes, string $delimiter, string $encoding, bool $hadBom): void
    {
        $reading = $this->read($bytes);

        $this->assertEquals(new CsvDialect($delimiter, $encoding, $hadBom), $reading['dialect']);
        $this->assertNull($reading['problem']);
        $this->assertCount(2, $reading['extracted'] ?? []);
        $first = $this->line($reading);
        $this->assertSame([], $first['errormsg']);
        $this->assertSame('Fiche une', $first['entry']['bf_titre'] ?? null);
        $this->assertSame('Thème libre', $first['entry']['bf_theme'] ?? null);
        $this->assertSame('Œuvre ouverte', $first['entry']['bf_oeuvre'] ?? null);
        $this->assertSame('2026-02-01', $first['entry']['bf_date'] ?? null, '01/02/2026 is the first of February');
        $this->assertSame('Thème; fermé', $reading['extracted'][1]['entry']['bf_theme'] ?? null, 'a quoted delimiter stays in its cell');
        $this->assertSame('2026-02-13', $reading['extracted'][1]['entry']['bf_date'] ?? null);
    }

    public function testATieBetweenDelimitersGoesToTheComma(): void
    {
        $this->assertSame(',', CSVManager::sniff("a;b,c\nd;e,f\n")->delimiter);
        $this->assertSame(',', CSVManager::sniff("seule colonne\nautre\n")->delimiter);
    }

    public function testAnAccentCutByTheSniffedLengthIsStillUtf8(): void
    {
        $head = str_repeat("Thème,Œuvre\n", 6000);

        $this->assertSame(CsvDialect::UTF8, CSVManager::sniff(substr($head, 0, 65536))->encoding);
    }

    public function testTheExtensionIsReadInAnyCase(): void
    {
        $this->assertTrue(CSVManager::isImportableFilename('EXPORT.CSV'));
        $this->assertTrue(CSVManager::isImportableFilename('export.tsv'));
        $this->assertTrue(CSVManager::isImportableFilename('export.Txt'));
        $this->assertFalse(CSVManager::isImportableFilename('export.xlsx'));
        $this->assertFalse(CSVManager::isImportableFilename('csv'));

        $reading = $this->read($this->csvManager->arrayToCSV([self::HEADERS, ['Majuscules', '', '', '']]), 'EXPORT.CSV');
        $this->assertSame('Majuscules', $reading['extracted'][0]['entry']['bf_titre'] ?? null);
    }

    public function testAFileOfAnotherKindIsRefusedWithAMessage(): void
    {
        $reading = $this->read('PK', 'export.xlsx');

        $this->assertNull($reading['extracted']);
        $this->assertStringContainsString('export.xlsx', (string)$reading['problem']);
        $this->assertStringContainsString('.csv', (string)$reading['problem']);
    }

    public function testAFileWithNoKnownHeaderSaysWhichAreExpected(): void
    {
        $reading = $this->read("Nom;Prénom\nDupont;Jean\n");

        $this->assertNull($reading['extracted']);
        $this->assertStringContainsString('"Titre *"', (string)$reading['problem']);
        $this->assertStringContainsString('"Œuvre"', (string)$reading['problem']);
    }

    public function testAnExportImportsBackUnchanged(): void
    {
        $original = ['bf_titre' => 'Fiche « exportée »', 'bf_theme' => 'Thème, avec virgule', 'bf_oeuvre' => 'Œuvre "citée"', 'bf_date' => '2026-02-01T14:30:00+01:00'];
        $entry = $this->entryManager->create(self::FORM_ID, ['antispam' => 1] + $original);
        $this->tags[] = $entry['tag'];
        $export = CSVManager::UTF8_BOM . $this->csvManager->arrayToCSV($this->csvManager->getCSVfromFormId(self::FORM_ID, []));

        $reading = $this->read($export);

        $this->assertSame([], $reading['extracted'][0]['errormsg'] ?? null);
        foreach ($original as $propertyName => $value) {
            $this->assertSame($value, $reading['extracted'][0]['entry'][$propertyName] ?? null, $propertyName);
        }
        $this->assertSame($entry['created_at'] ?? null, $reading['extracted'][0]['entry']['created_at'] ?? null, 'the creation date reads back too');
    }

    public function testTypographicEntitiesBecomeCharactersButMarkupStaysEscaped(): void
    {
        $reading = $this->read($this->csvManager->arrayToCSV([self::HEADERS, ['L&rsquo;&oelig;uvre &hellip;', '&lt;b&gt; &amp; co', '', '']]));

        $this->assertSame('L’œuvre …', $reading['extracted'][0]['entry']['bf_titre'] ?? null);
        $this->assertSame('&lt;b&gt; &amp; co', $reading['extracted'][0]['entry']['bf_theme'] ?? null);
    }

    public function testAnImportedTimeIsInTheWikiTimezone(): void
    {
        $zone = new \DateTimeZone((string)$this->getWiki()->services->get(RuntimeConfig::class)['timezone']);

        $date = $this->importDate('2026-02-01 14:30');

        $this->assertSame((new \DateTimeImmutable('2026-02-01 14:30', $zone))->format('c'), $date['entry']['bf_date'] ?? null);
        $this->assertSame([], $date['errormsg']);
    }

    /** @return array<string, array{0: string}> */
    public static function unreadableDatesProvider(): array
    {
        return ['month first' => ['02/13/2026'], 'no such day' => ['31/02/2026'], 'words' => ['next friday'], 'half a date' => ['13/2026']];
    }

    #[DataProvider('unreadableDatesProvider')]
    public function testAnUnreadableDateIsReportedOnItsLineAndLeftEmpty(string $cell): void
    {
        $date = $this->importDate($cell);

        $this->assertArrayNotHasKey('bf_date', $date['entry']);
        $this->assertCount(1, $date['errormsg']);
        $this->assertStringContainsString($cell, $date['errormsg'][0]);
        $this->assertStringContainsString('Date', $date['errormsg'][0]);
        $this->assertStringContainsString('01/02/2026', $date['errormsg'][0], 'the message gives the accepted formats');
    }

    public function testAnUnreadableCreationDateIsReportedNotReplaced(): void
    {
        $reading = $this->read($this->csvManager->arrayToCSV([
            ['datetime_create', 'datetime_latest', ...self::HEADERS],
            ['hier', '01/02/2026 10:00:00', 'Fiche datée', '', '', ''],
        ]));

        $line = $this->line($reading);
        $this->assertCount(1, $line['errormsg']);
        $this->assertStringContainsString('datetime_create', $line['errormsg'][0]);
        $this->assertStringContainsString('2026-02-01 10:00:00', (string)($line['entry']['updated_at'] ?? ''));
    }

    public function testTheTemplateShowsADateColumnInIsoForm(): void
    {
        $template = $this->csvManager->getCSVfromFormId(self::FORM_ID, [], ['fakeMode' => true]);

        $this->assertSame('2026-02-01', $template[1][3] ?? null);
    }

    public function testTheDateReaderNeverReadsMonthFirst(): void
    {
        $zone = new \DateTimeZone('Europe/Paris');

        $this->assertSame('2026-02-01', CsvDateReader::storedValue('01/02/2026', $zone));
        $this->assertSame('2026-02-01', CsvDateReader::storedValue('01.02.2026', $zone));
        $this->assertSame('2026-02-01', CsvDateReader::storedValue('01-02-2026', $zone));
        $this->assertSame('2026-02-01T14:30:00+01:00', CsvDateReader::storedValue('01/02/2026 14:30', $zone));
        $this->assertSame('2026-02-01T14:30:00+01:00', CsvDateReader::storedValue('2026-02-01T14:30:00+01:00', $zone));
        $this->assertNull(CsvDateReader::read('02/13/2026', $zone));
        $this->assertNull(CsvDateReader::read('', $zone));
    }

    /** @return array{entry: array<string, mixed>, errormsg: list<string>} */
    private function importDate(string $cell): array
    {
        return $this->line($this->read($this->csvManager->arrayToCSV([self::HEADERS, ['Fiche datée', '', '', $cell]])));
    }

    /**
     * @param array{extracted: list<array{entry: array<string, mixed>, errormsg: list<string>}>|null, dialect: CsvDialect|null, problem: string|null} $reading
     *
     * @return array{entry: array<string, mixed>, errormsg: list<string>}
     */
    private function line(array $reading): array
    {
        $line = $reading['extracted'][0] ?? null;
        if ($line === null) {
            $this->fail('the file read as no line: ' . ($reading['problem'] ?? 'no problem given'));
        }

        return $line;
    }

    /** @return array{extracted: list<array{entry: array<string, mixed>, errormsg: list<string>}>|null, dialect: CsvDialect|null, problem: string|null} */
    private function read(string $bytes, string $name = 'import.csv'): array
    {
        $path = tempnam(sys_get_temp_dir(), 'csvspreadsheets');
        file_put_contents($path, $bytes);

        try {
            return $this->csvManager->readImportFile(
                self::FORM_ID,
                ['name' => $name, 'tmp_name' => $path, 'error' => 0],
                true,
                $this->formManager->getOne(self::FORM_ID),
            );
        } finally {
            unlink($path);
        }
    }
}
