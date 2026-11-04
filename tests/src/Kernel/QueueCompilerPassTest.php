<?php

namespace YesWiki\Test\Kernel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Messenger\Envelope;
use YesWiki\Core\YesWikiQueueCompilerPass;
use YesWiki\Kernel\Queue\ProvidesSchedule;
use YesWiki\Kernel\Service\Queue\QueueHandlerLocator;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';
require_once 'tests/src/Kernel/QueueTestMessageHandler.php';
require_once 'tests/src/Kernel/QueueTestMisdeclaredHandlers.php';

/** A handler enrols by carrying the attribute and nothing else: there is no list of handlers. */
#[CoversClass(YesWikiQueueCompilerPass::class)]
#[CoversClass(QueueHandlerLocator::class)]
class QueueCompilerPassTest extends YesWikiTestCase
{
    public function testAnAttributedHandlerIsFoundForItsMessage(): void
    {
        $locator = $this->compiled()->get(QueueHandlerLocator::class);

        $this->assertInstanceOf(QueueHandlerLocator::class, $locator);
        self::assertHandled(QueueTestMessage::class, $locator);
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
        $locator = $services->get(QueueHandlerLocator::class);
        $src = dirname(__DIR__, 3) . '/src/';
        $classOf = static fn (string $file): string => 'YesWiki\\' . str_replace('/', '\\', substr($file, strlen($src), -4));

        $handlers = 0;
        foreach (glob($src . '*/Message/*.php') ?: [] as $file) {
            $class = $classOf($file);
            if (str_ends_with($class, 'Handler')) {
                $handlers++;
                $this->assertTrue($services->has($class), "{$class} is a service");
                $message = substr($class, 0, -strlen('Handler'));
                $this->assertTrue(class_exists($message), "{$class} sits beside the message it handles");
                self::assertHandled($message, $locator);
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

    /** A handler that cannot say which message it handles would compile into a wrong or empty map: it stops the build instead. */
    #[DataProvider('misdeclaredHandlers')]
    public function testAHandlerThatDoesNotSayWhatItHandlesStopsTheBuild(string $handler): void
    {
        $this->expectException(\LogicException::class);

        $this->compiled($handler);
    }

    /** @return array<string, array{class-string}> */
    public static function misdeclaredHandlers(): array
    {
        return [
            'no __invoke()' => [QueueTestHandlerWithoutInvoke::class],
            'untyped parameter' => [QueueTestHandlerUntyped::class],
            'builtin parameter' => [QueueTestHandlerBuiltin::class],
            'attribute on a method' => [QueueTestHandlerOnAMethod::class],
        ];
    }

    /** `handles:` is the message when given, whatever the parameter says. */
    public function testAHandlerThatNamesItsMessageIsFoundForIt(): void
    {
        $locator = $this->compiled(QueueTestHandlerExplicit::class)->get(QueueHandlerLocator::class);

        $descriptors = [...$locator->getHandlers(new Envelope(new QueueTestMessage('x')))];

        $this->assertCount(1, $descriptors);
        $this->assertStringContainsString(QueueTestHandlerExplicit::class, $descriptors[0]->getName());
    }

    /** The locator hands a handler out of the compiled container: it cannot if the service was left private and removed. */
    public function testAHandlerIsMadePublicForTheLocator(): void
    {
        $container = $this->compiled(QueueTestMessageHandler::class);

        $this->assertTrue($container->findDefinition(QueueTestMessageHandler::class)->isPublic());
        $this->assertInstanceOf(QueueTestMessageHandler::class, $container->get(QueueTestMessageHandler::class));
    }

    /** A container that has no queue (no locator to fill) compiles all the same. */
    public function testWithoutALocatorThereIsNothingToBuild(): void
    {
        $container = new ContainerBuilder();
        YesWikiQueueCompilerPass::autoconfigure($container);
        $container->addCompilerPass(new YesWikiQueueCompilerPass());
        $container->register(QueueTestMessageHandler::class)->setAutoconfigured(true);

        $container->compile();

        $this->assertFalse($container->has(QueueHandlerLocator::class));
    }

    /** @param class-string $handler */
    private function compiled(string $handler = QueueTestMessageHandler::class): ContainerBuilder
    {
        $container = new ContainerBuilder();
        YesWikiQueueCompilerPass::autoconfigure($container);
        $container->addCompilerPass(new YesWikiQueueCompilerPass());
        $container->register(QueueHandlerLocator::class)->setPublic(true);
        $container->register($handler)->setAutoconfigured(true)->setPublic(false);
        $container->compile();

        return $container;
    }
}
