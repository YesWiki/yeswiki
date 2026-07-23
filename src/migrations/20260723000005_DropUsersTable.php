<?php

use YesWiki\Core\YesWikiMigration;

class DropUsersTable extends YesWikiMigration
{
    public function run()
    {
        // users live in `pages` now (see MigrateUsersToPages, which must run before this)
        $this->dbService->query("DROP TABLE IF EXISTS {$this->dbService->prefixTable('users')}");
    }
}
