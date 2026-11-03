<?php

namespace YesWiki\Kernel\Queue;

/**
 * The process that took a message from the queue: its pid, the machine it runs on, the instant it started (R19),
 * written into the claim's token so that a process on the same machine can tell whether the holder is still there.
 */
final class QueueClaimHolder
{
    public const ALIVE = 'alive';
    public const GONE = 'gone';
    public const UNKNOWN = 'unknown';

    private const TOKEN = '/^([cs])(\d{1,10})@([0-9a-f]{0,12})\.(\d{0,20})\/[0-9a-f]+$/';

    private const START_TIME_FIELD = 19;

    private const NO_SUCH_PROCESS = 3;

    private static ?self $thisProcess = null;

    public function __construct(
        public readonly bool $ownProcess,
        public readonly int $pid,
        public readonly string $machine,
        public readonly string $startedAt,
    ) {
    }

    /** The process running this code. Read again after a fork, whose child has a pid of its own. */
    public static function thisProcess(): self
    {
        $pid = (int)getmypid();
        if (self::$thisProcess === null || self::$thisProcess->pid !== $pid) {
            $namespace = @readlink('/proc/self/ns/pid');
            $start = self::startTimeOf('self');
            self::$thisProcess = new self(
                PHP_SAPI === 'cli',
                $pid,
                is_string($namespace) && $start !== null ? substr(sha1(gethostname() . "\0" . $namespace), 0, 12) : '',
                $start ?? '',
            );
        }

        return self::$thisProcess;
    }

    /** The holder a token names; null for a token that names none (written before tokens did). */
    public static function ofToken(string $token): ?self
    {
        if (!preg_match(self::TOKEN, $token, $parts)) {
            return null;
        }

        return new self($parts[1] === 'c', (int)$parts[2], $parts[3], $parts[4]);
    }

    /** A token for one claim: this holder, and a nonce that sets the claim apart from any other this holder makes. */
    public function token(): string
    {
        return ($this->ownProcess ? 'c' : 's') . $this->pid . '@' . $this->machine . '.' . $this->startedAt . '/' . bin2hex(random_bytes(8));
    }

    public function isSameProcessAs(self $other): bool
    {
        return $this->machine !== '' && $this->machine === $other->machine && $this->pid === $other->pid && $this->startedAt === $other->startedAt;
    }

    /** Whether this holder's process is still there, as the process $observer sees it: ALIVE, GONE or UNKNOWN. */
    public function processSeenFrom(self $observer): string
    {
        if ($this->machine === '' || $this->machine !== $observer->machine || $this->startedAt === '' || $this->pid <= 0) {
            return self::UNKNOWN;
        }

        $start = self::startTimeOf((string)$this->pid);
        if ($start !== null) {
            return $start === $this->startedAt ? self::ALIVE : self::GONE;
        }
        if (function_exists('posix_kill') && !@posix_kill($this->pid, 0) && posix_get_last_error() === self::NO_SUCH_PROCESS) {
            return self::GONE;
        }

        return self::UNKNOWN;
    }

    /** When that process started, in clock ticks since boot; null when /proc does not say. */
    private static function startTimeOf(string $pid): ?string
    {
        $stat = @file_get_contents("/proc/{$pid}/stat");
        if (!is_string($stat)) {
            return null;
        }
        $closing = strrpos($stat, ')');
        if ($closing === false) {
            return null;
        }
        $fields = explode(' ', trim(substr($stat, $closing + 2)));
        $start = $fields[self::START_TIME_FIELD] ?? '';

        return ctype_digit($start) ? $start : null;
    }
}
