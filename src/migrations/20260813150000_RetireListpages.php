<?php

use YesWiki\Content\Entity\PageBody;
use YesWiki\Content\Entity\PageType;
use YesWiki\Content\Service\FormManager;
use YesWiki\Content\Service\ListpagesRewriter;
use YesWiki\Core\YesWikiMigration;
use YesWiki\Kernel\Database\SqlParameters;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Search\Service\SearchIndexer;

/** `{{listpages}}` is retired: a page is an entry, so a page list is an entry list. */
class RetireListpages extends YesWikiMigration
{
    public function run()
    {
        $db = $this->getService(DbService::class);
        $pages = $db->prefixTable('pages');

        $form = $this->getService(FormManager::class)->getByContentType(PageType::PAGE);
        if ($form === null || ($form['id'] ?? null) === null) {
            $this->say(
                'listpages could not be retired: this wiki has no Pages form to list the pages of. '
                . 'Any listpages call in a page body now renders an error; run this migration again '
                . 'once the built-in forms are installed.'
            );

            return;
        }
        $pagesFormId = (string)$form['id'];

        $rows = $db->loadAll(
            "SELECT id, tag, body FROM {$pages} WHERE {$db->jsonAsText('body')} LIKE ?" . SqlParameters::LIKE_CLAUSE_SUFFIX,
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

        $this->getService(SearchIndexer::class)->enqueue(array_keys($rewritten));

        $this->say(
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
            $this->say(
                'These lists asked for something an entry list cannot be told, so they now show '
                . 'more pages than they did: ' . implode('; ', $described)
                . '. Excluding pages by name and filtering on who took part in them have no '
                . 'equivalent; narrow the list with a filter on a field instead, or edit these '
                . 'pages by hand.'
            );
        }
    }
}
