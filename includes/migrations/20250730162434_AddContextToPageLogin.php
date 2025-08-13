<?php

use YesWiki\Core\Service\PageManager;
use YesWiki\Core\YesWikiMigration;

class AddContextToPageLogin extends YesWikiMigration
{
    public function run(): void
    {
        // TODO : test if PageLogin exists, and add context="login-page" to {{login}} if exists
        $pageManager = $this->getService(PageManager::class);

        return;
    }
}
