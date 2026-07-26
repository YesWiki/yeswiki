<?php

use YesWiki\Core\Service\EntryManager;
use YesWiki\Core\YesWikiMigration;

class RemoveAttributesFromEntries extends YesWikiMigration
{
    public function run()
    {
        $entryManager = $this->wiki->services->get(EntryManager::class);
        $entryManager->removeAttributes([], ['createur'], true);
    }
}
