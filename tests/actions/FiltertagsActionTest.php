<?php

namespace YesWiki\Test\Actions;

use YesWiki\Kernel\Service\DbService;
use YesWiki\Kernel\Service\TripleStore;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** Regression test for the SQL injection in the {{filtertags}} action. */
class FiltertagsActionTest extends YesWikiTestCase
{
    private const MALICIOUS_TAG = 'x\\';
    private const DECOY_TAG = 'evil';
    private const PAGE_TAG = 'FiltertagsSqliRegressionPage';
    private const TAG_PROPERTY = 'http://outils-reseaux.org/_vocabulary/tag';

    public function testTrailingBackslashInFilterValueIsEscapedBeforeReachingSql(): void
    {
        $wiki = $this->getWiki();
        $dbService = $wiki->services->get(DbService::class);
        $tripleStore = $wiki->services->get(TripleStore::class);

        $GLOBALS['yeswikiServices'] = $wiki->services;

        $tripleStore->create(self::PAGE_TAG, self::TAG_PROPERTY, self::MALICIOUS_TAG, '', '');

        $tripleStore->create(self::PAGE_TAG, self::TAG_PROPERTY, self::DECOY_TAG, '', '');

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

            $req = 'SELECT DISTINCT resource FROM ' . $dbService->prefixTable('triples')
                . ' WHERE property = ? AND value IN ('
                . \YesWiki\Kernel\Database\SqlParameters::placeholders(count($taglist)) . ')';
            $rows = $dbService->loadAll($req, [self::TAG_PROPERTY, ...$taglist]);

            $this->assertCount(
                1,
                $rows,
                "the query should match exactly one distinct page (both tags belong to the same page): $req"
            );
            $this->assertSame(self::PAGE_TAG, $rows[0]['resource']);
        } finally {
            $tripleStore->delete(self::PAGE_TAG, self::TAG_PROPERTY, null, '', '');
            unset($GLOBALS['wiki']);
        }
    }
}
