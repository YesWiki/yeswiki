<?php

use YesWiki\Admin\Service\AdministrativeLogService;
use YesWiki\Content\Entity\PageBody;
use YesWiki\Content\Service\PageManager;
use YesWiki\Core\YesWikiMigration;
use YesWiki\Render\Service\CustomCssService;

/**
 * Ticket 30: the `PageCss` page's stylesheet moves to `custom/styles/custom.css`.
 *
 * CoreAssets no longer reads the page, so a wiki that had CSS there would lose it at
 * upgrade. This carries it across.
 *
 * **The page is left in place, deliberately.** Deleting a webmaster's stylesheet because it
 * moved house is not a migration's call: `PageCss` keeps its revisions, and anything that
 * still links to it still resolves. It simply stops being what the wiki loads. The move is
 * written to the administrative log, which is where a webmaster looks when something they
 * edited stopped taking effect.
 *
 * Refuses to overwrite: an instance that already has `custom/styles/custom.css` -- a farm
 * whose files were provisioned, an upgrade run twice -- keeps the file it has, and the log
 * says the page was left alone.
 *
 * Idempotent through that same check.
 */
class PageCssBecomesAFile extends YesWikiMigration
{
    public function run()
    {
        $service = $this->getService(CustomCssService::class);
        $log = $this->getService(AdministrativeLogService::class);

        $page = $this->getService(PageManager::class)->getOne('PageCss', null, true, true);
        $css = $page === null ? '' : trim(PageBody::content($page['body'] ?? []));

        if ($css === '') {
            return;
        }

        if ($service->exists()) {
            $log->log(
                'migration',
                "PageCss still holds CSS, but {$service->path()} already exists and was kept."
                . ' The page is no longer loaded by the wiki (ticket 30) -- merge it by hand if it is still wanted.'
            );

            return;
        }

        $service->write($css);
        $log->log(
            'migration',
            "the CSS on the page 'PageCss' was moved to {$service->path()} and is loaded from there now"
            . ' (ticket 30). The page itself was left untouched.'
        );
    }
}
