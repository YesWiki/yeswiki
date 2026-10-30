<?php

namespace YesWiki\Kernel\Service\Queue;

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

    /** `request` while the visitor waits for the response (mod_php, CGI: it cannot leave before the script ends). */
    private const CEILING_VISITOR_WAITS = 3.0;
    /** `request` once the response has left (FPM, LiteSpeed, worker). */
    private const CEILING_RESPONSE_SENT = 20.0;
    /** `command` and `request-spawn` when PHP sets no time limit (CLI): long enough to be useful, short enough to come back and reschedule. */
    private const CEILING_UNLIMITED = 300.0;
    /** `api`: an HTTP caller (online cron, monitor) gives up long before a command would. */
    private const CEILING_API = 50.0;

    private const MEMORY_RATIO = 0.8;

    /** How long a message may stay in progress when no PHP limit would cut it: blocking consumers, passes on a server without `max_execution_time`, and the messages a request pins to its end. */
    public const KILL_LIMIT_NO_PHP_LIMIT = 6 * 3600;

    /** @var callable(string): (string|false) */
    private $ini;
    /** @var callable(bool): float */
    private $now;
    private readonly float $constructedAt;

    /**
     * @param (callable(string): (string|false))|null $ini              ini_get(), injectable for tests
     * @param (callable(bool): float)|null            $now              microtime(), injectable for tests
     * @param float|null                              $requestStartedAt when the request began; default $_SERVER['REQUEST_TIME_FLOAT'], read at each budget so a worker that serves many requests measures each from its own start
     */
    public function __construct(
        ?callable $ini = null,
        ?callable $now = null,
        private readonly ?float $requestStartedAt = null
    ) {
        $this->ini = $ini ?? static fn (string $name) => ini_get($name);
        $this->now = $now ?? static fn (bool $asFloat = true) => microtime(true);
        $this->constructedAt = (float)($this->now)(true);
    }

    /**
     * @param bool $responseAlreadySent whether the visitor already has the response; only `request` cares, the other modes do not run for a visitor
     */
    public function forMode(QueueConsumerMode $mode, bool $responseAlreadySent): QueueBudget
    {
        $memoryCeiling = $this->memoryCeiling();
        if (!$mode->isBudgeted()) {
            return new QueueBudget(INF, $memoryCeiling, self::KILL_LIMIT_NO_PHP_LIMIT);
        }

        $now = (float)($this->now)(true);
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

        $seconds = match ($mode) {
            QueueConsumerMode::Request => $this->capped($usable, $responseAlreadySent ? self::CEILING_RESPONSE_SENT : self::CEILING_VISITOR_WAITS),
            QueueConsumerMode::Api => $this->capped($usable, self::CEILING_API),
            default => $usable ?? self::CEILING_UNLIMITED,
        };

        return new QueueBudget($now + $seconds, $memoryCeiling, $killLimit);
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
}
