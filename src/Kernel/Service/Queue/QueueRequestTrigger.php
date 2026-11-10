<?php

namespace YesWiki\Kernel\Service\Queue;

use Psr\Clock\ClockInterface;
use YesWiki\Kernel\Queue\QueueConsumerMode;
use YesWiki\Kernel\Service\ConsoleService;
use YesWiki\Kernel\Service\HibernationService;
use YesWiki\Kernel\Service\Journal;
use YesWiki\Kernel\Service\RequestScopedState;

/**
 * What the end of a web request does with the queue, once the response is handed over: runs the messages it pinned,
 * then a pass in `request` mode, or when the mode's own consumer is no longer seen at work (QueueFallback).
 * A message that runs for its sender (RunsForItsSender) is pinned where nothing else would take it soon: always in `api`, in `request`
 * only where the response cannot leave before the end of the script (mod_php, CGI), never in `worker`.
 */
class QueueRequestTrigger implements RequestScopedState
{
    private const FINISHERS = ['fastcgi_finish_request', 'frankenphp_finish_request', 'litespeed_finish_request'];

    /** @var list<int> */
    private array $pinned = [];

    private bool $servedByWorker = false;

    /** @var \Closure(): bool */
    private \Closure $finishResponse;

    private readonly string $workingDirectory;

    /** @var \Closure(): bool */
    private \Closure $respondsEarly;

    /**
     * @param (\Closure(): bool)|null $finishResponse
     * @param (\Closure(): bool)|null $respondsEarly  whether this server sends the response before the end of the script (default: respondsEarly(), or the FrankenPHP worker)
     */
    public function __construct(
        private readonly QueueSettings $settings,
        private readonly QueueConsumer $consumer,
        private readonly QueuePassLimitsFactory $passLimits,
        private readonly QueueStatistics $statistics,
        private readonly QueueScheduleTicker $ticker,
        private readonly QueueFallback $fallback,
        private readonly ConsoleService $console,
        private readonly HibernationService $hibernation,
        private readonly Journal $journal,
        private readonly ClockInterface $clock,
        ?\Closure $finishResponse = null,
        ?string $workingDirectory = null,
        ?\Closure $respondsEarly = null,
    ) {
        $this->finishResponse = $finishResponse ?? self::finishResponse(...);
        $this->respondsEarly = $respondsEarly ?? fn (): bool => $this->servedByWorker || self::respondsEarly();
        $this->workingDirectory = $workingDirectory ?? (\defined('YESWIKI_INSTANCE_DIR') ? YESWIKI_INSTANCE_DIR : (string)getcwd());
    }

    /**
     * Whether a message that runs for its sender (RunsForItsSender) is this request's to run: in `api`, where nothing else comes before the cron;
     * in `request`, where the response cannot leave first, so that the admin does not wait for the whole backup; in `worker`, never.
     */
    public function runsItself(): bool
    {
        return match ($this->settings->mode()) {
            QueueConsumerMode::Api => true,
            QueueConsumerMode::Request => !($this->respondsEarly)(),
            QueueConsumerMode::Worker => false,
        };
    }

    /** Runs that message at the end of this request, after the response, whatever the mode: in a process of its own when the response cannot leave first. */
    public function pin(int $messageId): void
    {
        $this->pinned[] = $messageId;
    }

    /** For the FrankenPHP worker, which calls afterResponse() itself once the response is sent. */
    public function servedByWorker(): void
    {
        $this->servedByWorker = true;
    }

    /** Never throws: whatever breaks is journaled, and the visitor already has the page. */
    public function afterResponse(): void
    {
        $buffers = ob_get_level();
        $foundIn = getcwd();
        $moved = $foundIn !== $this->workingDirectory && @chdir($this->workingDirectory);
        try {
            $pinned = $this->pinned;
            $this->pinned = [];
            $mode = $this->settings->mode();
            $consumesByItself = $mode->consumesAtEndOfRequests();
            $fallback = !$consumesByItself && $this->fallback->shouldConsume();
            if ($pinned === [] && !$consumesByItself && !$fallback) {
                return;
            }

            $responseAlreadySent = $this->handOverResponse();
            if (!$responseAlreadySent) {
                ob_start(static fn (): string => '', 4096);
                $pinned = array_values(array_filter($pinned, fn (int $messageId): bool => !$this->handToAProcess($messageId)));
            }
            if ($pinned !== []) {
                $this->passLimits->raisePhpLimit();
                foreach ($pinned as $messageId) {
                    $this->consumer->handleById($messageId);
                }
            }

            if (($consumesByItself || $fallback) && $this->hasWork()) {
                $this->consumer->drain(QueueConsumerMode::Request, $this->passLimits->forPass(), $responseAlreadySent);
            }
        } catch (\Throwable $failed) {
            try {
                $this->journal->error('The queue could not be run at the end of the request: ' . $failed->getMessage(), [
                    'action' => 'queue.trigger_failed',
                    'exception' => $failed,
                ]);
            } catch (\Throwable) {
            }
        } finally {
            while (ob_get_level() > $buffers && @ob_end_clean()) {
            }
            if ($moved && $foundIn !== false) {
                @chdir($foundIn);
            }
        }
    }

    public function startNewRequest(): void
    {
        $this->pinned = [];
    }

    /** Starts a process that runs that pinned message (`messenger:consume --message`), answering whether one started. */
    private function handToAProcess(int $messageId): bool
    {
        try {
            return $this->console->startDetachedConsole('messenger:consume', ['--message', (string)$messageId]) !== null;
        } catch (\Throwable) {
            return false;
        }
    }

    /** Whether a message can be taken now, or the schedule is due (or has a task never seen); a hibernated wiki has none, but an `archiving` a dead backup left is lifted on the way (HibernationService). */
    private function hasWork(): bool
    {
        if ($this->hibernation->isWikiHibernated()) {
            return false;
        }
        $now = $this->clock->now();
        $available = $this->statistics->oldestPendingAt();
        if ($available !== null && $available <= $now) {
            return true;
        }
        $due = $this->ticker->dueAt();

        return $due === null || $due <= $now;
    }

    /** Releases the session and lets the visitor go, answering whether the response has left. */
    private function handOverResponse(): bool
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        if (function_exists('ignore_user_abort')) {
            ignore_user_abort(true);
        }

        return $this->servedByWorker || ($this->finishResponse)();
    }

    /** Whether this server can send the response before the end-of-request pass, so that a visitor does not wait for it. */
    public static function respondsEarly(): bool
    {
        return PHP_SAPI !== 'cli' && array_filter(self::FINISHERS, 'function_exists') !== [];
    }

    /** Ends the response early where the server offers it (FPM, FrankenPHP outside its worker, LiteSpeed). */
    private static function finishResponse(): bool
    {
        if (PHP_SAPI === 'cli') {
            return false;
        }
        foreach (self::FINISHERS as $finisher) {
            if (function_exists($finisher)) {
                $finisher();

                return true;
            }
        }

        return false;
    }
}
