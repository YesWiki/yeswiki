<?php

namespace YesWiki\Kernel\Service\Queue;

use Symfony\Component\Scheduler\Generator\CheckpointInterface;
use YesWiki\Files\Service\RuntimeLock;
use YesWiki\Kernel\Service\DbService;

/**
 * Where the Scheduler keeps how far it got, in the wiki's own database (messenger / scheduler): the `__checkpoint` row of the queue's scheduled tasks (QueueTaskRows).
 *
 * Symfony's own Checkpoint wants a cache pool and symfony/lock, neither of which the wiki has;
 * this one keeps the same three values -- the last run time it dispatched, the position of that
 * run's message in the schedule, and the time the schedule was first ticked, which anchors
 * every periodic trigger -- in the row's `state`, and takes RuntimeLock's file lock so that
 * two processes never tick at once. The state is read again at each acquire(), since the
 * process that ticked last is rarely this one: a request, then the command, then another request.
 *
 * Unlike Symfony's, release() lets go of the lock. A blocking consumer that kept it for its
 * whole life would starve every other consumer of the schedule, and would gain nothing by it:
 * the state is in the database anyway.
 *
 * The state is the row's `state` field, its times to the microsecond. release() also writes the
 * next due time to the row's `next_due_at`, rounded up to the second like every time of the
 * queue. That is what makes a tick cheap when nothing is due (QueueScheduleTicker reads that one
 * row and stops), and what the end of a request reads to know whether the schedule needs a pass
 * (QueueStatistics::scheduleDueAt()).
 *
 * All times come from the Scheduler, which takes them from the ticker's clock; none is the database's.
 */
class QueueCheckpoint implements CheckpointInterface
{
    /** The `task_id` of the checkpoint's row, which is not a task: no task id may start with two underscores. */
    public const ROW = '__checkpoint';

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

    private readonly QueueTaskRows $rows;

    public function __construct(
        DbService $db,
        QueueSchema $schema,
        private readonly RuntimeLock $runtimeLock,
    ) {
        $this->rows = new QueueTaskRows($db, $schema);
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

        $this->write(['state' => [
            'time' => self::stateTime($time),
            'index' => $index,
            'from' => self::stateTime($this->from),
        ]]);
    }

    /** The tick is over: the next one is due at $nextTime (null when no trigger will ever fire again). */
    public function release(\DateTimeImmutable $now, ?\DateTimeImmutable $nextTime): void
    {
        try {
            $this->write(['next_due_at' => $nextTime === null ? null : self::roundedUp($nextTime)]);
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

    /** When the schedule is next due, as release() last wrote it; null when it never ticked. */
    public function dueAt(): ?\DateTimeImmutable
    {
        $due = $this->rows->read(self::ROW)['next_due_at'] ?? null;

        return $due === null ? null : new \DateTimeImmutable((string)$due, new \DateTimeZone('UTC'));
    }

    /**
     * The saved state, or null when there is none (never ticked) or it cannot be read.
     *
     * @return array{\DateTimeImmutable, int, \DateTimeImmutable}|null
     */
    private function state(): ?array
    {
        $state = $this->rows->read(self::ROW)['state'] ?? null;
        if (!is_array($state) || !is_string($state['time'] ?? null) || !is_int($state['index'] ?? null) || !is_string($state['from'] ?? null)) {
            return null;
        }

        $time = \DateTimeImmutable::createFromFormat(self::STATE_FORMAT, $state['time'], new \DateTimeZone('UTC'));
        $from = \DateTimeImmutable::createFromFormat(self::STATE_FORMAT, $state['from'], new \DateTimeZone('UTC'));

        return $time === false || $from === false ? null : [$time, $state['index'], $from];
    }

    /** @param array<string, mixed> $fields written on the row, which is created if it is not there yet; a null one is taken away */
    private function write(array $fields): void
    {
        $this->rows->update(self::ROW, static fn (array $current): array => array_merge($current, $fields));
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
