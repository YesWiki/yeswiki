<?php

namespace YesWiki\Test\Search;

use YesWiki\Kernel\Database\SqlParameters;
use YesWiki\Search\Service\SearchIndexQuery;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * The one LIKE left in the search index query, and its wildcards.
 *
 * `titleHitExpression()` decides whether every searched word appears in the title, which is
 * what makes a title match outrank a body match. It is the only LIKE in SearchIndexQuery --
 * matching itself goes through each driver's full-text engine (MySQL BOOLEAN MODE, SQLite FTS5,
 * PostgreSQL to_tsquery), and those take the sanitised terms as documented contracts.
 *
 * `%` and `_` are pattern syntax inside a LIKE, and nothing in a value-escaping or
 * value-binding path touches them -- correctly, since a query's own wildcards have to survive.
 * So they have to be defused deliberately, and the term reaching here has NOT been through
 * anything that removes `_`: FormOptionTranslator::normalize() keeps letters, digits and
 * underscore, so a search for `a_b` scored a title hit against a title reading `aXb`. (`%` is
 * stripped by that same normalisation, so of the two only the underscore was reachable -- the
 * defusing covers both because relying on a caller two classes away to keep stripping one of
 * them is not a property worth resting on.)
 *
 * Asserted on the generated expression rather than on the order of a live search, for the
 * reason FieldNamedLikeAPageColumnTest gives: the generated SQL is the part all three drivers
 * share, and whether a full-text engine tokenises `a_b` as one word or two is per-engine.
 * BoundValuesTest covers the other half -- that an escaped pattern plus this ESCAPE clause
 * really does match literally on the live driver.
 */
class TitleHitWildcardsTest extends YesWikiTestCase
{
    /**
     * The generated expression, with its bound values spliced back in for readability.
     *
     * titleHitExpression() returns a SqlFragment since ticket 31, so the patterns live in
     * `params` rather than in the SQL; interpolating them here keeps these assertions about
     * what the database will actually match, which is what they are for.
     *
     * @param list<list<string>> $groups
     */
    private function titleHitSql(array $groups): string
    {
        $method = new \ReflectionMethod(SearchIndexQuery::class, 'titleHitExpression');
        $fragment = $method->invoke(
            $this->getWiki()->services->get(SearchIndexQuery::class),
            $groups
        );

        return SqlParameters::interpolateForDisplay($fragment->sql, $fragment->params);
    }

    public function testAnUnderscoreInATermIsNotASingleCharacterWildcard(): void
    {
        $sql = $this->titleHitSql([['a_b']]);

        $this->assertStringContainsString('a!_b', $sql, 'the underscore must be defused');
        $this->assertStringNotContainsString(
            "'%a_b%'",
            $sql,
            'an undefused a_b would score a title hit against a title reading aXb'
        );
    }

    public function testAPercentInATermIsNotAWildcard(): void
    {
        $sql = $this->titleHitSql([['100%']]);

        $this->assertStringContainsString('100!%', $sql);
    }

    /**
     * The clause is what makes the escaping mean anything: SQLite has no default escape
     * character, so without it the defusing above is inert on that driver while working on
     * MySQL -- one more "passes locally, wrong in production" shape.
     */
    public function testEveryLikeNamesItsEscapeCharacter(): void
    {
        $sql = $this->titleHitSql([['first'], ['second', 'alternative']]);

        $likes = substr_count($sql, ' LIKE ');
        $escapes = substr_count($sql, SqlParameters::LIKE_CLAUSE_SUFFIX);

        $this->assertSame(3, $likes, 'one LIKE per alternative across both groups');
        $this->assertSame($likes, $escapes, 'every LIKE must carry an ESCAPE clause');
    }

    /** An ordinary term is unaffected -- no escape character appears where none is needed. */
    public function testAnOrdinaryTermIsNotRewritten(): void
    {
        $sql = $this->titleHitSql([['rhubarbe']]);

        $this->assertStringContainsString("'%rhubarbe%'", $sql);
    }
}
