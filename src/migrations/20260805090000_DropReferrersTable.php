<?php

use YesWiki\Core\YesWikiMigration;

/** Ticket 28: drop the `referrers` table. */
class DropReferrersTable extends YesWikiMigration
{
    public function run()
    {
        $this->dbService->query('DROP TABLE IF EXISTS' . $this->dbService->prefixTable('referrers'));
    }
}
