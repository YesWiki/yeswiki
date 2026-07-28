<?php

namespace YesWiki\Test\Kernel;

use YesWiki\Kernel\Performable\PerformableEvent;
use YesWiki\Kernel\Service\EventDispatcher;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * Ticket 06: extensions hook actions and handlers through events instead of by dropping a
 * `__name.php` beside core's file.
 *
 * Core does not use this -- its own callbacks were merged into the classes they wrapped.
 * It exists purely so extensions keep the capability, and the bundled helloworld sample is
 * the live proof (see GreetingHooksSubscriber).
 */
class PerformableEventTest extends YesWikiTestCase
{
    private function dispatcher(): EventDispatcher
    {
        $dispatcher = $this->getWiki()->services?->get(EventDispatcher::class);
        $this->assertInstanceOf(EventDispatcher::class, $dispatcher);

        return $dispatcher;
    }

    public function testEventNamesRunCoarseToSpecific(): void
    {
        $event = new PerformableEvent('action', 'greeting', []);

        $this->assertSame(
            ['performable.before', 'action.before', 'action.greeting.before'],
            $event->eventNames(PerformableEvent::BEFORE)
        );
        $this->assertSame(
            ['performable.after', 'action.after', 'action.greeting.after'],
            $event->eventNames(PerformableEvent::AFTER)
        );
    }

    public function testABeforeListenerCanRewriteArguments(): void
    {
        // this is what most old __hooks actually did: adjust an argument, not print
        $event = new PerformableEvent('action', 'x', ['message' => 'hi', 'keep' => 1]);
        $event->mergeArguments(['message' => 'hi there']);

        $this->assertSame(['message' => 'hi there', 'keep' => 1], $event->getArguments());
    }

    public function testAnAfterListenerCanContributeOutput(): void
    {
        $event = new PerformableEvent('handler', 'show', []);
        $event->appendOutput('<i>a</i>');
        $event->appendOutput('<i>b</i>');

        $this->assertSame('<i>a</i><i>b</i>', $event->getOutput());
    }

    /**
     * The whole point: an extension reaches a core action without touching core. The
     * helloworld sample subscribes to action.greeting.before/.after -- the two hook files
     * it used to ship were deleted in favour of this.
     */
    public function testTheBundledExtensionHooksACoreActionThroughEvents(): void
    {
        $output = $this->getWiki()->Action('greeting');

        $this->assertStringContainsString(
            'added by the action callback',
            $output,
            'the after-listener must still contribute its markup'
        );
        $this->assertStringContainsString(
            'parameter defined in the',
            $output,
            'the before-listener must still rewrite the message argument'
        );
    }

    public function testDispatchingAnUnsubscribedEventIsHarmless(): void
    {
        $event = new PerformableEvent('action', 'nobody-listens', ['a' => 1]);
        $this->dispatcher()->dispatch($event, 'action.nobody-listens.before');

        $this->assertSame(['a' => 1], $event->getArguments());
        $this->assertSame('', $event->getOutput());
    }
}
