<?php

use YesWiki\Core\YesWikiMigration;
use YesWiki\Identity\Service\GroupManager;
use YesWiki\Kernel\Service\DbService;

/**
 * Content the wiki made for itself gets an owner.
 *
 * A migration and the installer both run with nobody signed in, so PageManager saves what they
 * create owned by no one -- the Page, User and File forms of ticket 10 among them. An unowned
 * row answers to admins alone, which is not what a wiki's own structure should be.
 *
 * The rows are found by an empty `user`, not by an empty `owner`: an anonymous visitor's edit
 * records their IP there, so `user = ''` means the console wrote the row and nobody else did.
 * That distinction is the whole safety of this migration -- an anonymous contribution keeps its
 * empty owner, because it was a person's, not the system's.
 */
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
