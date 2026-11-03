<?php

namespace YesWiki\Test\Kernel;

use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Messenger\Exception\NoHandlerForMessageException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;
use YesWiki\Kernel\Queue\QueueJournalEntry;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Kernel\Service\Journal;
use YesWiki\Kernel\Service\JournalSchema;
use YesWiki\Kernel\Service\Queue\QueueBus;
use YesWiki\Kernel\Service\Queue\QueueHandlerLocator;
use YesWiki\Kernel\Service\Queue\QueueRecorder;
use YesWiki\Kernel\Service\Queue\QueueSchema;
use YesWiki\Kernel\Service\Queue\QueueTaskRows;
use YesWiki\Kernel\Service\Queue\QueueTransport;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';
require_once 'tests/src/Kernel/QueueTestKit.php';
require_once 'tests/src/Kernel/QueueTestMessageHandler.php';
require_once 'tests/src/Kernel/QueueTestTask.php';

/** The bus the wiki dispatches on: a message always goes to the queue, and only the consumer ever runs a handler. */
#[CoversClass(QueueBus::class)]
#[CoversClass(QueueHandlerLocator::class)]
class QueueBusTest extends YesWikiTestCase
{
    private string $file;
    private DbService $db;
    private QueueSchema $schema;
    private QueueTransport $transport;
    private QueueRecorder $recorder;
    private QueueTestMessageHandler $handler;
    private Journal $journal;
    /** @var resource */
    private $log;
    private QueueBus $bus;

    protected function setUp(): void
    {
        $this->file = tempnam(sys_get_temp_dir(), 'yw-queue-') . '.db';
        $this->db = new DbService(new ParameterBag([
            'db_driver' => 'sqlite',
            'db_database' => $this->file,
            'table_prefix' => 'ywq_',
            'debug' => false,
        ]));
        $this->schema = new QueueSchema($this->db);
        $this->schema->create();
        (new JournalSchema($this->db))->create();
        $clock = new MockClock('2026-10-23 12:00:00', 'UTC');
        $this->transport = new QueueTransport($this->db, $this->schema, clock: $clock);
        $this->recorder = new QueueRecorder($this->db, new QueueTaskRows($this->db, $this->schema, $clock), new JournalSchema($this->db), $clock);
        $this->handler = new QueueTestMessageHandler();

        $this->journal = self::getWiki()->services->get(Journal::class);
        $this->log = self::memory();
        $this->journal->writeTo($this->log);

        $locator = new QueueHandlerLocator(
            [QueueTestMessage::class => ['handler']],
            new ServiceLocator(['handler' => fn () => $this->handler])
        );
        $this->bus = new QueueBus($this->transport, $locator, $this->journal, $this->recorder, QueueTestKit::noRequest());
    }

    protected function tearDown(): void
    {
        $this->journal->writeTo(self::memory());
        $wikiDb = self::getWiki()->services->get(DbService::class);
        $wikiDb->query(
            'DELETE FROM ' . $wikiDb->quoteIdentifier(self::getWiki()->services->get(JournalSchema::class)->table())
            . ' WHERE ' . $wikiDb->quoteIdentifier('action') . ' = ?',
            ['queue.dispatch_failed']
        );
        unset($this->bus, $this->recorder, $this->transport, $this->schema, $this->db);
        foreach (['', '-wal', '-shm'] as $suffix) {
            @unlink($this->file . $suffix);
        }
    }

    public function testDispatchStoresTheMessageAndRunsNothing(): void
    {
        $envelope = $this->bus->dispatch(new QueueTestMessage('later'));

        $this->assertSame([], $this->handler->handled, 'dispatch() never runs a handler');
        $this->assertSame(1, $this->transport->getMessageCount());
        $this->assertNotNull($envelope->last(TransportMessageIdStamp::class), 'the caller is told which message was stored');
    }

    public function testDispatchWithoutTablesIsJournaledNotThrown(): void
    {
        $this->schema->drop();

        $envelope = $this->bus->dispatch(new QueueTestMessage('lost'));

        $this->assertInstanceOf(QueueTestMessage::class, $envelope->getMessage(), 'the page that dispatched carries on');
        rewind($this->log);
        $written = stream_get_contents($this->log);
        $this->assertIsString($written);
        $this->assertStringContainsString('queue.dispatch_failed', $written);
        $this->assertStringContainsString(addcslashes(QueueTestMessage::class, '\\'), $written, 'the target is the message class');
    }

    /** Inside the caller's transaction a failed send cannot be swallowed: its scope has already doomed the caller's commit, and on PostgreSQL aborted the transaction. It is thrown where it happened rather than at a commit far from it. */
    public function testDispatchInsideATransactionThatCannotQueueThrows(): void
    {
        $this->schema->drop();
        $this->db->beginTransaction();

        try {
            $this->expectExceptionMessageMatches('/ywq_queue/');
            $this->bus->dispatch(new QueueTestMessage('lost'));
        } finally {
            $this->db->rollBack();
        }
    }

    /** The Scheduler counts a task's interval from its last dispatch: each one the queue took is a Journal row, the one a waiting run absorbed too. */
    public function testAScheduledTaskSentWritesItsDispatch(): void
    {
        $this->bus->dispatch(new QueueTestTask());
        $this->bus->dispatch(new QueueTestTask());

        $this->assertSame(1, $this->transport->getMessageCount(), 'the second is absorbed by the run that waits');
        $this->assertSame(['test-task', 'test-task'], $this->dispatches());
    }

    public function testAFailedSendWritesNoDispatch(): void
    {
        $this->schema->drop();

        $this->bus->dispatch(new QueueTestTask());

        $this->assertSame([], $this->dispatches(), 'nothing was sent: the task is still due');
    }

    /** The run is queued all the same: the page that sent it carries on, and the next tick's send is absorbed by it. */
    public function testADispatchTheJournalCannotTakeIsSaidNotThrown(): void
    {
        (new JournalSchema($this->db))->drop();

        $this->bus->dispatch(new QueueTestTask());

        $this->assertSame(1, $this->transport->getMessageCount());
        rewind($this->log);
        $this->assertStringContainsString('could not journal its dispatch', (string)stream_get_contents($this->log));
    }

    public function testAPlainMessageWritesNoDispatch(): void
    {
        $this->bus->dispatch(new QueueTestMessage('plain'));

        $this->assertSame([], $this->dispatches());
    }

    public function testAReceivedMessageIsHandledAndNotStoredAgain(): void
    {
        $envelope = $this->bus->dispatch(new QueueTestMessage('now'), [new ReceivedStamp('default')]);

        $this->assertSame(['now'], $this->handler->handled);
        $this->assertSame(0, $this->transport->getMessageCount());
        $this->assertCount(1, $envelope->all(HandledStamp::class));
    }

    public function testAReceivedMessageNobodyHandlesFailsLoudly(): void
    {
        $bus = new QueueBus($this->transport, new QueueHandlerLocator([], new ServiceLocator([])), $this->journal, $this->recorder, QueueTestKit::noRequest());

        $this->expectException(NoHandlerForMessageException::class);

        $bus->dispatch(new QueueTestMessage('orphan'), [new ReceivedStamp('default')]);
    }

    /**
     * The tasks of the dispatch rows the bus wrote, in order.
     *
     * @return list<string>
     */
    private function dispatches(): array
    {
        $table = (new JournalSchema($this->db))->table();

        return array_map('strval', array_column($this->db->loadAll("SELECT target FROM {$table} WHERE action = ? ORDER BY id", [QueueJournalEntry::TASK_DISPATCHED]), 'target'));
    }

    /** @return resource */
    private static function memory()
    {
        $stream = fopen('php://memory', 'w+b');
        self::assertIsResource($stream);

        return $stream;
    }

    public function testTheKernelsBusIsTheQueueBus(): void
    {
        $services = self::getWiki()->services;

        $this->assertInstanceOf(QueueBus::class, $services->get(MessageBusInterface::class));
        $this->assertInstanceOf(QueueTransport::class, $services->get(QueueTransport::class));
    }
}
