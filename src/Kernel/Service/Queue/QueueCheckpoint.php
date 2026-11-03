<?php

namespace YesWiki\Kernel\Service\Queue;

use Symfony\Component\Scheduler\Generator\CheckpointInterface;
use YesWiki\Files\Service\RuntimeLock;
use YesWiki\Kernel\Service\TripleStore;

/**
 * Where the Scheduler keeps how far it got, in the wiki's own database (messenger / scheduler): two triples of the resource RESOURCE.
 *
 * Symfony's own Checkpoint wants a cache pool and symfony/lock, neither of which the wiki has;
 * this one keeps the same three values -- the last run time it dispatched, the position of that
 * run's message in the schedule, and the time the schedule was first ticked, which anchors
 * every periodic trigger -- in the triple STATE, as JSON, and takes RuntimeLock's file lock so
 * that two processes never tick at once. The state is read again at each acquire(), since the
 * process that ticked last is rarely this one: a request, then an API call, then another request.
 *
 * Unlike Symfony's, release() lets go of the lock. A blocking consumer that kept it for its
 * whole life would starve every other consumer of the schedule, and would gain nothing by it:
 * the state is in the database anyway.
 *
 * The state's times are to the microsecond. release() also writes the next due time to the
 * triple NEXT_DUE_AT, rounded up to the second like every time of the queue. That is what makes
 * a tick cheap when nothing is due (QueueScheduleTicker reads that one triple and stops), and
 * what the end of a request reads to know whether the schedule needs a pass (dueAt()).
 *
 * The triples are written through the TripleStore, which refuses any write while the wiki is
 * hibernated: QueueScheduleTicker does not tick then, so that nothing is dispatched that the
 * checkpoint would not remember. They are read past its cache, since another process writes
 * them. A backup leaves them out and a restore keeps the live ones (DumpRewriter::leftOutRows()):
 * a restored copy taking the backup's would send the schedule back to an older checkpoint.
 *
 * All times come from the Scheduler, which takes them from the ticker's clock; none is the database's.
 */
class QueueCheckpoint implements CheckpointInterface
{
    /** The resource of the checkpoint's triples: no page tag has a colon. */
    public const RESOURCE = 'queue:checkpoint';

    /** The triple of the Scheduler's state: JSON, `time`, `index` and `from`. */
    public const STATE = 'https://yeswiki.net/vocabulary/queue/checkpointState';

    /** The triple of the time the schedule is next due, `Y-m-d H:i:s` in UTC; none when it never ticked. */
    public const NEXT_DUE_AT = 'https://yeswiki.net/vocabulary/queue/nextDueAt';

    /** One tick at a time per wiki, whichever consumer runs it. */
    public const LOCK = 'cache/queue-schedule.lock';

    /** How the state's times are written: to the microsecond, since the Scheduler compares them for equality. */
    private const STATE_FORMAT = 'Y-m-d H:i:s.u';

    private const TIME_FORMAT = 'Y-m-d H:i:s';

    /** @var resource|null the lock, held from acquire() to release() */
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
     * Takes the lock and reads the state; false when another process is ticking.
     *
     * A wiki that never ticked starts its schedule now: every periodic task runs first one
     * interval from now, not at once.
     *
     * @throws \RuntimeException when the lock cannot be created: no tick would ever run, which is not "somebody else is ticking"
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

    /**
     * Lets go of the lock if a tick still holds it; harmless otherwise.
     *
     * For a tick that broke before release(): the Scheduler only releases when it got to the end.
     */
    public function unlock(): void
    {
        if ($this->lock !== null) {
            $this->runtimeLock->release($this->lock);
            $this->lock = null;
        }
    }

    /**
     * When the schedule is next due, as release() last wrote it, which is when a pass has something to do even with no message waiting.
     *
     * Null when the schedule never ticked on this wiki: the first tick is due at once, to start it.
     * (A schedule where no trigger will ever fire again would also leave it null; the kernel's own
     * daily prune keeps that from happening.) Null too when the triple holds no time.
     */
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
     * Sets that triple to $value, or takes it away when $value is null.
     *
     * Only ever called under the lock, so no other process writes it in between.
     *
     * @throws \RuntimeException when the TripleStore does not write it
     * @throws \Exception        when the wiki is hibernated (TripleStore)
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

    /** A due time to the second, as the queue keeps its times, never earlier than the real one: "due" read from it is never early. */
    private static function roundedUp(\DateTimeImmutable $time): string
    {
        $utc = $time->setTimezone(new \DateTimeZone('UTC'));
        if ((int)$utc->format('u') > 0) {
            $utc = $utc->setTime((int)$utc->format('H'), (int)$utc->format('i'), (int)$utc->format('s'))->modify('+1 second');
        }

        return $utc->format(self::TIME_FORMAT);
    }
}
