<?php

use YesWiki\Admin\Service\AdministrativeLogService;
use YesWiki\Content\Entity\PageBody;
use YesWiki\Content\Service\PageManager;
use YesWiki\Core\YesWikiMigration;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Search\Service\SearchIndexer;

/**
 * Ticket 30: `LookWiki` is retired, and its links go to `admin/preset`.
 *
 * It was a seeded page holding `{{themeselector}}` and a gallery of components -- "the place
 * you go to change the colours", which you had to be told, because nothing about the name
 * says so. That is now the Personnalisation screen, which does the same job at an address
 * that names it and behind an admin gate rather than a page ACL.
 *
 * ## Deleted only if nobody ever touched it
 *
 * A page with **one revision** is a page that has sat exactly as the installer wrote it since
 * the day the wiki was created -- nobody's work is in it, and leaving it behind is leaving a
 * page whose theme selector now duplicates a screen. That one is deleted.
 *
 * A page with a history has somebody's content in it, and deleting that is not a migration's
 * call to make -- the same line `PageCssBecomesAFile` drew. It is kept, and named in the log
 * along with the reason, so a webmaster can decide.
 *
 * ## Links follow the decision
 *
 * Stored links to `LookWiki` are rewritten to `admin/preset` **only when the page is deleted**
 * -- the seeded `GererThemes` body carries one, and so does the seeded `PageMenu`. A wiki that
 * kept its `LookWiki` keeps working links to it, which is the whole point of keeping it.
 *
 * Idempotent: once the page is gone and no body mentions it, there is nothing to do.
 */
class LookWikiIsRetired extends YesWikiMigration
{
    private const TAG = 'LookWiki';

    /** What a link to it becomes. Two spellings, because both are in the seed. */
    private const REWRITES = [
        // {{button ... link="LookWiki" ...}} and any other action taking a link=
        '/(\blink=")LookWiki(")/' => '${1}admin/preset${2}',
        // a markdown link, with or without a tooltip
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
     * Every revision and not just the latest: restoring an older one must not bring back a
     * link to a page that no longer exists. Bodies are JSON, so this goes through PageBody
     * rather than a string replace on the column (ticket 25's defect 3).
     *
     * @return list<string> the tags that changed
     */
    private function rewriteLinks(DbService $db, string $pages): array
    {
        $rows = $db->loadAll("SELECT id, tag, body FROM {$pages} WHERE body LIKE '%LookWiki%'");

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
                "UPDATE {$pages} SET body = '{$db->escape(PageBody::encode($body))}'"
                . " WHERE id = '{$db->escape((string)$row['id'])}'"
            );
            $changedTags[(string)$row['tag']] = true;
        }

        // the rewritten prose is what the index holds, so those rows are re-indexed -- queued
        // rather than indexed inline, like every other write path (ticket 18)
        $this->getService(SearchIndexer::class)->enqueue(array_keys($changedTags));

        return array_keys($changedTags);
    }
}
