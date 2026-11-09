<?php

namespace YesWiki\Test\Kernel;

use PHPUnit\Framework\Attributes\CoversClass;
use Psr\Clock\ClockInterface;
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Kernel\Service\Queue\QueueBus;
use YesWiki\Kernel\Service\Queue\QueueHolderLock;
use YesWiki\Kernel\Service\Queue\QueuePassLimitsFactory;
use YesWiki\Kernel\Service\Queue\QueueSchema;
use YesWiki\Kernel\Service\Queue\QueueTransport;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';
require_once 'tests/src/Kernel/QueueTestMessage.php';

/** On the wiki's own container: what a service dispatches on Messenger's bus is stored for the consumer, not handled on the spot. */
#[CoversClass(QueueBus::class)]
#[CoversClass(QueuePassLimitsFactory::class)]
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

    /** The queue goes by PHP's own clock, in the wiki's timezone, and the pass limits factory counts on that same clock: the database is never asked what time it is. */
    public function testTheContainersClockIsPhpsOwnAndTheLimitsFactoryCountsOnIt(): void
    {
        $services = self::getWiki()->services;
        $clock = $services->get(ClockInterface::class);
        $this->assertInstanceOf(NativeClock::class, $clock, 'the clock the queue writes and compares times by');

        $this->assertSame($clock, (new \ReflectionProperty(QueuePassLimitsFactory::class, 'clock'))->getValue($services->get(QueuePassLimitsFactory::class)));
    }

    /** The container's transport holds and reads the holder lock of its claims: without it a server's process at work is judged by its limit alone. */
    public function testTheContainersTransportHoldsTheLocksOfItsClaims(): void
    {
        $services = self::getWiki()->services;

        $holderLock = (new \ReflectionProperty(QueueTransport::class, 'holderLock'))->getValue($services->get(QueueTransport::class));

        $this->assertSame($services->get(QueueHolderLock::class), $holderLock);
    }
}
