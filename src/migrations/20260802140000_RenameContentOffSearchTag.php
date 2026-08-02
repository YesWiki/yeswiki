<?php

use YesWiki\Admin\Service\AdministrativeLogService;
use YesWiki\Content\Service\PageManager;
use YesWiki\Core\YesWikiMigration;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Search\Service\SearchIndexer;

/**
 * Ticket 26: move any Content sitting on `search`, which the `/search` route now owns.
 *
 * This is `20260801000000_RenameContentOffReservedTags` again, for one newly reserved name.
 * It needs its own migration rather than a re-run of that one because a migration runs once,
 * and `search` was not reserved when it did.
 *
 * The rule it encodes is ticket 20's, unchanged: **the route wins.** A page tagged `search`
 * becomes unreachable the moment the route exists, because dispatch checks routed names
 * before it looks for a page -- so renaming is the only thing that gives such a page a URL
 * back. Leaving it in place would preserve nothing but the invisibility.
 *
 * Case-insensitive, because a MySQL wiki's default collation means a page tagged `Search`
 * already answers to a lookup for `search`.
 *
 * Idempotent: once nothing sits on the tag, it does nothing.
 */
class RenameContentOffSearchTag extends YesWikiMigration
{
    private const RESERVED = 'search';

    public function run()
    {
        $db = $this->getService(DbService::class);
        $pageManager = $this->getService(PageManager::class);
        $log = $this->getService(AdministrativeLogService::class);
        $pages = $db->prefixTable('pages');
        $triples = $db->prefixTable('triples');

        $rows = $db->loadAll(
            "SELECT DISTINCT tag FROM {$pages} WHERE LOWER(tag) = '" . self::RESERVED . "'"
        );

        foreach ($rows as $row) {
            $oldTag = (string)$row['tag'];
            $newTag = $pageManager->suggestFreeTag($oldTag);
            if ($newTag === $oldTag) {
                // suggestFreeTag() treats reserved as unavailable, so this cannot happen
                // unless the reserved list and this migration have disagreed
                continue;
            }

            $db->query("UPDATE {$pages} SET tag = '{$db->escape($newTag)}' WHERE tag = '{$db->escape($oldTag)}'");
            $db->query("UPDATE {$pages} SET comment_on = '{$db->escape($newTag)}' WHERE comment_on = '{$db->escape($oldTag)}'");
            $db->query("UPDATE {$triples} SET resource = '{$db->escape($newTag)}' WHERE resource = '{$db->escape($oldTag)}'");

            // the tag moved in `pages` directly rather than through renameTag(), so nothing
            // told the search index; it would keep answering under a tag that now 404s
            $this->getService(SearchIndexer::class)->rename($oldTag, $newTag);

            // no third argument -- see RewriteRetiredSearchActions: it names the page the
            // log is appended to, so passing the tag writes this into the renamed page
            $log->log(
                'migration',
                "reserved tag '{$oldTag}' is now the /search route; its Content was renamed to '{$newTag}' (ticket 26)"
            );
        }
    }
}
