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
use YesWiki\Kernel\Service\DbService;
use YesWiki\Kernel\Service\Journal;
use YesWiki\Kernel\Service\JournalSchema;
use YesWiki\Kernel\Service\Queue\QueueBus;
use YesWiki\Kernel\Service\Queue\QueueHandlerLocator;
use YesWiki\Kernel\Service\Queue\QueueSchema;
use YesWiki\Kernel\Service\Queue\QueueTransport;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';
require_once 'tests/src/Kernel/QueueTestMessageHandler.php';

/** The bus the wiki dispatches on: a message always goes to the queue, and only the consumer ever runs a handler. */
#[CoversClass(QueueBus::class)]
#[CoversClass(QueueHandlerLocator::class)]
class QueueBusTest extends YesWikiTestCase
{
    private string $file;
    private DbService $db;
    private QueueSchema $schema;
    private QueueTransport $transport;
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
        $this->transport = new QueueTransport($this->db, $this->schema, clock: new MockClock('2026-10-23 12:00:00', 'UTC'));
        $this->handler = new QueueTestMessageHandler();

        $this->journal = self::getWiki()->services->get(Journal::class);
        $this->log = self::memory();
        $this->journal->writeTo($this->log);

        $locator = new QueueHandlerLocator(
            [QueueTestMessage::class => ['handler']],
            new ServiceLocator(['handler' => fn () => $this->handler])
        );
        $this->bus = new QueueBus($this->transport, $locator, $this->journal);
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
        unset($this->bus, $this->transport, $this->schema, $this->db);
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

    public function testAReceivedMessageIsHandledAndNotStoredAgain(): void
    {
        $envelope = $this->bus->dispatch(new QueueTestMessage('now'), [new ReceivedStamp('default')]);

        $this->assertSame(['now'], $this->handler->handled);
        $this->assertSame(0, $this->transport->getMessageCount());
        $this->assertCount(1, $envelope->all(HandledStamp::class));
    }

    public function testAReceivedMessageNobodyHandlesFailsLoudly(): void
    {
        $bus = new QueueBus($this->transport, new QueueHandlerLocator([], new ServiceLocator([])), $this->journal);

        $this->expectException(NoHandlerForMessageException::class);

        $bus->dispatch(new QueueTestMessage('orphan'), [new ReceivedStamp('default')]);
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
