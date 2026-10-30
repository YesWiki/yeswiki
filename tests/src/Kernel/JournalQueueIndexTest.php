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

        $name = $this->db->getDriver() === 'mysql' ? 'idx_channel_target_at' : self::PREFIX . 'journal_idx_channel_target_at';
        $this->assertTrue($this->db->schema()->indexExists('journal', $name), 'a new Journal has it');

        $dialect = $this->db->dialect();
        $quotedTable = $dialect->quoteIdentifier(self::PREFIX . 'journal');
        $this->db->query($this->db->getDriver() === 'mysql'
            ? "DROP INDEX {$dialect->quoteIdentifier($name)} ON {$quotedTable}"
            : "DROP INDEX {$dialect->quoteIdentifier($name)}");
        $this->assertFalse($this->db->schema()->indexExists('journal', $name), 'an old Journal does not');

        $schema->ensureQueueIndex();
        $schema->ensureQueueIndex();

        $this->assertTrue($this->db->schema()->indexExists('journal', $name));
    }
}
