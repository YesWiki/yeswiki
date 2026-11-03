<?php

namespace YesWiki\Test\Kernel;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Kernel\Service\JournalSchema;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';
require_once 'tests/src/Kernel/QueueTransportTest.php';

/** A Journal created before the queue channel existed gets its index added once, on every server the wiki runs on. */
class JournalQueueIndexTest extends YesWikiTestCase
{
    private const PREFIX = 'ywqidx_';

    private ?string $file = null;
    private ?DbService $db = null;

    protected function tearDown(): void
    {
        $this->db?->query('DROP TABLE IF EXISTS ' . $this->db->quoteIdentifier(self::PREFIX . 'journal'));
        $this->db = null;
        if ($this->file !== null) {
            foreach (['', '-wal', '-shm'] as $suffix) {
                @unlink($this->file . $suffix);
            }
        }
        parent::tearDown();
    }

    /**
     * @return array<string, array{array<string, string|bool>}>
     */
    public static function servers(): array
    {
        $servers = [];
        foreach (QueueTransportTest::realServers() as $name => [$params]) {
            $servers[$name] = [['table_prefix' => self::PREFIX] + $params];
        }

        return ['sqlite' => [['db_driver' => 'sqlite', 'table_prefix' => self::PREFIX, 'debug' => false]]] + $servers;
    }

    /** @param array<string, string|bool> $params */
    #[DataProvider('servers')]
    public function testTheIndexIsAddedToAnExistingJournalAndAddingItAgainChangesNothing(array $params): void
    {
        $schema = $this->openJournal($params);
        $name = $this->indexName('idx_channel_target_at');
        $this->assertTrue($this->db->schema()->indexExists('journal', $name), 'a new Journal has it');

        $this->dropIndex($name);
        $this->assertFalse($this->db->schema()->indexExists('journal', $name), 'an old Journal does not');

        $schema->ensureQueueIndex();
        $schema->ensureQueueIndex();

        $this->assertTrue($this->db->schema()->indexExists('journal', $name));
    }

    /** @param array<string, string|bool> $params */
    #[DataProvider('servers')]
    public function testTheJournalHasTheTaskIndex(array $params): void
    {
        $schema = $this->openJournal($params);
        $name = $this->indexName('idx_channel_action_target');
        $this->assertTrue($this->db->schema()->indexExists('journal', $name), 'a new Journal has it');

        $this->dropIndex($name);
        $this->assertFalse($this->db->schema()->indexExists('journal', $name), 'a Journal made before the task rows does not');

        $schema->ensureQueueIndex();
        $schema->ensureQueueIndex();

        $this->assertTrue($this->db->schema()->indexExists('journal', $name), 'it is added, and adding it again changes nothing');
    }

    /**
     * A fresh Journal on that server, skipping the test where none is reachable.
     *
     * @param array<string, string|bool> $params
     */
    private function openJournal(array $params): JournalSchema
    {
        if ($params['db_driver'] !== 'sqlite' && !QueueTransportTest::reachable($params)) {
            $this->markTestSkipped("no {$params['db_driver']} server at {$params['db_host']}");
        }
        if ($params['db_driver'] === 'sqlite') {
            $this->file = tempnam(sys_get_temp_dir(), 'yw-journal-index-') . '.db';
            $params['db_database'] = $this->file;
        }
        $this->db = new DbService(new ParameterBag($params));
        $this->db->query('DROP TABLE IF EXISTS ' . $this->db->quoteIdentifier(self::PREFIX . 'journal'));
        $schema = new JournalSchema($this->db);
        $schema->create();

        return $schema;
    }

    /** The index as the server names it: MySQL keeps it per table, the others per schema. */
    private function indexName(string $name): string
    {
        return $this->db->getDriver() === 'mysql' ? $name : self::PREFIX . 'journal_' . $name;
    }

    private function dropIndex(string $name): void
    {
        $dialect = $this->db->dialect();
        $this->db->query($this->db->getDriver() === 'mysql'
            ? "DROP INDEX {$dialect->quoteIdentifier($name)} ON {$dialect->quoteIdentifier(self::PREFIX . 'journal')}"
            : "DROP INDEX {$dialect->quoteIdentifier($name)}");
    }
}
