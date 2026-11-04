<?php

namespace YesWiki\Test\Kernel;

use PHPUnit\Framework\Attributes\CoversClass;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;
use YesWiki\Kernel\Service\DatabaseClock;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Kernel\Service\Queue\QueueBudgetFactory;
use YesWiki\Kernel\Service\Queue\QueueBus;
use YesWiki\Kernel\Service\Queue\QueueHolderLock;
use YesWiki\Kernel\Service\Queue\QueueSchema;
use YesWiki\Kernel\Service\Queue\QueueTransport;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';
require_once 'tests/src/Kernel/QueueTestMessage.php';

/** On the wiki's own container: what a service dispatches on Messenger's bus is stored for the consumer, not handled on the spot. */
#[CoversClass(QueueBus::class)]
#[CoversClass(QueueBudgetFactory::class)]
#[CoversClass(QueueTransport::class)]
class QueueKernelWiringTest extends YesWikiTestCase
{
    public function testAMessageDispatchedOnTheContainersBusIsStoredAndNotHandled(): void
    {
        $services = self::getWiki()->services;
        $db = $services->get(DbService::class);
        $table = $db->quoteIdentifier($services->get(QueueSchema::class)->table());

        $envelope = $services->get(MessageBusInterface::class)->dispatch(new QueueTestMessage('wired'));

        $id = $envelope->last(TransportMessageIdStamp::class)?->getId();
        try {
            $this->assertNotNull($id, 'the caller is told which message was stored');
            $this->assertSame([], $envelope->all(HandledStamp::class), 'nothing ran on the dispatching side');
            $row = $db->loadSingle("SELECT queue_name, message_type FROM {$table} WHERE id = ?", [$id]);
            $this->assertIsArray($row);
            $this->assertSame(QueueTransport::DEFAULT_QUEUE, $row['queue_name']);
            $this->assertSame(QueueTestMessage::class, $row['message_type']);
        } finally {
            if ($id !== null) {
                $db->query("DELETE FROM {$table} WHERE id = ?", [$id]);
            }
        }
    }

    /**
     * A pass's budget is a duration that QueueBudget counts down on microtime(): the factory must read the same clock, not the
     * database's. With the database's, a server whose clock lags by 25 s gave the end-of-request pass no budget at all.
     */
    public function testTheContainersBudgetFactoryCountsOnPhpsClockNotTheDatabases(): void
    {
        $services = self::getWiki()->services;
        $this->assertInstanceOf(DatabaseClock::class, $services->get(ClockInterface::class), 'the clock the queue writes and compares times by');

        $clock = (new \ReflectionProperty(QueueBudgetFactory::class, 'phpClock'))->getValue($services->get(QueueBudgetFactory::class));

        $this->assertNotInstanceOf(DatabaseClock::class, $clock);
    }

    /** The container's transport holds and reads the holder lock of its claims: without it a server's process at work is judged by its limit alone. */
    public function testTheContainersTransportHoldsTheLocksOfItsClaims(): void
    {
        $services = self::getWiki()->services;

        $holderLock = (new \ReflectionProperty(QueueTransport::class, 'holderLock'))->getValue($services->get(QueueTransport::class));

        $this->assertSame($services->get(QueueHolderLock::class), $holderLock);
    }
}
