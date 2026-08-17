<?php

namespace YesWiki\HelloWorld\Service;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use YesWiki\Kernel\Entity\Event;
use YesWiki\Kernel\Performable\PerformableEvent;
use YesWiki\Render\Service\TemplateEngine;

/**
 * How an extension hooks a core action, replacing the old `__GreetingAction.php` / `GreetingAction__.php` filename convention (wave-two ticket 06).
 */
class GreetingHooksSubscriber implements EventSubscriberInterface
{
    private TemplateEngine $templateEngine;

    /**
     * @var list<array<string, mixed>>
     */
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

    /** Housekeeping of an extension's own, on the wiki's schedule. */
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
     * What the old HelloHandler__::run() did -- rewriting the handler's own output rather than appending to it.
     */
    public function rewriteHelloOutput(PerformableEvent $event): void
    {
        $event->appendOutput('<!-- helloworld: handler.hello.after listener ran -->');
    }
}
