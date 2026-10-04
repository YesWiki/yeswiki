<?php

namespace YesWiki\Test\Kernel\Database;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use YesWiki\Admin\Service\SchemaCreator;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** On the wiki's own server, a staged restore over itself or under another prefix leaves every index, key and sequence named after the live prefix. */
class StagedRestoreKeepsObjectNamesTest extends YesWikiTestCase
{
    private const SOURCE = 'ywsrn_';
    private const COPY = 'ywsrncp_';

    private DbService $source;
    private DbService $copy;

    protected function setUp(): void
    {
        parent::setUp();
        $config = $this->getWiki()->config;
        if (!\in_array($config['db_driver'] ?? 'mysql', ['mysql', 'pgsql'], true)) {
            $this->markTestSkipped('SqliteStagedRestoreTest covers SQLite');
        }
        $this->source = $this->db(self::SOURCE);
        $this->copy = $this->db(self::COPY);
        $this->dropProbeTables();
        $link = (new \ReflectionProperty(DbService::class, 'link'))->getValue($this->source);
        SchemaCreator::create($link, self::SOURCE);
        $this->insertPage($this->source, self::SOURCE, 'Mine');
    }

    protected function tearDown(): void
    {
        if (isset($this->source)) {
            $this->dropProbeTables();
            unset($this->source, $this->copy);
        }
        parent::tearDown();
    }

    private function db(string $prefix): DbService
    {
        $config = $this->getWiki()->config;
        $params = [];
        foreach (['db_driver', 'db_host', 'db_port', 'db_database', 'db_user', 'db_password', 'db_charset'] as $key) {
            if (isset($config[$key])) {
                $params[$key] = $config[$key];
            }
        }

        return new DbService(new ParameterBag($params + ['table_prefix' => $prefix, 'base_url' => '', 'debug' => false]));
    }

    private function dropProbeTables(): void
    {
        foreach ([self::SOURCE, self::COPY] as $prefix) {
            $pattern = '/^(' . $prefix . '|x*yw(staging|replaced)' . substr(sha1($prefix), 0, 6) . '_)/';
            foreach ($this->source->schema()->getTables() as $table) {
                if (preg_match($pattern, $table)) {
                    $this->source->query('DROP TABLE IF EXISTS ' . $this->source->quoteIdentifier($table));
                }
            }
        }
    }

    /**
     * The names of the indexes, constraints and sequences on the tables under a prefix.
     *
     * @return list<string>
     */
    private function objects(string $prefix): array
    {
        if ($this->source->getDriver() === 'pgsql') {
            $rows = $this->source->loadAll(
                "SELECT c.relname AS name FROM pg_class c JOIN pg_namespace n ON n.oid = c.relnamespace WHERE n.nspname = current_schema() AND c.relkind IN ('i', 'S') AND left(c.relname, ?) = ?"
                . ' UNION ALL SELECT k.conname FROM pg_constraint k JOIN pg_class t ON t.oid = k.conrelid JOIN pg_namespace n ON n.oid = t.relnamespace'
                . ' WHERE n.nspname = current_schema() AND left(t.relname, ?) = ?',
                [\strlen($prefix), $prefix, \strlen($prefix), $prefix]
            );
        } else {
            $rows = $this->source->loadAll(
                'SELECT DISTINCT CONCAT(TABLE_NAME, \'.\', INDEX_NAME) AS name FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND LEFT(TABLE_NAME, ?) = ?',
                [\strlen($prefix), $prefix]
            );
        }

        $names = array_map(static fn (array $row): string => (string)$row['name'], $rows);
        sort($names);

        return $names;
    }

    private function insertPage(DbService $db, string $prefix, string $tag): void
    {
        $columns = implode(', ', array_map([$db, 'quoteIdentifier'], ['tag', 'time', 'body', 'owner', 'user', 'latest']));
        $db->query('INSERT INTO ' . $db->quoteIdentifier($prefix . 'pages') . " ($columns) VALUES (?, '2026-01-02 00:00:00', '{}', '', '', 'Y')", [$tag]);
    }

    public function testAWikiRestoredOverItselfTwiceKeepsItsNames(): void
    {
        $dump = $this->source->dumper()->dump();
        $this->assertSame('', $dump['error']);
        $before = $this->objects(self::SOURCE);
        $this->assertNotEmpty($before);

        $this->source->restoreStagedFromDump($dump['sql']);
        $this->source->restoreStagedFromDump($dump['sql']);

        $this->assertSame($before, $this->objects(self::SOURCE));
        $this->insertPage($this->source, self::SOURCE, 'AfterTheRestore');
        $this->assertSame(2, (int)$this->source->scalar('SELECT COUNT(*) FROM ' . $this->source->quoteIdentifier(self::SOURCE . 'pages')));
    }

    public function testAWikiRestoredUnderAnotherPrefixNamesEverythingAfterIt(): void
    {
        $dump = $this->source->dumper()->dump();
        $before = $this->objects(self::SOURCE);

        $this->copy->restoreStagedFromDump($dump['sql']);
        $this->copy->restoreStagedFromDump($dump['sql']);

        $this->assertSame($before, $this->objects(self::SOURCE), 'the source keeps its own');
        $renamed = array_map(static fn (string $name): string => str_replace(self::SOURCE, self::COPY, $name), $before);
        sort($renamed);
        $this->assertSame($renamed, $this->objects(self::COPY));
        $this->insertPage($this->copy, self::COPY, 'InTheCopy');
        $this->assertSame(1, (int)$this->source->scalar('SELECT COUNT(*) FROM ' . $this->source->quoteIdentifier(self::SOURCE . 'pages')));
    }
}
