<?php

use YesWiki\Content\Service\PageManager;
use YesWiki\Core\YesWikiMigration;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Search\Service\SearchIndexer;

/** Ticket 26: move any Content sitting on `search`, which the `/search` route now owns. */
class RenameContentOffSearchTag extends YesWikiMigration
{
    private const RESERVED = 'search';

    public function run()
    {
        $db = $this->getService(DbService::class);
        $pageManager = $this->getService(PageManager::class);
        $pages = $db->prefixTable('pages');
        $triples = $db->prefixTable('triples');

        $rows = $db->loadAll(
            "SELECT DISTINCT tag FROM {$pages} WHERE LOWER(tag) = '" . self::RESERVED . "'"
        );

        foreach ($rows as $row) {
            $oldTag = (string)$row['tag'];
            $newTag = $pageManager->suggestFreeTag($oldTag);
            if ($newTag === $oldTag) {
                continue;
            }

            $db->query("UPDATE {$pages} SET tag = '{$db->escape($newTag)}' WHERE tag = '{$db->escape($oldTag)}'");
            $db->query("UPDATE {$pages} SET parent = '{$db->escape($newTag)}' WHERE parent = '{$db->escape($oldTag)}'");
            $db->query("UPDATE {$triples} SET resource = '{$db->escape($newTag)}' WHERE resource = '{$db->escape($oldTag)}'");

            $this->getService(SearchIndexer::class)->rename($oldTag, $newTag);

            $this->say(
                "reserved tag '{$oldTag}' is now the /search route; its Content was renamed to '{$newTag}' (ticket 26)"
            );
        }
    }
}
