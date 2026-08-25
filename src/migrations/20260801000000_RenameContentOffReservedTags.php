<?php

use YesWiki\Content\Service\PageManager;
use YesWiki\Core\YesWikiMigration;
use YesWiki\Kernel\Routing\ReservedTags;

/** Ticket 20 (ADR-0001 as amended): move any Content sitting on a tag the router owns. */
class RenameContentOffReservedTags extends YesWikiMigration
{
    public function run()
    {
        $pageManager = $this->getService(PageManager::class);
        $pages = $this->dbService->prefixTable('pages');
        $triples = $this->dbService->prefixTable('triples');

        foreach (ReservedTags::NAMES as $reserved) {
            $rows = $this->dbService->loadAll(
                "SELECT DISTINCT tag FROM {$pages} WHERE LOWER(tag) = '{$this->dbService->escape($reserved)}'"
            );

            foreach ($rows as $row) {
                $oldTag = (string)$row['tag'];
                $newTag = $pageManager->suggestFreeTag($oldTag);
                if ($newTag === $oldTag) {
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

                $this->say(
                    "reserved tag '{$oldTag}' was unreachable; its Content was renamed to '{$newTag}' (ticket 20)"
                );
            }
        }
    }
}
