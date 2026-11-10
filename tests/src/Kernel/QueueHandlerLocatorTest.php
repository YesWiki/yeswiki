<?php

namespace YesWiki\Test\Kernel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Messenger\Envelope;
use YesWiki\Kernel\Service\Queue\QueueHandlerLocator;

interface QueueTestLocatedKind
{
}

final class QueueTestLocatedMessage implements QueueTestLocatedKind
{
}

/** A message goes to the handlers of its own class and of what it extends or implements, each service once. */
#[CoversClass(QueueHandlerLocator::class)]
class QueueHandlerLocatorTest extends TestCase
{
    public function testAHandlerOfAnInterfaceHandlesItsImplementationsAndAServiceUnderTwoTypesRunsOnce(): void
    {
        $locator = new QueueHandlerLocator(
            [
                QueueTestLocatedKind::class => ['kind-handler', 'shared-handler'],
                QueueTestLocatedMessage::class => ['shared-handler', 'own-handler'],
            ],
            new ServiceLocator([
                'kind-handler' => static fn (): \Closure => static function (): void {},
                'shared-handler' => static fn (): \Closure => static function (): void {},
                'own-handler' => static fn (): \Closure => static function (): void {},
            ])
        );

        $descriptors = [...$locator->getHandlers(new Envelope(new QueueTestLocatedMessage()))];

        $this->assertCount(3, $descriptors, 'the interface\'s handler and the class\'s, the one under both once');
        $this->assertSame([], [...$locator->getHandlers(new Envelope(new \stdClass()))], 'a message of another kind has none');
    }
}
