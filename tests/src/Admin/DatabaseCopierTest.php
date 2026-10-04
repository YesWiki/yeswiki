<?php

namespace YesWiki\Test\Admin;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use YesWiki\Admin\Service\DatabaseCopier;
use YesWiki\Admin\Service\SchemaCreator;
use YesWiki\Kernel\Database\DumpRewriter;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** db:copy onto another engine: the installer's schema, every row, the same bytes, and no second copy over the first. */
class DatabaseCopierTest extends YesWikiTestCase
{
    private string $file = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->file = sys_get_temp_dir() . '/yeswiki-db-copy-' . bin2hex(random_bytes(4)) . '.sqlite';
    }

    protected function tearDown(): void
    {
        @unlink($this->file);
        parent::tearDown();
    }

    public function testTheWikiArrivesWholeOnAnotherEngine(): void
    {
        $db = $this->getWiki()->services->get(DbService::class);
        $prefix = trim($db->prefixTable(''));
        $copier = new DatabaseCopier($db);
        $target = DatabaseCopier::connect('sqlite', '', '', $this->file, '', '');

        $counts = $copier->copy($target, $prefix);

        $this->assertArrayHasKey($prefix . 'pages', $counts);
        $this->assertArrayHasKey($prefix . 'journal', $counts);
        foreach ($counts as $table => [$from, $to]) {
            $this->assertSame($from, $to, "{$table} lost or gained rows");
        }
        $this->assertGreaterThan(0, $counts[$prefix . 'pages'][1], 'the wiki has pages to copy');

        $source = $db->loadSingle('SELECT id, body FROM ' . $db->prefixTable('pages') . " WHERE latest = 'Y' ORDER BY id DESC LIMIT 1");
        $this->assertNotNull($source);
        $copy = $target->prepare('SELECT body FROM "' . $prefix . 'pages" WHERE id = ?');
        $copy->execute([$source['id']]);
        $this->assertSame(json_decode((string)$source['body'], true), json_decode((string)$copy->fetchColumn(), true));
    }

    /** A wiki under a longer prefix, such as yeswiki_ecto_ beside yeswiki_, is another wiki: neither copied nor in the way. */
    public function testACoHostedWikiIsNeitherCopiedNorInTheWay(): void
    {
        $sourceFile = sys_get_temp_dir() . '/yeswiki-db-copy-source-' . bin2hex(random_bytes(4)) . '.sqlite';
        try {
            $sourcePdo = DatabaseCopier::connect('sqlite', '', '', $sourceFile, '', '');
            SchemaCreator::create($sourcePdo, 'ywcopy_');
            SchemaCreator::create($sourcePdo, 'ywcopy_other_');
            $sourcePdo->exec("INSERT INTO ywcopy_other_triples (resource, property, value) VALUES ('Theirs', 'p', 'v')");
            $source = new DbService(new ParameterBag(['db_driver' => 'sqlite', 'db_database' => $sourceFile, 'table_prefix' => 'ywcopy_', 'base_url' => '', 'debug' => false]));

            $target = DatabaseCopier::connect('sqlite', '', '', $this->file, '', '');
            SchemaCreator::create($target, 'ywcopy_other_');
            $target->exec("INSERT INTO ywcopy_other_triples (resource, property, value) VALUES ('AlreadyThere', 'p', 'v')");

            $copier = new DatabaseCopier($source);
            $counts = $copier->copy($target, 'ywcopy_');

            foreach (array_keys($counts) as $table) {
                $this->assertStringStartsNotWith('ywcopy_other_', $table, 'the other wiki is not copied');
            }
            $this->assertSame([], array_filter($copier->notes(), static fn (string $note): bool => str_contains($note, 'ywcopy_other_')));
            $kept = $target->query('SELECT resource FROM ywcopy_other_triples');
            $this->assertNotFalse($kept);
            $this->assertSame('AlreadyThere', $kept->fetchColumn(), 'the wiki already on the target is left alone');
        } finally {
            unset($source, $sourcePdo);
            @unlink($sourceFile);
        }
    }

    /** SQLite keeps every wiki's triggers in one file, and a dump replays only those of its own tables. */
    public function testASqliteDumpCarriesOnlyItsOwnTriggers(): void
    {
        $sourceFile = sys_get_temp_dir() . '/yeswiki-db-triggers-' . bin2hex(random_bytes(4)) . '.sqlite';
        try {
            $pdo = DatabaseCopier::connect('sqlite', '', '', $sourceFile, '', '');
            SchemaCreator::createContentTables($pdo, 'ywcopy_');
            SchemaCreator::createContentTables($pdo, 'ywcopy_other_');
            foreach (['ywcopy_', 'ywcopy_other_'] as $prefix) {
                $pdo->exec("CREATE TRIGGER {$prefix}probe AFTER INSERT ON {$prefix}triples BEGIN SELECT 1; END");
            }
            $db = new DbService(new ParameterBag(['db_driver' => 'sqlite', 'db_database' => $sourceFile, 'table_prefix' => 'ywcopy_', 'base_url' => '', 'debug' => false]));

            $statements = implode("\n", $db->schema()->postDataStatements(DumpRewriter::ownTables($db->schema()->getTables(), 'ywcopy_')));

            $this->assertStringContainsString('ywcopy_probe', $statements);
            $this->assertStringNotContainsString('ywcopy_other_probe', $statements);
        } finally {
            unset($db, $pdo);
            @unlink($sourceFile);
        }
    }

    public function testASecondCopyOverTheFirstIsRefused(): void
    {
        $db = $this->getWiki()->services->get(DbService::class);
        $prefix = trim($db->prefixTable(''));
        $target = DatabaseCopier::connect('sqlite', '', '', $this->file, '', '');
        (new DatabaseCopier($db))->copy($target, $prefix);

        $this->expectException(\RuntimeException::class);
        (new DatabaseCopier($db))->copy($target, $prefix);
    }
}
