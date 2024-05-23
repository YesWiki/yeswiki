<?php

use YesWiki\AutoUpdate\Service\UpdateAdminPagesService;
use YesWiki\Core\Service\PageManager;
use YesWiki\Core\YesWikiMigration;

class IntroduceArchiveMecanism extends YesWikiMigration
{
    public function run()
    {
        $page = $this->wiki->services->get(PageManager::class)->getOne('GererSauvegardes');
        if (empty($page)) {
            $this->wiki->services->get(UpdateAdminPagesService::class)->update(['GererSauvegardes']);
        }
    }
}
