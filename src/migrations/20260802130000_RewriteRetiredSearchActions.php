<?php

use YesWiki\Admin\Service\AdministrativeLogService;
use YesWiki\Content\Entity\PageBody;
use YesWiki\Core\YesWikiMigration;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Search\Service\SearchIndexer;

/**
 * Ticket 26: rewrite `{{newtextsearch}}` and `{{searchform}}` out of stored content.
 *
 * Both actions are deleted in this release. Changing the seed only fixes *new* installs, so
 * every existing wiki's `PageRapideHaut` would keep calling an action that no longer exists
 * -- which renders as an "unknown action" box where the search button used to be.
 *
 * ## This rewrite is lossy, and says so
 *
 * `{{searchform}}` becomes `{{button icon="loupe" link="search"}}`. A button has no
 * `template`, `btnclass`, `iconclass` or custom `url`, so a webmaster who set any of those
 * loses them. That is a deliberate trade: the alternative is keeping a compatibility shim
 * whose parameters silently do nothing, which is worse because it looks like it works. Every
 * rewritten page is named in the administrative log so the loss is discoverable rather than
 * merely announced in a changelog.
 *
 * `{{newtextsearch}}` becomes `{{search}}`, which is a closer match: the replacement action
 * takes a `phrase` and a `limit` too. Its `label`, `size`, `button` and `separator` do not
 * survive.
 *
 * ## Where it sweeps
 *
 * `pages` is the obvious place and not the only one. A form's template can carry action
 * calls inside a `labelhtml` field's `form_text`, and those are stored in the form's body
 * like anything else -- so the same body rewrite covers them, because a form IS a page row
 * (ticket 05). What this migration deliberately does **not** touch is `themes/`: files on
 * disk are not this migration's to edit, and a squelette calling a retired action is
 * reported instead. That directory is the one these sweeps keep forgetting (ticket 23).
 *
 * Bodies are JSON, so every rewrite goes through PageBody rather than a string replace on
 * the column -- ticket 25's defect 3 is what a string replace on stored JSON looks like when
 * it goes wrong.
 *
 * Idempotent: once no body mentions either action, it does nothing.
 */
class RewriteRetiredSearchActions extends YesWikiMigration
{
    /** What each retired call becomes. Order matters: longest/most specific first. */
    private const REWRITES = [
        '/\{\{\s*searchform\b[^}]*\}\}/i' => '{{button icon="loupe" link="search"}}',
        '/\{\{\s*newtextsearch\b[^}]*\}\}/i' => '{{search}}',
    ];

    public function run()
    {
        $db = $this->getService(DbService::class);
        $log = $this->getService(AdministrativeLogService::class);
        $pages = $db->prefixTable('pages');

        // every revision, not just the latest: reverting a page to an older revision must not
        // resurrect a call to an action that no longer exists
        $rows = $db->loadAll(
            "SELECT id, tag, body FROM {$pages}"
            . " WHERE body LIKE '%searchform%' OR body LIKE '%newtextsearch%'"
        );

        $rewritten = [];
        foreach ($rows as $row) {
            $body = PageBody::decode((string)$row['body']);
            $changed = $this->rewriteBody($body);
            if ($changed === null) {
                continue;
            }

            $db->query(
                "UPDATE {$pages} SET body = ? WHERE id = ?",
                [PageBody::encode($changed), (string)$row['id']]
            );
            $rewritten[(string)$row['tag']] = true;
        }

        // the rewritten prose is what the index holds, so it has to be rebuilt for these
        // rows -- queued rather than indexed inline, like every other write path (ticket 18)
        $this->getService(SearchIndexer::class)->enqueue(array_keys($rewritten));

        foreach (array_keys($rewritten) as $tag) {
            // No third argument: it names the page the log is APPENDED TO, defaulting to
            // the daily log page. Passing the affected tag would write this sentence into the
            // very body just rewritten. And the message deliberately spells the actions
            // without their braces -- the log page is a wiki page, so `{{search}}` in it would
            // not be a record of a rewrite, it would BE a search box.
            $log->log(
                'migration',
                "searchform/newtextsearch are retired (ticket 26); page '{$tag}' was rewritten to "
                . 'a button linking to /search, or to the search action. Any template, class or '
                . 'url parameter on the old call could not be carried over.'
            );
        }

        $this->reportThemesStillCallingRetiredActions($log);
    }

    /**
     * @param array<string, mixed> $body
     *
     * @return array<string, mixed>|null null when nothing in it changed
     */
    private function rewriteBody(array $body): ?array
    {
        $changed = false;
        array_walk_recursive($body, function (&$value) use (&$changed): void {
            if (!is_string($value)) {
                return;
            }
            $before = $value;
            foreach (self::REWRITES as $pattern => $replacement) {
                $value = (string)preg_replace($pattern, $replacement, $value);
            }
            if ($value !== $before) {
                $changed = true;
            }
        });

        return $changed ? $body : null;
    }

    /**
     * Themes are files, not rows. A squelette or theme template calling a retired action is
     * the webmaster's to fix, so it is reported rather than edited -- silently rewriting
     * someone's theme files from a database migration is not a thing this should do.
     */
    private function reportThemesStillCallingRetiredActions(AdministrativeLogService $log): void
    {
        $found = [];
        foreach (['themes', 'custom/themes'] as $dir) {
            if (!is_dir($dir)) {
                continue;
            }
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
            foreach ($iterator as $file) {
                if (!$file->isFile() || !in_array($file->getExtension(), ['twig', 'html', 'php'], true)) {
                    continue;
                }
                $contents = (string)@file_get_contents($file->getPathname());
                if (preg_match('/\{\{\s*(searchform|newtextsearch)\b/i', $contents)) {
                    $found[] = $file->getPathname();
                }
            }
        }

        if ($found !== []) {
            $log->log(
                'migration',
                'These theme files still call a retired search action (ticket 26) and were NOT '
                . 'rewritten -- files on disk are yours to edit: ' . implode(', ', $found),
                ''
            );
        }
    }
}
