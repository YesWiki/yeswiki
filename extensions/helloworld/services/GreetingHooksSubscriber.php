<?php

namespace YesWiki\HelloWorld\Service;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use YesWiki\Kernel\Entity\Event;
use YesWiki\Kernel\Performable\PerformableEvent;
use YesWiki\Render\Service\TemplateEngine;

/**
 * How an extension hooks a core action, replacing the old `__GreetingAction.php` /
 * `GreetingAction__.php` filename convention (wave-two ticket 06).
 *
 * The convention could not survive namespacing -- the hook's target came from its filename
 * -- and it instantiated a whole action object just to adjust an argument. Subscribe to
 * `action.<name>.before` / `.after` instead. Core no longer hooks itself at all: its own
 * callbacks were merged into the classes they wrapped. This mechanism exists for extensions.
 *
 * The same subscriber is where an extension hangs work on the wiki's own housekeeping:
 * `maintenance.before` / `maintenance.after` (YesWikiRuntime::maintenance()).
 */
class GreetingHooksSubscriber implements EventSubscriberInterface
{
    private TemplateEngine $templateEngine;

    /** @var list<array<string, mixed>> */
    private array $maintenanceSeen = [];

    public function __construct(TemplateEngine $templateEngine)
    {
        $this->templateEngine = $templateEngine;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            'action.greeting.before' => 'prepareArguments',
            'action.greeting.after' => 'appendSuffix',
            'handler.hello.after' => 'rewriteHelloOutput',
            'maintenance.before' => 'onMaintenanceStarting',
            'maintenance.after' => 'onMaintenanceDone',
        ];
    }

    /**
     * Housekeeping of an extension's own, on the wiki's schedule.
     *
     * The event carries `startedAt`, `interval` and `previousRun` -- the last being how an
     * extension with its own rhythm decides whether this run is one of its own: core runs
     * every half hour, and "once a day" is `startedAt - myLastRun > 86400`, kept in the
     * extension's own storage.
     *
     * Two things this listener must not do, and neither is enforced: take its time, or
     * matter. It runs inside a page view somebody else asked for, and whatever it throws
     * is swallowed so their page still renders -- so anything long, or anything that must
     * be *known* to have happened, belongs in a command instead.
     */
    public function onMaintenanceStarting(Event $event): void
    {
        $this->maintenanceSeen[] = ['phase' => 'before'] + $event->getData();
    }

    public function onMaintenanceDone(Event $event): void
    {
        $this->maintenanceSeen[] = ['phase' => 'after'] + $event->getData();
    }

    /**
     * What this listener saw, for the test that pins the contract.
     *
     * A sample that wrote to the log or to a file would be doing real work in a stranger's
     * page view on every wiki that installs it -- which is the one thing the note above
     * asks extensions not to do.
     *
     * @return list<array<string, mixed>>
     */
    public function maintenanceSeen(): array
    {
        return $this->maintenanceSeen;
    }

    /** What the old __GreetingAction::formatArguments() did. */
    public function prepareArguments(PerformableEvent $event): void
    {
        $args = $event->getArguments();
        $event->mergeArguments([
            'message' => !empty($args['message'])
                ? $args['message'] . ' ' . _t('HELLOWORD_CALLBACK_MSG')
                : _t('HELLOWORD_NO_MSG_PARAM'),
        ]);
    }

    /** What the old GreetingAction__::run() did. */
    public function appendSuffix(PerformableEvent $event): void
    {
        $event->appendOutput($this->templateEngine->render('@helloworld/greeting-suffix.twig'));
    }

    /**
     * What the old HelloHandler__::run() did -- rewriting the handler's own output rather
     * than appending to it. An after-listener that needs the produced markup asks the
     * performable's caller for it; here the sample simply contributes its replacement.
     */
    public function rewriteHelloOutput(PerformableEvent $event): void
    {
        $event->appendOutput('<!-- helloworld: handler.hello.after listener ran -->');
    }
}
