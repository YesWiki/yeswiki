<?php

namespace YesWiki\Test\Kernel;

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
use YesWiki\Kernel\Queue\QueueConsumerMode;
use YesWiki\Kernel\Queue\UniqueStamp;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Kernel\Service\JournalSchema;
use YesWiki\Kernel\Service\Queue\QueueCheckpoint;
use YesWiki\Kernel\Service\Queue\QueueRecorder;
use YesWiki\Kernel\Service\Queue\QueueSchema;
use YesWiki\Kernel\Service\Queue\QueueStatistics;
use YesWiki\Kernel\Service\Queue\QueueTaskRows;
use YesWiki\Kernel\Service\Queue\QueueTransport;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';
require_once 'tests/src/Kernel/QueueTestMessage.php';
require_once 'tests/src/Kernel/QueueTestTask.php';
require_once 'tests/src/Kernel/QueueTestHolders.php';

/** The Messenger transport that keeps the queue in the wiki's own database, and its claim that two consumers never share. */
#[CoversClass(QueueTransport::class)]
#[CoversClass(UniqueStamp::class)]
#[CoversClass(QueueClaimHolder::class)]
class QueueTransportTest extends YesWikiTestCase
{
    private const START = '2026-10-23 12:00:00';

    private string $file;
    private MockClock $clock;
    private DbService $db;
    private QueueSchema $schema;
    private QueueTransport $transport;

    protected function setUp(): void
    {
        $this->file = tempnam(sys_get_temp_dir(), 'yw-queue-') . '.db';
        $this->clock = new MockClock(self::START, 'UTC');
        $this->db = $this->open();
        $this->schema = new QueueSchema($this->db);
        $this->schema->create();
        $this->transport = new QueueTransport($this->db, $this->schema, clock: $this->clock);
    }

    protected function tearDown(): void
    {
        unset($this->transport, $this->schema, $this->db);
        foreach (['', '-wal', '-shm'] as $suffix) {
            @unlink($this->file . $suffix);
        }
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

    public function testUniqueStampDoesNotDuplicateAPendingMessage(): void
    {
        $first = $this->transport->send(new Envelope(new QueueTestMessage('one'), [new UniqueStamp('search:drain')]));
        $second = $this->transport->send(new Envelope(new QueueTestMessage('two'), [new UniqueStamp('search:drain')]));

        $this->assertSame(1, $this->transport->getMessageCount());
        $this->assertSame(self::idOf($first), self::idOf($second), 'the caller is told which message will do the work');
    }

    public function testUniqueStampAllowsANewOneOnceTheFirstIsTaken(): void
    {
        $this->transport->send(new Envelope(new QueueTestMessage('one'), [new UniqueStamp('search:drain')]));
        [$taken] = [...$this->transport->get()];

        $again = $this->transport->send(new Envelope(new QueueTestMessage('two'), [new UniqueStamp('search:drain')]));

        $this->assertNotSame(self::idOf($taken), self::idOf($again));
        $this->assertSame(1, $this->transport->getMessageCount());
        $this->assertSame(1, (int)$this->db->scalar(
            "SELECT COUNT(*) FROM {$this->schema->table()} WHERE delivered_at IS NOT NULL"
        ));
    }

    /** R7: Messenger re-sends the envelope with its UniqueStamp; absorbing the retry would drop its count, and a handler that always fails would never reach `failed`. */
    public function testARetryIsNeverAbsorbedByAWaitingMessage(): void
    {
        $this->transport->send(new Envelope(new QueueTestMessage('first'), [new UniqueStamp('search:drain')]));
        [$taken] = [...$this->transport->get()];
        $fresh = $this->transport->send(new Envelope(new QueueTestMessage('fresh'), [new UniqueStamp('search:drain')]));

        $retry = $this->transport->send($taken->with(new RedeliveryStamp(1), new DelayStamp(60000)));

        $this->assertNotSame(self::idOf($fresh), self::idOf($retry));
        $this->assertSame(2, $this->transport->getMessageCount());
        $this->assertSame(1, (int)($this->row(self::idOf($retry))['attempts'] ?? 0));
        $this->assertSame(0, (int)($this->row(self::idOf($fresh))['attempts'] ?? -1));
    }

    /** R7: a retry may wait up to 30 min; a fresh trigger must not wait behind it. */
    public function testAFreshTriggerIsNotAbsorbedIntoADelayedRetry(): void
    {
        $this->transport->send(new Envelope(new QueueTestMessage('first'), [new UniqueStamp('search:drain')]));
        [$taken] = [...$this->transport->get()];
        $retry = $this->transport->send($taken->with(new RedeliveryStamp(1), new DelayStamp(1800000)));

        $fresh = $this->transport->send(new Envelope(new QueueTestMessage('fresh'), [new UniqueStamp('search:drain')]));

        $this->assertNotSame(self::idOf($retry), self::idOf($fresh));
        $received = [...$this->transport->get()];
        $this->assertCount(1, $received, 'the fresh one is available at once');
        $this->assertSame(self::idOf($fresh), self::idOf($received[0]));
    }

    /** R7: each definitive failure keeps its own row in `failed`, with its cause where the admin page reads it. */
    public function testEveryDefinitiveFailureHasItsOwnRowAndCauseInFailed(): void
    {
        $failed = $this->transport->withQueue('failed');
        $fail = static fn (string $why): Envelope => new Envelope(new QueueTestMessage('broken'), [
            new UniqueStamp('task:digest'),
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
        $this->transport->claimAs(QueueConsumerMode::Command, 30);
        $this->transport->send(new Envelope(new QueueTestMessage('slow')));
        [$taken] = [...$this->transport->get()];
        QueueTestHolders::hand($this->db, $this->schema, self::idOf($taken), QueueTestHolders::living(true));

        $this->clock->sleep(3 * 3600);
        $this->assertSame([], $this->transport->reapKilled());
        $this->assertSame([], $this->transport->expiredClaims());
        $this->assertTrue($this->transport->isHandling());

        $this->clock->sleep(3 * 3600 + 61);
        $this->assertSame([self::idOf($taken)], array_column($this->transport->reapKilled(), 'id'), 'past the ceiling it is stuck, not slow');
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

    /** Review Focus 4: a database on another time zone, or a PHP clock far from the database's, must not hide messages. */
    public function testClaimIgnoresTheDatabaseClock(): void
    {
        $transport = new QueueTransport($this->db, $this->schema, clock: new MockClock('2030-01-01 00:00:00', 'UTC'));
        $transport->send(new Envelope(new QueueTestMessage('future')));

        $this->assertCount(1, [...$transport->get()]);
    }

    public function testOldestPendingAtIsWhenTheLongestWaitingMessageBecameAvailable(): void
    {
        $this->assertNull($this->transport->oldestPendingAt());

        $this->transport->send(new Envelope(new QueueTestMessage('first')));
        $this->clock->sleep(30);
        $this->transport->send(new Envelope(new QueueTestMessage('second')));

        $this->assertEquals(new \DateTimeImmutable(self::START, new \DateTimeZone('UTC')), $this->transport->oldestPendingAt());
    }

    public function testFindAndAllListWhatWaitsInThisQueueOnly(): void
    {
        $waiting = $this->transport->send(new Envelope(new QueueTestMessage('waiting')));
        $failed = $this->transport->send(new Envelope(new QueueTestMessage('failed')));
        $this->transport->moveToFailed(self::idOf($failed), 'boom');

        $listed = [...$this->transport->all()];
        $this->assertSame([self::idOf($waiting)], array_map(self::idOf(...), $listed));
        $this->assertSame([self::idOf($failed)], array_map(self::idOf(...), [...$this->transport->withQueue('failed')->all(10)]));

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
     * A scheduled task and the Scheduler's checkpoint are rows of the same table, in a queue no consumer reads: nothing done with messages sees them.
     *
     * @param array<string, string|bool> $params
     */
    #[DataProvider('servers')]
    public function testTaskRowsAreNeverMessages(array $params): void
    {
        $db = $this->db;
        if ($params['db_driver'] !== 'sqlite') {
            if (!self::reachable($params)) {
                $this->markTestSkipped("no {$params['db_driver']} server at {$params['db_host']}");
            }
            $db = new DbService(new ParameterBag($params));
        }
        $schema = new QueueSchema($db);
        $journal = new JournalSchema($db);
        foreach ([$schema, $journal] as $tables) {
            $tables->drop();
            $tables->create();
        }

        try {
            $transport = new QueueTransport($db, $schema, clock: $this->clock);
            $statistics = new QueueStatistics($db, $schema, $journal, $transport, $this->clock);
            (new QueueRecorder($db, $schema, $journal, $this->clock))->taskDispatched(new QueueTestTask(), 1800, $this->clock->now()->modify('+30 minutes'));
            (new QueueTaskRows($db, $schema, $this->clock))->update(
                QueueCheckpoint::ROW,
                static fn (array $fields): array => ['next_due_at' => '2026-10-23 12:30:00', 'state' => ['time' => '2026-10-23 12:00:00.000000', 'index' => -1, 'from' => '2026-10-23 12:00:00.000000']] + $fields
            );
            $rows = array_map(
                static fn (array $row): int => (int)$row['id'],
                $db->loadAll("SELECT id FROM {$schema->table()} WHERE queue_name = ? ORDER BY id", [QueueSchema::SCHEDULE_QUEUE])
            );
            $this->assertCount(2, $rows, 'one row for the task, one for the checkpoint');
            $this->clock->sleep(60);

            $this->assertSame(0, $transport->getMessageCount());
            $this->assertSame(0, $transport->withQueue(QueueTransport::FAILED_QUEUE)->getMessageCount());
            $this->assertSame(0, $statistics->pendingCount());
            $this->assertSame(0, $statistics->failedCount());
            $this->assertSame([], [...$transport->get()]);
            $this->assertSame([], [...$transport->withQueue(QueueTransport::FAILED_QUEUE)->get()]);
            $this->assertSame([], $statistics->byType());
            foreach ($rows as $id) {
                $this->assertSame('unknown', $statistics->messageState($id));
                $this->assertNull($transport->getById($id));
                $transport->moveToFailed($id, 'boom');
                $transport->deleteFailed($id);
            }
            $this->assertSame(0, $transport->pruneFailed(0));

            $this->assertSame(2, (int)$db->scalar("SELECT COUNT(*) FROM {$schema->table()} WHERE queue_name = ?", 0, [QueueSchema::SCHEDULE_QUEUE]), 'neither moved nor deleted');
            $this->assertSame(['test-task'], array_column($statistics->tasks(), 'taskId'));
            $this->assertSame('2026-10-23 12:30:00', $statistics->scheduleDueAt()?->format('Y-m-d H:i:s'));
        } finally {
            if ($db !== $this->db) {
                $schema->drop();
                $journal->drop();
            }
        }
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

            $first = $transport->send(new Envelope(new QueueTestMessage('one'), [new UniqueStamp($long)]));
            $again = $transport->send(new Envelope(new QueueTestMessage('two'), [new UniqueStamp($long)]));
            $other = $transport->send(new Envelope(new QueueTestMessage('three'), [new UniqueStamp($sibling)]));

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
     * @return array<string, array{array<string, string|bool>}>
     */
    public static function realServers(): array
    {
        $common = ['db_database' => 'yeswiki', 'db_user' => 'yeswiki', 'db_password' => 'password', 'table_prefix' => 'ywqtest_', 'debug' => false];

        return [
            'postgresql' => [['db_driver' => 'pgsql', 'db_host' => 'yeswiki-pg', 'db_port' => '5432'] + $common],
            'mysql' => [['db_driver' => 'mysql', 'db_host' => 'yeswiki-db', 'db_port' => '3306'] + $common],
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
                $transport->send(new Envelope(new QueueTestMessage("m{$i}"), [new UniqueStamp("k{$i}")]));
            }
            $transport->send(new Envelope(new QueueTestMessage('duplicate'), [new UniqueStamp('k0')]));
            $this->assertSame(6, $other->getMessageCount());

            $taken = self::claimAlternately($transport, $other);
            $this->assertCount(6, array_unique(array_map(self::idOf(...), $taken)));
            foreach ($taken as $envelope) {
                $this->assertLessThanOrEqual(64, strlen((string)$mine->scalar("SELECT delivered_by FROM {$schema->table()} WHERE id = ?", null, [self::idOf($envelope)])));
                $transport->ack($envelope);
            }
            $this->assertSame(0, $transport->getMessageCount());

            $retried = $transport->send(new Envelope(new QueueTestMessage('retried'), [new UniqueStamp('k1'), new RedeliveryStamp(2)]));
            $this->assertNotSame(self::idOf($retried), self::idOf($transport->send(new Envelope(new QueueTestMessage('fresh'), [new UniqueStamp('k1')]))));
            $broken = $other->withQueue('failed')->send(new Envelope(new QueueTestMessage('broken'), [
                new SentToFailureTransportStamp('default'),
                ErrorDetailsStamp::create(new \RuntimeException('boom')),
            ]));
            $this->assertSame('RuntimeException: boom', $mine->scalar("SELECT last_error FROM {$schema->table()} WHERE id = ?", null, [self::idOf($broken)]));
            $mine->query("DELETE FROM {$schema->table()}");

            $transport->claimAs(QueueConsumerMode::Command, 1);
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
     * Whether the development container of that engine answers; the other queue tests that run on real servers ask it too.
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
        } catch (\PDOException) {
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

    private static function idOf(Envelope $envelope): int
    {
        $stamp = $envelope->last(TransportMessageIdStamp::class);
        self::assertInstanceOf(TransportMessageIdStamp::class, $stamp);

        return (int)$stamp->getId();
    }
}
