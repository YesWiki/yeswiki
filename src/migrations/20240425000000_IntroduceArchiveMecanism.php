<?php

use YesWiki\Admin\Service\UpdateAdminPagesService;
use YesWiki\Content\Service\PageManager;
use YesWiki\Core\YesWikiMigration;

class IntroduceArchiveMecanism extends YesWikiMigration
{
    public function run()
    {
        $page = $this->getService(PageManager::class)->getOne('GererSauvegardes');
        if (empty($page)) {
            $this->getService(UpdateAdminPagesService::class)->update(['GererSauvegardes']);
        }
    }
}
