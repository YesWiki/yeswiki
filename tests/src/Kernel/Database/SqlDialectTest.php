<?php

namespace YesWiki\Test\Kernel\Database;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use YesWiki\Kernel\Database\MySqlDialect;
use YesWiki\Kernel\Database\PostgreSqlDialect;
use YesWiki\Kernel\Database\SqlDialect;
use YesWiki\Kernel\Database\SqlDialectFactory;
use YesWiki\Kernel\Database\SqliteDialect;

/**
 * Ticket 05 (CP3) split the per-driver SQL out of DbService, where 30 of 43 methods
 * branched on $this->driver. These pin each dialect's fragments so a future driver change
 * cannot quietly alter MySQL or SQLite: before the split there was nothing to test without
 * a live connection, which is why none of this was covered.
 */
class SqlDialectTest extends TestCase
{
    /**
     * @return array<string, array{SqlDialect, string}>
     */
    public static function dialects(): array
    {
        return [
            'mysql' => [new MySqlDialect(), 'mysql'],
            'sqlite' => [new SqliteDialect(), 'sqlite'],
            'pgsql' => [new PostgreSqlDialect(), 'pgsql'],
        ];
    }

    #[DataProvider('dialects')]
    public function testFactoryReturnsTheDialectForItsDriver(SqlDialect $expected, string $driver): void
    {
        $this->assertInstanceOf($expected::class, SqlDialectFactory::forDriver($driver));
        $this->assertSame($driver, SqlDialectFactory::forDriver($driver)->driverName());
    }

    public function testUnknownDriverFallsBackToMySql(): void
    {
        // preserves the `default:` branch of the switch statements this replaced
        $this->assertInstanceOf(MySqlDialect::class, SqlDialectFactory::forDriver('oracle'));
        $this->assertInstanceOf(MySqlDialect::class, SqlDialectFactory::forDriver(''));
    }

    public function testMySqlFragments(): void
    {
        $d = new MySqlDialect();
        $this->assertSame('NOW()', $d->now());
        $this->assertSame('DATE_SUB(NOW(), INTERVAL 7 DAY)', $d->dateSubDays(7));
        $this->assertSame('DATE_SUB(NOW(), INTERVAL 3 HOUR)', $d->dateSubHours(3));
        $this->assertSame('`user`', $d->quoteIdentifier('user'));
        $this->assertSame(' COLLATE utf8mb4_unicode_ci', $d->collateClause());
        $this->assertSame('REGEXP', $d->regexpOperator());
        $this->assertSame('NOT REGEXP', $d->regexpOperator(true));
        $this->assertSame("FIND_IN_SET('a', tags)", $d->findInSet("'a'", 'tags'));
        $this->assertSame("NOT FIND_IN_SET('a', tags)", $d->findInSet("'a'", 'tags', true));
        $this->assertStringContainsString('JSON_UNQUOTE', $d->jsonExtract('body', '$.title'));
    }

    public function testSqliteFragments(): void
    {
        $d = new SqliteDialect();
        $this->assertSame("datetime('now')", $d->now());
        $this->assertSame("datetime('now', '-7 days')", $d->dateSubDays(7));
        $this->assertSame('"user"', $d->quoteIdentifier('user'));
        $this->assertSame(' COLLATE NOCASE', $d->collateClause());
        // json_extract() errors on non-JSON, so the guard must be present
        $this->assertStringContainsString('json_valid', $d->jsonExtract('body', '$.title'));
        // GROUP_CONCAT supports neither ORDER BY nor DISTINCT-with-separator here
        $this->assertStringNotContainsString('ORDER BY', $d->groupConcat('tag', 'tag'));
        $this->assertStringNotContainsString('SEPARATOR', $d->groupConcat('tag', 'tag'));
    }

    public function testSqliteFindInSetCoversEveryPositionInTheList(): void
    {
        $sql = (new SqliteDialect())->findInSet("'b'", 'tags');
        // start, middle, end, and sole value
        $this->assertSame(3, substr_count($sql, 'LIKE'));
        $this->assertStringContainsString("tags = 'b'", $sql);

        $negated = (new SqliteDialect())->findInSet("'b'", 'tags', true);
        $this->assertSame(3, substr_count($negated, 'NOT LIKE'));
        $this->assertStringContainsString("tags != 'b'", $negated);
    }

    public function testPostgreSqlFragments(): void
    {
        $d = new PostgreSqlDialect();
        $this->assertSame("NOW() - INTERVAL '7 days'", $d->dateSubDays(7));
        $this->assertSame('"user"', $d->quoteIdentifier('user'));
        $this->assertSame('', $d->collateClause(), 'pgsql uses ILIKE rather than COLLATE');
        $this->assertSame('~', $d->regexpOperator());
        $this->assertSame('!~', $d->regexpOperator(true));
        // one quoted element per path segment, via #>> -- see
        // testANestedJsonPathAddressesTheNestedKey for why `->>` with the whole path was wrong
        $this->assertStringContainsString("#>> ARRAY['title']", $d->jsonExtract('body', '$.title'));
    }

    #[DataProvider('dialects')]
    public function testEveryDialectQuotesReservedWordsAndNegatesConsistently(SqlDialect $d, string $driver): void
    {
        foreach (['user', 'time', 'order'] as $reserved) {
            $quoted = $d->quoteIdentifier($reserved);
            $this->assertNotSame($reserved, $quoted, "$driver must quote the reserved word '$reserved'");
            $this->assertStringContainsString($reserved, $quoted);
        }
        $this->assertNotSame($d->regexpOperator(false), $d->regexpOperator(true));
        $this->assertNotSame($d->findInSet("'x'", 'c', false), $d->findInSet("'x'", 'c', true));
    }

    /**
     * The quote character inside a name is escaped, not merely wrapped around.
     *
     * quoteIdentifier() used to concatenate delimiters and inspect nothing, so a name carrying
     * the delimiter closed the quoting early -- a method whose whole job is to make a name safe
     * to interpolate, that did not. No caller passes such a name today (they pass literals like
     * `time`), which is exactly why nothing would have caught it.
     *
     * Note what this still does NOT make safe: an arbitrary user-supplied identifier. An
     * identifier cannot be bound, so it has to be constrained before it arrives -- see
     * SearchManager::asSafeIdentifier() and FieldNameIsNotSqlTest.
     */
    /**
     * A NESTED json path must address a nested key.
     *
     * PostgreSqlDialect stripped the leading `$.` and passed the remainder to `->>`, which takes
     * a single key -- so `$.acls.read` looked for a top-level key literally named `acls.read`,
     * found none, and returned NULL for every row. The only nested paths in the codebase are the
     * ACL ones, which made it a security bug: with every read ACL reading as NULL,
     * AclService's predicate fell back to `default_read_acl` for the whole table and page-level
     * read ACLs stopped filtering on PostgreSQL entirely.
     *
     * Asserted as "the segments are addressed separately", which is the property; each driver
     * spells it its own way (`JSON_EXTRACT`/`json_extract` take the path whole, PostgreSQL needs
     * `#>>` with an array).
     */
    #[DataProvider('dialects')]
    public function testANestedJsonPathAddressesTheNestedKey(SqlDialect $d, string $driver): void
    {
        // `metadata` is read through jsonExtractText() -- it is JSON in a TEXT column and stays
        // that way (ADR-0018) -- but the property is the path's, so both forms are asserted.
        foreach ([$d->jsonExtractText('metadata', '$.acls.read'), $d->jsonExtract('body', '$.acls.read')] as $nested) {
            $this->assertStringNotContainsString(
                "'acls.read'",
                $nested,
                "$driver must not treat the whole path as one key name"
            );

            if ($driver === 'pgsql') {
                // the two segments, each its own quoted element
                $this->assertStringContainsString("#>> ARRAY['acls', 'read']", $nested);
            } else {
                // these engines parse the path themselves
                $this->assertStringContainsString('$.acls.read', $nested);
            }
        }

        // a flat path must keep working -- every other caller uses one
        $this->assertStringContainsString(
            $driver === 'pgsql' ? "ARRAY['form_id']" : '$.form_id',
            $d->jsonExtract('body', '$.form_id')
        );
    }

    /**
     * A path segment can be a form field name, which is user data -- so it must not be able to
     * end the literal it sits in.
     */
    #[DataProvider('dialects')]
    public function testAQuoteInAJsonPathSegmentCannotEndTheLiteral(SqlDialect $d, string $driver): void
    {
        $expression = $d->jsonExtract('body', "$.bf_a'b");

        $this->assertSame(
            0,
            substr_count($expression, "'") % 2,
            "$driver: the quotes in the generated expression must balance"
        );
    }

    /**
     * ADR-0018. `body` is the dialect's own JSON type where there is one; SQLite says TEXT and
     * means it, which is why the method returns a type rather than a nullable "native or not".
     */
    #[DataProvider('dialects')]
    public function testJsonColumnTypeIsTheDialectsOwn(SqlDialect $d, string $driver): void
    {
        $this->assertSame(
            ['mysql' => 'JSON', 'pgsql' => 'JSONB', 'sqlite' => 'TEXT'][$driver],
            $d->jsonColumnType()
        );
    }

    /**
     * The seam ADR-0018 turns on: reading a column that is known to be JSON is not the same
     * expression as reading JSON out of a text column, and only PostgreSQL had anything to
     * drop -- the regex guard and the `::jsonb` cast it paid per row *per extracted field*.
     *
     * Asserted as "cheaper than, and a strict subset of" rather than by pinning the exact SQL:
     * what matters is that the native form stops proving the column is JSON, not how it is
     * spelled.
     */
    #[DataProvider('dialects')]
    public function testOnlyPostgreSqlDropsAnythingForANativeColumn(SqlDialect $d, string $driver): void
    {
        $native = $d->jsonExtract('body', '$.form_id');
        $guarded = $d->jsonExtractText('metadata', '$.form_id');

        if ($driver !== 'pgsql') {
            $this->assertSame(
                $native,
                str_replace('metadata', 'body', $guarded),
                "$driver has no guard to drop: both forms are the same read"
            );

            return;
        }

        $this->assertStringNotContainsString('CASE WHEN', $native, 'no guard on a jsonb column');
        $this->assertStringNotContainsString('::jsonb', $native, 'nothing to cast: it is already jsonb');
        $this->assertStringContainsString('CASE WHEN', $guarded, 'a text column may hold a non-document');
        $this->assertStringContainsString('::jsonb', $guarded);
    }

    /**
     * `LIKE` over a whole body -- an administrator's free-text filter, which has no path to
     * extract because it is not asking about a field. PostgreSQL has no `jsonb LIKE` operator
     * at all, so the cast is the difference between a query and a syntax error.
     */
    #[DataProvider('dialects')]
    public function testJsonAsTextCastsOnlyWhereTheOperatorIsMissing(SqlDialect $d, string $driver): void
    {
        $expression = $d->jsonAsText('p.body');

        if ($driver === 'pgsql') {
            $this->assertStringContainsString('::text', $expression);

            return;
        }

        $this->assertSame('p.body', $expression, "$driver coerces for a string operator already");
    }

    #[DataProvider('dialects')]
    public function testQuoteIdentifierEscapesItsOwnDelimiter(SqlDialect $d, string $driver): void
    {
        $delimiter = $driver === 'mysql' ? '`' : '"';

        $quoted = $d->quoteIdentifier('a' . $delimiter . 'b');

        $this->assertSame(
            $delimiter . 'a' . $delimiter . $delimiter . 'b' . $delimiter,
            $quoted,
            "$driver must double a $delimiter inside the name rather than let it end the quoting"
        );
        // the delimiters are balanced, which is what "cannot break out" means in practice
        $this->assertSame(0, substr_count($quoted, $delimiter) % 2);
    }
}
