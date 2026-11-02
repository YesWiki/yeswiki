<?php

namespace YesWiki\Kernel\Service\Queue;

use Psr\Clock\ClockInterface;
use YesWiki\Kernel\Queue\QueueBudget;
use YesWiki\Kernel\Queue\QueueConsumerMode;

/**
 * Works out what a pass may spend from the limits the server itself gives PHP, at the moment the pass starts.
 *
 * Time: `max_execution_time` less what the request already used, less a margin to finish cleanly (ack, heartbeat, end of request); then the ceiling of the mode, because a visitor who waits for the response must not wait for the queue. Memory: 80 % of `memory_limit`.
 */
class QueueBudgetFactory
{
    /** Seconds kept back for finishing the pass: this plus MARGIN_RATIO of the limit. */
    private const MARGIN_SECONDS = 2.0;
    private const MARGIN_RATIO = 0.1;

    private const MEMORY_RATIO = 0.8;

    /** How long a message may stay in progress when no PHP limit would cut it: blocking consumers, passes on a server without `max_execution_time`, and the messages a request pins to its end. */
    public const KILL_LIMIT_NO_PHP_LIMIT = 6 * 3600;

    /** @var callable(string): (string|false) */
    private $ini;
    private readonly float $constructedAt;

    /**
     * @param (callable(string): (string|false))|null $ini              ini_get(), injectable for tests
     * @param float|null                              $requestStartedAt when the request began; default $_SERVER['REQUEST_TIME_FLOAT'], read at each budget so a worker that serves many requests measures each from its own start
     */
    public function __construct(
        private readonly ClockInterface $clock,
        ?callable $ini = null,
        private readonly ?float $requestStartedAt = null
    ) {
        $this->ini = $ini ?? static fn (string $name) => ini_get($name);
        $this->constructedAt = $this->now();
    }

    /**
     * @param bool $responseAlreadySent whether the visitor already has the response; only `request` cares, the other modes do not run for a visitor
     */
    public function forMode(QueueConsumerMode $mode, bool $responseAlreadySent): QueueBudget
    {
        if (!$mode->isBudgeted()) {
            return $this->forHand();
        }

        $now = $this->now();
        $elapsed = max(0.0, $now - $this->startedAt());
        $limit = (int)($this->ini)('max_execution_time');

        if ($limit > 0) {
            $untilPhpStops = max(0.0, $limit - $elapsed);
            $usable = max(0.0, $untilPhpStops - (self::MARGIN_SECONDS + self::MARGIN_RATIO * $limit));
            $killLimit = max(1, (int)floor($untilPhpStops));
        } else {
            $usable = null;
            $killLimit = self::KILL_LIMIT_NO_PHP_LIMIT;
        }

        return new QueueBudget($now + $this->capped($usable, $mode->passCeilingSeconds($responseAlreadySent)), $this->memoryCeiling(), $killLimit);
    }

    /**
     * What one pass may spend in this mode, in whole seconds, as a page says it; null for a mode with no deadline.
     *
     * A page is a web request, so this reads this server's PHP limits: right for the budgeted modes,
     * which run under them.
     */
    public function secondsPerPass(QueueConsumerMode $mode, bool $responseAlreadySent): ?int
    {
        return $mode->isBudgeted() ? (int)round($this->forMode($mode, $responseAlreadySent)->remainingSeconds()) : null;
    }

    /** A pass with no deadline but the memory ceiling every pass stops at: one run by hand (`messenger:consume --drain`), which ends when the queue is empty, or a blocking consumer's. */
    public function forHand(): QueueBudget
    {
        return new QueueBudget(INF, $this->memoryCeiling(), self::KILL_LIMIT_NO_PHP_LIMIT);
    }

    private function capped(?float $usable, float $ceiling): float
    {
        return $usable === null ? $ceiling : min($usable, $ceiling);
    }

    private function startedAt(): float
    {
        return $this->requestStartedAt
            ?? (isset($_SERVER['REQUEST_TIME_FLOAT']) ? (float)$_SERVER['REQUEST_TIME_FLOAT'] : $this->constructedAt);
    }

    /** Where the pass stops on memory; PHP_INT_MAX when `memory_limit` is -1 (or unreadable), as then PHP never says when it is too much. */
    private function memoryCeiling(): int
    {
        $limit = self::bytes((string)($this->ini)('memory_limit'));

        return $limit > 0 ? (int)floor($limit * self::MEMORY_RATIO) : PHP_INT_MAX;
    }

    /**
     * "128M"-style size to bytes, as PHP reads it in php.ini; 0 when there is none (-1, empty, unreadable).
     *
     * Public because the consume command reads the same notation in its --memory-limit, which it
     * checks first: PHP warns on a size it cannot read.
     */
    public static function bytes(string $size): int
    {
        return max(0, ini_parse_quantity($size));
    }

    private function now(): float
    {
        return (float)$this->clock->now()->format('U.u');
    }
}
