<?php

namespace YesWiki\Kernel\Service\Queue;

use Psr\Clock\ClockInterface;
use YesWiki\Kernel\Queue\QueuePassLimits;

/**
 * Works out what limits a pass from what the server itself gives PHP, at the moment the pass starts: the instant
 * `max_execution_time` less what was used and a margin makes it stop, the kill limit of its claim, 80 % of `memory_limit`.
 *
 * The instants are in PHP's time, timestamps in the database's: $phpClock is PHP's own clock (NativeClock, wired by
 * YesWikiKernel), never the DatabaseClock the queue writes times by. The stop is read against microtime() (QueuePassLimits)
 * and the time used from `REQUEST_TIME_FLOAT`, so a database clock ahead or behind PHP's would add to the time or take from it.
 */
class QueuePassLimitsFactory
{
    private const MARGIN_SECONDS = 2.0;
    private const MARGIN_RATIO = 0.1;

    private const MEMORY_RATIO = 0.8;

    /** One hour, the longest a host lets a process live: the kill limit of a pass to which PHP gives no limit of its own. */
    public const KILL_LIMIT_NO_PHP_LIMIT = 3600;

    /** @var callable(string): (string|false) */
    private $ini;
    /** @var callable(int): bool */
    private $setTimeLimit;
    private readonly float $constructedAt;

    /**
     * @param (callable(string): (string|false))|null $ini
     * @param (callable(int): bool)|null              $setTimeLimit
     */
    public function __construct(
        private readonly ClockInterface $phpClock,
        ?callable $ini = null,
        private readonly ?float $requestStartedAt = null,
        ?callable $setTimeLimit = null
    ) {
        $this->ini = $ini ?? static fn (string $name) => ini_get($name);
        $this->setTimeLimit = $setTimeLimit ?? static fn (int $seconds): bool => function_exists('set_time_limit') && @set_time_limit($seconds);
        $this->constructedAt = $this->now();
    }

    /**
     * What one pass may spend: the memory ceiling, a kill limit of what PHP leaves of its time limit, and the instant
     * PHP's limit less a margin makes it stop taking messages (none without a limit).
     */
    public function forPass(): QueuePassLimits
    {
        $now = $this->now();
        $limit = $this->phpTimeLimit();
        $untilPhpStops = $this->untilPhpStops($limit, $now);
        $phpStopsAt = $untilPhpStops === null ? null : $now + max(0.0, $untilPhpStops - (self::MARGIN_SECONDS + self::MARGIN_RATIO * $limit));

        return new QueuePassLimits($this->memoryCeiling(), $this->killLimit($untilPhpStops), $phpStopsAt);
    }

    /** The limits of the jobs page's button: like any drain pass it runs what was waiting when it began (the cutoff), but it has no memory ceiling and no PHP stop time; its claims carry PHP's own kill limit. */
    public function untilEmpty(): QueuePassLimits
    {
        $limits = $this->forPass();

        return new QueuePassLimits(PHP_INT_MAX, $limits->killLimitSeconds, $limits->phpStopsAt);
    }

    /**
     * Asks PHP to drop its time limit for this request, then reads the limit that holds; the kill limit to claim by when it is gone, null when the host keeps one.
     */
    public function liftPhpLimit(): ?int
    {
        ($this->setTimeLimit)(0);

        return $this->phpTimeLimit() === 0 ? self::KILL_LIMIT_NO_PHP_LIMIT : null;
    }

    /** PHP's `max_execution_time` in seconds; 0 when it has none. */
    private function phpTimeLimit(): int
    {
        return (int)($this->ini)('max_execution_time');
    }

    /** What PHP leaves of that limit to the request at that instant, never negative; null when PHP has no limit. */
    private function untilPhpStops(int $limit, float $now): ?float
    {
        return $limit > 0 ? max(0.0, $limit - max(0.0, $now - $this->startedAt())) : null;
    }

    /** How long a claim may last before it can only have been killed: what PHP leaves, at least a second, or an hour when it has no limit. */
    private function killLimit(?float $untilPhpStops): int
    {
        return $untilPhpStops === null ? self::KILL_LIMIT_NO_PHP_LIMIT : max(1, (int)floor($untilPhpStops));
    }

    private function startedAt(): float
    {
        return $this->requestStartedAt
            ?? (isset($_SERVER['REQUEST_TIME_FLOAT']) ? (float)$_SERVER['REQUEST_TIME_FLOAT'] : $this->constructedAt);
    }

    /** Where the pass stops on memory; PHP_INT_MAX when `memory_limit` is -1 (or unreadable). */
    private function memoryCeiling(): int
    {
        $limit = self::bytes((string)($this->ini)('memory_limit'));

        return $limit > 0 ? (int)floor($limit * self::MEMORY_RATIO) : PHP_INT_MAX;
    }

    /** "128M"-style size to bytes, as PHP reads it in php.ini; 0 when there is none (-1, empty, unreadable). */
    public static function bytes(string $size): int
    {
        return max(0, ini_parse_quantity($size));
    }

    private function now(): float
    {
        return (float)$this->phpClock->now()->format('U.u');
    }
}
