<?php

use YesWiki\Bazar\Service\EntryManager;
use YesWiki\Core\YesWikiMigration;

class RemoveAttributesFromEntries extends YesWikiMigration
{
    public function run()
    {
        $entryManager = $this->wiki->services->get(EntryManager::class);
        $entryManager->removeAttributes([], ['createur'], true);
    }
}
