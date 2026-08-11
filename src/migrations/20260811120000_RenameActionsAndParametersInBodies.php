<?php

use YesWiki\Admin\Service\AdministrativeLogService;
use YesWiki\Content\Entity\PageBody;
use YesWiki\Content\Service\ActionCallRewriter;
use YesWiki\Core\YesWikiMigration;
use YesWiki\Kernel\Database\SqlFragment;
use YesWiki\Kernel\Database\SqlParameters;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Search\Service\SearchIndexer;

/**
 * Ticket 33: the rename migration tickets 22 and 23 deliberately deferred.
 *
 * Those tickets renamed 20 French action names and 45 parameter names and stopped there --
 * "documented breaking changes now, migration magic later" (2026-07-31) -- recording both maps
 * as machine-readable JSON in docs/ for exactly this. Without this migration every existing
 * wiki's page bodies still say `{{bazarliste}}`, which resolves to nothing: there is no alias
 * layer, so each one renders an unknown-action box where content used to be.
 *
 * The rewriting rules, what is out of scope and why, and the ordering hazard in the two maps all
 * live in ActionCallRewriter. This migration is the sweep.
 *
 * ## Every revision, not just the latest
 *
 * A page's history is shown by the revisions handler and diffed revision against revision.
 * Rewriting only `latest = 'Y'` would make every historical diff show the rename as though the
 * last author had made it, and reverting to an older revision would resurrect a dead action
 * call. So the sweep is over rows, not pages.
 *
 * ## What it does NOT touch
 *
 * Files on disk. A squelette or a custom template calling a French action name is the
 * webmaster's to fix -- silently editing someone's theme from a database migration is not a
 * thing this should do -- so `themes/`, `custom/` and `extensions/` are *reported* instead.
 * That is the same division ticket 26's migration drew, and `themes/` is the directory these
 * sweeps keep forgetting (ticket 23).
 *
 * It also reports a leftover `tools/` directory, which is not about renames at all but is the
 * other silent failure an upgrade hits: extensions live in `extensions/` and
 * `custom/extensions/` now, `tools/` is not scanned, and anything still in it simply stops
 * existing with no error anywhere.
 *
 * Idempotent: the new names are not keys in either map, so a second run finds nothing. Asserted
 * by test rather than assumed -- a rewrite that widened its own match would show up here first.
 */
class RenameActionsAndParametersInBodies extends YesWikiMigration
{
    public function run()
    {
        $db = $this->getService(DbService::class);
        $log = $this->getService(AdministrativeLogService::class);
        $rewriter = $this->getService(ActionCallRewriter::class);
        $pages = $db->prefixTable('pages');

        $candidates = $this->candidatePredicate($rewriter);
        $rows = $db->loadAll(
            "SELECT id, tag, body FROM {$pages} WHERE " . $candidates->sql,
            $candidates->params
        );

        $rewritten = [];
        foreach ($rows as $row) {
            $body = PageBody::decode((string)$row['body']);
            $changed = $rewriter->rewriteBody($body);
            if ($changed === null) {
                continue;
            }

            $db->query(
                "UPDATE {$pages} SET body = ? WHERE id = ?",
                [PageBody::encode($changed), (string)$row['id']]
            );
            $rewritten[(string)$row['tag']] = true;
        }

        // the rewritten text is what the index holds; queued rather than indexed inline, like
        // every other write path (ticket 18)
        $this->getService(SearchIndexer::class)->enqueue(array_keys($rewritten));

        if ($rewritten !== []) {
            // One entry naming all of them rather than one per page: unlike ticket 26's lossy
            // rewrite there is nothing here a webmaster has to go and repair, so this is a record
            // of what changed, not a list of things to do. The action names are written without
            // braces -- the log is itself a wiki page, and `{{entrylist}}` in it would not be a
            // record of a rewrite, it would BE an entry list.
            $log->log(
                'migration',
                'Ticket 33: renamed French action and parameter names in the stored bodies of '
                . count($rewritten) . ' page(s), across all revisions: '
                . implode(', ', array_keys($rewritten))
                . '. Parameter values and template filenames were left unchanged.'
            );
        }

        $this->reportFilesStillUsingFrenchNames($log, $rewriter);
        $this->reportLeftoverToolsDirectory($log);
    }

    /**
     * Narrow the sweep to rows that could possibly contain something to rewrite.
     *
     * A superset, always: every row it returns is still parsed properly, so a needle that is too
     * broad costs time and nothing else. The point is only to avoid decoding and re-encoding
     * every body of every revision on a wiki with a long history -- ADR-0016 puts the target at
     * 100k+ Contents, and each of those has revisions.
     *
     * Bound, not interpolated. The needles are ours rather than user input, so this is not about
     * injection -- it is that a migration is the last place in the codebase that should carry a
     * hand-rolled quote, and `SqlParameters::likeContains()` already knows to escape the LIKE
     * metacharacters that SQLite has no default escape character for.
     */
    private function candidatePredicate(ActionCallRewriter $rewriter): SqlFragment
    {
        $clauses = array_map(
            static fn (string $needle): SqlFragment => SqlFragment::of(
                'body LIKE ?' . SqlParameters::LIKE_CLAUSE_SUFFIX,
                [SqlParameters::likeContains($needle)]
            ),
            $rewriter->candidateNeedles()
        );

        return SqlFragment::all(' OR ', ...$clauses)->wrappedIn('(', ')');
    }

    /**
     * Templates and squelettes are files, not rows. Report, do not edit.
     *
     * Matches both the ways a file can name an action: wiki syntax (`{{bazarliste`) and Twig's
     * own helper (`action('bazarliste'`), which is how the shipped squelettes call them -- the
     * nine calls in themes/yeswiki/squelettes/1col.twig are the reason ticket 23 listed this
     * directory in its scope.
     */
    private function reportFilesStillUsingFrenchNames(AdministrativeLogService $log, ActionCallRewriter $rewriter): void
    {
        $names = array_keys($rewriter->actionRenames());
        if ($names === []) {
            return;
        }
        $alternation = implode('|', array_map('preg_quote', $names));
        $pattern = '/(\{\{\s*(' . $alternation . ')\b)|(\baction\s*\(\s*[\'"](' . $alternation . ')\b)/i';

        $found = [];
        foreach (['themes', 'custom', 'extensions'] as $dir) {
            if (!is_dir($dir)) {
                continue;
            }
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
            foreach ($iterator as $file) {
                if (!$file->isFile() || !in_array($file->getExtension(), ['twig', 'html', 'php'], true)) {
                    continue;
                }
                if (preg_match($pattern, (string)@file_get_contents($file->getPathname())) === 1) {
                    $found[] = $file->getPathname();
                }
            }
        }

        if ($found !== []) {
            $log->log(
                'migration',
                'Ticket 33: these files still name a renamed French action and were NOT rewritten '
                . '-- files on disk are yours to edit, see docs/action-name-renames.json for the '
                . 'mapping: ' . implode(', ', $found),
                ''
            );
        }
    }

    /**
     * The other silent upgrade failure: extensions left in `tools/`.
     *
     * `tools/` is not scanned any more (YesWikiRuntime loads `extensions/` and
     * `custom/extensions/`), so an extension still sitting there does not error -- its features
     * simply stop existing, which is the worst way for this to be discovered. Reported rather
     * than moved: relocating somebody's third-party code from a migration, over whatever is
     * already at the destination, is a worse default than telling them.
     */
    private function reportLeftoverToolsDirectory(AdministrativeLogService $log): void
    {
        if (!is_dir('tools')) {
            return;
        }
        $entries = array_values(array_filter(
            (array)scandir('tools'),
            fn ($entry) => !in_array($entry, ['.', '..'], true) && is_dir('tools/' . $entry)
        ));
        if ($entries === []) {
            return;
        }

        $log->log(
            'migration',
            'Extensions are loaded from extensions/ and custom/extensions/ now; tools/ is not '
            . 'scanned, so these are being silently ignored and their features no longer exist. '
            . 'Move them to custom/extensions/ and check each is Ectoplasme-compatible: '
            . implode(', ', $entries),
            ''
        );
    }
}
