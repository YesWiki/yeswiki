<?php

namespace YesWiki\Test\Kernel;

use PHPUnit\Framework\TestCase;
use YesWiki\Kernel\Service\AfterResponse;

/** Work deferred until the response has gone: at once on the command line, after each request in the FrankenPHP worker. */
class AfterResponseTest extends TestCase
{
    public function testOnTheCommandLineATaskRunsAtOnce(): void
    {
        $ran = false;

        (new AfterResponse())->defer(function () use (&$ran): void {
            $ran = true;
        });

        $this->assertTrue($ran);
    }

    public function testTheWorkerRunsTheQueueOnlyWhenItAsks(): void
    {
        $afterResponse = new AfterResponse();
        $afterResponse->drainedByWorker();
        $ran = [];

        $afterResponse->defer(function () use (&$ran): void {
            $ran[] = 'first';
        });
        $afterResponse->defer(function () use (&$ran): void {
            $ran[] = 'second';
        });
        $this->assertSame([], $ran);

        $afterResponse->run();
        $this->assertSame(['first', 'second'], $ran);

        $afterResponse->run();
        $this->assertSame(['first', 'second'], $ran, 'a task runs once, not again after the next request');
    }

    public function testAFailingTaskDoesNotStopTheOthers(): void
    {
        $afterResponse = new AfterResponse();
        $afterResponse->drainedByWorker();
        $ran = false;
        $previousLog = ini_set('error_log', '/dev/null');

        try {
            $afterResponse->defer(function (): void {
                throw new \RuntimeException('unreachable follower');
            });
            $afterResponse->defer(function () use (&$ran): void {
                $ran = true;
            });
            $afterResponse->run();
        } finally {
            ini_set('error_log', (string)$previousLog);
        }

        $this->assertTrue($ran);
    }
}
