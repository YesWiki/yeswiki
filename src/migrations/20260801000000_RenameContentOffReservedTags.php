<?php

use YesWiki\Admin\Service\AdministrativeLogService;
use YesWiki\Content\Service\PageManager;
use YesWiki\Core\YesWikiMigration;
use YesWiki\Kernel\Routing\ReservedTags;

/**
 * Ticket 20 (ADR-0001 as amended): move any Content sitting on a tag the router owns.
 *
 * ## The decision this migration encodes
 *
 * When Content and a route want the same name, **the route wins** — and it always has: a
 * page tagged `api` or `doc` is already unreachable today, because dispatch checks the
 * routed names before it ever looks for a page. That is not a decision this ticket is free
 * to reverse, either: letting Content shadow a route would mean anyone able to create a
 * page could switch off the wiki's API by naming a page after it.
 *
 * So the only open question was what to do about rows that already exist, and the answer
 * here is to **rename them** rather than leave them shadowed. That is a data change, made
 * deliberately: such a row is unreachable by its own tag right now, so renaming it is the
 * only thing that gives it back a URL. Leaving it in place would preserve nothing but the
 * invisibility. Renaming does break inbound links to a page that had one — but a shadowed
 * page never answered those links anyway.
 *
 * The new tag comes from PageManager::suggestFreeTag(), so it lands on `api2` / `doc2` and
 * follows the same collision convention as everything else.
 *
 * Idempotent: once nothing sits on a reserved tag, it does nothing.
 *
 * `pages` carries the identity and `triples` keys Content type and keyword index off the
 * same tag, so both move. `links` was a derived index that any later page save rebuilt; it
 * was deliberately left alone rather than half-migrated, and ticket 29 removed it entirely.
 */
class RenameContentOffReservedTags extends YesWikiMigration
{
    public function run()
    {
        $pageManager = $this->getService(PageManager::class);
        $log = $this->getService(AdministrativeLogService::class);
        $pages = $this->dbService->prefixTable('pages');
        $triples = $this->dbService->prefixTable('triples');

        foreach (ReservedTags::NAMES as $reserved) {
            // case-insensitively, because a MySQL wiki can hold `Api` and answer to `api`
            $rows = $this->dbService->loadAll(
                "SELECT DISTINCT tag FROM {$pages} WHERE LOWER(tag) = '{$this->dbService->escape($reserved)}'"
            );

            foreach ($rows as $row) {
                $oldTag = (string)$row['tag'];
                $newTag = $pageManager->suggestFreeTag($oldTag);
                if ($newTag === $oldTag) {
                    // suggestFreeTag() treats reserved as unavailable, so this cannot happen
                    // unless the reserved list and this loop have disagreed -- skip rather
                    // than rename a row onto the name it is already stuck on
                    continue;
                }

                $this->dbService->query(
                    "UPDATE {$pages} SET tag = '{$this->dbService->escape($newTag)}'"
                    . " WHERE tag = '{$this->dbService->escape($oldTag)}'"
                );
                $this->dbService->query(
                    "UPDATE {$pages} SET parent = '{$this->dbService->escape($newTag)}'"
                    . " WHERE parent = '{$this->dbService->escape($oldTag)}'"
                );
                $this->dbService->query(
                    "UPDATE {$triples} SET resource = '{$this->dbService->escape($newTag)}'"
                    . " WHERE resource = '{$this->dbService->escape($oldTag)}'"
                );

                // a migration has no per-row output channel (MigrationService returns one
                // message per migration), and a silent rename is exactly what the ticket
                // says not to do -- so it goes in the administrative log, where a webmaster
                // can find out why a URL changed
                // AdministrativeLogService::log()'s third parameter names the page the entry
                // is APPENDED TO, not a page it refers to -- passing $newTag wrote this
                // sentence into the body of the very page the migration had just rescued.
                // Omitted, so it lands on the daily log page where a webmaster looks.
                $log->log(
                    'migration',
                    "reserved tag '{$oldTag}' was unreachable; its Content was renamed to '{$newTag}' (ticket 20)"
                );
            }
        }
    }
}
