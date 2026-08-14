<?php

use YesWiki\Admin\Service\AdministrativeLogService;
use YesWiki\Content\Entity\PageBody;
use YesWiki\Content\Entity\PageType;
use YesWiki\Content\Service\FormManager;
use YesWiki\Content\Service\ListpagesRewriter;
use YesWiki\Core\YesWikiMigration;
use YesWiki\Kernel\Database\SqlParameters;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Search\Service\SearchIndexer;

/**
 * `{{listpages}}` is retired: a page is an entry, so a page list is an entry list.
 *
 * Since ADR-0011 the Pages form describes pages the way any form describes its entries, which
 * left the wiki with two actions, two palette cards and two Presentation Sources answering one
 * question. This rewrites the stored calls onto `{{entrylist}}` over the Pages form and the
 * action goes with it.
 *
 * ## Every revision, not just the latest
 *
 * The same reasoning as ticket 33's rename: a page's history is diffed revision against
 * revision, and rewriting only the current one would make every historical diff show this
 * migration's edit as though the last author had made it -- and reverting to an older revision
 * would resurrect a call nothing answers.
 *
 * ## What it cannot carry over
 *
 * Three parameters have no equivalent (`ListpagesRewriter` documents the whole mapping):
 * `exclude`, `user`, and `sort="user"`. They are dropped rather than left in place -- a call
 * nothing answers renders an error where a list used to be -- and every page that had one is
 * named in the log, because a wider list than the author asked for is a thing they should be
 * told about rather than discover.
 */
class RetireListpages extends YesWikiMigration
{
    public function run()
    {
        $db = $this->getService(DbService::class);
        $log = $this->getService(AdministrativeLogService::class);
        $pages = $db->prefixTable('pages');

        $form = $this->getService(FormManager::class)->getByContentType(PageType::PAGE);
        if ($form === null || ($form['id'] ?? null) === null) {
            // nothing to rewrite the calls ONTO. Silence would leave the wiki with calls to an
            // action that no longer exists and no clue why.
            $log->log(
                'migration',
                'listpages could not be retired: this wiki has no Pages form to list the pages of. '
                . 'Any listpages call in a page body now renders an error; run this migration again '
                . 'once the built-in forms are installed.'
            );

            return;
        }
        $pagesFormId = (string)$form['id'];

        $rows = $db->loadAll(
            "SELECT id, tag, body FROM {$pages} WHERE body LIKE ?" . SqlParameters::LIKE_CLAUSE_SUFFIX,
            [SqlParameters::likeContains('listpages')]
        );

        $rewriter = new ListpagesRewriter();
        $rewritten = [];
        $lost = [];
        foreach ($rows as $row) {
            $rewriter->forgetDropped();
            $body = PageBody::decode((string)$row['body']);
            $changed = $rewriter->rewriteBody($body, $pagesFormId);
            if ($changed === null) {
                continue;
            }

            $db->query(
                "UPDATE {$pages} SET body = ? WHERE id = ?",
                [PageBody::encode($changed), (string)$row['id']]
            );
            $rewritten[(string)$row['tag']] = true;
            $dropped = $rewriter->droppedParameters();
            if ($dropped !== []) {
                $lost[(string)$row['tag']] = $dropped;
            }
        }

        if ($rewritten === []) {
            return;
        }

        // the rewritten text is what the index holds; queued rather than indexed inline, like
        // every other write path (ticket 18)
        $this->getService(SearchIndexer::class)->enqueue(array_keys($rewritten));

        // The action names are written without braces on purpose: the log is itself a wiki
        // page, so a call in it would not be a record of a rewrite, it would BE a list.
        $log->log(
            'migration',
            'listpages is retired -- a page is an entry of the Pages form, so its list is an '
            . 'entry list. Rewritten onto entrylist id="' . $pagesFormId . '" in '
            . count($rewritten) . ' page(s), across all revisions: '
            . implode(', ', array_keys($rewritten)) . '.'
        );

        if ($lost !== []) {
            $described = [];
            foreach ($lost as $tag => $parameters) {
                $described[] = $tag . ' (' . implode(', ', $parameters) . ')';
            }
            $log->log(
                'migration',
                'These lists asked for something an entry list cannot be told, so they now show '
                . 'more pages than they did: ' . implode('; ', $described)
                . '. Excluding pages by name and filtering on who took part in them have no '
                . 'equivalent; narrow the list with a filter on a field instead, or edit these '
                . 'pages by hand.'
            );
        }
    }
}
