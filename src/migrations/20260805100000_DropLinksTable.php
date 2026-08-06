<?php

use YesWiki\Core\YesWikiMigration;
use YesWiki\Kernel\Service\DbService;

/**
 * Ticket 29: drop the `links` table.
 *
 * It was a derived index of which page linked to which, rebuilt by re-rendering the whole
 * body on every save. Four surfaces read it -- `{{orphanedpages}}`, `{{wantedpages}}`,
 * `{{listpages tree=…}}` and the deletion screen's backlink warning -- and all four are
 * retired with it. Search (ticket 26) answers "what mentions this page" now.
 *
 * Unlike `body_r` (see 00000000000001_DropBodyRColumn, which has to run before every other
 * migration) this one needs no special ordering: nothing writes the table any more, and
 * leaving its rows in place for the rest of the upgrade breaks nothing.
 *
 * Idempotent: `DROP TABLE IF EXISTS` is understood on all three dialects.
 */
class DropLinksTable extends YesWikiMigration
{
    public function run()
    {
        $db = $this->getService(DbService::class);
        $db->query('DROP TABLE IF EXISTS' . $db->prefixTable('links'));
    }
}
