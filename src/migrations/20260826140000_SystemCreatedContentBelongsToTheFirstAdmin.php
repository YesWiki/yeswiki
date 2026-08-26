<?php

use YesWiki\Core\YesWikiMigration;
use YesWiki\Identity\Service\GroupManager;
use YesWiki\Kernel\Service\DbService;

/** Content the wiki made for itself, found by an empty `user`, gets the first admin as owner. */
class SystemCreatedContentBelongsToTheFirstAdmin extends YesWikiMigration
{
    public function run(): void
    {
        $owner = $this->getService(GroupManager::class)->firstAdmin();
        if ($owner === null) {
            return;
        }

        $db = $this->getService(DbService::class);
        $ownerCol = $db->quoteIdentifier('owner');
        $userCol = $db->quoteIdentifier('user');
        $unowned = " WHERE latest = 'Y' AND {$ownerCol} = '' AND {$userCol} = ''";

        $claimed = $db->countRows('SELECT tag FROM ' . $db->prefixTable('pages') . $unowned);
        if ($claimed === 0) {
            return;
        }

        $db->query('UPDATE ' . $db->prefixTable('pages') . " SET {$ownerCol} = ?" . $unowned, [$owner]);

        $this->say("{$claimed} unowned Content the wiki created for itself now belongs to {$owner}.");
    }
}
