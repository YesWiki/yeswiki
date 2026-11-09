<?php

namespace YesWiki\Test\Kernel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\NoHandlerForMessageException;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;
use YesWiki\Kernel\Queue\QueueHistoryEntry;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Kernel\Service\Journal;
use YesWiki\Kernel\Service\JournalSchema;
use YesWiki\Kernel\Service\Queue\QueueBus;
use YesWiki\Kernel\Service\Queue\QueueHandlerLocator;
use YesWiki\Kernel\Service\Queue\QueueRecorder;
use YesWiki\Kernel\Service\Queue\QueueSchema;
use YesWiki\Kernel\Service\Queue\QueueTransport;
use YesWiki\Kernel\Service\ThrowableFormatter;

require_once 'tests/src/Kernel/QueueTestKit.php';
require_once 'tests/src/Kernel/QueueTestMessageHandler.php';
require_once 'tests/src/Kernel/QueueTestTask.php';

/** The bus the wiki dispatches on: a message always goes to the queue, and only the consumer ever runs a handler. */
#[CoversClass(QueueBus::class)]
#[CoversClass(QueueHandlerLocator::class)]
class QueueBusTest extends TestCase
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
        $clock = new MockClock(new \DateTimeImmutable('2026-10-23 12:00:00'));
        $this->transport = new QueueTransport($this->db, $this->schema, clock: $clock);
        $this->recorder = new QueueRecorder($this->db, new QueueSchema($this->db), $clock);
        $this->handler = new QueueTestMessageHandler();

        $this->journal = new Journal(new ServiceLocator([
            DbService::class => fn (): DbService => $this->db,
            JournalSchema::class => fn (): JournalSchema => new JournalSchema($this->db),
            ThrowableFormatter::class => static fn (): ThrowableFormatter => new ThrowableFormatter(),
        ]));
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
        $table = (new JournalSchema($this->db))->table();
        $this->assertSame(
            QueueTestMessage::class,
            $this->db->scalar("SELECT target FROM {$table} WHERE action = ?", null, ['queue.dispatch_failed']),
            'and the Journal keeps it for the health page'
        );
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

    /** Inside the caller's transaction a history that cannot take the task's dispatch is thrown, not swallowed: the caller's commit is doomed either way. */
    public function testADispatchTheHistoryCannotTakeInsideATransactionThrows(): void
    {
        $this->dropHistory();
        $this->db->beginTransaction();

        try {
            $this->expectExceptionMessageMatches('/ywq_queue_history/');
            $this->bus->dispatch(new QueueTestTask());
        } finally {
            $this->db->rollBack();
        }
    }

    /** The Scheduler counts a task's interval from its last dispatch: each one the queue took is a row of the history, the one a waiting run absorbed too. */
    public function testAScheduledTaskSentWritesItsDispatch(): void
    {
        $this->bus->dispatch(new QueueTestTask());
        $this->bus->dispatch(new QueueTestTask());

        $this->assertSame(1, $this->transport->getMessageCount(), 'the second is absorbed by the run that waits');
        $this->assertSame(['test-task', 'test-task'], $this->dispatches());
    }

    /** A task sent in an Envelope of its own is a dispatch all the same. */
    public function testAScheduledTaskSentInAnEnvelopeWritesItsDispatch(): void
    {
        $this->bus->dispatch(new Envelope(new QueueTestTask()));

        $this->assertSame(['test-task'], $this->dispatches());
    }

    public function testAFailedSendWritesNoDispatch(): void
    {
        $this->db->query('DROP TABLE ' . $this->db->quoteIdentifier($this->schema->table()));

        $this->bus->dispatch(new QueueTestTask());

        $this->assertSame([], $this->dispatches(), 'nothing was sent: the task is still due');
    }

    /**
     * A task's run and its dispatch row are written together or not at all: a run queued without the row the Scheduler counts from
     * would leave the task due, and once the pass took the run (which clears its dedup key) the next tick would queue it again.
     * The page that sent it carries on, the failure is said, and the task stays due for the next tick.
     */
    public function testATaskWhoseDispatchTheHistoryCannotTakeIsNotQueued(): void
    {
        $this->dropHistory();

        $envelope = $this->bus->dispatch(new QueueTestTask());

        $this->assertNull($envelope->last(TransportMessageIdStamp::class), 'the ticker is told it was not sent');
        $this->assertSame(0, $this->transport->getMessageCount(), 'no run without its dispatch row');
        rewind($this->log);
        $written = (string)stream_get_contents($this->log);
        $this->assertStringContainsString('queue.dispatch_failed', $written);
        $this->assertStringContainsString('ywq_queue_history', $written, 'what the database answered');
    }

    /** Only a scheduled task needs its dispatch row: a plain message is queued whatever the history. */
    public function testAPlainMessageIsQueuedWhenTheHistoryIsMissing(): void
    {
        $this->dropHistory();

        $envelope = $this->bus->dispatch(new QueueTestMessage('plain'));

        $this->assertNotNull($envelope->last(TransportMessageIdStamp::class));
        $this->assertSame(1, $this->transport->getMessageCount());
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
        $table = $this->schema->historyTable();

        return array_map('strval', array_column($this->db->loadAll("SELECT target FROM {$table} WHERE action = ? ORDER BY id", [QueueHistoryEntry::TASK_DISPATCHED]), 'target'));
    }

    private function dropHistory(): void
    {
        $this->db->query('DROP TABLE ' . $this->db->quoteIdentifier($this->schema->historyTable()));
    }

    /** @return resource */
    private static function memory()
    {
        $stream = fopen('php://memory', 'w+b');
        self::assertIsResource($stream);

        return $stream;
    }
}
