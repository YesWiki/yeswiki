<?php

namespace YesWiki\Kernel\Service\Queue;

use Symfony\Component\Scheduler\Generator\CheckpointInterface;
use YesWiki\Files\Service\RuntimeLock;
use YesWiki\Kernel\Service\TripleStore;

/**
 * Where the Scheduler keeps how far it got, in two triples of the resource RESOURCE, under RuntimeLock's file lock;
 * release() also writes the next due time (NEXT_DUE_AT), which makes a tick and the end-of-request check cheap.
 */
class QueueCheckpoint implements CheckpointInterface
{
    public const RESOURCE = 'queue:checkpoint';

    public const STATE = 'https://yeswiki.net/vocabulary/queue/checkpointState';

    public const NEXT_DUE_AT = 'https://yeswiki.net/vocabulary/queue/nextDueAt';

    public const LOCK = 'cache/queue-schedule.lock';

    private const STATE_FORMAT = 'Y-m-d H:i:s.u';

    private const TIME_FORMAT = 'Y-m-d H:i:s';

    /** @var resource|null */
    private $lock;

    private \DateTimeImmutable $from;
    private \DateTimeImmutable $time;
    private int $index = -1;

    public function __construct(
        private readonly TripleStore $triples,
        private readonly RuntimeLock $runtimeLock,
    ) {
    }

    /**
     * Takes the lock and reads the state; false when another process is ticking. A wiki that never ticked starts now.
     *
     * @throws \RuntimeException
     */
    public function acquire(\DateTimeImmutable $now): bool
    {
        $this->unlock();
        $lock = $this->runtimeLock->acquire(self::LOCK);
        if ($lock === null) {
            throw new \RuntimeException('The schedule lock ' . self::LOCK . ' cannot be created');
        }
        if (!$this->runtimeLock->tryLock($lock)) {
            $this->runtimeLock->release($lock);

            return false;
        }
        $this->lock = $lock;

        try {
            $state = $this->state();
            if ($state === null) {
                unset($this->from);
                $this->save($now, -1);
            } else {
                [$this->time, $this->index, $this->from] = $state;
            }
        } catch (\Throwable $failed) {
            $this->unlock();

            throw $failed;
        }

        return true;
    }

    public function from(): \DateTimeImmutable
    {
        return $this->from;
    }

    public function time(): \DateTimeImmutable
    {
        return $this->time;
    }

    public function index(): int
    {
        return $this->index;
    }

    /** The Scheduler has dispatched the run due at $time of the message at $index: it is not to be dispatched again. */
    public function save(\DateTimeImmutable $time, int $index): void
    {
        $this->time = $time;
        $this->index = $index;
        $this->from ??= $time;

        $this->write(self::STATE, (string)json_encode([
            'time' => self::stateTime($time),
            'index' => $index,
            'from' => self::stateTime($this->from),
        ]));
    }

    /** The tick is over: the next one is due at $nextTime (null when no trigger will ever fire again). */
    public function release(\DateTimeImmutable $now, ?\DateTimeImmutable $nextTime): void
    {
        try {
            $this->write(self::NEXT_DUE_AT, $nextTime === null ? null : self::roundedUp($nextTime));
        } finally {
            $this->unlock();
        }
    }

    /** Lets go of the lock if a tick that broke before release() still holds it; harmless otherwise. */
    public function unlock(): void
    {
        if ($this->lock !== null) {
            $this->runtimeLock->release($this->lock);
            $this->lock = null;
        }
    }

    /** When the schedule is next due, as release() last wrote it; null when it never ticked (the first tick is due at once). */
    public function dueAt(): ?\DateTimeImmutable
    {
        $due = $this->read(self::NEXT_DUE_AT);
        $time = $due === null ? false : \DateTimeImmutable::createFromFormat('!' . self::TIME_FORMAT, $due, new \DateTimeZone('UTC'));

        return $time === false ? null : $time;
    }

    /**
     * The saved state, or null when there is none (never ticked) or it cannot be read.
     *
     * @return array{\DateTimeImmutable, int, \DateTimeImmutable}|null
     */
    private function state(): ?array
    {
        $state = json_decode($this->read(self::STATE) ?? '', true);
        if (!is_array($state) || !is_string($state['time'] ?? null) || !is_int($state['index'] ?? null) || !is_string($state['from'] ?? null)) {
            return null;
        }

        $time = \DateTimeImmutable::createFromFormat(self::STATE_FORMAT, $state['time'], new \DateTimeZone('UTC'));
        $from = \DateTimeImmutable::createFromFormat(self::STATE_FORMAT, $state['from'], new \DateTimeZone('UTC'));

        return $time === false || $from === false ? null : [$time, $state['index'], $from];
    }

    /** That triple's value as the database has it now, not as this process cached it. */
    private function read(string $property): ?string
    {
        $this->triples->forget(self::RESOURCE, '');

        return $this->triples->getOne(self::RESOURCE, $property, '', '');
    }

    /**
     * Sets that triple to $value, or takes it away when $value is null; only ever called under the lock.
     *
     * @throws \RuntimeException
     * @throws \Exception
     */
    private function write(string $property, ?string $value): void
    {
        $current = $this->read($property);
        if ($value === $current) {
            return;
        }
        $written = match (true) {
            $value === null => $this->triples->delete(self::RESOURCE, $property, null, '', ''),
            $current === null => $this->triples->create(self::RESOURCE, $property, $value, '', '') === 0,
            default => $this->triples->update(self::RESOURCE, $property, $current, $value, '', '') === 0,
        };
        if (!$written) {
            throw new \RuntimeException('The schedule checkpoint could not be written to the triples');
        }
    }

    private static function stateTime(\DateTimeImmutable $time): string
    {
        return $time->setTimezone(new \DateTimeZone('UTC'))->format(self::STATE_FORMAT);
    }

    /** A due time to the second, never earlier than the real one: "due" read from it is never early. */
    private static function roundedUp(\DateTimeImmutable $time): string
    {
        $utc = $time->setTimezone(new \DateTimeZone('UTC'));
        if ((int)$utc->format('u') > 0) {
            $utc = $utc->setTime((int)$utc->format('H'), (int)$utc->format('i'), (int)$utc->format('s'))->modify('+1 second');
        }

        return $utc->format(self::TIME_FORMAT);
    }
}
