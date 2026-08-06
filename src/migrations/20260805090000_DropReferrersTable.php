<?php

use YesWiki\Core\YesWikiMigration;

/**
 * Ticket 28: drop the `referrers` table.
 *
 * It was written on every page view that arrived with an external `HTTP_REFERER` and read
 * by nothing -- no action, no handler, no template, no API. The surface that once displayed
 * it is long gone, so what remained was an INSERT in the render path feeding storage nobody
 * could look at.
 *
 * `IF EXISTS` on all three dialects, so this is idempotent and survives a wiki whose table
 * was already dropped by hand.
 */
class DropReferrersTable extends YesWikiMigration
{
    public function run()
    {
        $this->dbService->query('DROP TABLE IF EXISTS' . $this->dbService->prefixTable('referrers'));
    }
}
