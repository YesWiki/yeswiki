<?php

namespace YesWiki\Kernel\Service\Queue;

use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\LogicException;
use Symfony\Component\Messenger\Exception\MessageDecodingFailedException;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Messenger\Stamp\ErrorDetailsStamp;
use Symfony\Component\Messenger\Stamp\RedeliveryStamp;
use Symfony\Component\Messenger\Stamp\SentToFailureTransportStamp;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;
use Symfony\Component\Messenger\Transport\Receiver\MessageCountAwareInterface;
use Symfony\Component\Messenger\Transport\Serialization\PhpSerializer;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;
use YesWiki\Kernel\Database\SqlParameters;
use YesWiki\Kernel\Queue\Deduplicated;
use YesWiki\Kernel\Queue\NotReplayableByHand;
use YesWiki\Kernel\Queue\QueueClaimHolder;
use YesWiki\Kernel\Queue\QueueClaimStamp;
use YesWiki\Kernel\Queue\QueueConsumerMode;
use YesWiki\Kernel\Queue\QueueFailureCause;
use YesWiki\Kernel\Queue\ScheduledTask;
use YesWiki\Kernel\Queue\UndecodableMessageException;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Kernel\Service\RequestScopedState;

/**
 * The Messenger transport of the queue: one row per message in the wiki's own database, written on DbService.
 * One table holds the `default` and `failed` queues, told apart by `queue_name`.
 * Every time is the database's (DatabaseClock), in UTC; whether a claim's holder is dead is judged per R19.
 */
class QueueTransport implements TransportInterface, MessageCountAwareInterface, RequestScopedState
{
    public const DEFAULT_QUEUE = 'default';
    public const FAILED_QUEUE = 'failed';

    public const QUEUES = [self::DEFAULT_QUEUE, self::FAILED_QUEUE];

    private const KILL_GRACE_SECONDS = 60;

    private const UNRECORDED_LIMIT_SECONDS = QueueBudgetFactory::KILL_LIMIT_NO_PHP_LIMIT;

    private const ALIVE_CEILING_SECONDS = QueueBudgetFactory::KILL_LIMIT_NO_PHP_LIMIT;

    private const CONFLICT_ATTEMPTS = 3;

    private const RACE_LOOKS = 3;

    private const TIME_FORMAT = 'Y-m-d H:i:s';

    private const UNCLAIMED = 'delivered_at = NULL, delivered_by = NULL, delivered_mode = NULL, delivered_limit = NULL';

    private ?string $claimMode = null;
    private ?int $claimLimit = null;

    /** @var array<string, array{id: int, type: string, mode: string, deliveredAt: string}> */
    private array $held = [];

    /** @var array<string, true> */
    private static array $heldHere = [];

    public function __construct(
        private readonly DbService $db,
        private readonly QueueSchema $schema,
        private readonly ClockInterface $clock,
        private readonly SerializerInterface $serializer = new PhpSerializer(),
        private string $queueName = self::DEFAULT_QUEUE,
    ) {
    }

    /** Forgets the claim mode, so that in worker mode a request never inherits the one of the request before. */
    public function startNewRequest(): void
    {
        $this->claimMode = null;
        $this->claimLimit = null;
    }

    /** The same table seen as another queue: `withQueue('failed')` is the failure transport. */
    public function withQueue(string $queueName): self
    {
        $other = clone $this;
        $other->queueName = $queueName;

        return $other;
    }

    /** Recorded on each row get() takes, so that the next pass can tell a killed delivery once its limit is exceeded. */
    public function claimAs(QueueConsumerMode $mode, int $limitSeconds): void
    {
        $this->claimMode = $mode->value;
        $this->claimLimit = $limitSeconds;
    }

    /** Queues the message after its DelayStamp; a first send whose dedup key already waits is absorbed by that message. */
    public function send(Envelope $envelope): Envelope
    {
        $envelope = $envelope->withoutAll(TransportMessageIdStamp::class);
        $now = $this->now();
        $delay = $envelope->last(DelayStamp::class)?->getDelay() ?? 0;
        $availableAt = $delay > 0 ? $now->modify("+{$delay} milliseconds") : $now;
        $key = $this->isFirstSend($envelope) ? self::fitted(self::deduplicationKeyOf($envelope->getMessage())) : null;
        $error = $this->queueName === self::FAILED_QUEUE ? QueueFailureCause::of($envelope) : null;
        $encoded = $this->serializer->encode($envelope);
        $table = $this->schema->table();

        $id = $this->db->transactional(function () use ($envelope, $now, $availableAt, $key, $error, $encoded, $table): int {
            for ($look = 1;; $look++) {
                $waiting = $key === null ? null : $this->waitingUnder($this->queueName, $key, locked: $look > 1);
                if ($waiting !== null) {
                    return $waiting;
                }

                $inserted = $this->db->query(
                    "INSERT INTO {$table} (queue_name, message_type, body, headers, dedup_key, created_at, available_at, attempts, last_error)"
                    . ' VALUES (' . SqlParameters::placeholders(9) . ')'
                    . ($key !== null ? $this->db->dialect()->onConflictDoNothing(['queue_name', 'dedup_key']) : ''),
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
                if ($key === null || $inserted->rowCount() === 1) {
                    return $this->db->lastInsertId();
                }
                if ($look >= self::RACE_LOOKS) {
                    throw new \RuntimeException("The queue kept refusing a message under {$key} that no longer waited");
                }
            }
        }, self::CONFLICT_ATTEMPTS);

        return $envelope->with(new TransportMessageIdStamp($id));
    }

    /**
     * The next available message, claimed for this consumer alone; one that cannot be decoded goes to `failed`.
     *
     * @return list<Envelope>
     *
     * @throws UndecodableMessageException
     */
    public function get(): iterable
    {
        $envelope = $this->take($this->claim());

        return $envelope === null ? [] : [$envelope];
    }

    /**
     * That message, claimed like get() would whether or not its delay is over; null when it no longer waits here.
     *
     * @throws UndecodableMessageException
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

    /** What ack() and reject() do; false when the message was failed meanwhile, its holder having been declared dead. */
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
     * Runs $work in one transaction on this queue's connection, so that what it writes besides a send() is kept or undone with it;
     * the whole of it is retried on a conflict, which a send() inside it no longer is on its own.
     *
     * @template T
     *
     * @param callable(): T $work
     *
     * @return T
     */
    public function atomically(callable $work): mixed
    {
        return $this->db->transactional($work, self::CONFLICT_ATTEMPTS);
    }

    /** Whether a caller's transaction is open on this queue's connection, which a failed send() leaves only able to roll back. */
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

    /** The class of the message get() would take now, without taking it. */
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

    /** Since when the longest-waiting message has been available (in the future if all of them are delayed). */
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
     * Moves to `failed` the deliveries expiredClaims() calls dead, each once whoever else reaps, and returns them.
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
     * Moves to `failed`, as killed, the claims this transport still holds: what the consumer does as its process ends.
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
     * The deliveries whose consumer must be dead (R19) and that nobody has moved to `failed` yet.
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

    /** Whether a consumer that is not dead holds a message of this queue, however old its last heartbeat. */
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
     * Who holds the claims of this queue that are not dead, and in which mode.
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

    /** @return list<array<string, mixed>> */
    private function claimedRows(): array
    {
        return $this->db->loadAll(
            "SELECT id, message_type, delivered_at, delivered_by, delivered_mode, delivered_limit FROM {$this->schema->table()}"
            . ' WHERE queue_name = ? AND delivered_at IS NOT NULL',
            [$this->queueName]
        );
    }

    /** Puts a message in `failed` with the reason, dated from now so that pruneFailed() counts from its arrival there. */
    public function moveToFailed(int $id, string $error): void
    {
        $this->db->query(
            "UPDATE {$this->schema->table()} SET queue_name = ?, last_error = ?, created_at = ?, dedup_key = NULL, " . self::UNCLAIMED
            . ' WHERE id = ? AND queue_name IN (' . SqlParameters::placeholders(count(self::QUEUES)) . ')',
            [self::FAILED_QUEUE, $error, self::format($this->now()), $id, ...self::QUEUES]
        );
    }

    /** The cause written for a message whose consumer was killed in that mode, in `failed`, the Journal and its task's last run. */
    public static function killedCause(string $mode): string
    {
        return "Its {$mode} consumer was stopped before it was done: too long, or too much memory, for that mode";
    }

    /** Gives a failed message another go as a fresh send; false when it left `failed` or is NotReplayableByHand. */
    public function requeue(int $id): bool
    {
        $table = $this->schema->table();
        $row = $this->db->loadSingle("SELECT * FROM {$table} WHERE id = ? AND queue_name = ?", [$id, self::FAILED_QUEUE]);
        if ($row === null || !self::replayableByHand((string)$row['message_type'])) {
            return false;
        }

        $body = (string)$row['body'];
        $headers = (string)$row['headers'];
        $key = null;
        try {
            $envelope = $this->decode($row);
            $key = self::fitted(self::deduplicationKeyOf($envelope->getMessage()));
            $encoded = $this->serializer->encode(
                $envelope
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

        for ($attempt = 1;; $attempt++) {
            try {
                return $this->db->transactional(function () use ($table, $id, $body, $headers, $key): bool {
                    if ($key !== null && $this->waitingUnder(self::DEFAULT_QUEUE, $key, locked: true) !== null) {
                        return $this->db->query("DELETE FROM {$table} WHERE id = ? AND queue_name = ?", [$id, self::FAILED_QUEUE])->rowCount() === 1;
                    }

                    return $this->db->query(
                        "UPDATE {$table} SET queue_name = ?, body = ?, headers = ?, dedup_key = ?, attempts = 0, last_error = NULL, killed_in = NULL, available_at = ?, " . self::UNCLAIMED
                        . ' WHERE id = ? AND queue_name = ?',
                        [self::DEFAULT_QUEUE, $body, $headers, $key, self::format($this->now()), $id, self::FAILED_QUEUE]
                    )->rowCount() === 1;
                }, self::CONFLICT_ATTEMPTS);
            } catch (\Exception $refused) {
                if ($attempt >= self::RACE_LOOKS || !self::isDuplicateKey($refused)) {
                    throw $refused;
                }
            }
        }
    }

    /** The first send waiting under that key in that queue; locked, it reads the latest committed row, not the snapshot. */
    private function waitingUnder(string $queue, string $key, bool $locked = false): ?int
    {
        $id = $this->db->scalar(
            "SELECT id FROM {$this->schema->table()} WHERE queue_name = ? AND dedup_key = ?"
            . ($locked ? $this->db->dialect()->lockRowsClause() : ''),
            null,
            [$queue, $key]
        );

        return $id === null ? null : (int)$id;
    }

    /** Whether the database refused a write for a unique key already taken: SQLSTATE 23505 or 23000. */
    private static function isDuplicateKey(\Throwable $failure): bool
    {
        for ($cause = $failure; $cause !== null; $cause = $cause->getPrevious()) {
            if ($cause instanceof \PDOException && in_array((string)$cause->getCode(), ['23000', '23505'], true)) {
                return true;
            }
        }

        return false;
    }

    /** The key a Deduplicated message declares, `task:<id>` for a scheduled task's run, null otherwise. */
    private static function deduplicationKeyOf(object $message): ?string
    {
        return match (true) {
            $message instanceof Deduplicated => $message->deduplicationKey(),
            $message instanceof ScheduledTask => 'task:' . $message->taskId(),
            default => null,
        };
    }

    /** Whether the jobs page may send a failed message of that type again: every type but NotReplayableByHand. */
    public static function replayableByHand(string $type): bool
    {
        return !is_a($type, NotReplayableByHand::class, true);
    }

    /** Forgets the messages in `failed` for more than $days, and answers how many; nothing in `default` is ever pruned. */
    public function pruneFailed(int $days): int
    {
        return $this->db->query(
            "DELETE FROM {$this->schema->table()} WHERE queue_name = ? AND created_at < ?",
            [self::FAILED_QUEUE, self::format($this->now()->modify("-{$days} days"))]
        )->rowCount();
    }

    /** Removes a message waiting in `failed`, answering whether it was there; one that has left it is left alone. */
    public function deleteFailed(int $id): bool
    {
        return $this->db->query(
            "DELETE FROM {$this->schema->table()} WHERE id = ? AND queue_name = ?",
            [$id, self::FAILED_QUEUE]
        )->rowCount() === 1;
    }

    /**
     * Claims the next available message (or that one) with a token only this call knows, and answers its row.
     *
     * @return array<string, mixed>|null
     */
    private function claim(?int $id = null): ?array
    {
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
                "UPDATE {$table} SET delivered_at = ?, delivered_by = ?, delivered_mode = ?, delivered_limit = ?, dedup_key = NULL"
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

    /** @param array<string, mixed> $row */
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

    /** Whether the holder a claim token names is ALIVE, GONE or UNKNOWN, as far as this process can tell. */
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

    /** Moves that message to `failed` as killed in that mode, if it still carries that claim. */
    private function failClaim(int $id, string $token, string $mode): bool
    {
        return $this->db->query(
            "UPDATE {$this->schema->table()} SET queue_name = ?, last_error = ?, killed_in = ?, created_at = ?, dedup_key = NULL, " . self::UNCLAIMED
            . ' WHERE id = ? AND queue_name = ? AND delivered_by = ?',
            [self::FAILED_QUEUE, self::killedCause($mode), $mode, self::format($this->now()), $id, $this->queueName, $token]
        )->rowCount() === 1;
    }

    private function letGo(string $token): void
    {
        unset($this->held[$token], self::$heldHere[$token]);
    }

    /** A dedup key cut to its column: a readable start and the hash of the whole, so that long keys stay distinct. */
    private static function fitted(?string $key): ?string
    {
        if ($key === null || mb_strlen($key) <= QueueSchema::DEDUP_KEY_LENGTH) {
            return $key;
        }

        return mb_substr($key, 0, QueueSchema::DEDUP_KEY_LENGTH - 41) . '#' . sha1($key);
    }

    /** Whether this is a fresh trigger rather than one of Messenger's re-sends. */
    private function isFirstSend(Envelope $envelope): bool
    {
        return $this->queueName !== self::FAILED_QUEUE
            && self::retries($envelope) === 0
            && $envelope->last(SentToFailureTransportStamp::class) === null;
    }

    /** The highest RedeliveryStamp count: the failure transport adds a RedeliveryStamp(0) after the real one. */
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
