<?php

use YesWiki\Core\YesWikiMigration;

class DropNatureTable extends YesWikiMigration
{
    public function run()
    {
        // forms live in `pages` now (see MigrateNatureToPages, which must run before this)
        $this->dbService->query("DROP TABLE IF EXISTS {$this->dbService->prefixTable('nature')}");
    }
}
