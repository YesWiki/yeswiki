<?php

use YesWiki\Core\YesWikiMigration;
use YesWiki\Identity\Service\UserManager;

class MigrateUsersToPages extends YesWikiMigration
{
    public function run()
    {
        if (!$this->dbService->schema()->columnExists('users', 'name')) {
            return;
        }

        $userManager = $this->getService(UserManager::class);
        $rows = $this->dbService->loadAll(
            "SELECT * FROM {$this->dbService->prefixTable('users')} ORDER BY name ASC"
        );

        foreach ($rows as $row) {
            $userManager->migrateLegacyUser($row);
        }
    }
}
