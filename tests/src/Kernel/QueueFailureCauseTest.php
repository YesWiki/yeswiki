<?php

namespace YesWiki\Test\Kernel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\ErrorDetailsStamp;
use YesWiki\Kernel\Queue\QueueFailureCause;

/** What a failed message keeps of its cause: short enough for the `last_error` column, whatever the handler threw. */
#[CoversClass(QueueFailureCause::class)]
class QueueFailureCauseTest extends TestCase
{
    public function testALongCauseIsCutToTheLengthTheColumnKeeps(): void
    {
        $cut = QueueFailureCause::fitted(str_repeat('é', 1500));

        $this->assertSame(1000, mb_strlen($cut), 'cut in characters, not in bytes');
        $this->assertTrue(mb_check_encoding($cut, 'UTF-8'), 'no character is split in two');
        $this->assertSame('short', QueueFailureCause::fitted('short'));
    }

    public function testAThrowableIsNamedByItsClassThenItsMessage(): void
    {
        $this->assertSame('RuntimeException: boom', QueueFailureCause::ofThrowable(new \RuntimeException('boom')));

        $long = QueueFailureCause::ofThrowable(new \RuntimeException(str_repeat('x', 2000)));
        $this->assertSame(1000, strlen($long));
        $this->assertStringStartsWith('RuntimeException: xxx', $long);
    }

    public function testTheCauseOfAnEnvelopeComesFromItsErrorDetails(): void
    {
        $envelope = Envelope::wrap(new \stdClass())->with(ErrorDetailsStamp::create(new \LogicException('nope')));

        $this->assertSame('LogicException: nope', QueueFailureCause::of($envelope));
        $this->assertNull(QueueFailureCause::of(Envelope::wrap(new \stdClass())), 'a message that never failed has no cause');
    }
}
