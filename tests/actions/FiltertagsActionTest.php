<?php

namespace YesWiki\Test\Actions;

use YesWiki\Content\Service\TripleStore;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * Regression test for the SQL injection in the {{filtertags}} action.
 *
 * get_filtertags_parameters_recursive() (src/tags.functions.php) used
 * to quote-wrap each comma-separated filterN token without escaping it, then
 * filtertags.php concatenated the result raw into a `tags.value IN (...)` clause.
 * Under MySQL/MariaDB's default sql_mode (backslash-escapes enabled, no
 * NO_BACKSLASH_ESCAPES), a token ending in a single backslash consumes the quote
 * that immediately follows it (the auto-inserted `","` separator) as an escaped,
 * inert character instead of it closing the string, shifting where the string
 * literal actually ends and letting attacker-controlled text after it run as bare
 * SQL.
 *
 * The fix (like AclService::updateRequestWithACL(), see
 * AclServiceUpdateRequestWithAclTest) wraps each escaped value in single-quoted SQL
 * literals rather than double-quoted ones, matching what DbService::escape()
 * (PDO::quote()) actually protects. Whether an unescaped trailing backslash is
 * itself dangerous is driver-dependent (MySQL's default sql_mode treats it as an
 * escape character inside a string literal; SQLite's dialect never does), so this
 * is verified black-box against the real database instead of by pattern-matching
 * one driver's specific escaped text: build the exact `tags.value IN (...)` query
 * the action builds, with real triples on both sides of the ambiguous boundary, and
 * confirm the malicious filter value only ever matches its own tag, never bleeds
 * into matching (or corrupting the query enough to match) an unrelated one.
 */
class FiltertagsActionTest extends YesWikiTestCase
{
    private const MALICIOUS_TAG = 'x\\';
    private const DECOY_TAG = 'evil';
    private const PAGE_TAG = 'FiltertagsSqliRegressionPage';
    private const TAG_PROPERTY = 'http://outils-reseaux.org/_vocabulary/tag';

    public function testTrailingBackslashInFilterValueIsEscapedBeforeReachingSql()
    {
        $wiki = $this->getWiki();
        $dbService = $wiki->services->get(DbService::class);
        $tripleStore = $wiki->services->get(TripleStore::class);
        // get_filtertags_parameters_recursive() (src/tags.functions.php)
        // reads $GLOBALS['wiki'], which is normally populated by the production HTTP
        // bootstrap rather than by the test harness.
        $GLOBALS['wiki'] = $wiki;

        // a real triple tagging PAGE_TAG with the malicious value itself (containing
        // the trailing backslash) -- what filtering for it should legitimately match
        $tripleStore->create(self::PAGE_TAG, self::TAG_PROPERTY, self::MALICIOUS_TAG, '', '');
        // a decoy triple with the literal string the attack tries to smuggle in as a
        // second, independent IN(...) value -- must NOT match unless the values
        // actually bled into each other
        $tripleStore->create(self::PAGE_TAG, self::TAG_PROPERTY, self::DECOY_TAG, '', '');

        try {
            include_once 'src/tags.functions.php';

            $filterArgs = ['filter1' => self::MALICIOUS_TAG . ',' . self::DECOY_TAG];
            $wiki->services->get(\YesWiki\Kernel\Service\PerformableArguments::class)->bind($filterArgs);
            $params = get_filtertags_parameters_recursive();
            $taglist = $params['tags'];

            $req = 'SELECT DISTINCT resource FROM ' . $dbService->prefixTable('triples')
                . " WHERE property = '" . self::TAG_PROPERTY . "' AND value IN (" . $taglist . ')';
            $rows = $dbService->loadAll($req);

            // both real triples are legitimately matched by their own exact value --
            // this is the correctness half: the fix must not just neutralize the
            // attack, it must still let normal multi-tag filtering work
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
