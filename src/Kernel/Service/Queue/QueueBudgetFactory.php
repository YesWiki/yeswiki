<?php

namespace YesWiki\Kernel\Service\Queue;

use Psr\Clock\ClockInterface;
use YesWiki\Kernel\Queue\QueueBudget;
use YesWiki\Kernel\Queue\QueueConsumerMode;

/**
 * Works out what a pass may spend from the limits the server itself gives PHP, at the moment the pass starts:
 * `max_execution_time` less what was used and a margin, capped by the mode's ceiling; 80 % of `memory_limit`.
 *
 * Durations and deadlines are in PHP's time, timestamps in the database's: $phpClock is PHP's own clock (NativeClock, wired by
 * YesWikiKernel), never the DatabaseClock the queue writes times by. The deadline is counted down on microtime() (QueueBudget)
 * and the time used from `REQUEST_TIME_FLOAT`, so a database clock ahead or behind PHP's would add to the budget or take from it.
 */
class QueueBudgetFactory
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

    public function forMode(QueueConsumerMode $mode, bool $responseAlreadySent): QueueBudget
    {
        if (!$mode->isBudgeted()) {
            return $this->forHand();
        }

        $now = $this->now();
        $limit = $this->phpTimeLimit();
        $untilPhpStops = $this->untilPhpStops($limit, $now);
        $usable = $untilPhpStops === null ? null : max(0.0, $untilPhpStops - (self::MARGIN_SECONDS + self::MARGIN_RATIO * $limit));

        return new QueueBudget($now + $this->capped($usable, $mode->passCeilingSeconds($responseAlreadySent)), $this->memoryCeiling(), $this->killLimit($untilPhpStops));
    }

    /** What one pass may spend in this mode, in whole seconds, as a page says it; null for a mode with no deadline. */
    public function secondsPerPass(QueueConsumerMode $mode, bool $responseAlreadySent): ?int
    {
        return $mode->isBudgeted() ? (int)round($this->forMode($mode, $responseAlreadySent)->remainingSeconds()) : null;
    }

    /** A pass with no deadline but the memory ceiling: one run by hand (`messenger:consume --drain`), or a blocking consumer's. */
    public function forHand(): QueueBudget
    {
        return new QueueBudget(INF, $this->memoryCeiling(), self::KILL_LIMIT_NO_PHP_LIMIT);
    }

    /** A pass that stops only when the queue is empty, hibernates or the server kills it: no deadline, no memory ceiling (the jobs page's button). */
    public function untilEmpty(): QueueBudget
    {
        return new QueueBudget(INF, PHP_INT_MAX, $this->killLimit($this->untilPhpStops($this->phpTimeLimit(), $this->now())));
    }

    /**
     * Asks PHP to drop its time limit for this request, then reads the limit that holds; the kill limit to claim by when it is gone, null when the host keeps one.
     */
    public function liftPhpLimit(): ?int
    {
        ($this->setTimeLimit)(0);

        return $this->phpTimeLimit() === 0 ? self::KILL_LIMIT_NO_PHP_LIMIT : null;
    }

    private function capped(?float $usable, float $ceiling): float
    {
        return $usable === null ? $ceiling : min($usable, $ceiling);
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
