<?php

use YesWiki\Admin\Service\AdministrativeLogService;
use YesWiki\Content\Service\PageManager;
use YesWiki\Core\YesWikiMigration;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Search\Service\SearchIndexer;

/** Move any Content sitting on `dashboard` or `admin`, which the new routes now own. */
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
                    continue;
                }

                $db->query("UPDATE {$pages} SET tag = '{$db->escape($newTag)}' WHERE tag = '{$db->escape($oldTag)}'");
                $db->query("UPDATE {$pages} SET parent = '{$db->escape($newTag)}' WHERE parent = '{$db->escape($oldTag)}'");
                $db->query("UPDATE {$triples} SET resource = '{$db->escape($newTag)}' WHERE resource = '{$db->escape($oldTag)}'");

                $this->getService(SearchIndexer::class)->rename($oldTag, $newTag);

                $log->log(
                    'migration',
                    "reserved tag '{$oldTag}' is now the /{$reserved} route; its Content was renamed to '{$newTag}'"
                );
            }
        }
    }
}
