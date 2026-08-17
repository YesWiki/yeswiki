<?php

use YesWiki\Core\YesWikiMigration;
use YesWiki\Kernel\Service\DbService;

/** Ticket 29: drop `pages.body_r`. */
class DropBodyRColumn extends YesWikiMigration
{
    public function run()
    {
        $this->getService(DbService::class)->schema()->dropColumn('pages', 'body_r');
    }
}
