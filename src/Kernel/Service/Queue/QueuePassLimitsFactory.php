<?php

namespace YesWiki\Kernel\Service\Queue;

use Psr\Clock\ClockInterface;
use YesWiki\Kernel\Queue\QueuePassLimits;

/**
 * Works out what limits a pass from what the server itself gives PHP: the instant it stops taking messages, 80 % into
 * `max_execution_time`, and 80 % of `memory_limit`. It also raises `max_execution_time`, step by step up to a ceiling.
 *
 * The instants are PHP's own: the clock is the wiki's (NativeClock, the ClockInterface of the kernel). The stop is read against
 * microtime() (QueuePassLimits) and the time used from `REQUEST_TIME_FLOAT`.
 */
class QueuePassLimitsFactory
{
    /** Where the climb of PHP's time limit stops: a job that loops is cut after this much CPU time. */
    private const MAX_TIME_LIMIT_SECONDS = 1200;
    private const FIRST_TIME_LIMIT_SECONDS = 60;
    private const TIME_LIMIT_GROWTH = 2;

    /** The share of PHP's time limit the pass leaves unused, as it reads the limit between two messages only. */
    private const TIME_MARGIN_RATIO = 0.2;

    private const MEMORY_RATIO = 0.8;

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
        private readonly ClockInterface $clock,
        ?callable $ini = null,
        private readonly ?float $requestStartedAt = null,
        ?callable $setTimeLimit = null
    ) {
        $this->ini = $ini ?? static fn (string $name) => ini_get($name);
        $this->setTimeLimit = $setTimeLimit ?? static fn (int $seconds): bool => function_exists('set_time_limit') && @set_time_limit($seconds);
        $this->constructedAt = $this->now();
    }

    /**
     * What one pass may spend: the memory ceiling, and the instant PHP's limit less the margin makes it stop taking
     * messages (none without a limit). PHP counts from the start of the request until set_time_limit() is called.
     */
    public function forPass(): QueuePassLimits
    {
        return new QueuePassLimits($this->memoryCeiling(), $this->phpStopCountedFrom($this->startedAt()), $this->limitIsAtTheCeiling());
    }

    /**
     * The same pass once PHP's limit is raised as far as the host lets it. Its stop is then counted from the raise, as
     * set_time_limit() restarts PHP's count; with nothing accepted the pass stays as it was.
     */
    public function withPhpLimitRaised(QueuePassLimits $limits): QueuePassLimits
    {
        $raisedAt = $this->raisePhpLimit();

        return $raisedAt === null
            ? $limits
            : new QueuePassLimits($limits->memoryCeilingBytes, $this->phpStopCountedFrom($raisedAt), $this->limitIsAtTheCeiling());
    }

    /**
     * Raises PHP's limit one step at a time until the ceiling or the first step the host refuses, each read back from
     * `max_execution_time`. Answers when the last accepted step was set; null when none was, or none was needed.
     */
    public function raisePhpLimit(): ?float
    {
        $raisedAt = null;
        foreach ($this->stepsAbove($this->phpTimeLimit()) as $seconds) {
            ($this->setTimeLimit)($seconds);
            if ($this->phpTimeLimit() !== $seconds) {
                break;
            }
            $raisedAt = $this->now();
        }

        return $raisedAt;
    }

    /**
     * The steps of the climb above that limit, the ceiling last; none when PHP has no limit (never replaced by one) or one past the ceiling (never lowered).
     *
     * @return list<int>
     */
    private function stepsAbove(int $limit): array
    {
        if ($limit <= 0 || $limit >= self::MAX_TIME_LIMIT_SECONDS) {
            return [];
        }
        $steps = [];
        for ($seconds = self::FIRST_TIME_LIMIT_SECONDS; $seconds < self::MAX_TIME_LIMIT_SECONDS; $seconds *= self::TIME_LIMIT_GROWTH) {
            if ($seconds > $limit) {
                $steps[] = $seconds;
            }
        }
        $steps[] = self::MAX_TIME_LIMIT_SECONDS;

        return $steps;
    }

    /** The instant the pass stops taking messages when PHP counts from that one; null when PHP has no limit. */
    private function phpStopCountedFrom(float $from): ?float
    {
        $limit = $this->phpTimeLimit();

        return $limit > 0 ? $from + $limit * (1 - self::TIME_MARGIN_RATIO) : null;
    }

    private function limitIsAtTheCeiling(): bool
    {
        return $this->phpTimeLimit() >= self::MAX_TIME_LIMIT_SECONDS;
    }

    /** PHP's `max_execution_time` in seconds; 0 when it has none. */
    private function phpTimeLimit(): int
    {
        return (int)($this->ini)('max_execution_time');
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
        return (float)$this->clock->now()->format('U.u');
    }
}
