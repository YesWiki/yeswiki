<?php

namespace YesWiki\Test\Core\Controller;

use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Depends;
use YesWiki\Admin\Controller\WebhooksController;
use YesWiki\Core\YesWikiRuntime;
use YesWiki\Kernel\Service\EventDispatcher;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * Ticket 20 (webhooks absorbed into core): the controller must be registered in the container, subscribed to the same comment/entry events as the old extension (and nothing more — no form/user events, per the ticket's scope), and read subscriptions from the triple store unchanged.
 */
#[CoversMethod(WebhooksController::class, 'getSubscribedEvents')]
#[CoversMethod(WebhooksController::class, 'get_all_webhooks')]
class WebhooksControllerTest extends YesWikiTestCase
{
    public function testWikiExisting(): YesWikiRuntime
    {
        $wiki = $this->getWiki();
        $this->assertTrue($wiki->services->has(WebhooksController::class));

        return $wiki;
    }

    #[Depends('testWikiExisting')]
    public function testSubscribedEventsUnchangedFromExtension(YesWikiRuntime $wiki): void
    {
        $events = WebhooksController::getSubscribedEvents();
        $this->assertSame([
            'comment.created' => 'sendCommentCreatedWebHook',
            'comment.updated' => 'sendCommentModifiedWebHook',
            'comment.deleted' => 'sendCommentDeletedWebHook',
            'entry.created' => 'sendEntryCreatedWebHook',
            'entry.updated' => 'sendEntryModifiedWebHook',
            'entry.deleted' => 'sendEntryDeletedWebHook',
        ], $events, 'event scope must match the old extension exactly — no form/user events');
    }

    #[Depends('testWikiExisting')]
    public function testRegisteredAsEventSubscriber(YesWikiRuntime $wiki): void
    {
        $this->assertTrue($wiki->services->has(EventDispatcher::class));
        $dispatcher = $wiki->services->get(EventDispatcher::class);
        $listeners = $dispatcher->getListeners('entry.created');
        $found = false;
        foreach ($listeners as $listener) {
            if (is_array($listener) && $listener[0] instanceof WebhooksController) {
                $found = true;
            }
        }
        $this->assertTrue($found, 'WebhooksController must listen on entry.created');
    }

    #[Depends('testWikiExisting')]
    public function testNoWebhooksRegisteredReturnsEmptyList(YesWikiRuntime $wiki): void
    {
        $controller = $wiki->services->get(WebhooksController::class);
        $this->assertSame([], $controller->get_all_webhooks());
    }
}
