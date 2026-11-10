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
use YesWiki\Kernel\Queue\QueueClaimStamp;
use YesWiki\Kernel\Queue\QueueConsumerMode;
use YesWiki\Kernel\Queue\QueueFailureCause;
use YesWiki\Kernel\Queue\ScheduledTask;
use YesWiki\Kernel\Queue\ToleratedFailure;
use YesWiki\Kernel\Queue\UndecodableMessageException;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Kernel\Service\RequestScopedState;

/**
 * The Messenger transport of the queue: one row per message in the wiki's own database, written on DbService.
 * One table holds the `default` and `failed` queues, told apart by `queue_name`.
 * Every time is the wiki's local time on PHP's clock; whether a claim's holder is dead is judged by isOverdue().
 * Every claim carries the token of the pass that took it, and that pass holds a lock on a file of its token (QueueHolderLock) from before its token is written until its row is closed: a holder that holds it is alive, one that left it free is gone, and one with no file waits an hour and a minute.
 */
class QueueTransport implements TransportInterface, MessageCountAwareInterface, RequestScopedState
{
    public const DEFAULT_QUEUE = 'default';
    public const FAILED_QUEUE = 'failed';

    public const QUEUES = [self::DEFAULT_QUEUE, self::FAILED_QUEUE];

    /** How long a claim or a pass whose lock file is absent is given from when it was taken: the longest a host lets a process live, and a minute. */
    public const ABSENT_HOLDER_SECONDS = 3660;

    private const CONFLICT_ATTEMPTS = 3;

    private const RACE_LOOKS = 3;

    private const TIME_FORMAT = 'Y-m-d H:i:s';

    private const UNCLAIMED = 'delivered_at = NULL, delivered_by = NULL, delivered_mode = NULL';

    private ?string $claimMode = null;

    /** The token of the pass this transport takes messages for, whose lock that pass holds; null outside a pass, where nothing can be taken. */
    private ?string $passToken = null;

    public function __construct(
        private readonly DbService $db,
        private readonly QueueSchema $schema,
        private readonly ClockInterface $clock,
        private readonly SerializerInterface $serializer = new PhpSerializer(),
        private string $queueName = self::DEFAULT_QUEUE,
        private readonly ?QueueHolderLock $holderLock = null,
    ) {
    }

    /** Forgets the claim mode and the pass token, so that in worker mode a request never inherits the ones of the request before. */
    public function startNewRequest(): void
    {
        $this->claimMode = null;
        $this->passToken = null;
    }

    /** The token of the pass that now takes messages, written on each of its claims; null once it is over, so that a later claim never carries a token whose lock is let go. */
    public function workFor(?string $passToken): void
    {
        $this->passToken = $passToken;
    }

    /** The same table seen as another queue: `withQueue('failed')` is the failure transport. */
    public function withQueue(string $queueName): self
    {
        $other = clone $this;
        $other->queueName = $queueName;

        return $other;
    }

    /** Recorded on each row get() takes, so that a delivery found killed is failed as killed in that mode. */
    public function claimAs(QueueConsumerMode $mode): void
    {
        $this->claimMode = $mode->value;
    }

    /**
     * Queues the message after its DelayStamp; a first send whose dedup key already waits is absorbed by that message.
     * On `failed`, a ToleratedFailure is not kept: the Worker then rejects the original, so its row goes, and its failure stays in the history and the Journal.
     */
    public function send(Envelope $envelope): Envelope
    {
        if ($this->queueName === self::FAILED_QUEUE && $envelope->getMessage() instanceof ToleratedFailure) {
            return $envelope;
        }
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
     * Like get(), but only a message whose id is at most $lastId: what was sent after that point stays for a later pass.
     *
     * @return list<Envelope>
     *
     * @throws UndecodableMessageException
     */
    public function getUpTo(int $lastId): iterable
    {
        $envelope = $this->take($this->claim(null, $lastId));

        return $envelope === null ? [] : [$envelope];
    }

    /** The highest id this queue has handed out, whatever the state of its message; 0 when it never held any. */
    public function lastId(): int
    {
        return (int)$this->db->scalar(
            "SELECT MAX(id) FROM {$this->schema->table()} WHERE queue_name = ?",
            0,
            [$this->queueName]
        );
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

        $id = self::idOf($envelope);
        $released = $this->db->query(
            "DELETE FROM {$this->schema->table()} WHERE id = ? AND delivered_by = ? AND queue_name IN (" . SqlParameters::placeholders(count(self::QUEUES)) . ')',
            [$id, $token, ...self::QUEUES]
        )->rowCount() === 1;

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

    /** The class of the message get() would take now (getUpTo($upToId) when given), without taking it. */
    public function peekNextType(?int $upToId = null): ?string
    {
        $type = $this->db->scalar(
            "SELECT message_type FROM {$this->schema->table()}"
            . ' WHERE queue_name = ? AND delivered_at IS NULL AND available_at <= ?'
            . ($upToId === null ? '' : ' AND id <= ?')
            . ' ORDER BY available_at, id LIMIT 1',
            null,
            [$this->queueName, self::format($this->now()), ...($upToId === null ? [] : [$upToId])]
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
     * Moves to `failed` the deliveries expiredClaims() calls dead, each once whoever else reaps, and returns them; the holder's file is the consumer's reaper's to remove, once its pass is closed too.
     *
     * @return list<array{id: int, type: string, mode: string, deliveredAt: string, deliveredBy: string}>
     */
    public function reapKilled(): array
    {
        return array_values(array_filter(
            $this->expiredClaims(),
            fn (array $claim): bool => $this->failClaim($claim['id'], $claim['deliveredBy'], $claim['mode'])
        ));
    }

    /**
     * The deliveries whose consumer must be dead (isOverdue()) and that nobody has moved to `failed` yet; it only reads, leaving every lock file where it is.
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

    /** @return list<array<string, mixed>> */
    private function claimedRows(): array
    {
        return $this->db->loadAll(
            "SELECT id, message_type, delivered_at, delivered_by, delivered_mode FROM {$this->schema->table()}"
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

    /** The cause written for a message whose consumer was killed in that mode, in `failed`, the queue's history and its task's last run. */
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
     * Claims the next available message (or that one, or the next of those with an id up to $upToId) under the token of the pass, and answers its row.
     *
     * The pass locked its token before it gave it here, so a judge never finds the file of a written token free while the pass runs.
     *
     * @return array<string, mixed>|null
     *
     * @throws LogicException outside a pass: a claim with no token to name its holder could never be judged
     */
    private function claim(?int $id = null, ?int $upToId = null): ?array
    {
        if ($this->passToken === null) {
            throw new LogicException('A message is taken for a pass, whose token names its holder: none is set.');
        }
        if ($id === null && $this->peekNextType($upToId) === null) {
            return null;
        }

        return $this->transactionalClaim($this->schema->table(), self::format($this->now()), $this->passToken, $id, $upToId);
    }

    /**
     * Takes that message, or the next one waiting, for that token in one transaction.
     *
     * @return array<string, mixed>|null
     */
    private function transactionalClaim(string $table, string $now, string $token, ?int $id, ?int $upToId = null): ?array
    {
        return $this->db->transactional(function () use ($table, $now, $token, $id, $upToId): ?array {
            $candidate = $id === null
                ? $this->db->scalar(
                    "SELECT id FROM {$table} WHERE queue_name = ? AND delivered_at IS NULL AND available_at <= ?"
                    . ($upToId === null ? '' : ' AND id <= ?')
                    . ' ORDER BY available_at, id LIMIT 1'
                    . $this->db->dialect()->lockRowsClause(),
                    null,
                    [$this->queueName, $now, ...($upToId === null ? [] : [$upToId])]
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
                "UPDATE {$table} SET delivered_at = ?, delivered_by = ?, delivered_mode = ?, dedup_key = NULL"
                . ' WHERE id = ? AND queue_name = ? AND delivered_at IS NULL',
                [$now, $token, $this->claimMode, (int)$candidate, $this->queueName]
            );

            return $this->db->loadSingle("SELECT * FROM {$table} WHERE id = ? AND queue_name = ? AND delivered_by = ?", [(int)$candidate, $this->queueName, $token]);
        }, self::CONFLICT_ATTEMPTS);
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
        return $this->isOverdue((string)($row['delivered_by'] ?? ''), self::parse((string)$row['delivered_at']), $now);
    }

    /**
     * The one verdict on a holder (ADR-0030, "The transport and its claims"), for a claim and for a pass: never while it holds its file, at once when its file is free,
     * and with no file once ABSENT_HOLDER_SECONDS have passed since $since.
     */
    public function isOverdue(string $token, \DateTimeImmutable $since, \DateTimeImmutable $now): bool
    {
        return match ($this->holderState($token)) {
            QueueHolderLock::HELD => false,
            QueueHolderLock::FREE => true,
            // Both callers parse $since back from its stored string; one made from the clock in the first occurrence of the autumn's repeated hour would add 7260 s here, not 3660.
            default => $now > $since->modify('+' . self::ABSENT_HOLDER_SECONDS . ' seconds'),
        };
    }

    /** What the file of that token says: HELD, FREE, or ABSENT when there is none (or no lock to read). Read only. */
    public function holderState(string $token): string
    {
        return $this->holderLock?->stateOf($token) ?? QueueHolderLock::ABSENT;
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
        return $this->clock->now();
    }

    private static function format(\DateTimeImmutable $time): string
    {
        return $time->format(self::TIME_FORMAT);
    }

    private static function parse(string $time): \DateTimeImmutable
    {
        return new \DateTimeImmutable($time);
    }
}
