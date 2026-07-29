<?php

use YesWiki\Content\Service\EntryManager;
use YesWiki\Core\YesWikiMigration;

class RemoveAttributesFromEntries extends YesWikiMigration
{
    public function run()
    {
        $entryManager = $this->getService(EntryManager::class);
        $entryManager->removeAttributes([], ['createur'], true);
    }
}
