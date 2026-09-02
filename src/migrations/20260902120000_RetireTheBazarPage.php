<?php

use YesWiki\Content\Service\PageManager;
use YesWiki\Core\YesWikiMigration;

/** Every form has its own screen, so the stock `BazaR` page that only ran `{{bazar}}` has nothing left to do; a page a webmaster rewrote is kept. */
class RetireTheBazarPage extends YesWikiMigration
{
    public function run(): void
    {
        $pageManager = $this->getService(PageManager::class);
        $page = $pageManager->getOne('BazaR');
        if ($page === null) {
            return;
        }
        $content = trim((string)(is_array($page['body'] ?? null) ? ($page['body']['content'] ?? '') : ''));
        if (preg_match('/^\{\{bazar[^}]*\}\}$/', $content) === 1) {
            $pageManager->deleteOrphaned('BazaR');
        }
    }
}
