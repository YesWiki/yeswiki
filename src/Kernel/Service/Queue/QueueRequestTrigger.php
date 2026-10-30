<?php

namespace YesWiki\Kernel\Service\Queue;

use Psr\Clock\ClockInterface;
use Symfony\Component\Clock\NativeClock;
use YesWiki\Files\Service\RuntimeLock;
use YesWiki\Kernel\Queue\QueueConsumerMode;
use YesWiki\Kernel\Queue\QueuePassOrigin;
use YesWiki\Kernel\Service\ConsoleService;
use YesWiki\Kernel\Service\HibernationService;
use YesWiki\Kernel\Service\Journal;
use YesWiki\Kernel\Service\RequestScopedState;

/**
 * What the end of a web request does with the queue (messenger / scheduler, spec §6 and §9.5).
 *
 * index.php registers afterResponse() as a shutdown function as soon as the wiki is booted, so it
 * runs however the request ends: a page, a redirect (ExitException), `exit`, an error. The FrankenPHP
 * worker calls it itself once frankenphp_handle_request() has sent each response. It first hands the
 * response over (session closed, then fastcgi_finish_request() and its kind where the server has
 * one), so the visitor does not wait for the queue. Where it cannot (mod_php, CGI, `php -S`), what the
 * pass prints is dropped, since it would land in the page. Then:
 *
 * - the messages this request pinned run now, whatever the mode: a backup an admin started on a
 *   wiki whose queue runs only when something calls it (§9.5); the sender decides when to pin;
 * - in `request`, a budgeted pass consumes the queue in this process;
 * - in `request-spawn`, a detached `messenger:drain` does, at most one launch tried a minute; a
 *   launch that fails is journaled, and the request does not consume in its place;
 * - in every other mode, nothing: a command, the API route or a blocking consumer does the work;
 * - but when the consumer of the mode is no longer seen at work (QueueFallback), the request
 *   consumes as `request` would, and its pass says for which consumer it stood in. In
 *   `request-spawn`, the request that tries a launch leaves the pass to it.
 *
 * Before any pass it asks two cheap questions -- can a message be taken now (a delayed retry
 * cannot), is the schedule due -- so a request with nothing to do costs two indexed reads, not a pass row. They are also what
 * fails first on a wiki whose queue table does not exist yet (not migrated, an update half done):
 * that costs one journaled `queue.trigger_failed`, merged per day by the Journal, and never the page.
 *
 * The pinned messages are the request's own: forgotten at the start of the next request, so a
 * worker never runs for one visitor what the previous one pinned.
 */
class QueueRequestTrigger implements RequestScopedState
{
    /** Stamped each time a `request-spawn` launch is tried, whether a process started or not; its age is the throttle. */
    public const SPAWN_LOCK = 'cache/queue-spawn.lock';

    /** At most one `messenger:drain` started per this many seconds: a busy wiki would otherwise start one per page view. */
    private const SPAWN_EVERY_SECONDS = 60;

    /** The functions with which a server ends the response early, in the order they are tried. */
    private const FINISHERS = ['fastcgi_finish_request', 'frankenphp_finish_request', 'litespeed_finish_request'];

    /** @var list<int> the ids of the messages to run in this request */
    private array $pinned = [];

    /** Whether the response has always left by the time afterResponse() runs: the FrankenPHP worker. */
    private bool $servedByWorker = false;

    private ClockInterface $clock;

    /** @var \Closure(): bool */
    private \Closure $finishResponse;

    /** The wiki's folder, where the end of the request works from. */
    private readonly string $workingDirectory;

    /**
     * @param (\Closure(): bool)|null $finishResponse   hands the response over to the client if the server can, answering whether it has gone; injectable so tests never call the SAPI's own (default: finishResponse())
     * @param string|null             $workingDirectory the wiki's folder (default: YESWIKI_INSTANCE_DIR, else the folder the wiki booted in)
     */
    public function __construct(
        private readonly QueueSettings $settings,
        private readonly QueueConsumer $consumer,
        private readonly QueueBudgetFactory $budgets,
        private readonly QueueStatistics $statistics,
        private readonly QueueFallback $fallback,
        private readonly ConsoleService $console,
        private readonly RuntimeLock $runtimeLock,
        private readonly HibernationService $hibernation,
        private readonly Journal $journal,
        ?ClockInterface $clock = null,
        ?\Closure $finishResponse = null,
        ?string $workingDirectory = null,
    ) {
        $this->clock = $clock ?? new NativeClock();
        $this->finishResponse = $finishResponse ?? self::finishResponse(...);
        $this->workingDirectory = $workingDirectory ?? (\defined('YESWIKI_INSTANCE_DIR') ? YESWIKI_INSTANCE_DIR : (string)getcwd());
    }

    /** Runs that message at the end of this request, after the response, without budget and whatever the mode; or, when the response cannot leave first, in a process of its own (handToAProcess()). */
    public function pin(int $messageId): void
    {
        $this->pinned[] = $messageId;
    }

    /** The FrankenPHP worker calls afterResponse() itself, once frankenphp_handle_request() has sent the response: there is nothing left to hand over. */
    public function servedByWorker(): void
    {
        $this->servedByWorker = true;
    }

    /** Never throws: whatever breaks is journaled, and the visitor already has the page. */
    public function afterResponse(): void
    {
        $buffers = ob_get_level();
        // mod_php may run shutdown functions elsewhere than the request ran (PHP's manual says so of
        // register_shutdown_function()), and the wiki finds much by a path relative to its folder:
        // an extension's classes, through src/autoload.inc.php, and the folder a process starts in.
        $foundIn = getcwd();
        $moved = $foundIn !== $this->workingDirectory && @chdir($this->workingDirectory);
        try {
            $pinned = $this->pinned;
            $this->pinned = [];
            $mode = $this->settings->mode();
            $consumesByItself = $mode === QueueConsumerMode::Request || $mode === QueueConsumerMode::RequestSpawn;
            $fallback = !$consumesByItself && $this->fallback->shouldConsume($this->hasWork(...));
            if ($pinned === [] && !$consumesByItself && !$fallback) {
                return;
            }

            $responseAlreadySent = $this->handOverResponse();
            if (!$responseAlreadySent) {
                // The response is still being written (mod_php, CGI, `php -S`): what the pass prints
                // would end up in the page, or break an API's JSON. Dropped every few kilobytes, so
                // a handler that prints a lot costs no memory.
                ob_start(static fn (): string => '', 4096);
            }

            if (!$responseAlreadySent) {
                // The response leaves when the script ends (mod_php, CGI): run here, a backup would keep the admin waiting for all of it, and a wiki fetching it would give up.
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

            if ($mode === QueueConsumerMode::Request) {
                $this->drainIfNeeded($responseAlreadySent);
            } elseif ($mode === QueueConsumerMode::RequestSpawn) {
                $this->spawnIfNeeded($responseAlreadySent);
            } elseif ($fallback && $this->hasWork()) {
                $this->drain($responseAlreadySent, $mode);
            }
        } catch (\Throwable $failed) {
            try {
                $this->journal->error('The queue could not be run at the end of the request: ' . $failed->getMessage(), [
                    'action' => 'queue.trigger_failed',
                    'exception' => $failed,
                ]);
            } catch (\Throwable) {
                // Nowhere left to say it; the page is served, which is what matters here.
            }
        } finally {
            // Ours, and any a handler left open: the response, or the next request of a worker, gets none of it.
            while (ob_get_level() > $buffers && @ob_end_clean()) {
            }
            // A worker serves its next request from where it was.
            if ($moved && $foundIn !== false) {
                @chdir($foundIn);
            }
        }
    }

    public function startNewRequest(): void
    {
        $this->pinned = [];
    }

    private function drainIfNeeded(bool $responseAlreadySent): void
    {
        if (!$this->hasWork()) {
            return;
        }
        $this->drain($responseAlreadySent);
    }

    /**
     * Tries to start `messenger:drain` in a process of its own, at most once a minute; between two tries, does the pass here if that process has gone unanswered.
     *
     * The throttle is stamped by every try, a failed one too: a server that cannot start a process
     * would otherwise try on every request. Nothing consumes in its place until the fallback does
     * (QueueFallback): a launch that fails and one that starts a process that never consumes are
     * the same outage, told the same way.
     */
    private function spawnIfNeeded(bool $responseAlreadySent): void
    {
        // time(), not the clock: the stamp is a file's mtime, which is the system's time.
        if (time() - $this->runtimeLock->lastTaken(self::SPAWN_LOCK) < self::SPAWN_EVERY_SECONDS) {
            if ($this->fallback->shouldConsume($this->hasWork(...)) && $this->hasWork()) {
                $this->drain($responseAlreadySent, QueueConsumerMode::RequestSpawn);
            }

            return;
        }
        if (!$this->hasWork()) {
            return;
        }

        try {
            $process = $this->console->startDetachedConsole('messenger:drain');
            $unavailable = $process === null ? 'no PHP binary to run the console with' : null;
        } catch (\Throwable $failed) {
            // Symfony's Process throws when proc_open() is disabled, as it often is on shared hosting.
            $unavailable = $failed->getMessage();
        }
        $this->runtimeLock->stamp(self::SPAWN_LOCK);
        $this->fallback->spawnAttempted();

        if ($unavailable !== null) {
            $this->journal->warning('No process could be started to consume the queue: ' . $unavailable, [
                'action' => 'queue.spawn_unavailable',
            ]);
        }
    }

    /**
     * Starts a process of its own that runs that pinned message (`messenger:drain --message`), answering whether one started.
     *
     * The message keeps the delay it was pinned with, so no pass takes it while the process starts,
     * and the process holds its claim as a CLI: if it dies, the next pass sees it at once. Where no
     * process can be started (no PHP binary, proc_open() disabled), the request runs it after all,
     * as a backup ran before there was a queue.
     */
    private function handToAProcess(int $messageId): bool
    {
        try {
            return $this->console->startDetachedConsole('messenger:drain', ['--message', (string)$messageId]) !== null;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * A pass in this process, as `request` does it: on the budget of a request, recorded as one.
     *
     * @param QueueConsumerMode|null $fallbackFor the consumer it stands in for, when it is the fallback's
     */
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

    /**
     * Whether a pass would have something to do: a message can be taken now, or the schedule is due (or never ticked).
     *
     * Available now, not merely waiting: a failed message waits up to half an hour for its retry,
     * and counting it would run a full pass, with nothing to take, on every request meanwhile.
     *
     * While the wiki is hibernated a pass consumes nothing, but still reaps what a dead consumer
     * left (R18): there is work only when such a claim is there. A backup PHP killed holds the wiki
     * `archiving`, and on a wiki whose queue runs at the end of requests, nothing else would reap it.
     */
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
        $due = $this->statistics->scheduleDueAt();

        return $due === null || $due <= $now;
    }

    /**
     * Releases the session and lets the visitor go, answering whether the response has left.
     *
     * The session first: a visitor's next request waits on its lock otherwise.
     */
    private function handOverResponse(): bool
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        // Shared hosts disable it, and then a call throws: the pass would never run. Little is lost
        // without it: PHP only notices a visitor left when it writes to them, and while they wait
        // the pass writes nothing (afterResponse() drops its output).
        if (function_exists('ignore_user_abort')) {
            ignore_user_abort(true);
        }

        return $this->servedByWorker || ($this->finishResponse)();
    }

    /**
     * Whether this server can send the response before the end-of-request pass, so that a visitor does not wait for it: what the jobs page tells an admin on mod_php or CGI.
     */
    public static function respondsEarly(): bool
    {
        return PHP_SAPI !== 'cli' && array_filter(self::FINISHERS, 'function_exists') !== [];
    }

    /**
     * What the server offers to end the response early: FPM, FrankenPHP outside its worker, LiteSpeed; mod_php and CGI have nothing, and the visitor waits for the pass.
     *
     * Where the function exists, the response has gone once it returns, whatever it answers: it
     * answers false when the response was already finished, which Symfony's Response::send() does
     * for every routed page.
     */
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
