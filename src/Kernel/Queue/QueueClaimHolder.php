<?php

namespace YesWiki\Kernel\Queue;

/**
 * The process that took a message from the queue: its pid, the machine it runs on, the instant it started (R19).
 *
 * Written into the claim's token (`delivered_by`), so that a process on the same machine can tell
 * whether the holder is still there instead of waiting for the limit it recorded: a backup the
 * host killed (FPM's request_terminate_timeout, the OOM killer, a restart) is seen dead at once,
 * and a handler still at work past PHP's own limit (which counts CPU time, not the hours a slow
 * network takes) is not reaped under it.
 *
 * "The same machine" is the host name and the pid namespace together (a container has its own),
 * hashed to fit the column; the start time, in clock ticks since boot, tells a pid the system has
 * given to another process since. Both come from /proc: where it cannot be read (not Linux, a
 * restricted host), the machine is left empty, nothing can be told, and the transport falls back
 * to the time rule it always had.
 *
 * A pid proves less for a server's process than for a process of its own: an FPM or Apache child
 * goes on serving other requests after the one that held the claim died of a fatal error, and a
 * FrankenPHP process runs many requests at once. So the token also says which of the two the
 * holder is.
 */
final class QueueClaimHolder
{
    /** The process is there, and it is the one that took the claim. */
    public const ALIVE = 'alive';
    /** The process is gone, or its pid is another process's now. */
    public const GONE = 'gone';
    /** Nothing can be told from here: another machine, /proc unreadable, a token of an older format. */
    public const UNKNOWN = 'unknown';

    /** Kind, pid, machine, start, and a nonce only the claim knows: `c1234@0123456789ab.7100459/0123456789abcdef`, within the 64 characters of the column. */
    private const TOKEN = '/^([cs])(\d{1,10})@([0-9a-f]{0,12})\.(\d{0,20})\/[0-9a-f]+$/';

    /** What /proc/<pid>/stat holds past the process name, and the place of its start time (field 22) among those fields. */
    private const START_TIME_FIELD = 19;

    /** posix_kill()'s error when no process has that pid. */
    private const NO_SUCH_PROCESS = 3;

    private static ?self $thisProcess = null;

    /**
     * @param bool   $ownProcess whether the process runs nothing but its consumer (the CLI), and so lives exactly as long as it
     * @param string $machine    the host and pid namespace, hashed; empty when they cannot be read
     * @param string $startedAt  the process's start time in clock ticks since boot; empty when it cannot be read
     */
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

    /**
     * Whether this holder's process is still there, as the process $observer sees it: ALIVE, GONE or UNKNOWN.
     *
     * Only on the same machine: a pid means nothing anywhere else. Its start time is compared when
     * /proc shows it; when /proc hides it (a host mounted with hidepid hides other users'
     * processes), the kernel is asked whether the pid exists at all, which proves it gone but
     * cannot prove it is still the same process.
     */
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
        // The process name, in parentheses, may itself hold spaces and parentheses: the fields are counted from its end.
        $closing = strrpos($stat, ')');
        if ($closing === false) {
            return null;
        }
        $fields = explode(' ', trim(substr($stat, $closing + 2)));
        $start = $fields[self::START_TIME_FIELD] ?? '';

        return ctype_digit($start) ? $start : null;
    }
}
