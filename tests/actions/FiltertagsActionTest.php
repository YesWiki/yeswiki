<?php

namespace YesWiki\Test\Actions;

use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * Regression test for the SQL injection in the {{filtertags}} action.
 *
 * get_filtertags_parameters_recursive() (tools/tags/libs/tags.functions.php) used
 * to quote-wrap each comma-separated filterN token without escaping it, then
 * filtertags.php concatenated the result raw into a `tags.value IN (...)` clause.
 * Under MySQL/MariaDB's default sql_mode (backslash-escapes enabled, no
 * NO_BACKSLASH_ESCAPES), a token ending in a single backslash consumes the quote
 * that immediately follows it (the auto-inserted `","` separator) as an escaped,
 * inert character instead of it closing the string, shifting where the string
 * literal actually ends and letting attacker-controlled text after it run as bare
 * SQL. Verified end-to-end against a live MariaDB connection: with the
 * pre-fix code, filter1 = 'x\,evil' (a trailing backslash before a comma)
 * produces the taglist string "x\","evil" (bytes: x, \, ", ,, ", e, v, i, l, ") ;
 * scanning it as MySQL would (backslash escapes the next character while inside a
 * string) shows the closing quote lands one position later than intended and the
 * comma that was meant to separate list values ends up swallowed as string
 * content.
 *
 * NB: the specific 5-column UNION SELECT PoC sometimes described for this class of
 * bug does NOT work here, because every comma in the attacker's payload is also
 * naively re-quoted by this same code (explode(',') + wrap each fragment in
 * quotes), which traps the commas a UNION column list needs inside inert string
 * literals and makes MariaDB reject the query with a syntax error (confirmed
 * against a live connection). The injection is still real, just via boolean/blind
 * conditions rather than a direct UNION read ; this test asserts against the root
 * cause (unescaped backslash reaching the SQL string) rather than against any one
 * downstream exploitation technique.
 */
class FiltertagsActionTest extends YesWikiTestCase
{
    public function testTrailingBackslashInFilterValueIsEscapedBeforeReachingSql()
    {
        $wiki = $this->getWiki();
        // get_filtertags_parameters_recursive() (tools/tags/libs/tags.functions.php)
        // reads $GLOBALS['wiki'], which is normally populated by the production HTTP
        // bootstrap rather than by the test harness.
        $GLOBALS['wiki'] = $wiki;

        try {
            include_once 'tools/tags/libs/tags.functions.php';

            $wiki->parameter = ['filter1' => 'x\\,evil'];
            $params = get_filtertags_parameters_recursive();
            $taglist = $params['tags'];

            // pre-fix: "x\","evil" -> a lone backslash sits directly in front of the
            // quote that's supposed to close the string, letting it escape (swallow)
            // that quote instead of being closed by it.
            // post-fix: "x\\","evil" -> the backslash is escaped (doubled), so it
            // neutralizes itself and the following quote closes the string normally.
            $this->assertStringNotContainsString(
                "x\\\",",
                $taglist,
                'a lone backslash reaches the SQL string unescaped: quote-parity flip / SQL injection'
            );
            $this->assertStringContainsString(
                "x\\\\\",",
                $taglist,
                'the backslash should be escaped (doubled) before being wrapped into the SQL string'
            );
        } finally {
            unset($GLOBALS['wiki']);
        }
    }
}
