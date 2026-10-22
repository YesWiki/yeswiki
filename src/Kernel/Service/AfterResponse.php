<?php

namespace YesWiki\Kernel\Service;

/** Runs work once the response has reached the browser, when the server can hand it over early; otherwise right away. */
class AfterResponse
{
    /** @var list<callable> */
    protected array $tasks = [];
    private bool $drainedByWorker = false;

    /** Queues a task for after the response, or runs it now when the response cannot be finished early. */
    public function defer(callable $task): void
    {
        if (!$this->drainedByWorker && !$this->canFinishEarly()) {
            $task();

            return;
        }
        if (!$this->drainedByWorker && $this->tasks === []) {
            register_shutdown_function([$this, 'run']);
        }
        $this->tasks[] = $task;
    }

    /** The FrankenPHP worker calls run() itself once each response has gone, so nothing waits for the worker to stop. */
    public function drainedByWorker(): void
    {
        $this->drainedByWorker = true;
    }

    /** Hands the response to the browser, releases the session, then runs every queued task; one failing task does not stop the others. */
    public function run(): void
    {
        if ($this->tasks === []) {
            return;
        }
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        ignore_user_abort(true);
        if (!$this->drainedByWorker) {
            if (function_exists('fastcgi_finish_request')) {
                fastcgi_finish_request();
            } elseif (function_exists('frankenphp_finish_request')) {
                frankenphp_finish_request();
            } elseif (function_exists('litespeed_finish_request')) {
                litespeed_finish_request();
            }
        }

        while (($task = array_shift($this->tasks)) !== null) {
            try {
                $task();
            } catch (\Throwable $e) {
                error_log('AfterResponse: ' . $e->getMessage());
            }
        }
    }

    protected function canFinishEarly(): bool
    {
        return PHP_SAPI !== 'cli' && (
            function_exists('fastcgi_finish_request')
            || function_exists('frankenphp_finish_request')
            || function_exists('litespeed_finish_request')
        );
    }
}
