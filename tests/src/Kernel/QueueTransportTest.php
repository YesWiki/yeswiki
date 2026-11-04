<?php

namespace YesWiki\Test\Kernel;

use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\LogicException;
use Symfony\Component\Messenger\Exception\MessageDecodingFailedException;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Messenger\Stamp\ErrorDetailsStamp;
use Symfony\Component\Messenger\Stamp\RedeliveryStamp;
use Symfony\Component\Messenger\Stamp\SentToFailureTransportStamp;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;
use YesWiki\Kernel\Queue\QueueClaimHolder;
use YesWiki\Kernel\Queue\QueueClaimStamp;
use YesWiki\Kernel\Queue\QueueConsumerMode;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Kernel\Service\Queue\QueueHolderLock;
use YesWiki\Kernel\Service\Queue\QueueSchema;
use YesWiki\Kernel\Service\Queue\QueueTransport;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';
require_once 'tests/src/Kernel/QueueTestMessage.php';
require_once 'tests/src/Kernel/QueueTestUniqueMessage.php';
require_once 'tests/src/Kernel/QueueTestTask.php';
require_once 'tests/src/Kernel/QueueTestHolders.php';
require_once 'tests/src/Kernel/QueueTestRacingDb.php';
require_once 'tests/src/Kernel/QueueTestKit.php';

/** The Messenger transport that keeps the queue in the wiki's own database, and its claim that two consumers never share. */
#[CoversClass(QueueTransport::class)]
#[CoversClass(QueueClaimHolder::class)]
class QueueTransportTest extends YesWikiTestCase
{
    private const START = '2026-10-23 12:00:00';

    private string $file;
    private MockClock $clock;
    private DbService $db;
    private QueueSchema $schema;
    private QueueTransport $transport;
    /** The instance directory of the holder locks of $locked. */
    private string $dir;
    private QueueHolderLock $holderLock;
    /** A transport that holds a lock for each of its claims and judges holders by theirs. */
    private QueueTransport $locked;

    protected function setUp(): void
    {
        $this->file = tempnam(sys_get_temp_dir(), 'yw-queue-') . '.db';
        $this->clock = new MockClock(self::START, 'UTC');
        $this->db = $this->open();
        $this->schema = new QueueSchema($this->db);
        $this->schema->create();
        $this->transport = new QueueTransport($this->db, $this->schema, clock: $this->clock);
        $this->dir = sys_get_temp_dir() . '/yw-holders-' . bin2hex(random_bytes(4));
        $this->holderLock = new QueueHolderLock(QueueTestKit::locksIn($this->dir));
        $this->locked = new QueueTransport($this->db, $this->schema, clock: $this->clock, holderLock: $this->holderLock);
    }

    protected function tearDown(): void
    {
        unset($this->transport, $this->locked, $this->holderLock, $this->schema, $this->db);
        foreach (['', '-wal', '-shm'] as $suffix) {
            @unlink($this->file . $suffix);
        }
        foreach (glob($this->dir . '/' . QueueHolderLock::DIRECTORY . '/*') ?: [] as $holder) {
            @unlink($holder);
        }
        @rmdir($this->dir . '/' . QueueHolderLock::DIRECTORY);
        @rmdir($this->dir . '/cache');
        @rmdir($this->dir);
    }

    public function testSendThenGetReturnsTheSameMessageOnce(): void
    {
        $sent = $this->transport->send(new Envelope(new QueueTestMessage('hello')));

        $received = [...$this->transport->get()];

        $this->assertCount(1, $received);
        $this->assertSame(self::idOf($sent), self::idOf($received[0]));
        $message = $received[0]->getMessage();
        $this->assertInstanceOf(QueueTestMessage::class, $message);
        $this->assertSame('hello', $message->value);
        $this->assertSame([], [...$this->transport->get()], 'a delivered message is not handed out a second time');
    }

    public function testAckRemovesAndRejectRemoves(): void
    {
        $this->transport->send(new Envelope(new QueueTestMessage('acked')));
        $this->transport->send(new Envelope(new QueueTestMessage('rejected')));

        [$acked] = [...$this->transport->get()];
        $this->transport->ack($acked);
        [$rejected] = [...$this->transport->get()];
        $this->transport->reject($rejected);

        $this->assertNull($this->row(self::idOf($acked)));
        $this->assertNull($this->row(self::idOf($rejected)));
        $this->assertSame(0, $this->transport->getMessageCount());
    }

    public function testDelayedMessageIsNotAvailableBeforeItsTime(): void
    {
        $this->transport->send(new Envelope(new QueueTestMessage('later'), [new DelayStamp(60000)]));

        $this->assertSame([], [...$this->transport->get()]);
        $this->assertSame(1, $this->transport->getMessageCount(), 'a delayed message is still waiting');

        $this->clock->sleep(61);

        $this->assertCount(1, [...$this->transport->get()]);
    }

    public function testADeduplicatedMessageDoesNotDuplicateAPendingOne(): void
    {
        $first = $this->transport->send(new Envelope(new QueueTestUniqueMessage('one', 'search:drain')));
        $second = $this->transport->send(new Envelope(new QueueTestUniqueMessage('two', 'search:drain')));

        $this->assertSame(1, $this->transport->getMessageCount());
        $this->assertSame(self::idOf($first), self::idOf($second), 'the caller is told which message will do the work');
    }

    public function testADeduplicatedMessageAllowsANewOneOnceTheFirstIsTaken(): void
    {
        $this->transport->send(new Envelope(new QueueTestUniqueMessage('one', 'search:drain')));
        [$taken] = [...$this->transport->get()];

        $again = $this->transport->send(new Envelope(new QueueTestUniqueMessage('two', 'search:drain')));

        $this->assertNotSame(self::idOf($taken), self::idOf($again));
        $this->assertSame(1, $this->transport->getMessageCount());
        $this->assertSame(1, (int)$this->db->scalar(
            "SELECT COUNT(*) FROM {$this->schema->table()} WHERE delivered_at IS NOT NULL"
        ));
    }

    /** R7: Messenger re-sends the envelope of a deduplicated message; absorbing the retry would drop its count, and a handler that always fails would never reach `failed`. */
    public function testARetryIsNeverAbsorbedByAWaitingMessage(): void
    {
        $this->transport->send(new Envelope(new QueueTestUniqueMessage('first', 'search:drain')));
        [$taken] = [...$this->transport->get()];
        $fresh = $this->transport->send(new Envelope(new QueueTestUniqueMessage('fresh', 'search:drain')));

        $retry = $this->transport->send($taken->with(new RedeliveryStamp(1), new DelayStamp(60000)));

        $this->assertNotSame(self::idOf($fresh), self::idOf($retry));
        $this->assertSame(2, $this->transport->getMessageCount());
        $this->assertSame(1, (int)($this->row(self::idOf($retry))['attempts'] ?? 0));
        $this->assertSame(0, (int)($this->row(self::idOf($fresh))['attempts'] ?? -1));
    }

    /** R7: a retry may wait up to 30 min; a fresh trigger must not wait behind it. */
    public function testAFreshTriggerIsNotAbsorbedIntoADelayedRetry(): void
    {
        $this->transport->send(new Envelope(new QueueTestUniqueMessage('first', 'search:drain')));
        [$taken] = [...$this->transport->get()];
        $retry = $this->transport->send($taken->with(new RedeliveryStamp(1), new DelayStamp(1800000)));

        $fresh = $this->transport->send(new Envelope(new QueueTestUniqueMessage('fresh', 'search:drain')));

        $this->assertNotSame(self::idOf($retry), self::idOf($fresh));
        $received = [...$this->transport->get()];
        $this->assertCount(1, $received, 'the fresh one is available at once');
        $this->assertSame(self::idOf($fresh), self::idOf($received[0]));
    }

    /** R7: each definitive failure keeps its own row in `failed`, with its cause where the admin page reads it. */
    public function testEveryDefinitiveFailureHasItsOwnRowAndCauseInFailed(): void
    {
        $failed = $this->transport->withQueue('failed');
        $fail = static fn (string $why): Envelope => new Envelope(new QueueTestUniqueMessage('broken', 'task:digest'), [
            new SentToFailureTransportStamp('default'),
            new RedeliveryStamp(0),
            ErrorDetailsStamp::create(new \RuntimeException($why)),
        ]);

        $first = $failed->send($fail('smtp refused'));
        $second = $failed->send($fail('smtp timed out'));

        $this->assertNotSame(self::idOf($first), self::idOf($second));
        $this->assertSame(2, $failed->getMessageCount());
        $this->assertSame('RuntimeException: smtp refused', $this->row(self::idOf($first))['last_error'] ?? null);
        $this->assertSame('RuntimeException: smtp timed out', $this->row(self::idOf($second))['last_error'] ?? null);
    }

    public function testAMessageSentToFailedWithoutDetailsHasNoCause(): void
    {
        $sent = $this->transport->withQueue('failed')->send(new Envelope(new QueueTestMessage('quiet')));

        $this->assertNull($this->row(self::idOf($sent))['last_error'] ?? null);
    }

    /** The key only stays on a row while it is a first send waiting: what the unique index holds the queue to. */
    public function testAMessageThatStopsWaitingLetsGoOfItsKey(): void
    {
        $sent = $this->transport->send(new Envelope(new QueueTestUniqueMessage('first', 'search:drain')));
        $this->assertSame('search:drain', $this->row(self::idOf($sent))['dedup_key'] ?? null);

        [$taken] = [...$this->transport->get()];
        $this->assertNull(($this->row(self::idOf($taken)) ?? ['dedup_key' => 'no row'])['dedup_key'], 'a claimed message no longer waits');

        $retry = $this->transport->send($taken->with(new RedeliveryStamp(1)));
        $this->assertNull(($this->row(self::idOf($retry)) ?? ['dedup_key' => 'no row'])['dedup_key'], 'a retry never absorbs a fresh trigger');

        $failed = $this->transport->withQueue('failed')->send(new Envelope(new QueueTestUniqueMessage('broken', 'search:drain'), [
            new SentToFailureTransportStamp('default'),
        ]));
        $this->assertNull(($this->row(self::idOf($failed)) ?? ['dedup_key' => 'no row'])['dedup_key'], 'a failure is not waiting to run');

        $waiting = $this->transport->send(new Envelope(new QueueTestUniqueMessage('waits', 'search:drain')));
        $this->transport->moveToFailed(self::idOf($waiting), 'gave up');
        $this->assertNull(($this->row(self::idOf($waiting)) ?? ['dedup_key' => 'no row'])['dedup_key'], 'moved to failed, it waits no more');
    }

    /** Sent back by hand, a message waits again under its key: a fresh trigger joins it. */
    public function testARequeuedMessageWaitsUnderItsKeyAgain(): void
    {
        $sent = $this->transport->send(new Envelope(new QueueTestUniqueMessage('first', 'search:drain')));
        $this->transport->moveToFailed(self::idOf($sent), 'boom');

        $this->assertTrue($this->transport->requeue(self::idOf($sent)));
        $again = $this->transport->send(new Envelope(new QueueTestUniqueMessage('again', 'search:drain')));

        $this->assertSame(self::idOf($sent), self::idOf($again));
        $this->assertSame(1, $this->transport->getMessageCount());
    }

    /** Sent back by hand while the same work already waits, a failure is absorbed by it: it leaves `failed`, and nothing is added to the queue. */
    public function testARequeueIsAbsorbedByTheMessageWaitingUnderItsKey(): void
    {
        $sent = $this->transport->send(new Envelope(new QueueTestUniqueMessage('first', 'search:drain')));
        $this->transport->moveToFailed(self::idOf($sent), 'boom');
        $waiting = $this->transport->send(new Envelope(new QueueTestUniqueMessage('fresh', 'search:drain')));

        $this->assertTrue($this->transport->requeue(self::idOf($sent)), 'the work it stood for will be done');

        $this->assertNull($this->row(self::idOf($sent)));
        $this->assertSame(0, $this->transport->withQueue('failed')->getMessageCount());
        $this->assertSame(1, $this->transport->getMessageCount());
        [$received] = [...$this->transport->get()];
        $this->assertSame(self::idOf($waiting), self::idOf($received));
    }

    public function testTwoTransportsNeverClaimTheSameMessage(): void
    {
        $theirs = $this->open();
        $other = new QueueTransport($theirs, new QueueSchema($theirs), clock: $this->clock);
        for ($i = 0; $i < 20; $i++) {
            $this->transport->send(new Envelope(new QueueTestMessage("m{$i}")));
        }

        $ids = array_map(self::idOf(...), self::claimAlternately($this->transport, $other));

        $this->assertCount(20, $ids);
        $this->assertCount(20, array_unique($ids));
    }

    public function testClaimRecordsModeAndLimit(): void
    {
        $this->transport->claimAs(QueueConsumerMode::Request, 25);
        $this->transport->send(new Envelope(new QueueTestMessage('timed')));

        [$taken] = [...$this->transport->get()];

        $row = $this->row(self::idOf($taken));
        $this->assertNotNull($row);
        $this->assertSame('request', $row['delivered_mode']);
        $this->assertSame(25, (int)$row['delivered_limit']);
        $this->assertSame(self::START, $row['delivered_at']);
        $this->assertNotEmpty($row['delivered_by']);
    }

    /** R19: the claim's token names the process that took it, so that another process on this machine can tell whether it is still there. */
    public function testAClaimNamesTheProcessThatTookIt(): void
    {
        $this->transport->send(new Envelope(new QueueTestMessage('named')));

        [$taken] = [...$this->transport->get()];

        $token = (string)($this->row(self::idOf($taken))['delivered_by'] ?? '');
        $this->assertLessThanOrEqual(64, strlen($token), 'the column holds 64 characters');
        $holder = QueueClaimHolder::ofToken($token);
        $this->assertNotNull($holder);
        $this->assertTrue($holder->isSameProcessAs(QueueClaimHolder::thisProcess()));
        $this->assertSame(getmypid(), $holder->pid);
        $this->assertTrue($holder->ownProcess, 'the CLI runs nothing but its consumer');
        $this->assertNull(QueueClaimHolder::ofToken('1234:0123456789abcdef'), 'a token written before tokens named their holder names none');
    }

    /** Worker mode: the transport outlives the request whose pass said who was consuming. */
    public function testANewRequestForgetsWhoWasClaiming(): void
    {
        $this->transport->claimAs(QueueConsumerMode::Request, 25);
        $this->transport->startNewRequest();
        $this->transport->send(new Envelope(new QueueTestMessage('untimed')));

        [$taken] = [...$this->transport->get()];

        $row = $this->row(self::idOf($taken));
        $this->assertNull($row['delivered_mode'] ?? null);
        $this->assertNull($row['delivered_limit'] ?? null);
    }

    /** R19: from another machine nothing can be told of the holder's process; the limit it recorded and the grace decide, as they always did. */
    public function testAClaimFromAnotherMachineIsReapedOncePastItsLimit(): void
    {
        $this->transport->claimAs(QueueConsumerMode::Request, 1);
        $this->transport->send(new Envelope(new QueueTestMessage('dies')));
        [$taken] = [...$this->transport->get()];
        QueueTestHolders::hand($this->db, $this->schema, self::idOf($taken), QueueTestHolders::elsewhere());

        $this->clock->sleep(61);
        $this->assertSame([], $this->transport->reapKilled(), 'within its limit and the grace, it may still be running');

        $this->clock->sleep(1);
        $reaped = $this->transport->reapKilled();

        $this->assertSame([[
            'id' => self::idOf($taken),
            'type' => QueueTestMessage::class,
            'mode' => 'request',
            'deliveredAt' => self::START,
        ]], $reaped);
        $this->assertSame([], $this->transport->reapKilled(), 'reaped once');
        $failed = [...$this->transport->withQueue('failed')->get()];
        $this->assertCount(1, $failed);
        $this->assertSame(self::idOf($taken), self::idOf($failed[0]));
        $this->assertSame('request', $this->row(self::idOf($taken))['killed_in'] ?? null);
    }

    /** R19: a holder whose process is gone from this machine (the host killed it, a restart) will never ack: its message is failed at once, not hours later. */
    public function testAClaimWhoseProcessIsGoneIsReapedAtOnce(): void
    {
        $this->transport->claimAs(QueueConsumerMode::Worker, 21600);
        $this->transport->send(new Envelope(new QueueTestMessage('dies')));
        [$taken] = [...$this->transport->get()];
        QueueTestHolders::hand($this->db, $this->schema, self::idOf($taken), QueueTestHolders::gone());

        $this->assertFalse($this->transport->isHandling());
        $this->assertSame([self::idOf($taken)], array_column($this->transport->reapKilled(), 'id'));
        $this->assertSame('worker', $this->row(self::idOf($taken))['killed_in'] ?? null);
    }

    /** The kill is the row's `killed_in`, for the alerts and the jobs page; its cause is the sentence an admin reads, and a retry forgets both. */
    public function testAKilledMessageCarriesItsModeUntilItIsRetried(): void
    {
        $this->transport->claimAs(QueueConsumerMode::Request, 30);
        $this->transport->send(new Envelope(new QueueTestMessage('dies')));
        [$taken] = [...$this->transport->get()];
        $this->transport->failHeldClaims();

        $row = $this->row(self::idOf($taken));
        $this->assertSame('request', $row['killed_in'] ?? null);
        $this->assertSame(QueueTransport::killedCause('request'), $row['last_error'] ?? null);

        $this->transport->withQueue('failed')->requeue(self::idOf($taken));

        $row = $this->row(self::idOf($taken));
        $this->assertSame('default', $row['queue_name'] ?? null);
        $this->assertNull($row['killed_in'] ?? null, 'retried, it is a message like any other');
        $this->assertNull($row['last_error'] ?? null);
    }

    /** The retention of `failed` counts from the failure: a message that waited, or was retried, long before it was killed today is still there for the admin to see. */
    public function testAMessageKilledTodayIsKeptHoweverLongAgoItWasSent(): void
    {
        $sent = $this->transport->send(new Envelope(new QueueTestMessage('old')));
        $this->clock->sleep(40 * 86400);
        $this->transport->claimAs(QueueConsumerMode::Request, 30);
        [$taken] = [...$this->transport->get()];
        $this->transport->failHeldClaims();

        $this->assertSame(0, $this->transport->pruneFailed(30));
        $this->assertSame('failed', $this->row(self::idOf($sent))['queue_name'] ?? null);
        $this->assertSame(self::idOf($sent), self::idOf($taken));
    }

    /** The same for one found undecodable today, which take() moves to `failed` with an update rather than a new row. */
    public function testAMessageMovedToFailedTodayIsKeptHoweverLongAgoItWasSent(): void
    {
        $id = self::idOf($this->transport->send(new Envelope(new QueueTestMessage('old'))));
        $this->clock->sleep(40 * 86400);
        $this->transport->moveToFailed($id, 'cannot be decoded');

        $this->assertSame(0, $this->transport->pruneFailed(30));
        $this->assertSame('failed', $this->row($id)['queue_name'] ?? null);
    }

    /** R19: a process of its own still at work is slow, not dead: PHP's limit counts CPU time, and a handler that waits on the network outlives it. Only the ceiling ends it. */
    public function testAProcessOfItsOwnStillAtWorkKeepsItsMessagePastItsLimit(): void
    {
        $this->transport->claimAs(QueueConsumerMode::Api, 30);
        $this->transport->send(new Envelope(new QueueTestMessage('slow')));
        [$taken] = [...$this->transport->get()];
        QueueTestHolders::hand($this->db, $this->schema, self::idOf($taken), QueueTestHolders::living(true));

        $this->clock->sleep(3000);
        $this->assertSame([], $this->transport->reapKilled());
        $this->assertSame([], $this->transport->expiredClaims());
        $this->assertTrue($this->transport->isHandling());

        $this->clock->sleep(600 + 61);
        $this->assertSame([self::idOf($taken)], array_column($this->transport->reapKilled(), 'id'), 'past the ceiling it is stuck, not slow');
    }

    /** R19: a claim that recorded no limit (made before limits were recorded, or by a caller that never set one) is judged on the ceiling, one hour, and the grace. */
    public function testAClaimWithoutARecordedLimitIsReapedPastOneHour(): void
    {
        $this->transport->send(new Envelope(new QueueTestMessage('untimed')));
        [$taken] = [...$this->transport->get()];
        QueueTestHolders::hand($this->db, $this->schema, self::idOf($taken), QueueTestHolders::elsewhere());
        $this->assertNull($this->row(self::idOf($taken))['delivered_limit'] ?? null);

        $this->clock->sleep(3600 + 60);
        $this->assertSame([], $this->transport->reapKilled(), 'within the ceiling and the grace');

        $this->clock->sleep(1);
        $this->assertSame([self::idOf($taken)], array_column($this->transport->reapKilled(), 'id'));
    }

    /** The same holds for this very process while it holds the claim: a budgeted pass that outlives its own limit is not reaped under it. */
    public function testThisProcessKeepsWhatItHoldsPastItsLimit(): void
    {
        $this->transport->claimAs(QueueConsumerMode::Request, 1);
        $this->transport->send(new Envelope(new QueueTestMessage('slow')));
        [...$this->transport->get()];

        $this->clock->sleep(3600);

        $this->assertSame([], $this->transport->reapKilled());
    }

    /** R19: a server's process (FPM, Apache, FrankenPHP) outlives the request that claimed: that it is there proves nothing, and the time rule decides. */
    public function testAServersProcessStillThereIsJudgedByItsLimit(): void
    {
        $this->transport->claimAs(QueueConsumerMode::Request, 1);
        $this->transport->send(new Envelope(new QueueTestMessage('dies')));
        [$taken] = [...$this->transport->get()];
        QueueTestHolders::hand($this->db, $this->schema, self::idOf($taken), QueueTestHolders::living(false));

        $this->clock->sleep(61);
        $this->assertSame([], $this->transport->reapKilled());

        $this->clock->sleep(1);
        $this->assertSame([self::idOf($taken)], array_column($this->transport->reapKilled(), 'id'));
    }

    /** A claim's lock is held, as another process sees it, from the moment the claim is made until its ack, which removes the file. */
    public function testAClaimHoldsItsLockUntilAcked(): void
    {
        $this->locked->send(new Envelope(new QueueTestMessage('held')));
        [$taken] = [...$this->locked->get()];
        $token = self::tokenOf($taken);

        $this->assertSame(QueueHolderLock::HELD, self::seenFromAnotherProcess($this->holderFile($token)));
        $this->assertSame(QueueHolderLock::HELD, $this->holderLock->stateOf($token));

        $this->locked->ack($taken);

        $this->assertFileDoesNotExist($this->holderFile($token));
    }

    /** What the consumer fails as its process ends lets go of its lock too, and leaves no file behind. */
    public function testAClaimFailedAsItsProcessEndsLetsGoOfItsLock(): void
    {
        $this->locked->claimAs(QueueConsumerMode::Request, 30);
        $this->locked->send(new Envelope(new QueueTestMessage('held')));
        [$taken] = [...$this->locked->get()];
        $token = self::tokenOf($taken);
        $this->assertSame(QueueHolderLock::HELD, self::seenFromAnotherProcess($this->holderFile($token)));

        $this->assertSame([self::idOf($taken)], array_column($this->locked->failHeldClaims(), 'id'));

        $this->assertFileDoesNotExist($this->holderFile($token));
    }

    /** A claim that finds nothing to take, the queue being empty or the message gone, releases the lock it took first. */
    public function testAClaimThatTakesNothingLeavesNoLockBehind(): void
    {
        $this->assertSame([], [...$this->locked->get()]);
        $this->assertNull($this->locked->getById(9999), 'a claim of a message that is not there');

        $this->assertSame([], glob($this->dir . '/' . QueueHolderLock::DIRECTORY . '/*'));
    }

    /** A claim whose transaction throws lets go of the lock it took first: no file stays behind for a claim that was never written. */
    public function testAClaimWhoseTransactionThrowsLeavesNoLockBehind(): void
    {
        $this->onRacing(['db_driver' => 'sqlite'], function (QueueTestRacingDb $db, QueueSchema $schema): void {
            $transport = new QueueTransport($db, $schema, clock: $this->clock, holderLock: $this->holderLock);
            $transport->send(new Envelope(new QueueTestMessage('unlucky')));
            $db->failNextTransaction();

            try {
                [...$transport->get()];
                $this->fail('the claim should have thrown');
            } catch (\RuntimeException $thrown) {
                $this->assertSame('the database went away', $thrown->getMessage());
            }

            $this->assertSame([], glob($this->dir . '/' . QueueHolderLock::DIRECTORY . '/*'));
            [$taken] = [...$transport->get()];
            $this->assertSame('unlucky', $taken->getMessage() instanceof QueueTestMessage ? $taken->getMessage()->value : null, 'the message waits for the next claim');
            $transport->ack($taken);
        });
    }

    /** A server's process (FPM) still at work past its limit holds its lock: no judge reaps it nor calls it dead; only the ceiling ends it. */
    public function testAServersProcessThatHoldsItsLockKeepsItsMessagePastItsLimit(): void
    {
        $this->locked->claimAs(QueueConsumerMode::Request, 1);
        $this->locked->send(new Envelope(new QueueTestMessage('slow')));
        [$taken] = [...$this->locked->get()];
        $token = QueueTestHolders::hand($this->db, $this->schema, self::idOf($taken), QueueTestHolders::living(false));
        $release = QueueTestHolders::lockHeldByAnotherProcess($this->holderFile($token));
        try {
            $this->clock->sleep(120);
            $this->assertSame([], $this->locked->reapKilled());
            $this->assertSame([], $this->locked->expiredClaims());
            $this->assertTrue($this->locked->isHandling());

            $this->clock->sleep(3600);
            $this->assertSame([self::idOf($taken)], array_column($this->locked->reapKilled(), 'id'), 'past the ceiling it is stuck, not slow');
        } finally {
            $this->assertSame(0, $release());
        }
    }

    /** A server's process that let go of its lock without its ack has ended its request: its message is failed at once, and the file removed. */
    public function testAServersProcessThatLetGoOfItsLockIsGoneAtOnce(): void
    {
        $this->locked->claimAs(QueueConsumerMode::Request, 1);
        $this->locked->send(new Envelope(new QueueTestMessage('dies')));
        [$taken] = [...$this->locked->get()];
        $token = QueueTestHolders::hand($this->db, $this->schema, self::idOf($taken), QueueTestHolders::living(false));
        $this->assertSame(0, QueueTestHolders::lockHeldByAnotherProcess($this->holderFile($token))());

        $this->assertSame([self::idOf($taken)], array_column($this->locked->reapKilled(), 'id'));

        $this->assertSame('request', $this->row(self::idOf($taken))['killed_in'] ?? null);
        $this->assertFileDoesNotExist($this->holderFile($token));
    }

    /** The judges that only read (statistics, health checks, the end of request) leave the file of a holder that is gone; the reaper removes it. */
    public function testOnlyTheReaperForgetsALockLeftBehind(): void
    {
        $this->locked->claimAs(QueueConsumerMode::Request, 1);
        $this->locked->send(new Envelope(new QueueTestMessage('dies')));
        [$taken] = [...$this->locked->get()];
        $token = QueueTestHolders::hand($this->db, $this->schema, self::idOf($taken), QueueTestHolders::living(false));
        $this->assertSame(0, QueueTestHolders::lockHeldByAnotherProcess($this->holderFile($token))());

        $this->assertSame([self::idOf($taken)], array_column($this->locked->expiredClaims(), 'id'));
        $this->assertFalse($this->locked->isHandling());
        $this->assertSame([], $this->locked->liveHolders());
        $this->assertFileExists($this->holderFile($token));

        $this->locked->reapKilled();

        $this->assertFileDoesNotExist($this->holderFile($token));
    }

    /** Where cache/ cannot be written the claim is made all the same, without a lock, and judged by the rules a claim without one always was. */
    public function testAClaimIsMadeEvenWhenItsLockCannotBeCreated(): void
    {
        $notADirectory = (string)tempnam(sys_get_temp_dir(), 'yw-not-a-dir-');
        try {
            $transport = new QueueTransport($this->db, $this->schema, clock: $this->clock, holderLock: new QueueHolderLock(QueueTestKit::locksIn($notADirectory)));
            $transport->send(new Envelope(new QueueTestMessage('unlocked')));

            [$taken] = [...$transport->get()];

            $this->assertSame(self::tokenOf($taken), $this->row(self::idOf($taken))['delivered_by'] ?? null);
            $this->assertTrue($transport->isHandling());
            $transport->ack($taken);
            $this->assertNull($this->row(self::idOf($taken)));
        } finally {
            @unlink($notADirectory);
        }
    }

    /**
     * R19: a claim this very process made and no longer holds was made by a request it served before, which died without letting go (a fatal error in a shutdown function).
     *
     * A process without threads runs one request at a time, so that request is over.
     */
    public function testAClaimThisProcessNoLongerHoldsIsDead(): void
    {
        if (ZEND_THREAD_SAFE) {
            $this->markTestSkipped('a process with threads may hold it in another one');
        }
        $this->transport->claimAs(QueueConsumerMode::Request, 1);
        $this->transport->send(new Envelope(new QueueTestMessage('dies')));
        [$taken] = [...$this->transport->get()];
        QueueTestHolders::hand($this->db, $this->schema, self::idOf($taken), QueueClaimHolder::thisProcess());

        $this->assertSame([self::idOf($taken)], array_column($this->transport->reapKilled(), 'id'));
    }

    /** R19: what a process still holds as it ends goes to `failed` as killed, once; a pass that comes after finds nothing more. */
    public function testWhatThisTransportStillHoldsIsFailedOnceWhenAsked(): void
    {
        $this->transport->claimAs(QueueConsumerMode::Api, 50);
        $this->transport->send(new Envelope(new QueueTestMessage('done')));
        $this->transport->send(new Envelope(new QueueTestMessage('held')));
        [$done] = [...$this->transport->get()];
        [$held] = [...$this->transport->get()];
        $this->transport->ack($done);

        $failed = $this->transport->failHeldClaims();

        $this->assertSame([[
            'id' => self::idOf($held),
            'type' => QueueTestMessage::class,
            'mode' => 'api',
            'deliveredAt' => self::START,
        ]], $failed);
        $this->assertSame('api', $this->row(self::idOf($held))['killed_in'] ?? null);
        $this->assertSame([], $this->transport->failHeldClaims());
        $this->assertSame([], $this->transport->reapKilled(), 'recorded once');
    }

    /** R19: a holder declared dead that ends after all must not erase the failure it was recorded as: its ack leaves the row in `failed`. */
    public function testALateAckLeavesTheKilledMessageInFailed(): void
    {
        $this->transport->claimAs(QueueConsumerMode::Request, 1);
        $this->transport->send(new Envelope(new QueueTestMessage('late')));
        [$taken] = [...$this->transport->get()];
        $this->transport->failHeldClaims();

        $this->assertFalse($this->transport->release($taken));
        $this->transport->ack($taken);
        $this->transport->reject($taken);

        $this->assertSame('failed', $this->row(self::idOf($taken))['queue_name'] ?? null);
        $this->assertSame('request', $this->row(self::idOf($taken))['killed_in'] ?? null);
    }

    /** An ack the database refused (locked, connection lost) leaves the message this process's: a pass that reaps after it must not take a handled message for a killed one. */
    public function testAnAckThatFailsKeepsTheClaimHeld(): void
    {
        if (ZEND_THREAD_SAFE) {
            $this->markTestSkipped('a process with threads cannot tell its own claims apart from another thread\'s');
        }
        $this->transport->claimAs(QueueConsumerMode::Worker, 21600);
        $this->transport->send(new Envelope(new QueueTestMessage('handled')));
        [$taken] = [...$this->transport->get()];
        $table = $this->schema->table();
        $this->db->query("ALTER TABLE {$table} RENAME TO {$table}_away");

        try {
            $this->transport->ack($taken);
            $this->fail('the ack cannot reach a table that is not there');
        } catch (\Throwable) {
        } finally {
            $this->db->query("ALTER TABLE {$table}_away RENAME TO {$table}");
        }

        $this->assertSame([], $this->transport->reapKilled(), 'this live process still holds it');
        $this->assertTrue($this->transport->release($taken), 'the next ack removes it');
    }

    public function testOnlyAnEnvelopeTheTransportHandedOutCanBeAcked(): void
    {
        $sent = $this->transport->send(new Envelope(new QueueTestMessage('never taken')));

        $this->expectException(LogicException::class);
        $this->transport->ack($sent);
    }

    public function testPeekNextTypeDoesNotClaim(): void
    {
        $this->assertNull($this->transport->peekNextType());
        $this->transport->send(new Envelope(new QueueTestMessage('peeked')));

        $this->assertSame(QueueTestMessage::class, $this->transport->peekNextType());
        $this->assertCount(1, [...$this->transport->get()]);
        $this->assertNull($this->transport->peekNextType());
    }

    /** The queue's dates are UTC whatever zone the PHP clock reads in: a sender on another zone neither hides its message from a consumer on UTC nor shows it early. */
    public function testTheQueueKeepsUtcWhateverTheZoneOfTheClock(): void
    {
        $tokyo = new QueueTransport($this->db, $this->schema, clock: new MockClock('2026-10-23 21:00:00', 'Asia/Tokyo'));
        $sent = $tokyo->send(new Envelope(new QueueTestMessage('from tokyo')));

        $this->assertSame(self::START, $this->row(self::idOf($sent))['available_at'] ?? null, 'the instant it was sent, in UTC');
        $received = [...$this->transport->get()];
        $this->assertCount(1, $received, 'a consumer on UTC sees it at once');
        $this->assertSame(self::idOf($sent), self::idOf($received[0]));
    }

    public function testOldestPendingAtIsWhenTheLongestWaitingMessageBecameAvailable(): void
    {
        $this->assertNull($this->transport->oldestPendingAt());

        $this->transport->send(new Envelope(new QueueTestMessage('first')));
        $this->clock->sleep(30);
        $this->transport->send(new Envelope(new QueueTestMessage('second')));

        $this->assertEquals(new \DateTimeImmutable(self::START, new \DateTimeZone('UTC')), $this->transport->oldestPendingAt());
    }

    public function testFindLooksInThisQueueOnly(): void
    {
        $waiting = $this->transport->send(new Envelope(new QueueTestMessage('waiting')));
        $failed = $this->transport->send(new Envelope(new QueueTestMessage('failed')));
        $this->transport->moveToFailed(self::idOf($failed), 'boom');

        $found = $this->transport->find(self::idOf($waiting));
        $this->assertNotNull($found);
        $this->assertSame('waiting', $found->getMessage()->value ?? null);
        $this->assertNull($this->transport->find(self::idOf($failed)), 'it is in the other queue');
    }

    public function testMoveToFailedThenRequeueStartsTheMessageAfresh(): void
    {
        $sent = $this->transport->send(new Envelope(new QueueTestMessage('again'), [
            new RedeliveryStamp(3),
            new SentToFailureTransportStamp('default'),
        ]));
        $id = self::idOf($sent);
        $this->assertSame(3, (int)($this->row($id)['attempts'] ?? 0));

        $this->transport->moveToFailed($id, 'gave up');
        $this->assertSame('failed', $this->row($id)['queue_name'] ?? null);
        $this->assertSame('gave up', $this->row($id)['last_error'] ?? null);
        $this->assertSame([], [...$this->transport->get()]);

        $this->transport->withQueue('failed')->requeue($id);

        $row = $this->row($id);
        $this->assertSame('default', $row['queue_name'] ?? null);
        $this->assertSame(0, (int)($row['attempts'] ?? -1));
        $this->assertNull($row['last_error'] ?? null);
        [$received] = [...$this->transport->get()];
        $this->assertSame($id, self::idOf($received));
        $this->assertNull($received->last(RedeliveryStamp::class), 'the retry count starts again');
        $this->assertNull(
            $received->last(SentToFailureTransportStamp::class),
            'Messenger would otherwise drop it, rather than send it to failed, if it failed again'
        );
    }

    /** A message whose body cannot be read any more is still given another go: it goes back to the queue as it is, to fail there with its reason. */
    public function testAnUndecodableFailedMessageIsRequeuedAsItIs(): void
    {
        $sent = $this->transport->send(new Envelope(new QueueTestMessage('corrupt')));
        $id = self::idOf($sent);
        $this->transport->moveToFailed($id, 'boom');
        $this->db->query("UPDATE {$this->schema->table()} SET body = ? WHERE id = ?", ['not a serialized envelope}', $id]);

        $this->assertTrue($this->transport->requeue($id));

        $row = $this->row($id);
        $this->assertSame('default', $row['queue_name'] ?? null);
        $this->assertSame('not a serialized envelope}', $row['body'] ?? null);
        $this->assertNull($row['dedup_key'] ?? null);
        $this->assertNull($row['last_error'] ?? null);
    }

    /** getById() hands out one message of this queue to whoever asks, when it is due or not, and only once. */
    public function testGetByIdClaimsADelayedMessageOnceAndNeverAFailedOne(): void
    {
        $delayed = self::idOf($this->transport->send(new Envelope(new QueueTestMessage('later'), [new DelayStamp(3600000)])));
        $failed = self::idOf($this->transport->send(new Envelope(new QueueTestMessage('failed'))));
        $this->transport->moveToFailed($failed, 'boom');

        $taken = $this->transport->getById($delayed);

        $this->assertNotNull($taken, 'not due yet, and handed out all the same');
        $this->assertSame($delayed, self::idOf($taken));
        $this->assertNotNull($this->row($delayed)['delivered_at'] ?? null, 'it is claimed');
        $this->assertNull($this->transport->getById($delayed), 'a claimed message is nobody else\'s');
        $this->assertNull($this->transport->getById($failed), 'a failed one is in the other queue');
        $this->assertNull($this->transport->getById(9999));
    }

    /** Who is at work on this queue: the live claims, not the ones whose process is gone. */
    public function testLiveHoldersListsALiveClaimAndOmitsADeadOne(): void
    {
        $this->transport->claimAs(QueueConsumerMode::Worker, 21600);
        $this->transport->send(new Envelope(new QueueTestMessage('mine')));
        $this->transport->send(new Envelope(new QueueTestMessage('dies')));
        [$mine, $dies] = [...$this->transport->get(), ...$this->transport->get()];
        QueueTestHolders::hand($this->db, $this->schema, self::idOf($dies), QueueTestHolders::gone());

        $this->assertSame(
            [['holder' => (string)($this->row(self::idOf($mine))['delivered_by'] ?? ''), 'mode' => 'worker']],
            $this->transport->liveHolders()
        );
        $this->assertSame([], $this->transport->withQueue('failed')->liveHolders(), 'the other queue holds nothing');
    }

    public function testDeleteFailedRemovesAFailedMessage(): void
    {
        $sent = $this->transport->send(new Envelope(new QueueTestMessage('gone')));
        $this->transport->moveToFailed(self::idOf($sent), 'boom');

        $this->assertTrue($this->transport->deleteFailed(self::idOf($sent)));

        $this->assertNull($this->row(self::idOf($sent)));
    }

    /** An admin acts on the failed list as it was shown: a message retried meanwhile, and maybe running, is no longer theirs to remove. */
    public function testDeleteFailedLeavesAMessageThatWentBackToTheQueue(): void
    {
        $sent = $this->transport->send(new Envelope(new QueueTestMessage('retried')));
        $this->transport->moveToFailed(self::idOf($sent), 'boom');
        $this->transport->requeue(self::idOf($sent));
        [$taken] = [...$this->transport->get()];

        $this->assertFalse($this->transport->deleteFailed(self::idOf($sent)));

        $this->assertTrue($this->transport->release($taken), 'the consumer that runs it still holds it');
    }

    public function testAnUndecodableMessageGoesToFailedRatherThanBlockingTheQueue(): void
    {
        $sent = $this->transport->send(new Envelope(new QueueTestMessage('corrupt')));
        $this->db->query("UPDATE {$this->schema->table()} SET body = ? WHERE id = ?", ['not a serialized envelope}', self::idOf($sent)]);

        try {
            [...$this->transport->get()];
            $this->fail('the receiver contract asks for a MessageDecodingFailedException');
        } catch (MessageDecodingFailedException) {
        }

        $this->assertSame('failed', $this->row(self::idOf($sent))['queue_name'] ?? null);
        $this->assertStringStartsWith('undecodable:', (string)($this->row(self::idOf($sent))['last_error'] ?? ''));
        $this->assertSame([], [...$this->transport->get()]);
    }

    /**
     * A key longer than its column -- `search:reindex:` and a tag of up to 191 characters -- is still stored, and still deduplicates, and two that only differ past the column's width stay apart.
     *
     * @param array<string, string|bool> $params
     */
    #[DataProvider('servers')]
    public function testAKeyLongerThanItsColumnStillDeduplicates(array $params): void
    {
        $db = $this->db;
        if ($params['db_driver'] !== 'sqlite') {
            if (!self::reachable($params)) {
                $this->markTestSkipped("no {$params['db_driver']} server at {$params['db_host']}");
            }
            $db = new DbService(new ParameterBag($params));
        }
        $schema = new QueueSchema($db);
        $schema->drop();
        $schema->create();

        try {
            $transport = new QueueTransport($db, $schema, clock: $this->clock);
            $long = 'search:reindex:' . str_repeat('é', 145) . 'l-ete-a-nantes' . str_repeat('x', 126);
            $sibling = mb_substr($long, 0, 160) . str_repeat('y', mb_strlen($long) - 160);

            $first = $transport->send(new Envelope(new QueueTestUniqueMessage('one', $long)));
            $again = $transport->send(new Envelope(new QueueTestUniqueMessage('two', $long)));
            $other = $transport->send(new Envelope(new QueueTestUniqueMessage('three', $sibling)));

            $this->assertSame(self::idOf($first), self::idOf($again));
            $this->assertNotSame(self::idOf($first), self::idOf($other));
            $this->assertSame(2, $transport->getMessageCount());
            foreach ($db->loadAll("SELECT dedup_key FROM {$schema->table()}") as $row) {
                $this->assertLessThanOrEqual(QueueSchema::DEDUP_KEY_LENGTH, mb_strlen((string)$row['dedup_key']));
            }
        } finally {
            if ($db !== $this->db) {
                $schema->drop();
            }
        }
    }

    /**
     * The database itself refuses a second first send waiting under a key, however it is written.
     *
     * @param array<string, string|bool> $params
     */
    #[DataProvider('servers')]
    public function testTheDatabaseRefusesTwoWaitingMessagesUnderOneKey(array $params): void
    {
        $this->onRacing($params, function (QueueTestRacingDb $db, QueueSchema $schema): void {
            $transport = new QueueTransport($db, $schema, clock: $this->clock);
            $transport->send(new Envelope(new QueueTestUniqueMessage('one', 'search:drain')));

            $insert = static fn (string $queue): mixed => $db->query(
                "INSERT INTO {$schema->table()} (queue_name, message_type, body, headers, dedup_key, created_at, available_at, attempts)"
                . " VALUES (?, 'x', 'b', '{}', 'search:drain', ?, ?, 0)",
                [$queue, self::START, self::START]
            );

            try {
                $insert('default');
                $this->fail('a second waiting row under the same key was accepted');
            } catch (\Exception) {
            }
            $insert('elsewhere');
            $this->assertSame(1, $transport->getMessageCount());
        });
    }

    /**
     * Two senders of one key at once: the one whose look came before the other's insert is absorbed by it when the database refuses its own.
     *
     * @param array<string, string|bool> $params
     */
    #[DataProvider('servers')]
    public function testASendThatLosesTheRaceIsAbsorbedByTheWinner(array $params): void
    {
        $this->onRacing($params, function (QueueTestRacingDb $db, QueueSchema $schema): void {
            $transport = new QueueTransport($db, $schema, clock: $this->clock);
            $winner = $transport->send(new Envelope(new QueueTestUniqueMessage('winner', 'search:drain')));

            $db->missNextWaitingLookup();
            $loser = $transport->send(new Envelope(new QueueTestUniqueMessage('loser', 'search:drain')));

            $this->assertSame(self::idOf($winner), self::idOf($loser));
            $this->assertSame(1, $transport->getMessageCount());
        });
    }

    /** A key whose holder keeps vanishing before the look is not looped on for ever: the send says it gave up. */
    public function testASendGivesUpWhenTheWinnerKeepsVanishing(): void
    {
        $this->onRacing(['db_driver' => 'sqlite'], function (QueueTestRacingDb $db, QueueSchema $schema): void {
            $transport = new QueueTransport($db, $schema, clock: $this->clock);
            $transport->send(new Envelope(new QueueTestUniqueMessage('winner', 'search:drain')));
            $db->missEveryWaitingLookup();

            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessageMatches('/search:drain/');

            $transport->send(new Envelope(new QueueTestUniqueMessage('loser', 'search:drain')));
        });
    }

    /**
     * A requeue that races a fresh send of its key is absorbed by it all the same.
     *
     * @param array<string, string|bool> $params
     */
    #[DataProvider('servers')]
    public function testARequeueThatLosesTheRaceIsAbsorbedByTheWinner(array $params): void
    {
        $this->onRacing($params, function (QueueTestRacingDb $db, QueueSchema $schema): void {
            $transport = new QueueTransport($db, $schema, clock: $this->clock);
            $failed = $transport->send(new Envelope(new QueueTestUniqueMessage('failed', 'search:drain')));
            $transport->moveToFailed(self::idOf($failed), 'boom');
            $winner = $transport->send(new Envelope(new QueueTestUniqueMessage('winner', 'search:drain')));

            $db->missNextWaitingLookup();
            $this->assertTrue($transport->requeue(self::idOf($failed)));

            $this->assertSame(0, $transport->withQueue('failed')->getMessageCount());
            $this->assertSame(1, $transport->getMessageCount());
            [$received] = [...$transport->get()];
            $this->assertSame(self::idOf($winner), self::idOf($received));
        });
    }

    /**
     * @param array<string, string|bool>                     $params
     * @param \Closure(QueueTestRacingDb, QueueSchema): void $work   on a queue table of its own, dropped afterwards
     */
    private function onRacing(array $params, \Closure $work): void
    {
        if ($params['db_driver'] === 'sqlite') {
            $params += ['db_database' => $this->file];
        } elseif (!self::reachable($params)) {
            $this->markTestSkipped("no {$params['db_driver']} server at {$params['db_host']}");
        }
        $db = new QueueTestRacingDb(new ParameterBag(['table_prefix' => 'ywqrace_', 'debug' => false] + $params));
        $schema = new QueueSchema($db);
        $schema->drop();
        $schema->create();

        try {
            $work($db, $schema);
        } finally {
            $schema->drop();
        }
    }

    /** The same round trip on whatever database the developer's wiki runs on, in tables of its own. */
    public function testOnTheSuitesOwnDatabase(): void
    {
        $wiki = self::getWiki();
        if (!$wiki->services->get(QueueSchema::class)->exists()) {
            $this->markTestSkipped('this wiki does not have its queue table yet');
        }
        $params = $wiki->services->get(ParameterBagInterface::class)->all();

        $this->exerciseOn(static fn (): DbService => new DbService(new ParameterBag(
            ['table_prefix' => 'ywqtest_', 'debug' => false] + $params
        )));
    }

    /**
     * The wiki's own connection when it runs on PostgreSQL or MySQL, read once: see wikiServer().
     *
     * @var array{db_driver: string, db_host: string, db_port: string, db_database: string, db_user: string, db_password: string}|null
     */
    private static ?array $wikiServer = null;
    private static bool $wikiServerRead = false;

    /**
     * The PostgreSQL and MySQL servers the queue's SQL is tried on.
     *
     * The engine the wiki under test runs on is tried on the wiki's own database -- in CI, the one the
     * matrix leg installed it on -- so a leg's driver is the one its queue tests prove, and that server is
     * never skipped (reachable()). The other engine is the development container, unless
     * YESWIKI_TEST_{PGSQL,MYSQL}_{HOST,PORT,DATABASE,USER,PASSWORD} say otherwise, and is skipped when absent.
     *
     * @return array<string, array{array<string, string|bool>}>
     */
    public static function realServers(): array
    {
        $common = ['table_prefix' => 'ywqtest_', 'debug' => false];
        $wiki = self::wikiServer();
        $server = static fn (string $driver, string $engine, string $host, string $port): array => ($wiki !== null && $wiki['db_driver'] === $driver)
            ? $wiki
            : ['db_driver' => $driver] + self::serverFromEnvironment($engine, $host, $port);

        return [
            'postgresql' => [$server('pgsql', 'PGSQL', 'yeswiki-pg', '5432') + $common],
            'mysql' => [$server('mysql', 'MYSQL', 'yeswiki-db', '3306') + $common],
        ];
    }

    /**
     * The connection of the wiki the suite runs on when it is PostgreSQL or MySQL, as the wiki resolved it; null on SQLite.
     *
     * @return array{db_driver: string, db_host: string, db_port: string, db_database: string, db_user: string, db_password: string}|null
     */
    private static function wikiServer(): ?array
    {
        if (!self::$wikiServerRead) {
            self::$wikiServerRead = true;
            $config = self::getWiki()->config;
            $driver = (string)($config['db_driver'] ?? '');
            if (in_array($driver, ['pgsql', 'mysql'], true)) {
                $host = (string)($config['db_host'] ?? '');
                $port = (string)($config['db_port'] ?? '');
                // "host:port" is how a MySQL host is often written; the DSN wants them apart.
                if ($port === '' && preg_match('/^([^:]+):(\d+)$/', $host, $parts) === 1) {
                    [, $host, $port] = $parts;
                }
                self::$wikiServer = [
                    'db_driver' => $driver,
                    'db_host' => $host,
                    'db_port' => $port !== '' ? $port : ($driver === 'pgsql' ? '5432' : '3306'),
                    'db_database' => (string)($config['db_database'] ?? ''),
                    'db_user' => (string)($config['db_user'] ?? ''),
                    'db_password' => (string)($config['db_password'] ?? ''),
                ];
            }
        }

        return self::$wikiServer;
    }

    /**
     * One engine's connection, each setting from its YESWIKI_TEST_{$engine}_* variable when set, an empty one included.
     *
     * @return array{db_host: string, db_port: string, db_database: string, db_user: string, db_password: string}
     */
    private static function serverFromEnvironment(string $engine, string $host, string $port): array
    {
        $read = static function (string $setting, string $default) use ($engine): string {
            $value = getenv("YESWIKI_TEST_{$engine}_{$setting}");

            return $value === false ? $default : $value;
        };

        return [
            'db_host' => $read('HOST', $host),
            'db_port' => $read('PORT', $port),
            'db_database' => $read('DATABASE', 'yeswiki'),
            'db_user' => $read('USER', 'yeswiki'),
            'db_password' => $read('PASSWORD', 'password'),
        ];
    }

    /**
     * SQLite in the file setUp() made, and the development servers when they are there.
     *
     * @return array<string, array{array<string, string|bool>}>
     */
    public static function servers(): array
    {
        return ['sqlite' => [['db_driver' => 'sqlite']]] + self::realServers();
    }

    /**
     * The claim relies on each engine's row locks, so it is run on the real ones when the development containers are there.
     *
     * @param array<string, string|bool> $params
     */
    #[DataProvider('realServers')]
    public function testOnARealServer(array $params): void
    {
        if (!self::reachable($params)) {
            $this->markTestSkipped("no {$params['db_driver']} server at {$params['db_host']}");
        }

        $this->exerciseOn(static fn (): DbService => new DbService(new ParameterBag($params)));
    }

    /** @param \Closure(): DbService $open a new connection to the database under test, on each call */
    private function exerciseOn(\Closure $open): void
    {
        $mine = $open();
        $theirs = $open();
        $schema = new QueueSchema($mine);
        $schema->drop();
        $schema->create();

        try {
            $clock = new MockClock(self::START, 'UTC');
            $transport = new QueueTransport($mine, $schema, clock: $clock);
            $other = new QueueTransport($theirs, new QueueSchema($theirs), clock: $clock);

            for ($i = 0; $i < 6; $i++) {
                $transport->send(new Envelope(new QueueTestUniqueMessage("m{$i}", "k{$i}")));
            }
            $transport->send(new Envelope(new QueueTestUniqueMessage('duplicate', 'k0')));
            $this->assertSame(6, $other->getMessageCount());

            $taken = self::claimAlternately($transport, $other);
            $this->assertCount(6, array_unique(array_map(self::idOf(...), $taken)));
            foreach ($taken as $envelope) {
                $this->assertLessThanOrEqual(64, strlen((string)$mine->scalar("SELECT delivered_by FROM {$schema->table()} WHERE id = ?", null, [self::idOf($envelope)])));
                $transport->ack($envelope);
            }
            $this->assertSame(0, $transport->getMessageCount());

            $retried = $transport->send(new Envelope(new QueueTestUniqueMessage('retried', 'k1'), [new RedeliveryStamp(2)]));
            $this->assertNotSame(self::idOf($retried), self::idOf($transport->send(new Envelope(new QueueTestUniqueMessage('fresh', 'k1')))));
            $broken = $other->withQueue('failed')->send(new Envelope(new QueueTestMessage('broken'), [
                new SentToFailureTransportStamp('default'),
                ErrorDetailsStamp::create(new \RuntimeException('boom')),
            ]));
            $this->assertSame('RuntimeException: boom', $mine->scalar("SELECT last_error FROM {$schema->table()} WHERE id = ?", null, [self::idOf($broken)]));
            $mine->query("DELETE FROM {$schema->table()}");

            $transport->claimAs(QueueConsumerMode::Api, 1);
            $transport->send(new Envelope(new QueueTestMessage('dies'), [new DelayStamp(1000)]));
            $this->assertSame([], [...$other->get()]);
            $clock->sleep(1);
            $this->assertSame(QueueTestMessage::class, $other->peekNextType());
            [$taken] = [...$transport->get()];
            $clock->sleep(62);
            $this->assertSame([], $other->reapKilled(), 'its holder, this process, is still at work');
            QueueTestHolders::hand($mine, $schema, self::idOf($taken), QueueTestHolders::elsewhere());
            $this->assertSame([self::idOf($taken)], array_column($other->reapKilled(), 'id'));
            $this->assertSame(1, $other->withQueue('failed')->getMessageCount());
            $transport->ack($taken);
            $this->assertSame(1, $other->withQueue('failed')->getMessageCount(), 'a late ack leaves the failure');

            $other->requeue(self::idOf($taken));
            [$again] = [...$transport->get()];
            $this->assertSame(self::idOf($taken), self::idOf($again));
            $this->assertSame([self::idOf($again)], array_column($transport->failHeldClaims(), 'id'));
            $this->assertSame(1, $other->withQueue('failed')->getMessageCount());
            $other->requeue(self::idOf($taken));
            [$last] = [...$other->get()];
            $transport->ack($last);
            $this->assertSame(0, (int)$mine->scalar("SELECT COUNT(*) FROM {$schema->table()}"));
        } finally {
            $schema->drop();
        }
    }

    /**
     * Two consumers take turns until neither gets anything.
     *
     * @return list<Envelope> what they took, in order
     */
    private static function claimAlternately(QueueTransport $one, QueueTransport $two): array
    {
        $taken = [];
        $consumers = [$one, $two];
        for ($turn = 0, $idle = 0; $idle < 2; $turn++) {
            $received = [...$consumers[$turn % 2]->get()];
            if ($received === []) {
                $idle++;
                continue;
            }
            $idle = 0;
            $taken[] = $received[0];
        }

        return $taken;
    }

    /**
     * Whether that engine's server answers; the other queue tests that run on real servers ask it too.
     *
     * The wiki's own server, and any under YESWIKI_TEST_REAL_SERVERS=required, fails the test that asked
     * when it does not answer, instead of letting it be skipped: a skip is silent, and the engine a CI leg
     * exists to prove would go untested unseen.
     *
     * @param array<string, string|bool> $params
     */
    public static function reachable(array $params): bool
    {
        $dsn = "{$params['db_driver']}:host={$params['db_host']};port={$params['db_port']};dbname={$params['db_database']}";
        if ($params['db_driver'] === 'pgsql') {
            $dsn .= ';connect_timeout=2';
        }

        try {
            new \PDO($dsn, (string)$params['db_user'], (string)$params['db_password'], [\PDO::ATTR_TIMEOUT => 2]);

            return true;
        } catch (\PDOException $e) {
            $wiki = self::wikiServer();
            $isTheWikis = $wiki !== null && array_intersect_key($params, $wiki) == $wiki;
            if ($isTheWikis || getenv('YESWIKI_TEST_REAL_SERVERS') === 'required') {
                throw new AssertionFailedError("no {$params['db_driver']} server at {$params['db_host']}:{$params['db_port']}/{$params['db_database']} although " . ($isTheWikis ? 'it is the database of the wiki under test' : 'YESWIKI_TEST_REAL_SERVERS=required') . ": {$e->getMessage()}");
            }

            return false;
        }
    }

    private function open(): DbService
    {
        return new DbService(new ParameterBag([
            'db_driver' => 'sqlite',
            'db_database' => $this->file,
            'table_prefix' => 'ywq_',
            'debug' => false,
        ]));
    }

    /** @return array<string, mixed>|null */
    private function row(int $id): ?array
    {
        return $this->db->loadSingle("SELECT * FROM {$this->schema->table()} WHERE id = ?", [$id]);
    }

    /** The absolute path of the lock file of that token. */
    private function holderFile(string $token): string
    {
        return $this->dir . '/' . QueueHolderLock::pathOf($token);
    }

    private static function tokenOf(Envelope $envelope): string
    {
        $stamp = $envelope->last(QueueClaimStamp::class);
        self::assertInstanceOf(QueueClaimStamp::class, $stamp);

        return $stamp->token;
    }

    /** Whether that file's lock is HELD, FREE or ABSENT, as a process other than this one finds it the way a judge does. */
    private static function seenFromAnotherProcess(string $absolutePath): string
    {
        $code = '$h = @fopen($argv[1], "r"); if ($h === false) { echo "' . QueueHolderLock::ABSENT . '"; exit; } echo flock($h, LOCK_SH | LOCK_NB) ? "' . QueueHolderLock::FREE . '" : "' . QueueHolderLock::HELD . '";';

        return trim((string)shell_exec(escapeshellarg(PHP_BINARY) . ' -r ' . escapeshellarg($code) . ' -- ' . escapeshellarg($absolutePath)));
    }

    private static function idOf(Envelope $envelope): int
    {
        $stamp = $envelope->last(TransportMessageIdStamp::class);
        self::assertInstanceOf(TransportMessageIdStamp::class, $stamp);

        return (int)$stamp->getId();
    }
}
