<?php

namespace YesWiki\Test\Actions;

use YesWiki\Kernel\Service\DbService;
use YesWiki\Search\Service\SearchIndexSchema;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** Regression test for the SQL injection in the {{filtertags}} action. */
class FiltertagsActionTest extends YesWikiTestCase
{
    private const MALICIOUS_TAG = 'x\\';
    private const DECOY_TAG = 'evil';
    private const PAGE_TAG = 'FiltertagsSqliRegressionPage';

    public function testTrailingBackslashInFilterValueIsEscapedBeforeReachingSql(): void
    {
        $wiki = $this->getWiki();
        $dbService = $wiki->services->get(DbService::class);
        $keywords = $wiki->services->get(SearchIndexSchema::class)->keywordsTable();

        $GLOBALS['yeswikiServices'] = $wiki->services;

        // The index the action reads since ticket 62, written straight: what this asserts is that a
        // hostile token reaches SQL as a bound value, not that the wiki can save such a keyword.
        foreach ([self::MALICIOUS_TAG, self::DECOY_TAG] as $keyword) {
            $dbService->query("INSERT INTO {$keywords} (tag, keyword) VALUES (?, ?)", [self::PAGE_TAG, $keyword]);
        }

        try {
            $filterArgs = ['filter1' => self::MALICIOUS_TAG . ',' . self::DECOY_TAG];
            $wiki->services->get(\YesWiki\Kernel\Service\PerformableArguments::class)->bind($filterArgs);

            $action = new \YesWiki\Content\Action\FiltertagsAction();
            $action->setServices($wiki->services);
            $params = (new \ReflectionMethod($action, 'filterParameters'))->invoke($action);
            $taglist = $params['tags'];

            $this->assertSame(
                [self::MALICIOUS_TAG, self::DECOY_TAG],
                $taglist,
                'the tokens must come back as values, not as a pre-quoted SQL string'
            );

            $req = "SELECT DISTINCT tag FROM {$keywords} WHERE keyword IN ("
                . \YesWiki\Kernel\Database\SqlParameters::placeholders(count($taglist)) . ')';
            $rows = $dbService->loadAll($req, $taglist);

            $this->assertCount(
                1,
                $rows,
                "the query should match exactly one distinct page (both tags belong to the same page): $req"
            );
            $this->assertSame(self::PAGE_TAG, $rows[0]['tag']);
        } finally {
            $dbService->query("DELETE FROM {$keywords} WHERE tag = ?", [self::PAGE_TAG]);
            unset($GLOBALS['wiki']);
        }
    }
}
