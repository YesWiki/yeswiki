<?php

namespace YesWiki\Test\Kernel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\RedeliveryStamp;
use YesWiki\Kernel\Queue\QueueRetryStrategy;
use YesWiki\Kernel\Queue\RetryPolicy;

/** When a failed message is tried again, and how long it waits: what the consumer tests only reach through a past-any-delay bound. */
#[CoversClass(QueueRetryStrategy::class)]
class QueueRetryStrategyTest extends TestCase
{
    /** 60 s, then five times longer after each failure (give or take the 10 % of jitter Messenger adds), never more than 30 minutes. */
    #[DataProvider('delays')]
    public function testTheWaitGrowsFiveFoldAndIsCapped(int $retries, int $milliseconds, float $jitter): void
    {
        $envelope = Envelope::wrap(new \stdClass())->with(new RedeliveryStamp($retries));

        $this->assertEqualsWithDelta($milliseconds, (new QueueRetryStrategy())->getWaitingTime($envelope), $milliseconds * $jitter);
    }

    /** @return array<string, array{int, int, float}> */
    public static function delays(): array
    {
        return [
            'first retry' => [0, 60000, 0.1],
            'second' => [1, 300000, 0.1],
            'third' => [2, 1500000, 0.1],
            'capped' => [3, 1800000, 0.0],
        ];
    }

    public function testAMessageIsRetriedThreeTimesByDefault(): void
    {
        $strategy = new QueueRetryStrategy();

        $this->assertTrue($strategy->isRetryable(Envelope::wrap(new \stdClass())->with(new RedeliveryStamp(2))));
        $this->assertFalse($strategy->isRetryable(Envelope::wrap(new \stdClass())->with(new RedeliveryStamp(3))));
    }

    /** A message that would retry a negative number of times is simply never retried. */
    public function testANegativeMaxRetriesNeverRetries(): void
    {
        $message = new class implements RetryPolicy {
            public function maxRetries(): int
            {
                return -2;
            }
        };

        $this->assertFalse((new QueueRetryStrategy())->isRetryable(Envelope::wrap($message)->with(new RedeliveryStamp(0))));
    }
}
