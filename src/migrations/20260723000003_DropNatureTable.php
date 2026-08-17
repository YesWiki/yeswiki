<?php

use YesWiki\Core\YesWikiMigration;

class DropNatureTable extends YesWikiMigration
{
    public function run()
    {
        $this->dbService->query("DROP TABLE IF EXISTS {$this->dbService->prefixTable('nature')}");
    }
}
