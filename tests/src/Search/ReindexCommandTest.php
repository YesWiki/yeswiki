<?php

namespace YesWiki\Test\Search;

use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ServiceLocator;
use YesWiki\Search\Command\ReindexCommand;
use YesWiki\Search\Message\ReindexContent;
use YesWiki\Search\Service\SearchableTextExtractor;
use YesWiki\Search\Service\SearchIndexer;
use YesWiki\Search\Service\SearchIndexSchema;
use YesWiki\Test\Core\YesWikiTestCase;
use YesWiki\Test\Kernel\QueueTestKit;

require_once 'tests/YesWikiTestCase.php';
require_once 'tests/src/Kernel/QueueTestKit.php';

/**
 * `search:reindex` only queues, and creates or rebuilds the tables.
 *
 * It runs over a throw-away SQLite wiki (the queue kit's database plus a minimal `pages`
 * table), so that dropping the index tables touches nobody else's search.
 */
#[CoversClass(ReindexCommand::class)]
#[CoversClass(SearchIndexer::class)]
class ReindexCommandTest extends YesWikiTestCase
{
    private QueueTestKit $kit;
    private SearchIndexSchema $schema;
    private SearchIndexer $indexer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->getWiki(); // before the kit points the environment at its own configuration
        $this->kit = new QueueTestKit([ReindexContent::class => static fn (): null => null]);
        $db = $this->kit->db;
        $db->query('CREATE TABLE ' . $db->prefixTable('pages') . " (id INTEGER PRIMARY KEY AUTOINCREMENT, tag TEXT NOT NULL, body TEXT, latest TEXT NOT NULL DEFAULT 'N')");
        foreach ([
            // PageA has two revisions, one of them the latest; PageB and PageC are the other pages
            ['PageA', '{}', 'N'], ['PageA', '{"form_id":"7"}', 'Y'],
            ['PageB', '{"form_id":"7"}', 'Y'], ['PageC', '{"form_id":"8"}', 'Y'],
            ['PageD', '{"form_id":"7"}', 'N'],
        ] as [$tag, $body, $latest]) {
            $db->query('INSERT INTO ' . $db->prefixTable('pages') . ' (tag, body, latest) VALUES (?, ?, ?)', [$tag, $body, $latest]);
        }
        $this->schema = new SearchIndexSchema($db);
        $this->schema->create();
        $this->indexer = new SearchIndexer($db, $this->schema, $this->createStub(SearchableTextExtractor::class), $this->kit->bus, $this->kit->statistics);
    }

    protected function tearDown(): void
    {
        $this->kit->dispose();
        parent::tearDown();
    }

    public function testAllQueuesOneMessageForEachLatestPage(): void
    {
        $tester = $this->runCommand(['--all' => true]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertStringContainsString('3 Content(s) queued.', $tester->getDisplay(), 'a page with two revisions counts once, a page with no latest revision not at all');
        $this->assertSame(3, $this->indexer->pending());
    }

    public function testAFormQueuesOnlyItsEntries(): void
    {
        $tester = $this->runCommand(['--form' => '7']);

        $this->assertStringContainsString('2 entrie(s) of form 7 queued.', $tester->getDisplay());
        $this->assertSame(2, $this->indexer->pending());
        $this->assertSame([ReindexContent::UNIQUE_KEY_PREFIX . 'PageA', ReindexContent::UNIQUE_KEY_PREFIX . 'PageB'], $this->queuedKeys());
    }

    public function testRebuildEmptiesTheIndexThenQueuesEverything(): void
    {
        $db = $this->kit->db;
        $db->query(
            "INSERT INTO {$this->schema->table()} (tag, acl, acl_hash, page_read_acl, owner, content_type, form_id, title, text, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            ['Stale', '', md5(''), '*', '', 'page', '', 'Stale', 'stale words', '2026-01-01 00:00:00']
        );

        $tester = $this->runCommand(['--rebuild' => true]);

        $this->assertStringContainsString('Dropping and recreating the index tables', $tester->getDisplay());
        $this->assertTrue($this->schema->exists());
        $this->assertSame(0, (int)$db->scalar("SELECT COUNT(*) FROM {$this->schema->table()} WHERE tag = ?", 0, ['Stale']), 'the old rows are gone');
        $this->assertSame(3, $this->indexer->pending());
    }

    public function testWithoutAnOptionNothingIsQueuedAndTheWayToDrainIsSaid(): void
    {
        $this->indexer->enqueue(['Waiting']);

        $tester = $this->runCommand([]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertSame(1, $this->indexer->pending());
        $this->assertStringContainsString('1 Content(s) waiting in the background queue', $tester->getDisplay());
        $this->assertStringContainsString('messenger:consume --drain', $tester->getDisplay());
    }

    public function testStatusSaysHowMuchIsWaitingAndQueuesNothing(): void
    {
        $this->indexer->enqueue(['Waiting', 'Waiting2']);

        $tester = $this->runCommand(['--status' => true, '--all' => true]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertStringContainsString('2 Content(s) queued for reindexing.', $tester->getDisplay());
        $this->assertSame(2, $this->indexer->pending(), '--status reports and exits');
    }

    public function testStatusOfAMissingIndexFails(): void
    {
        $this->schema->drop();

        $tester = $this->runCommand(['--status' => true]);

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $this->assertFalse($this->schema->exists(), '--status creates nothing');
        $this->assertSame(0, $this->indexer->pending());
    }

    public function testAMissingIndexIsCreated(): void
    {
        $this->schema->drop();

        $tester = $this->runCommand([]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertStringContainsString('Creating the index tables', $tester->getDisplay());
        $this->assertTrue($this->schema->exists());
    }

    public function testWithoutAnIndexNothingCanBeQueued(): void
    {
        $this->schema->drop();

        $this->assertSame(0, $this->indexer->enqueueEverything());
        $this->assertSame(0, $this->indexer->enqueueForm('7'));
        $this->indexer->enqueue(['PageA']);
        $this->assertSame(0, $this->indexer->pending(), 'a wiki without an index has nothing to reindex');
    }

    public function testAnUnknownOrEmptyFormQueuesNothing(): void
    {
        $this->assertSame(0, $this->indexer->enqueueForm('NoSuchForm'));
        $this->assertSame(0, $this->indexer->enqueueForm(''));
        $this->assertSame(0, $this->indexer->pending());
    }

    /** @param array<string, mixed> $input */
    private function runCommand(array $input): CommandTester
    {
        $services = new ServiceLocator([
            SearchIndexSchema::class => fn (): SearchIndexSchema => $this->schema,
            SearchIndexer::class => fn (): SearchIndexer => $this->indexer,
        ]);
        $tester = new CommandTester(new ReindexCommand($services));
        $tester->execute($input);

        return $tester;
    }

    /** @return list<string> */
    private function queuedKeys(): array
    {
        $rows = $this->kit->db->loadAll("SELECT dedup_key FROM {$this->kit->schema->table()} WHERE delivered_at IS NULL ORDER BY dedup_key");

        return array_map(static fn (array $row): string => (string)$row['dedup_key'], $rows);
    }
}
