<?php

namespace YesWiki\Test\Admin;

use YesWiki\Admin\Service\ArchiveService;
use YesWiki\Kernel\Database\DumpRewriter;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Kernel\Service\Queue\QueueSchema;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * An archive keeps the dump tool's dump only when it creates every table a restore replaces: the wiki's content, which leaves the queue's table out.
 *
 * Measured against every table of the wiki, the queue's included, no dump would ever pass, and
 * every archive would quietly be dumped a second time through SqlDumper.
 */
class ArchiveDumpHoldsEveryTableTest extends YesWikiTestCase
{
    private string $file;

    protected function setUp(): void
    {
        parent::setUp();
        $this->file = sys_get_temp_dir() . '/yw-archive-check-' . bin2hex(random_bytes(4)) . '.sql';
    }

    protected function tearDown(): void
    {
        @unlink($this->file);
        parent::tearDown();
    }

    /** What core:exportdb has the dump tool write: the wiki's content tables, which leave the queue's out. */
    public function testADumpOfTheContentHoldsEveryTable(): void
    {
        $db = $this->getWiki()->services->get(DbService::class);
        $this->assertTrue((new QueueSchema($db))->exists(), 'the wiki has a queue, which the dump leaves out');
        $created = '';
        foreach (DumpRewriter::contentTables($db->schema()->getTables(), trim($db->prefixTable(''))) as $table) {
            $created .= 'CREATE TABLE ' . $db->quoteIdentifier($table) . " (id INT);\n";
        }
        file_put_contents($this->file, $created);

        $this->assertTrue($this->holdsEveryTable());
    }

    public function testADumpThatLacksATableIsNotKept(): void
    {
        $db = $this->getWiki()->services->get(DbService::class);
        file_put_contents($this->file, 'CREATE TABLE ' . $db->quoteIdentifier(trim($db->prefixTable('pages'))) . " (tag VARCHAR(190));\n");

        $this->assertFalse($this->holdsEveryTable());
    }

    private function holdsEveryTable(): bool
    {
        $archives = $this->getWiki()->services->get(ArchiveService::class);

        return (bool)(new \ReflectionMethod($archives, 'dumpHoldsEveryTable'))->invoke($archives, $this->file);
    }
}
