<?php

use YesWiki\Admin\Service\AdministrativeLogService;
use YesWiki\Content\Entity\PageBody;
use YesWiki\Content\Service\PageManager;
use YesWiki\Core\YesWikiMigration;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Search\Service\SearchIndexer;

/** Ticket 30: `LookWiki` is retired, and its links go to `admin/preset`. */
class LookWikiIsRetired extends YesWikiMigration
{
    private const TAG = 'LookWiki';

    /** What a link to it becomes. */
    private const REWRITES = [
        '/(\blink=")LookWiki(")/' => '${1}admin/preset${2}',

        '/\]\(\s*LookWiki(\s+"[^"]*")?\s*\)/' => '](admin/preset${1})',
    ];

    public function run()
    {
        $db = $this->getService(DbService::class);
        $log = $this->getService(AdministrativeLogService::class);
        $pages = $db->prefixTable('pages');

        $revisions = $db->loadAll(
            "SELECT id FROM {$pages} WHERE tag = '" . $db->escape(self::TAG) . "'"
        );

        if ($revisions === []) {
            return;
        }

        if (count($revisions) > 1) {
            $log->log(
                'migration',
                "the page 'LookWiki' has been edited since this wiki was installed, so it was KEPT"
                . ' (ticket 30) -- what it does is the Personnalisation screen now (?admin/preset),'
                . ' but the content on that page is yours, and links to it still work.'
            );

            return;
        }

        $this->getService(PageManager::class)->deleteOrphaned(self::TAG);
        $rewritten = $this->rewriteLinks($db, $pages);

        $log->log(
            'migration',
            "the seeded page 'LookWiki' was removed (ticket 30): it held the theme selector and a"
            . ' component gallery, which are the Personnalisation screen now (?admin/preset). It was'
            . ' still exactly as the installer wrote it, so nothing of yours was in it.'
            . ($rewritten === []
                ? ''
                : ' Links to it were repointed at that screen on: ' . implode(', ', $rewritten) . '.')
        );
    }

    /**
     * Repoint every stored link, in every revision.
     *
     * @return list<string> the tags that changed
     */
    private function rewriteLinks(DbService $db, string $pages): array
    {
        $bodyAsText = $db->jsonAsText('body');
        $rows = $db->loadAll("SELECT id, tag, body FROM {$pages} WHERE {$bodyAsText} LIKE '%LookWiki%'");

        $changedTags = [];
        foreach ($rows as $row) {
            $body = PageBody::decode((string)$row['body']);
            $changed = false;
            array_walk_recursive($body, function (&$value) use (&$changed): void {
                if (!is_string($value)) {
                    return;
                }
                $before = $value;
                foreach (self::REWRITES as $pattern => $replacement) {
                    $value = (string)preg_replace($pattern, $replacement, $value);
                }
                $changed = $changed || $value !== $before;
            });

            if (!$changed) {
                continue;
            }

            $db->query(
                "UPDATE {$pages} SET body = ? WHERE id = ?",
                [PageBody::encode($body), (string)$row['id']]
            );
            $changedTags[(string)$row['tag']] = true;
        }

        $this->getService(SearchIndexer::class)->enqueue(array_keys($changedTags));

        return array_keys($changedTags);
    }
}
