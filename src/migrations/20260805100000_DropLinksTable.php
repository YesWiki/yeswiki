<?php

use YesWiki\Core\YesWikiMigration;
use YesWiki\Kernel\Service\DbService;

/** Ticket 29: drop the `links` table. */
class DropLinksTable extends YesWikiMigration
{
    public function run()
    {
        $db = $this->getService(DbService::class);
        $db->query('DROP TABLE IF EXISTS' . $db->prefixTable('links'));
    }
}
