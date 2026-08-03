<?php

use YesWiki\Admin\Service\AdministrativeLogService;
use YesWiki\Content\Service\PageManager;
use YesWiki\Core\YesWikiMigration;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Search\Service\SearchIndexer;

/**
 * Move any Content sitting on `dashboard` or `admin`, which the new routes now own.
 *
 * `20260802140000_RenameContentOffSearchTag` again, for two more newly reserved names --
 * same rule, and a new migration rather than a re-run because a migration runs once and
 * neither name was reserved when that one did.
 *
 * **The route wins** (ticket 20): dispatch checks routed names before it looks for a page,
 * so Content on one of these tags has no URL from the moment the route exists. Renaming is
 * what gives it one back; leaving it in place would preserve only the invisibility.
 *
 * Case-insensitive, because a MySQL wiki's default collation means a page tagged `Admin`
 * already answers to a lookup for `admin`. Idempotent: with nothing on either tag it does
 * nothing.
 */
class RenameContentOffDashboardTags extends YesWikiMigration
{
    private const RESERVED = ['dashboard', 'admin'];

    public function run()
    {
        $db = $this->getService(DbService::class);
        $pageManager = $this->getService(PageManager::class);
        $log = $this->getService(AdministrativeLogService::class);
        $pages = $db->prefixTable('pages');
        $triples = $db->prefixTable('triples');

        foreach (self::RESERVED as $reserved) {
            $rows = $db->loadAll("SELECT DISTINCT tag FROM {$pages} WHERE LOWER(tag) = '{$reserved}'");

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

                // the tag moved in `pages` directly rather than through renameTag(), so
                // nothing told the search index; it would answer under a tag that now 404s
                $this->getService(SearchIndexer::class)->rename($oldTag, $newTag);

                $log->log(
                    'migration',
                    "reserved tag '{$oldTag}' is now the /{$reserved} route; its Content was renamed to '{$newTag}'"
                );
            }
        }
    }
}
