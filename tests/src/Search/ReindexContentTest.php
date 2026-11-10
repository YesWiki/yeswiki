<?php

namespace YesWiki\Test\Search;

use PHPUnit\Framework\Attributes\CoversClass;
use Psr\Container\ContainerInterface;
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Messenger\MessageBusInterface;
use YesWiki\Content\Service\PageManager;
use YesWiki\Identity\Service\AclService;
use YesWiki\Kernel\Entity\Event;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Kernel\Service\Queue\QueueBus;
use YesWiki\Kernel\Service\Queue\QueueHandlerLocator;
use YesWiki\Kernel\Service\Queue\QueueRecorder;
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
use YesWiki\Test\Kernel\QueueTestKit;

require_once 'tests/YesWikiTestCase.php';
require_once 'tests/src/Kernel/QueueTestKit.php';

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

    /** A Content saved queues one message; the point of the unique key: saved twice before the consumer gets to it, it is reindexed once. */
    public function testSavingTwiceBeforeThePassQueuesOneMessage(): void
    {
        $subscriber = $this->getWiki()->services->get(SearchIndexSubscriber::class);

        $subscriber->onContentChanged(new Event(['id' => self::TAG_PREFIX . 'A']));
        $subscriber->onContentChanged(new Event(['id' => self::TAG_PREFIX . 'B']));
        $subscriber->onContentChanged(new Event(['id' => self::TAG_PREFIX . 'A']));

        $this->assertSame([self::TAG_PREFIX . 'A', self::TAG_PREFIX . 'B'], $this->waitingTags(), 'one message each, in the order they were first sent, keyed by the tag');
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

    public function testRunningTheMessageIndexesTheContent(): void
    {
        $services = $this->getWiki()->services;
        $services->get(PageManager::class)->save(self::TAG_PREFIX . 'Indexed', ['title' => 'Indexed', 'content' => 'a word like zygopetalumqueue'], '', true);
        $this->assertSame([self::TAG_PREFIX . 'Indexed'], $this->waitingTags(), 'the save went through the subscriber');

        $this->assertGreaterThanOrEqual(1, self::reindexWaitingContents($this->getWiki()), 'it runs every waiting message, whoever queued them');

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

    /** Saving a page must never break because the queue cannot be written to (the journaling of the failure is QueueBus's own). */
    public function testAQueueThatCannotBeWrittenToDoesNotBreakEnqueue(): void
    {
        $services = $this->getWiki()->services;
        $file = tempnam(sys_get_temp_dir(), 'yw-search-queue-') . '.db';
        $empty = new DbService(new ParameterBag(['db_driver' => 'sqlite', 'db_database' => $file, 'table_prefix' => 'ywq_', 'debug' => false]));
        $kit = new QueueTestKit();

        try {
            $transport = new QueueTransport($empty, new QueueSchema($empty), new NativeClock());
            $indexer = new SearchIndexer(
                $services->get(DbService::class),
                $services->get(SearchIndexSchema::class),
                $services->get(SearchableTextExtractor::class),
                new QueueBus($transport, new QueueHandlerLocator([], new ServiceLocator([])), $kit->journal, new QueueRecorder($empty, new QueueSchema($empty), new NativeClock()), QueueTestKit::noRequest()),
                $services->get(QueueStatistics::class)
            );

            $indexer->enqueue([self::TAG_PREFIX . 'Broken']);

            $this->assertSame([], $this->waitingTags(), 'nothing reached the real queue');
        } finally {
            $kit->dispose();
            foreach (['', '-wal', '-shm'] as $suffix) {
                @unlink($file . $suffix);
            }
        }
    }

    /** The page shows where things stand; getting the work done is the queue's job. */
    public function testTheSearchActionOnlyReportsWhatWaits(): void
    {
        $html = $this->searchPageWhileBuilding(admin: false);

        $this->assertStringContainsString(htmlspecialchars(_t('SEARCH_INDEX_BUILDING', ['pending' => 7]), ENT_QUOTES), $html);
    }

    /** Only an admin is sent to the jobs page, where the queue can be watched. */
    public function testOnlyAnAdminIsGivenTheLinkToTheJobs(): void
    {
        $this->assertStringContainsString('admin/jobs', $this->searchPageWhileBuilding(admin: true));
        $this->assertStringNotContainsString('admin/jobs', $this->searchPageWhileBuilding(admin: false));
    }

    /** A request has no business reindexing: saving queues, and nothing is reindexed (or run) before the consumer comes. */
    public function testSavingReindexesNothingByItself(): void
    {
        $indexer = $this->indexer();
        $indexed = $indexer->indexedCount();

        $this->getWiki()->services->get(SearchIndexSubscriber::class)->onContentChanged(new Event(['id' => self::TAG_PREFIX . 'Lazy']));

        $this->assertSame([self::TAG_PREFIX . 'Lazy'], $this->waitingTags(), 'the message is still waiting');
        $this->assertSame($indexed, $indexer->indexedCount(), 'and the index has not moved');
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
        $lastMessage = (int)$db->scalar('SELECT MAX(id) FROM ' . $this->messages(), 0);

        try {
            $migration = new \ReindexingGoesThroughTheQueue();
            $migration->setServices($services);
            $migration->setDbService($db);
            $migration->run();

            $this->assertNotContains($old, $db->schema()->getTables());
            $this->assertContains(self::TAG_PREFIX . 'Migrated', $this->waitingTags());
        } finally {
            // It queued the whole wiki: take back what it queued, and only that.
            $db->query('DELETE FROM ' . $this->messages() . ' WHERE message_type = ? AND delivered_at IS NULL AND id > ?', [ReindexContent::class, $lastMessage]);
        }
    }

    public function testEnqueueEverythingReturnsTheNumberOfDistinctLatestTags(): void
    {
        $services = $this->getWiki()->services;
        $pages = $services->get(PageManager::class);
        $pages->save(self::TAG_PREFIX . 'Revised', ['title' => 'One', 'content' => 'first'], '', true);
        $pages->save(self::TAG_PREFIX . 'Revised', ['title' => 'Two', 'content' => 'second'], '', true);
        $pages->save(self::TAG_PREFIX . 'Other', ['title' => 'Other', 'content' => 'x'], '', true);
        $db = $services->get(DbService::class);
        $expected = (int)$db->scalar('SELECT COUNT(*) FROM (SELECT DISTINCT tag FROM ' . $db->prefixTable('pages') . " WHERE latest = 'Y') latest_tags", 0);
        $this->assertSame(2, (int)$db->scalar('SELECT COUNT(*) FROM ' . $db->prefixTable('pages') . ' WHERE tag = ?', 0, [self::TAG_PREFIX . 'Revised']), 'fixture: two revisions, one tag');
        $lastMessage = (int)$db->scalar('SELECT MAX(id) FROM ' . $this->messages(), 0);
        $this->forgetWaitingReindexing();

        try {
            $queued = $this->indexer()->enqueueEverything();

            $this->assertSame($expected, $queued);
            $tags = $this->waitingTags();
            sort($tags);
            $this->assertSame([self::TAG_PREFIX . 'Other', self::TAG_PREFIX . 'Revised'], $tags, 'each once, whatever its revisions');
        } finally {
            $db->query('DELETE FROM ' . $this->messages() . ' WHERE message_type = ? AND delivered_at IS NULL AND id > ?', [ReindexContent::class, $lastMessage]);
        }
    }

    /** Without the index tables nothing is queued, so a wiki that never got them does not fill its queue. */
    public function testWithoutIndexTablesNothingIsQueued(): void
    {
        $services = $this->getWiki()->services;
        $schema = $this->createStub(SearchIndexSchema::class);
        $schema->method('exists')->willReturn(false);
        $indexer = new SearchIndexer(
            $services->get(DbService::class),
            $schema,
            $services->get(SearchableTextExtractor::class),
            $services->get(MessageBusInterface::class),
            $services->get(QueueStatistics::class)
        );

        $indexer->enqueue([self::TAG_PREFIX . 'Nowhere']);

        $this->assertSame(0, $indexer->enqueueEverything());
        $this->assertSame(0, $indexer->enqueueForm('1'));
        $this->assertSame([], $this->waitingTags());
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

    /** This test's waiting ReindexContent, not the ones another test or a real visitor queued. */
    private function forgetWaitingReindexing(): void
    {
        $this->getWiki()->services->get(DbService::class)->query(
            'DELETE FROM ' . $this->messages() . ' WHERE message_type = ? AND delivered_at IS NULL AND dedup_key LIKE ?',
            [ReindexContent::class, ReindexContent::UNIQUE_KEY_PREFIX . self::TAG_PREFIX . '%']
        );
    }

    /** The page of the search action while the index is being built, as an admin or a visitor sees it. */
    private function searchPageWhileBuilding(bool $admin): string
    {
        $services = $this->getWiki()->services;
        $indexer = $this->createStub(SearchIndexer::class);
        $indexer->method('indexedCount')->willReturn(0);
        $indexer->method('pending')->willReturn(7);
        $acl = $this->createStub(AclService::class);
        $acl->method('isAdmin')->willReturn($admin);
        $replaced = [SearchIndexer::class => $indexer, AclService::class => $acl];

        $container = new class($services, $replaced) implements ContainerInterface {
            /** @param array<string, object> $replaced */
            public function __construct(private readonly ContainerInterface $real, private readonly array $replaced)
            {
            }

            public function get(string $id): mixed
            {
                return $this->replaced[$id] ?? $this->real->get($id);
            }

            public function has(string $id): bool
            {
                return $this->real->has($id);
            }
        };
        $action = new SearchAction();
        $action->setServices($container);

        return $action->run();
    }
}
