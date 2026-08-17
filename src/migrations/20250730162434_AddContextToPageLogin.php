<?php

use YesWiki\Content\Service\PageManager;
use YesWiki\Core\YesWikiMigration;

class AddContextToPageLogin extends YesWikiMigration
{
    public function run(): void
    {
        $pageManager = $this->getService(PageManager::class);
    }
}
