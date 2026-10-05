<?php

use YesWiki\Content\Service\PageManager;
use YesWiki\Core\YesWikiMigration;
use YesWiki\Render\Service\CustomCssService;

/** Ticket 30: the `PageCss` page's stylesheet moves to `custom/styles/custom.css`, and the page goes. */
class PageCssBecomesAFile extends YesWikiMigration
{
    public function run()
    {
        $service = $this->getService(CustomCssService::class);
        $done = $service->absorbPage($this->getService(PageManager::class), 'PageCss', false);

        $said = [
            CustomCssService::WRITTEN => "the CSS on the page 'PageCss' was moved to {$service->path()}",
            CustomCssService::APPENDED => "the CSS on the page 'PageCss' was added to the end of {$service->path()}",
            CustomCssService::ALREADY_THERE => "{$service->path()} already held the CSS on the page 'PageCss'",
        ];
        if (isset($said[$done])) {
            $this->say($said[$done] . ', which is loaded on every page; the page itself was deleted (ticket 30).');
        }
    }
}
