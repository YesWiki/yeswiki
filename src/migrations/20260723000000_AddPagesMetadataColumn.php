<?php

use YesWiki\Core\YesWikiMigration;

class AddPagesMetadataColumn extends YesWikiMigration
{
    public function run()
    {
        if (!$this->dbService->columnExists('pages', 'metadata')) {
            $this->dbService->query("ALTER TABLE {$this->dbService->prefixTable('pages')} ADD COLUMN metadata TEXT DEFAULT NULL");
        }
    }
}
