<?php

use YesWiki\Content\Entity\PageBody;
use YesWiki\Content\Service\PageManager;
use YesWiki\Core\YesWikiMigration;
use YesWiki\Render\Service\CustomCssService;

/** Ticket 30: the `PageCss` page's stylesheet moves to `custom/styles/custom.css`. */
class PageCssBecomesAFile extends YesWikiMigration
{
    public function run()
    {
        $service = $this->getService(CustomCssService::class);

        $page = $this->getService(PageManager::class)->getOne('PageCss', null, true, true);
        $css = $page === null ? '' : trim(PageBody::content($page['body'] ?? []));

        if ($css === '') {
            return;
        }

        if ($service->exists()) {
            $this->say(
                "PageCss still holds CSS, but {$service->path()} already exists and was kept."
                . ' The page is no longer loaded by the wiki (ticket 30) -- merge it by hand if it is still wanted.'
            );

            return;
        }

        $service->write($css);
        $this->say(
            "the CSS on the page 'PageCss' was moved to {$service->path()} and is loaded from there now"
            . ' (ticket 30). The page itself was left untouched.'
        );
    }
}
