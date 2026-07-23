<?php

use YesWiki\Core\YesWikiMigration;

class DropAclsTable extends YesWikiMigration
{
    public function run()
    {
        // ACLs live in pages.metadata now (see AclService), not a standalone table.
        // Clean break, per this rewrite's decisions: existing acls-table rows are not
        // migrated into metadata here -- a separate migration script for existing installs'
        // data is planned as later, dedicated work once the new shape has settled.
        $this->dbService->query("DROP TABLE IF EXISTS {$this->dbService->prefixTable('acls')}");
    }
}
