<?php

namespace YesWiki\Kernel\Service\Queue;

use Psr\Clock\ClockInterface;
use YesWiki\Kernel\Queue\QueueConsumerMode;
use YesWiki\Kernel\Queue\QueuePassOrigin;
use YesWiki\Kernel\Service\ConsoleService;
use YesWiki\Kernel\Service\HibernationService;
use YesWiki\Kernel\Service\Journal;
use YesWiki\Kernel\Service\RequestScopedState;

/**
 * What the end of a web request does with the queue, once the response is handed over: runs the messages it pinned,
 * then a budgeted pass in `request` mode, or when the mode's own consumer is no longer seen at work (QueueFallback).
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

    /**
     * @param (\Closure(): bool)|null $finishResponse
     */
    public function __construct(
        private readonly QueueSettings $settings,
        private readonly QueueConsumer $consumer,
        private readonly QueueBudgetFactory $budgets,
        private readonly QueueStatistics $statistics,
        private readonly QueueCheckpoint $checkpoint,
        private readonly QueueFallback $fallback,
        private readonly ConsoleService $console,
        private readonly HibernationService $hibernation,
        private readonly Journal $journal,
        private readonly ClockInterface $clock,
        ?\Closure $finishResponse = null,
        ?string $workingDirectory = null,
    ) {
        $this->finishResponse = $finishResponse ?? self::finishResponse(...);
        $this->workingDirectory = $workingDirectory ?? (\defined('YESWIKI_INSTANCE_DIR') ? YESWIKI_INSTANCE_DIR : (string)getcwd());
    }

    /** Whether a message that must not run on a request's budget (RunsUnbudgeted) is this request's to run. */
    public function runsItself(): bool
    {
        return $this->settings->mode()->isBudgeted() || $this->fallback->consumerIsGone();
    }

    /** Runs that message at the end of this request, after the response, without budget and whatever the mode. */
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
                if (function_exists('set_time_limit')) {
                    @set_time_limit(0);
                }
                foreach ($pinned as $messageId) {
                    $this->consumer->handleById($messageId, $mode);
                }
            }

            if (($consumesByItself || $fallback) && $this->hasWork()) {
                $this->drain($responseAlreadySent, $fallback ? $mode : null);
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

    /** A pass in this process, as `request` does it; $fallbackFor is the consumer it stands in for, if any. */
    private function drain(bool $responseAlreadySent, ?QueueConsumerMode $fallbackFor = null): void
    {
        $this->consumer->drain(
            QueueConsumerMode::Request,
            $this->budgets->forMode(QueueConsumerMode::Request, $responseAlreadySent),
            true,
            $responseAlreadySent,
            $fallbackFor === null ? QueuePassOrigin::Consumer : QueuePassOrigin::Fallback,
            $fallbackFor,
        );
    }

    /** Whether a message can be taken now, the schedule is due (or never ticked), or, while hibernated, a claim expired. */
    private function hasWork(): bool
    {
        if ($this->hibernation->isWikiHibernated()) {
            return $this->statistics->expiredClaims() !== [];
        }
        $now = $this->clock->now();
        $available = $this->statistics->oldestPendingAt();
        if ($available !== null && $available <= $now) {
            return true;
        }
        $due = $this->checkpoint->dueAt();

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
