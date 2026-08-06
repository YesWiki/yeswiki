<?php

namespace YesWiki\Test\Kernel\Database;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use YesWiki\Kernel\Database\SqlParameters;

/**
 * The value-to-placeholder mapping, with no connection involved.
 *
 * The DbService::escape() this replaces could not be tested this way at all -- it needs a live
 * PDO link to call quote() on, which is why nothing pinned its behaviour in five years. What a
 * value binds as is a pure function of the value, so it can be pinned here, per driver-free
 * case, the way the dialects next door are.
 */
class SqlParametersTest extends TestCase
{
    /**
     * @return array<string, array{mixed, int}>
     */
    public static function values(): array
    {
        return [
            // the case escape() got wrong: it cast null through (string) and the database
            // stored '' -- a value that is not NULL and does not match IS NULL
            'null' => [null, \PDO::PARAM_NULL],
            'int' => [42, \PDO::PARAM_INT],
            'zero' => [0, \PDO::PARAM_INT],
            'negative int' => [-1, \PDO::PARAM_INT],
            'true' => [true, \PDO::PARAM_BOOL],
            'false' => [false, \PDO::PARAM_BOOL],
            'string' => ['PageDeTest', \PDO::PARAM_STR],
            'empty string' => ['', \PDO::PARAM_STR],
            // no PARAM_FLOAT exists; a string is exact and locale-independent
            'float' => [1.5, \PDO::PARAM_STR],
            // a numeric *string* is a string: it came from a request, and silently
            // renumbering it is how '007' becomes 7
            'numeric string' => ['007', \PDO::PARAM_STR],
        ];
    }

    #[DataProvider('values')]
    public function testEachValueBindsAsTheTypeItIs(mixed $value, int $expected): void
    {
        $this->assertSame($expected, SqlParameters::typeOf($value));
    }

    public function testPlaceholdersMatchTheNumberOfValues(): void
    {
        $this->assertSame('?', SqlParameters::placeholders(1));
        $this->assertSame('?, ?, ?', SqlParameters::placeholders(3));
    }

    /**
     * `IN ()` is a syntax error on every driver, so an empty list must fail here rather than
     * reach the database. The caller's own empty-list guard is the fix; this makes forgetting
     * it loud instead of a query that dies at runtime with a driver-specific message.
     */
    public function testAnEmptyInListIsRefusedRatherThanBuilt(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        SqlParameters::placeholders(0);
    }

    public function testPositionalValuesAreShownInOrderForTheDebugFooter(): void
    {
        $this->assertSame(
            "SELECT * FROM pages WHERE tag = 'Home' AND latest = 'Y'",
            SqlParameters::interpolateForDisplay(
                'SELECT * FROM pages WHERE tag = ? AND latest = ?',
                ['Home', 'Y']
            )
        );
    }

    public function testNullAndBoolAreLegibleInTheDebugFooter(): void
    {
        // 'NULL' not "''": the footer exists to tell a developer what actually went to the
        // database, and those two behave completely differently in a WHERE
        $this->assertSame(
            'UPDATE pages SET parent = NULL, latest = TRUE WHERE id = 7',
            SqlParameters::interpolateForDisplay(
                'UPDATE pages SET parent = ?, latest = ? WHERE id = ?',
                [null, true, 7]
            )
        );
    }

    public function testNamedValuesAreShownWithOrWithoutTheirColon(): void
    {
        $this->assertSame(
            "... WHERE tag = 'Home'",
            SqlParameters::interpolateForDisplay('... WHERE tag = :tag', ['tag' => 'Home'])
        );
        $this->assertSame(
            "... WHERE tag = 'Home'",
            SqlParameters::interpolateForDisplay('... WHERE tag = :tag', [':tag' => 'Home'])
        );
    }

    /**
     * `:id` is a prefix of `:id_form`, so replacing the short name first would leave
     * `'7_form'` in the middle of the query and make the footer lie about what ran.
     */
    public function testAShorterNameDoesNotCorruptALongerOneItPrefixes(): void
    {
        $this->assertSame(
            "... WHERE id = 7 AND id_form = 'BazaR'",
            SqlParameters::interpolateForDisplay(
                '... WHERE id = :id AND id_form = :id_form',
                ['id' => 7, 'id_form' => 'BazaR']
            )
        );
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function likeTerms(): array
    {
        return [
            'ordinary text is untouched' => ['nantes', 'nantes'],
            'a percent stops being "anything"' => ['100%', '100!%'],
            'an underscore stops being "any character"' => ['a_b', 'a!_b'],
            // the escape character has to be defused first, or it would defuse the wrong thing
            'the escape character escapes itself' => ['wow!', 'wow!!'],
            'all three at once' => ['a_b!c%d', 'a!_b!!c!%d'],
            'empty stays empty' => ['', ''],
        ];
    }

    #[DataProvider('likeTerms')]
    public function testLikeWildcardsAreDefused(string $term, string $expected): void
    {
        $this->assertSame($expected, SqlParameters::likeWildcardsEscaped($term));
    }

    public function testLikeContainsWrapsTheDefusedTerm(): void
    {
        $this->assertSame('%100!%%', SqlParameters::likeContains('100%'));
    }

    /**
     * The suffix is not decoration: SQLite has NO default escape character, so without the
     * clause the escaping above is inert there while working on MySQL.
     */
    public function testTheEscapeClauseNamesTheEscapeCharacter(): void
    {
        $this->assertSame(" ESCAPE '!'", SqlParameters::LIKE_CLAUSE_SUFFIX);
        $this->assertStringContainsString(SqlParameters::LIKE_ESCAPE, SqlParameters::LIKE_CLAUSE_SUFFIX);
    }

    /** Long text is what an indexed `text` column holds; the footer prints one line per query. */
    public function testLongTextIsCutSoTheFooterStaysReadable(): void
    {
        $shown = SqlParameters::interpolateForDisplay('INSERT INTO x VALUES (?)', [str_repeat('a', 500)]);

        $this->assertStringContainsString("...'", $shown);
        $this->assertLessThan(200, mb_strlen($shown));
    }
}
