<?php

use YesWiki\Content\Service\PageManager;
use YesWiki\Core\YesWikiMigration;
use YesWiki\Render\Service\CustomCssService;

/** A `PageCss` page the first migration left behind, and Markdown since mangled, is folded into custom.css and deleted. */
class PageCssLeavesOnceItIsAFile extends YesWikiMigration
{
    public function run()
    {
        $service = $this->getService(CustomCssService::class);
        $done = $service->absorbPage($this->getService(PageManager::class), 'PageCss', true);

        $said = [
            CustomCssService::WRITTEN => "{$service->path()} was missing, so the CSS on the page 'PageCss' was written to it",
            CustomCssService::APPENDED => "{$service->path()} lacked the CSS on the page 'PageCss', which was added to its end",
            CustomCssService::ALREADY_THERE => "{$service->path()} already held the CSS on the page 'PageCss'",
        ];
        if (isset($said[$done])) {
            $this->say($said[$done] . '; the page, which nothing loads any more, was deleted.');
        }
    }
}
