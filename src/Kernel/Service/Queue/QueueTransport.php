<?php

namespace YesWiki\Kernel\Service\Queue;

use Psr\Clock\ClockInterface;
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\LogicException;
use Symfony\Component\Messenger\Exception\MessageDecodingFailedException;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Messenger\Stamp\ErrorDetailsStamp;
use Symfony\Component\Messenger\Stamp\RedeliveryStamp;
use Symfony\Component\Messenger\Stamp\SentToFailureTransportStamp;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;
use Symfony\Component\Messenger\Transport\Receiver\ListableReceiverInterface;
use Symfony\Component\Messenger\Transport\Receiver\MessageCountAwareInterface;
use Symfony\Component\Messenger\Transport\Serialization\PhpSerializer;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;
use YesWiki\Kernel\Database\SqlParameters;
use YesWiki\Kernel\Queue\QueueClaimHolder;
use YesWiki\Kernel\Queue\QueueClaimStamp;
use YesWiki\Kernel\Queue\QueueConsumerMode;
use YesWiki\Kernel\Queue\UndecodableMessageException;
use YesWiki\Kernel\Queue\UniqueStamp;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Kernel\Service\RequestScopedState;

/**
 * The Messenger transport of the queue: one row of the queue's table per message, in the wiki's own database.
 *
 * Written on DbService and its dialects rather than on doctrine/messenger, which would bring
 * Doctrine DBAL into a codebase that does not otherwise have it. One table holds both queues --
 * `default`, the work, and `failed`, what an admin has to look at -- told apart by `queue_name`,
 * so moving a message from one to the other is an UPDATE and keeps its id. The same table also
 * holds the scheduled tasks, in a queue of their own (QueueSchema::SCHEDULE_QUEUE): every query
 * here names the queues it means, so that a task's row is never taken, counted nor pruned.
 *
 * Every time is PHP's, from the injected clock, in UTC, bound as a parameter: never the
 * database's NOW(). A database on another time zone than PHP (common on shared hosting) would
 * otherwise make a message available hours early or late, and a delivery look killed or alive
 * for the wrong reason.
 *
 * A row is a message waiting while `delivered_at` is NULL, and a message some consumer is
 * handling once it is set; ack() and reject() delete it, as long as it is still under the claim
 * they were handed it with (a message whose holder was declared dead is in `failed` by then).
 *
 * Whether the holder of a claim is dead is first asked of the process itself (R19): the token in
 * `delivered_by` names it (QueueClaimHolder), so a process gone from this machine leaves a message
 * that is failed at once, and a process of its own still at work keeps its message however long
 * it takes, up to a ceiling. Only what cannot be told from here -- another machine, a server's
 * process that goes on serving after the request that held the claim died, no /proc -- waits for
 * the limit the holder recorded, as everything did before.
 */
class QueueTransport implements TransportInterface, ListableReceiverInterface, MessageCountAwareInterface, RequestScopedState
{
    public const DEFAULT_QUEUE = 'default';
    public const FAILED_QUEUE = 'failed';

    /** The queues that hold messages: what a message found by its id alone may be in. */
    public const QUEUES = [self::DEFAULT_QUEUE, self::FAILED_QUEUE];

    /** How long past the limit it recorded a delivery may still be finishing: acking, closing its pass. */
    private const KILL_GRACE_SECONDS = 60;

    /** The limit assumed for a delivery that recorded none (taken without claimAs()): the longest any mode records. */
    private const UNRECORDED_LIMIT_SECONDS = 21600;

    /** How long a holder that is provably still at work may keep its message, if its own limit is shorter: past it, it is stuck rather than slow. */
    private const ALIVE_CEILING_SECONDS = 21600;

    /** Replays of a transaction the engine undid to break a deadlock (see DbService::transactional()). */
    private const CONFLICT_ATTEMPTS = 3;

    private const TIME_FORMAT = 'Y-m-d H:i:s';

    /** Characters of a failure's cause kept in `last_error`: enough to recognise it; the whole of it stays in the envelope. */
    private const ERROR_LENGTH = 1000;

    /** The columns a claim sets, back to "nobody holds it". */
    private const UNCLAIMED = 'delivered_at = NULL, delivered_by = NULL, delivered_mode = NULL, delivered_limit = NULL';

    private ClockInterface $clock;

    /** Written on the rows get() takes, so that the next pass can tell a slow delivery from a dead one. */
    private ?string $claimMode = null;
    private ?int $claimLimit = null;

    /**
     * The claims this transport took and has not let go of yet, by token: what failHeldClaims() fails when the process ends.
     *
     * @var array<string, array{id: int, type: string, mode: string, deliveredAt: string}>
     */
    private array $held = [];

    /**
     * The tokens every transport of this process holds now. A claim made by this very process that is not among them
     * was made by a request this process served before, and that request is over (see holderState()).
     *
     * @var array<string, true>
     */
    private static array $heldHere = [];

    public function __construct(
        private readonly DbService $db,
        private readonly QueueSchema $schema,
        private readonly SerializerInterface $serializer = new PhpSerializer(),
        private string $queueName = self::DEFAULT_QUEUE,
        ?ClockInterface $clock = null,
    ) {
        $this->clock = $clock ?? new NativeClock();
    }

    /** A pass ends with its request: the next one says who it is again, rather than inheriting a mode from the request before (worker mode). */
    public function startNewRequest(): void
    {
        $this->claimMode = null;
        $this->claimLimit = null;
    }

    /** The same table, seen as another queue: `withQueue('failed')` is the failure transport. */
    public function withQueue(string $queueName): self
    {
        $other = clone $this;
        $other->queueName = $queueName;

        return $other;
    }

    /**
     * Who takes the messages from now on, and how long it may take before its process is cut.
     *
     * Recorded on each row get() takes, in `delivered_mode` and `delivered_limit`: a consumer
     * killed by PHP's time limit or the OOM killer cannot say so, so the next pass reads the
     * limit back and calls the delivery killed once it has been exceeded (reapKilled()).
     */
    public function claimAs(QueueConsumerMode $mode, int $limitSeconds): void
    {
        $this->claimMode = $mode->value;
        $this->claimLimit = $limitSeconds;
    }

    /**
     * Queue the message, available after its DelayStamp if it has one.
     *
     * With a UniqueStamp, a first send whose key matches a first send still waiting in this
     * queue is absorbed by it: nothing is inserted, and the envelope comes back with the waiting
     * message's id. Only fresh triggers are absorbed that way. Messenger's own re-sends -- a retry
     * (RedeliveryStamp above 0), a definitive failure (SentToFailureTransportStamp), anything sent
     * to `failed` -- always get a row of their own: absorbing them would drop a retry count, so a
     * handler that always fails would never reach `failed`, or merge a failure into an older one
     * and lose its cause. And a fresh trigger never waits behind a retry that is delayed for
     * minutes, since it only matches rows that are themselves first sends (`attempts` = 0).
     *
     * The check and the insert are not locked against another sender: two of them racing can
     * both insert, which costs one redundant run of idempotent work and nothing else.
     *
     * A message sent to `failed` carries its cause in `last_error`, read from the ErrorDetailsStamp
     * Messenger put on it, so that the admin page and the health check need not decode bodies.
     */
    public function send(Envelope $envelope): Envelope
    {
        $envelope = $envelope->withoutAll(TransportMessageIdStamp::class);
        $now = $this->now();
        $delay = $envelope->last(DelayStamp::class)?->getDelay() ?? 0;
        $availableAt = $delay > 0 ? $now->modify("+{$delay} milliseconds") : $now;
        $key = self::fitted($envelope->last(UniqueStamp::class)?->key);
        $absorbable = $key !== null && $this->isFirstSend($envelope);
        $error = $this->queueName === self::FAILED_QUEUE ? self::errorOf($envelope) : null;
        $encoded = $this->serializer->encode($envelope);
        $table = $this->schema->table();

        $id = $this->db->transactional(function () use ($envelope, $now, $availableAt, $key, $absorbable, $error, $encoded, $table): int {
            if ($absorbable) {
                $waiting = $this->db->scalar(
                    "SELECT id FROM {$table} WHERE queue_name = ? AND dedup_key = ? AND delivered_at IS NULL AND attempts = 0"
                    . ' ORDER BY id LIMIT 1',
                    null,
                    [$this->queueName, $key]
                );
                if ($waiting !== null) {
                    return (int)$waiting;
                }
            }

            $this->db->query(
                "INSERT INTO {$table} (queue_name, message_type, body, headers, dedup_key, created_at, available_at, attempts, last_error)"
                . ' VALUES (' . SqlParameters::placeholders(9) . ')',
                [
                    $this->queueName,
                    $envelope->getMessage()::class,
                    $encoded['body'],
                    json_encode($encoded['headers'] ?? [], JSON_THROW_ON_ERROR),
                    $key,
                    self::format($now),
                    self::format($availableAt),
                    self::retries($envelope),
                    $error,
                ]
            );

            return $this->db->lastInsertId();
        }, self::CONFLICT_ATTEMPTS);

        return $envelope->with(new TransportMessageIdStamp($id));
    }

    /**
     * The next available message, taken for this consumer alone; at most one.
     *
     * A message that cannot be decoded (its class is gone, its body is damaged) would be taken
     * again by every pass: it goes to `failed` with the reason, where an admin can see it, and
     * the exception the receiver contract asks for is thrown.
     *
     * @return list<Envelope>
     *
     * @throws UndecodableMessageException after moving the message to `failed`
     */
    public function get(): iterable
    {
        $envelope = $this->take($this->claim());

        return $envelope === null ? [] : [$envelope];
    }

    /**
     * That message, taken for this consumer alone like get() would, whether or not its delay is over; null when it is not waiting in this queue (already taken, handled, or gone).
     *
     * For a message its sender wants run now, in its own request (a backup archive): the claim
     * still guards it against a consumer that would take it at the same moment.
     *
     * @throws UndecodableMessageException after moving the message to `failed`
     */
    public function getById(int $id): ?Envelope
    {
        return $this->take($this->claim($id));
    }

    public function ack(Envelope $envelope): void
    {
        $this->release($envelope);
    }

    public function reject(Envelope $envelope): void
    {
        $this->release($envelope);
    }

    /**
     * Removes a message this transport handed out, done with: what ack() and reject() do, answering whether it was still this claim's.
     *
     * False when its holder was declared dead meanwhile (its process looked gone, or it ran past
     * every limit) and the message moved to `failed`: that failure stays, for an admin to see,
     * rather than being erased by the late ack of a holder that was alive after all.
     *
     * The claim is let go of only once the database took the delete: an ack it refused (locked,
     * connection lost) leaves the message this process's, or the next reap would see a live
     * process that no longer holds it and fail a handled message as killed.
     */
    public function release(Envelope $envelope): bool
    {
        $token = $envelope->last(QueueClaimStamp::class)?->token;
        if ($token === null) {
            throw new LogicException('No QueueClaimStamp found on the Envelope: it was not handed out by this transport.');
        }

        $released = $this->db->query(
            "DELETE FROM {$this->schema->table()} WHERE id = ? AND delivered_by = ? AND queue_name IN (" . SqlParameters::placeholders(count(self::QUEUES)) . ')',
            [self::idOf($envelope), $token, ...self::QUEUES]
        )->rowCount() === 1;
        $this->letGo($token);

        return $released;
    }

    public function find(mixed $id): ?Envelope
    {
        if (!is_numeric($id)) {
            return null;
        }
        $row = $this->db->loadSingle(
            "SELECT * FROM {$this->schema->table()} WHERE id = ? AND queue_name = ?",
            [(int)$id, $this->queueName]
        );

        return $row === null ? null : $this->decode($row);
    }

    /**
     * The messages waiting in this queue, delayed ones included, in the order they will be taken.
     *
     * @return list<Envelope>
     */
    public function all(?int $limit = null): iterable
    {
        $rows = $this->db->loadAll(
            "SELECT * FROM {$this->schema->table()} WHERE queue_name = ? AND delivered_at IS NULL ORDER BY available_at, id"
            . ($limit === null ? '' : ' LIMIT ' . max(0, $limit)),
            [$this->queueName]
        );

        return array_map($this->decode(...), $rows);
    }

    /** Whether a caller holds a transaction open on this queue's connection, which a failed send() leaves only able to roll back (QueueBus). */
    public function inTransaction(): bool
    {
        return $this->db->inTransaction();
    }

    /** The messages waiting in this queue, delayed ones included; not those being handled. */
    public function getMessageCount(): int
    {
        return (int)$this->db->scalar(
            "SELECT COUNT(*) FROM {$this->schema->table()} WHERE queue_name = ? AND delivered_at IS NULL",
            0,
            [$this->queueName]
        );
    }

    /** The class of the message get() would take now, without taking it: the consumer asks before it commits its budget. */
    public function peekNextType(): ?string
    {
        $type = $this->db->scalar(
            "SELECT message_type FROM {$this->schema->table()}"
            . ' WHERE queue_name = ? AND delivered_at IS NULL AND available_at <= ? ORDER BY available_at, id LIMIT 1',
            null,
            [$this->queueName, self::format($this->now())]
        );

        return $type === null ? null : (string)$type;
    }

    /** Since when the longest-waiting message of this queue has been available (in the future if all of them are delayed). */
    public function oldestPendingAt(): ?\DateTimeImmutable
    {
        $oldest = $this->db->scalar(
            "SELECT MIN(available_at) FROM {$this->schema->table()} WHERE queue_name = ? AND delivered_at IS NULL",
            null,
            [$this->queueName]
        );

        return $oldest === null ? null : self::parse((string)$oldest);
    }

    /**
     * Moves to `failed` the deliveries whose consumer must have died, and says which.
     *
     * Dead is what expiredClaims() says. Each move is guarded by the claim token, so two passes
     * reaping at once, or a pass and the holder's own end (failHeldClaims()), report a message once.
     *
     * @return list<array{id: int, type: string, mode: string, deliveredAt: string}>
     */
    public function reapKilled(): array
    {
        $killed = [];
        foreach ($this->expiredClaims() as $claim) {
            if ($this->failClaim($claim['id'], $claim['deliveredBy'], $claim['mode'])) {
                $killed[] = ['id' => $claim['id'], 'type' => $claim['type'], 'mode' => $claim['mode'], 'deliveredAt' => $claim['deliveredAt']];
            }
        }

        return $killed;
    }

    /**
     * Moves to `failed` the messages this transport still holds, as killed, and says which: what the consumer does when its process ends in the middle of one.
     *
     * A fatal error, PHP's time limit, an `exit` in a handler: the process will never ack them,
     * and an FPM child, a FrankenPHP worker, goes on living after such an end, so its pid alone
     * would not tell the next pass. A message the holder no longer carries (a pass reaped it
     * first) is left alone.
     *
     * @return list<array{id: int, type: string, mode: string, deliveredAt: string}>
     */
    public function failHeldClaims(): array
    {
        $killed = [];
        foreach ($this->held as $token => $claim) {
            $this->letGo($token);
            if ($this->failClaim($claim['id'], $token, $claim['mode'])) {
                $killed[] = $claim;
            }
        }

        return $killed;
    }

    /**
     * The deliveries whose consumer must have died and that nobody has moved to `failed` yet, as reapKilled() would move them.
     *
     * Dead, per claim (R19): a holder whose process is gone from this machine, at once; a holder
     * provably still at work, only past the ceiling (its own limit when longer); any other, past
     * the limit it recorded plus a grace, PHP having cut that process by then. The comparison is
     * made here in PHP, row by row -- the rows being handled are a handful, one per consumer --
     * since the limit varies per row and date arithmetic on a column is not portable.
     *
     * What a health check reads when no pass runs to reap them: a message stuck "in progress"
     * past the limit of its mode is not in the queue any more (it is claimed) and not yet in `failed`.
     *
     * @return list<array{id: int, type: string, mode: string, deliveredAt: string, deliveredBy: string}>
     */
    public function expiredClaims(): array
    {
        $now = $this->now();
        $expired = [];
        foreach ($this->claimedRows() as $row) {
            if (!$this->isDead($row, $now)) {
                continue;
            }
            $expired[] = [
                'id' => (int)$row['id'],
                'type' => (string)$row['message_type'],
                'mode' => (string)($row['delivered_mode'] ?? 'unknown'),
                'deliveredAt' => (string)$row['delivered_at'],
                'deliveredBy' => (string)$row['delivered_by'],
            ];
        }

        return $expired;
    }

    /**
     * Whether a consumer holds a message of this queue and is not dead (see expiredClaims()): it is at work now, however long ago it last said so.
     *
     * A blocking consumer beats between two messages, never during one, so a handler that runs
     * for an hour (a backup) leaves its heartbeat an hour old; the message it holds is what still
     * shows it alive. Judged in PHP for the same reason as expiredClaims().
     */
    public function isHandling(): bool
    {
        $now = $this->now();
        foreach ($this->claimedRows() as $row) {
            if (!$this->isDead($row, $now)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Who holds the claims of this queue that are not dead, and in which mode: what shows a blocking consumer at work inside one long message, when it does not beat.
     *
     * @return list<array{holder: string, mode: string}>
     */
    public function liveHolders(): array
    {
        $now = $this->now();
        $holders = [];
        foreach ($this->claimedRows() as $row) {
            if (!$this->isDead($row, $now)) {
                $holders[] = ['holder' => (string)($row['delivered_by'] ?? ''), 'mode' => (string)($row['delivered_mode'] ?? '')];
            }
        }

        return $holders;
    }

    /**
     * The rows a consumer of this queue holds, dead or alive: one per consumer at work, a handful.
     *
     * @return list<array<string, mixed>>
     */
    private function claimedRows(): array
    {
        return $this->db->loadAll(
            "SELECT id, message_type, delivered_at, delivered_by, delivered_mode, delivered_limit FROM {$this->schema->table()}"
            . ' WHERE queue_name = ? AND delivered_at IS NOT NULL',
            [$this->queueName]
        );
    }

    /** Puts a message in `failed`, waiting there for an admin, with the reason, dated from now (pruneFailed()). */
    public function moveToFailed(int $id, string $error): void
    {
        $this->db->query(
            "UPDATE {$this->schema->table()} SET queue_name = ?, last_error = ?, created_at = ?, " . self::UNCLAIMED
            . ' WHERE id = ? AND queue_name IN (' . SqlParameters::placeholders(count(self::QUEUES)) . ')',
            [self::FAILED_QUEUE, $error, self::format($this->now()), $id, ...self::QUEUES]
        );
    }

    /**
     * The cause written for a message whose consumer was killed in that mode: what an admin reads, the mode itself being the row's `killed_in`.
     *
     * Also the cause of its run in the Journal, and of its task's last run.
     */
    public static function killedCause(string $mode): string
    {
        return "Its {$mode} consumer was stopped before it was done: too long, or too much memory, for that mode";
    }

    /**
     * Gives a failed message another go, as if it had just been sent.
     *
     * Its envelope loses the stamps of its first life: the retry count, so that it gets its
     * retries again, and the "sent to the failure transport" mark, with which Messenger would
     * drop it rather than send it back to `failed` if it failed again. A message that can no
     * longer be decoded goes back as it is (an extension may have brought its class back; if not,
     * get() returns it to `failed`).
     */
    public function requeue(int $id): void
    {
        $table = $this->schema->table();
        $row = $this->db->loadSingle("SELECT * FROM {$table} WHERE id = ? AND queue_name = ?", [$id, self::FAILED_QUEUE]);
        if ($row === null) {
            return;
        }

        $body = (string)$row['body'];
        $headers = (string)$row['headers'];
        try {
            $encoded = $this->serializer->encode(
                $this->decode($row)
                    ->withoutAll(TransportMessageIdStamp::class)
                    ->withoutAll(RedeliveryStamp::class)
                    ->withoutAll(SentToFailureTransportStamp::class)
                    ->withoutAll(ErrorDetailsStamp::class)
                    ->withoutAll(DelayStamp::class)
            );
            $body = $encoded['body'];
            $headers = json_encode($encoded['headers'] ?? [], JSON_THROW_ON_ERROR);
        } catch (MessageDecodingFailedException) {
        }

        $this->db->query(
            "UPDATE {$table} SET queue_name = ?, body = ?, headers = ?, attempts = 0, last_error = NULL, killed_in = NULL, available_at = ?, " . self::UNCLAIMED
            . ' WHERE id = ? AND queue_name = ?',
            [self::DEFAULT_QUEUE, $body, $headers, self::format($this->now()), $id, self::FAILED_QUEUE]
        );
    }

    /**
     * Forgets the messages that have waited in `failed` for more than $days, and answers how many went.
     *
     * Counted from `created_at`, which in `failed` is when the message got there: Messenger sends
     * it there as a new row, and a message killed or found undecodable is dated anew as it moves,
     * however long it waited before. Nothing waiting in `default` is ever pruned, however old:
     * that is work still to do.
     */
    public function pruneFailed(int $days): int
    {
        return $this->db->query(
            "DELETE FROM {$this->schema->table()} WHERE queue_name = ? AND created_at < ?",
            [self::FAILED_QUEUE, self::format($this->now()->modify("-{$days} days"))]
        )->rowCount();
    }

    /**
     * Removes a message waiting in `failed`, answering whether it was there; one that has left it is left alone.
     *
     * An admin acts on the failed list as the page showed it: by then the message may have been
     * retried (another tab, another admin) and taken by a consumer, which must not lose it while it runs.
     */
    public function deleteFailed(int $id): bool
    {
        return $this->db->query(
            "DELETE FROM {$this->schema->table()} WHERE id = ? AND queue_name = ?",
            [$id, self::FAILED_QUEUE]
        )->rowCount() === 1;
    }

    /**
     * Takes the next available message (or the one asked for) for this consumer alone, and answers its row.
     *
     * Picks a candidate, marks it with a token only this call knows -- on the condition that
     * nobody marked it first -- and reads back what carries the token. On MySQL and PostgreSQL
     * the candidate is also locked (FOR UPDATE), so a second consumer waits for the first to
     * commit and then moves on to the next row; on SQLite the transaction is BEGIN IMMEDIATE,
     * which already lets one writer in at a time.
     *
     * @param int|null $id that message rather than the next available one
     *
     * @return array<string, mixed>|null
     */
    private function claim(?int $id = null): ?array
    {
        // An idle blocking consumer asks every second: "nothing" is answered without the
        // transaction, whose BEGIN IMMEDIATE takes SQLite's write lock from the wiki's requests.
        if ($id === null && $this->peekNextType() === null) {
            return null;
        }

        $table = $this->schema->table();
        $now = self::format($this->now());
        $token = QueueClaimHolder::thisProcess()->token();

        $row = $this->db->transactional(function () use ($table, $now, $token, $id): ?array {
            $candidate = $id === null
                ? $this->db->scalar(
                    "SELECT id FROM {$table} WHERE queue_name = ? AND delivered_at IS NULL AND available_at <= ?"
                    . ' ORDER BY available_at, id LIMIT 1'
                    . $this->db->dialect()->lockRowsClause(),
                    null,
                    [$this->queueName, $now]
                )
                : $this->db->scalar(
                    "SELECT id FROM {$table} WHERE id = ? AND queue_name = ? AND delivered_at IS NULL"
                    . $this->db->dialect()->lockRowsClause(),
                    null,
                    [$id, $this->queueName]
                );
            if ($candidate === null) {
                return null;
            }

            $this->db->query(
                "UPDATE {$table} SET delivered_at = ?, delivered_by = ?, delivered_mode = ?, delivered_limit = ?"
                . ' WHERE id = ? AND queue_name = ? AND delivered_at IS NULL',
                [$now, $token, $this->claimMode, $this->claimLimit, (int)$candidate, $this->queueName]
            );

            return $this->db->loadSingle("SELECT * FROM {$table} WHERE id = ? AND queue_name = ? AND delivered_by = ?", [(int)$candidate, $this->queueName, $token]);
        }, self::CONFLICT_ATTEMPTS);
        if ($row !== null) {
            $this->held[$token] = [
                'id' => (int)$row['id'],
                'type' => (string)$row['message_type'],
                'mode' => (string)($row['delivered_mode'] ?? 'unknown'),
                'deliveredAt' => (string)$row['delivered_at'],
            ];
            self::$heldHere[$token] = true;
        }

        return $row;
    }

    /**
     * The envelope of a row just claimed; a row that cannot be decoded goes to `failed` with the reason.
     *
     * @param array<string, mixed>|null $row
     *
     * @throws UndecodableMessageException
     */
    private function take(?array $row): ?Envelope
    {
        if ($row === null) {
            return null;
        }

        try {
            return $this->decode($row)->with(new QueueClaimStamp((string)$row['delivered_by']));
        } catch (MessageDecodingFailedException $undecodable) {
            $this->letGo((string)$row['delivered_by']);
            $this->moveToFailed((int)$row['id'], 'undecodable: ' . $undecodable->getMessage());

            throw new UndecodableMessageException((int)$row['id'], (string)$row['message_type'], $undecodable);
        }
    }

    /** @param array<string, mixed> $row */
    private function decode(array $row): Envelope
    {
        $headers = json_decode((string)$row['headers'], true);
        $envelope = $this->serializer->decode([
            'body' => (string)$row['body'],
            'headers' => is_array($headers) ? $headers : [],
        ]);

        return $envelope->withoutAll(TransportMessageIdStamp::class)->with(new TransportMessageIdStamp((int)$row['id']));
    }

    private static function idOf(Envelope $envelope): int
    {
        $stamp = $envelope->last(TransportMessageIdStamp::class);
        if ($stamp === null) {
            throw new LogicException('No TransportMessageIdStamp found on the Envelope.');
        }

        return (int)$stamp->getId();
    }

    /**
     * Whether the holder of a claimed row must be dead by now (see expiredClaims()).
     *
     * @param array<string, mixed> $row with `delivered_at`, `delivered_by` and `delivered_limit`
     */
    private function isDead(array $row, \DateTimeImmutable $now): bool
    {
        $limit = $row['delivered_limit'] === null ? self::UNRECORDED_LIMIT_SECONDS : (int)$row['delivered_limit'];

        $seconds = match ($this->holderState((string)($row['delivered_by'] ?? ''))) {
            QueueClaimHolder::GONE => null,
            QueueClaimHolder::ALIVE => max($limit, self::ALIVE_CEILING_SECONDS),
            default => $limit,
        };

        return $seconds === null || $now > self::parse((string)$row['delivered_at'])->modify('+' . ($seconds + self::KILL_GRACE_SECONDS) . ' seconds');
    }

    /**
     * Whether the holder a claim token names is ALIVE, GONE or UNKNOWN, as this process can tell.
     *
     * Its own claims, this process knows: held now, alive; not held, they were taken by a request
     * it served before, which ended without letting go (a fatal error in a shutdown function, where
     * nothing can run after it) -- unless the process runs requests side by side on threads
     * (FrankenPHP), when another thread may hold it. Another process's: gone when the machine says
     * so; alive only when it is a process of its own (the CLI), since a server's process outlives
     * the request that claimed and can only be judged by the time rule.
     */
    private function holderState(string $token): string
    {
        $holder = QueueClaimHolder::ofToken($token);
        if ($holder === null) {
            return QueueClaimHolder::UNKNOWN;
        }
        $here = QueueClaimHolder::thisProcess();
        if ($holder->isSameProcessAs($here)) {
            if (isset(self::$heldHere[$token])) {
                return QueueClaimHolder::ALIVE;
            }

            return ZEND_THREAD_SAFE ? QueueClaimHolder::UNKNOWN : QueueClaimHolder::GONE;
        }

        $process = $holder->processSeenFrom($here);
        if ($process === QueueClaimHolder::ALIVE && !$holder->ownProcess) {
            return QueueClaimHolder::UNKNOWN;
        }

        return $process;
    }

    /** Moves that message to `failed` as killed in that mode, dated from now (pruneFailed()), if it still carries that claim; answers whether it did. */
    private function failClaim(int $id, string $token, string $mode): bool
    {
        return $this->db->query(
            "UPDATE {$this->schema->table()} SET queue_name = ?, last_error = ?, killed_in = ?, created_at = ?, " . self::UNCLAIMED
            . ' WHERE id = ? AND queue_name = ? AND delivered_by = ?',
            [self::FAILED_QUEUE, self::killedCause($mode), $mode, self::format($this->now()), $id, $this->queueName, $token]
        )->rowCount() === 1;
    }

    /** This transport is done with that claim, whatever became of its message. */
    private function letGo(string $token): void
    {
        unset($this->held[$token], self::$heldHere[$token]);
    }

    /**
     * A dedup key as its column holds it. One longer -- `search:reindex:` and a tag of up to 191
     * characters -- keeps a readable start and the hash of the whole: still the same key on every
     * send, and two that differ only past the width stay apart.
     */
    private static function fitted(?string $key): ?string
    {
        if ($key === null || mb_strlen($key) <= QueueSchema::DEDUP_KEY_LENGTH) {
            return $key;
        }

        return mb_substr($key, 0, QueueSchema::DEDUP_KEY_LENGTH - 41) . '#' . sha1($key);
    }

    /** Whether this is a fresh trigger rather than one of Messenger's re-sends (see send()). */
    private function isFirstSend(Envelope $envelope): bool
    {
        return $this->queueName !== self::FAILED_QUEUE
            && self::retries($envelope) === 0
            && $envelope->last(SentToFailureTransportStamp::class) === null;
    }

    /** The cause of a definitive failure, from the stamp Messenger leaves on the envelope; null when it left none. */
    private static function errorOf(Envelope $envelope): ?string
    {
        $details = $envelope->last(ErrorDetailsStamp::class);
        if ($details === null) {
            return null;
        }

        return mb_substr($details->getExceptionClass() . ': ' . $details->getExceptionMessage(), 0, self::ERROR_LENGTH);
    }

    /** How many times the message was already retried: what `attempts` shows. The failure transport adds a RedeliveryStamp(0) after the real count, hence the highest rather than the last. */
    private static function retries(Envelope $envelope): int
    {
        $counts = array_map(static fn (RedeliveryStamp $stamp): int => $stamp->getRetryCount(), $envelope->all(RedeliveryStamp::class));

        return $counts === [] ? 0 : max($counts);
    }

    private function now(): \DateTimeImmutable
    {
        return $this->clock->now()->setTimezone(new \DateTimeZone('UTC'));
    }

    private static function format(\DateTimeImmutable $time): string
    {
        return $time->format(self::TIME_FORMAT);
    }

    private static function parse(string $time): \DateTimeImmutable
    {
        return new \DateTimeImmutable($time, new \DateTimeZone('UTC'));
    }
}
