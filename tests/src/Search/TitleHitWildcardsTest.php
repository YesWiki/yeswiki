<?php

namespace YesWiki\Test\Search;

use YesWiki\Kernel\Database\SqlParameters;
use YesWiki\Search\Service\SearchIndexQuery;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** The one LIKE left in the search index query, and its wildcards. */
class TitleHitWildcardsTest extends YesWikiTestCase
{
    /**
     * The generated expression, with its bound values spliced back in for readability.
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
     * The clause is what makes the escaping mean anything: SQLite has no default escape character, so without it the defusing above is inert on that driver while working on MySQL -- one more "passes locally, wrong in production" shape.
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
