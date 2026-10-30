<?php

namespace YesWiki\Test\Kernel;

use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Messenger\Envelope;
use YesWiki\Core\YesWikiQueueCompilerPass;
use YesWiki\Kernel\Queue\ProvidesSchedule;
use YesWiki\Kernel\Service\Queue\QueueHandlerLocator;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';
require_once 'tests/src/Kernel/QueueTestMessageHandler.php';

/** A handler enrols by carrying the attribute and nothing else: there is no list of handlers. */
#[CoversClass(YesWikiQueueCompilerPass::class)]
#[CoversClass(QueueHandlerLocator::class)]
class QueueCompilerPassTest extends YesWikiTestCase
{
    public function testAnAttributedHandlerIsFoundForItsMessage(): void
    {
        $locator = $this->compiled()->get(QueueHandlerLocator::class);

        $this->assertInstanceOf(QueueHandlerLocator::class, $locator);
        $this->assertContains(QueueTestMessage::class, $locator->handledTypes());
    }

    public function testTheLocatorHandsOutTheHandlerFromTheContainer(): void
    {
        $locator = $this->compiled()->get(QueueHandlerLocator::class);

        $descriptors = [...$locator->getHandlers(new Envelope(new QueueTestMessage('x')))];

        $this->assertCount(1, $descriptors);
        $this->assertStringContainsString(QueueTestMessageHandler::class, $descriptors[0]->getName());
        $this->assertSame([], [...$locator->getHandlers(new Envelope(new \stdClass()))], 'a message nobody handles has no handler');
    }

    /** On the wiki's own container: a handler of a Message/ folder is a service and handles the message beside it, that message is no service, and a Scheduler/ class is a schedule. */
    public function testTheMessageAndSchedulerFoldersAreWhatTheContainerRegisters(): void
    {
        $services = $this->getWiki()->services;
        $handled = $services->get(QueueHandlerLocator::class)->handledTypes();
        $src = dirname(__DIR__, 3) . '/src/';
        $classOf = static fn (string $file): string => 'YesWiki\\' . str_replace('/', '\\', substr($file, strlen($src), -4));

        $handlers = 0;
        foreach (glob($src . '*/Message/*.php') ?: [] as $file) {
            $class = $classOf($file);
            if (str_ends_with($class, 'Handler')) {
                $handlers++;
                $this->assertTrue($services->has($class), "{$class} is a service");
                $this->assertContains(substr($class, 0, -strlen('Handler')), $handled);
            } else {
                $this->assertFalse($services->has($class), "{$class} is a message, not a service");
            }
        }
        $this->assertGreaterThanOrEqual(14, $handlers);

        $schedules = glob($src . '*/Scheduler/*.php') ?: [];
        $this->assertGreaterThanOrEqual(5, count($schedules));
        foreach ($schedules as $file) {
            $class = $classOf($file);
            $this->assertTrue($services->has($class), "{$class} is a service");
            $this->assertTrue(is_a($class, ProvidesSchedule::class, true), "{$class} is a schedule");
        }
    }

    private function compiled(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        YesWikiQueueCompilerPass::autoconfigure($container);
        $container->addCompilerPass(new YesWikiQueueCompilerPass());
        $container->register(QueueHandlerLocator::class)->setPublic(true);
        $container->register(QueueTestMessageHandler::class)->setAutoconfigured(true);
        $container->compile();

        return $container;
    }
}
