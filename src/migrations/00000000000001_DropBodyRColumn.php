<?php

use YesWiki\Core\YesWikiMigration;
use YesWiki\Kernel\Service\DbService;

/**
 * Ticket 29: drop `pages.body_r`.
 *
 * The column held the empty string on every revision row of every page -- written at three
 * insert sites, read at none.
 *
 * **The date is deliberately impossible, and load-bearing.** MigrationService runs whatever
 * is not yet recorded in plain `sort()` order of the file names, so a low name runs first
 * even though this was written in 2026. It has to: `body_r` is `NOT NULL` with no default on
 * every existing install, and core stopped supplying a value for it the moment the ticket
 * landed. Any migration that writes a page row -- and several do, if only through the
 * administrative log -- would fail with an integrity-constraint violation while upgrading,
 * for as long as the old column was still there. Dating this migration normally left the
 * upgrade broken for every wiki older than 2026-08-05, which is all of them.
 *
 * Idempotent: DbService::dropColumn() checks the column exists, because `DROP COLUMN IF
 * EXISTS` is not a thing on MySQL.
 */
class DropBodyRColumn extends YesWikiMigration
{
    public function run()
    {
        $this->getService(DbService::class)->dropColumn('pages', 'body_r');
    }
}
