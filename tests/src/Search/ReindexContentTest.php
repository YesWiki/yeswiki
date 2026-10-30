<?php

namespace YesWiki\Test\Search;

use PHPUnit\Framework\Attributes\CoversClass;
use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\DependencyInjection\ServiceLocator;
use YesWiki\Content\Service\PageManager;
use YesWiki\Kernel\Entity\Event;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Kernel\Service\Journal;
use YesWiki\Kernel\Service\JournalSchema;
use YesWiki\Kernel\Service\Queue\QueueBus;
use YesWiki\Kernel\Service\Queue\QueueHandlerLocator;
use YesWiki\Kernel\Service\Queue\QueueSchema;
use YesWiki\Kernel\Service\Queue\QueueStatistics;
use YesWiki\Kernel\Service\Queue\QueueTransport;
use YesWiki\Search\Action\SearchAction;
use YesWiki\Search\Message\ReindexContent;
use YesWiki\Search\Message\ReindexContentHandler;
use YesWiki\Search\Service\SearchableTextExtractor;
use YesWiki\Search\Service\SearchIndexer;
use YesWiki\Search\Service\SearchIndexQuery;
use YesWiki\Search\Service\SearchIndexSchema;
use YesWiki\Search\Service\SearchIndexSubscriber;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** Reindexing goes through the queue: one ReindexContent per Content, and nothing else reindexes. */
#[CoversClass(ReindexContent::class)]
#[CoversClass(ReindexContentHandler::class)]
#[CoversClass(SearchIndexer::class)]
#[CoversClass(SearchIndexSubscriber::class)]
class ReindexContentTest extends YesWikiTestCase
{
    private const TAG_PREFIX = 'ReindexContentTestTag';

    protected function setUp(): void
    {
        parent::setUp();
        $services = $this->getWiki()->services;
        if (!$services->get(SearchIndexSchema::class)->exists() || !$services->get(QueueSchema::class)->exists()) {
            $this->markTestSkipped('no search index or no queue on this wiki -- run ./yeswicli migrate');
        }
        $this->forgetWaitingReindexing();
    }

    protected function tearDown(): void
    {
        $services = $this->getWiki()->services;
        if ($services->get(SearchIndexSchema::class)->exists() && $services->get(QueueSchema::class)->exists()) {
            $this->forgetWaitingReindexing();
            foreach ($services->get(DbService::class)->loadAll(
                "SELECT DISTINCT tag FROM {$services->get(DbService::class)->prefixTable('pages')} WHERE tag LIKE ?",
                [self::TAG_PREFIX . '%']
            ) as $row) {
                $services->get(PageManager::class)->deleteOrphaned((string)$row['tag']);
                $services->get(SearchIndexer::class)->delete((string)$row['tag']);
            }
        }
        parent::tearDown();
    }

    public function testSavingAContentQueuesOneMessageForIt(): void
    {
        $subscriber = $this->getWiki()->services->get(SearchIndexSubscriber::class);

        $subscriber->onContentChanged(new Event(['id' => self::TAG_PREFIX . 'A']));
        $subscriber->onContentChanged(new Event(['id' => self::TAG_PREFIX . 'B']));

        $this->assertSame([self::TAG_PREFIX . 'A', self::TAG_PREFIX . 'B'], $this->waitingTags());
    }

    /** The point of the unique key: a Content saved twice before the consumer gets to it is reindexed once. */
    public function testSavingTwiceBeforeThePassQueuesOneMessage(): void
    {
        $subscriber = $this->getWiki()->services->get(SearchIndexSubscriber::class);

        $subscriber->onContentChanged(new Event(['id' => self::TAG_PREFIX . 'Twice']));
        $subscriber->onContentChanged(new Event(['id' => self::TAG_PREFIX . 'Twice']));

        $this->assertSame([self::TAG_PREFIX . 'Twice'], $this->waitingTags());
        $this->assertSame(
            ReindexContent::UNIQUE_KEY_PREFIX . self::TAG_PREFIX . 'Twice',
            $this->getWiki()->services->get(DbService::class)->scalar(
                'SELECT dedup_key FROM ' . $this->messages() . ' WHERE message_type = ? AND delivered_at IS NULL',
                null,
                [ReindexContent::class]
            )
        );
    }

    public function testARenameQueuesTheNewTag(): void
    {
        $this->indexer()->rename(self::TAG_PREFIX . 'Old', self::TAG_PREFIX . 'New');

        $this->assertSame([self::TAG_PREFIX . 'New'], $this->waitingTags());
    }

    public function testPendingCountsTheWaitingMessages(): void
    {
        $before = $this->indexer()->pending();

        $this->indexer()->enqueue([self::TAG_PREFIX . 'One', self::TAG_PREFIX . 'Two', self::TAG_PREFIX . 'One']);

        $this->assertSame($before + 2, $this->indexer()->pending());
    }

    /** What makes the dispatches enough: the message is found by its type, on the container the wiki really runs on. */
    public function testTheHandlerIsDiscoveredOnTheRealContainer(): void
    {
        $services = $this->getWiki()->services;

        $this->assertContains(
            ReindexContent::class,
            $services->get(QueueHandlerLocator::class)->handledTypes(),
            'the compiler pass found #[AsMessageHandler] on ReindexContentHandler (delete cache/container/* after adding a service class)'
        );
        $this->assertInstanceOf(ReindexContentHandler::class, $services->get(ReindexContentHandler::class));
    }

    public function testRunningTheMessageIndexesTheContent(): void
    {
        $services = $this->getWiki()->services;
        $services->get(PageManager::class)->save(self::TAG_PREFIX . 'Indexed', ['title' => 'Indexed', 'content' => 'a word like zygopetalumqueue'], '', true);
        $this->assertSame([self::TAG_PREFIX . 'Indexed'], $this->waitingTags(), 'the save went through the subscriber');

        $this->assertSame(1, self::reindexWaitingContents($this->getWiki()));

        $this->assertSame([], $this->waitingTags());
        $found = $services->get(SearchIndexQuery::class)->search('zygopetalumqueue', null, 10);
        $this->assertSame([self::TAG_PREFIX . 'Indexed'], array_column($found['results'], 'tag'));
    }

    /** A Content deleted between the save and the pass leaves no rows: the handler reads what is there now. */
    public function testAContentGoneByThePassLeavesNoRows(): void
    {
        $this->getWiki()->services->get(ReindexContentHandler::class)(new ReindexContent(self::TAG_PREFIX . 'Gone'));

        $this->assertSame(0, (int)$this->getWiki()->services->get(DbService::class)->scalar(
            "SELECT COUNT(*) FROM {$this->getWiki()->services->get(SearchIndexSchema::class)->table()} WHERE tag = ?",
            0,
            [self::TAG_PREFIX . 'Gone']
        ));
    }

    /** Saving a page must never break because the queue cannot be written to. */
    public function testAQueueThatCannotBeWrittenToDoesNotBreakEnqueue(): void
    {
        $services = $this->getWiki()->services;
        $file = tempnam(sys_get_temp_dir(), 'yw-search-queue-') . '.db';
        $empty = new DbService(new ParameterBag(['db_driver' => 'sqlite', 'db_database' => $file, 'table_prefix' => 'ywq_', 'debug' => false]));
        $journal = $services->get(Journal::class);
        $log = fopen('php://memory', 'w+b');
        $this->assertIsResource($log);
        $journal->writeTo($log);

        try {
            $transport = new QueueTransport($empty, new QueueSchema($empty));
            $indexer = new SearchIndexer(
                $services->get(DbService::class),
                $services->get(SearchIndexSchema::class),
                $services->get(SearchableTextExtractor::class),
                new QueueBus($transport, new QueueHandlerLocator([], new ServiceLocator([])), $journal),
                $services->get(QueueStatistics::class)
            );

            $indexer->enqueue([self::TAG_PREFIX . 'Broken']);

            rewind($log);
            $this->assertStringContainsString('queue.dispatch_failed', (string)stream_get_contents($log));
        } finally {
            $journal->writeTo(fopen('php://memory', 'w+b') ?: $log);
            $services->get(DbService::class)->query(
                'DELETE FROM ' . $services->get(DbService::class)->quoteIdentifier($services->get(JournalSchema::class)->table())
                . ' WHERE ' . $services->get(DbService::class)->quoteIdentifier('action') . ' = ?',
                ['queue.dispatch_failed']
            );
            foreach (['', '-wal', '-shm'] as $suffix) {
                @unlink($file . $suffix);
            }
        }
    }

    /** The page shows where things stand; getting the work done is the queue's job. */
    public function testTheSearchActionOnlyReportsWhatWaits(): void
    {
        $services = $this->getWiki()->services;
        $indexer = $this->createMock(SearchIndexer::class);
        $indexer->method('indexedCount')->willReturn(0);
        $indexer->method('pending')->willReturn(7);
        $indexer->expects($this->never())->method('index');

        $replaced = new class($services, $indexer) implements ContainerInterface {
            public function __construct(private readonly ContainerInterface $real, private readonly SearchIndexer $indexer)
            {
            }

            public function get(string $id): mixed
            {
                return $id === SearchIndexer::class ? $this->indexer : $this->real->get($id);
            }

            public function has(string $id): bool
            {
                return $this->real->has($id);
            }
        };
        $action = new SearchAction();
        $action->setServices($replaced);

        $html = $action->run();

        $this->assertStringContainsString(htmlspecialchars(_t('SEARCH_INDEX_BUILDING', ['pending' => 7]), ENT_QUOTES), $html);
    }

    /** A request has no business reindexing: that is what the old shutdown function did, and a spawned `search:reindex --drain` after it. */
    public function testNoShutdownFunctionIsRegistered(): void
    {
        $source = (string)file_get_contents((string)(new \ReflectionClass(SearchIndexSubscriber::class))->getFileName());

        $this->assertStringNotContainsString('register_shutdown_function', $source);
        $this->assertStringNotContainsString('startConsoleAsync', $source);
    }

    public function testTheMigrationDropsTheOldTableAndQueuesEverything(): void
    {
        require_once 'src/migrations/20261023125000_ReindexingGoesThroughTheQueue.php';
        $services = $this->getWiki()->services;
        $db = $services->get(DbService::class);
        $old = trim($db->prefixTable('search_queue'));
        $db->query("CREATE TABLE IF NOT EXISTS {$old} (tag VARCHAR(191) PRIMARY KEY)");
        $services->get(PageManager::class)->save(self::TAG_PREFIX . 'Migrated', ['title' => 'Migrated', 'content' => 'x'], '', true);
        $this->forgetWaitingReindexing();

        $migration = new \ReindexingGoesThroughTheQueue();
        $migration->setServices($services);
        $migration->setDbService($db);
        $migration->run();

        $this->assertNotContains($old, $db->schema()->getTables());
        $this->assertContains(self::TAG_PREFIX . 'Migrated', $this->waitingTags());
    }

    private function indexer(): SearchIndexer
    {
        return $this->getWiki()->services->get(SearchIndexer::class);
    }

    private function messages(): string
    {
        return $this->getWiki()->services->get(QueueSchema::class)->table();
    }

    /**
     * The tags of this test's ReindexContent waiting in the queue, in the order they were sent.
     *
     * @return list<string>
     */
    private function waitingTags(): array
    {
        $rows = $this->getWiki()->services->get(DbService::class)->loadAll(
            'SELECT dedup_key FROM ' . $this->messages()
            . ' WHERE message_type = ? AND queue_name = ? AND delivered_at IS NULL AND dedup_key LIKE ? ORDER BY id',
            [ReindexContent::class, QueueTransport::DEFAULT_QUEUE, ReindexContent::UNIQUE_KEY_PREFIX . self::TAG_PREFIX . '%']
        );

        return array_map(
            static fn (array $row): string => substr((string)$row['dedup_key'], strlen(ReindexContent::UNIQUE_KEY_PREFIX)),
            $rows
        );
    }

    /** Whatever ReindexContent waits, this test's and everybody else's: the migration test queues the whole wiki. */
    private function forgetWaitingReindexing(): void
    {
        $this->getWiki()->services->get(DbService::class)->query(
            'DELETE FROM ' . $this->messages() . ' WHERE message_type = ? AND delivered_at IS NULL',
            [ReindexContent::class]
        );
    }
}
