<?php

use YesWiki\Core\Service\UserManager;
use YesWiki\Core\YesWikiMigration;

class MigrateUsersToPages extends YesWikiMigration
{
    public function run()
    {
        // users are load-bearing content (real accounts, real password hashes) rather than
        // reconfigurable state like the dropped `acls` table -- migrated in place rather
        // than reset, same reasoning as MigrateNatureToPages (ticket 05)
        if (!$this->dbService->columnExists('users', 'name')) {
            return;
        }

        $userManager = $this->getService(UserManager::class);
        $rows = $this->dbService->loadAll(
            "SELECT * FROM {$this->dbService->prefixTable('users')} ORDER BY name ASC"
        );

        foreach ($rows as $row) {
            // migrateLegacyUser() preserves the stored password hash verbatim -- it must
            // NOT go through UserManager::create(), which always hashes a fresh plaintext
            // password and would silently invalidate every existing user's password
            $userManager->migrateLegacyUser($row);
        }
    }
}
