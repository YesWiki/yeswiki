<?php

use YesWiki\Core\YesWikiMigration;
use YesWiki\Kernel\Service\SessionSchema;

/** PHP sessions move from files on the web server's disk to a table of the wiki's database, which every replica shares (replicas 02). */
class SessionsLiveInTheDatabase extends YesWikiMigration
{
    public function run()
    {
        $schema = $this->getService(SessionSchema::class);
        if (!$schema->exists()) {
            $schema->create();
        }
    }
}
