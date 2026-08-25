<?php

use YesWiki\Content\Entity\PageBody;
use YesWiki\Content\Service\ActionCallRewriter;
use YesWiki\Core\YesWikiMigration;
use YesWiki\Kernel\Database\SqlFragment;
use YesWiki\Kernel\Database\SqlParameters;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Search\Service\SearchIndexer;

/** Ticket 33: the rename migration tickets 22 and 23 deliberately deferred. */
class RenameActionsAndParametersInBodies extends YesWikiMigration
{
    public function run()
    {
        $db = $this->getService(DbService::class);
        $rewriter = $this->getService(ActionCallRewriter::class);
        $pages = $db->prefixTable('pages');

        $candidates = $this->candidatePredicate($rewriter, $db->jsonAsText('body'));
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

        $this->getService(SearchIndexer::class)->enqueue(array_keys($rewritten));

        if ($rewritten !== []) {
            $this->say(
                'Ticket 33: renamed French action and parameter names in the stored bodies of '
                . count($rewritten) . ' page(s), across all revisions: '
                . implode(', ', array_keys($rewritten))
                . '. Parameter values and template filenames were left unchanged.'
            );
        }

        // Ticket 53: both of these are claims about the present -- which files still name a
        // renamed action, and which extensions are still sitting in `tools/`. They are checks the
        // modules that own those subjects declare, run here and re-runnable from /admin/health.
        $this->reportCheck('files-name-renamed-actions');
        $this->reportCheck('leftover-tools-directory');
    }

    /** Narrow the sweep to rows that could possibly contain something to rewrite. */
    private function candidatePredicate(ActionCallRewriter $rewriter, string $bodyAsText): SqlFragment
    {
        $clauses = array_map(
            static fn (string $needle): SqlFragment => SqlFragment::of(
                $bodyAsText . ' LIKE ?' . SqlParameters::LIKE_CLAUSE_SUFFIX,
                [SqlParameters::likeContains($needle)]
            ),
            $rewriter->candidateNeedles()
        );

        return SqlFragment::all(' OR ', ...$clauses)->wrappedIn('(', ')');
    }
}
