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
 * Ticket 05 (CP3) split the per-driver SQL out of DbService, where 30 of 43 methods branched on $this->driver.
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

        $this->assertStringContainsString('json_valid', $d->jsonExtract('body', '$.title'));

        $this->assertStringNotContainsString('ORDER BY', $d->groupConcat('tag', 'tag'));
        $this->assertStringNotContainsString('SEPARATOR', $d->groupConcat('tag', 'tag'));
    }

    public function testSqliteFindInSetCoversEveryPositionInTheList(): void
    {
        $sql = (new SqliteDialect())->findInSet("'b'", 'tags');

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

    /** A NESTED json path must address a nested key. */
    #[DataProvider('dialects')]
    public function testANestedJsonPathAddressesTheNestedKey(SqlDialect $d, string $driver): void
    {
        foreach ([$d->jsonExtractText('metadata', '$.acls.read'), $d->jsonExtract('body', '$.acls.read')] as $nested) {
            $this->assertStringNotContainsString(
                "'acls.read'",
                $nested,
                "$driver must not treat the whole path as one key name"
            );

            if ($driver === 'pgsql') {
                $this->assertStringContainsString("#>> ARRAY['acls', 'read']", $nested);
            } else {
                $this->assertStringContainsString('$.acls.read', $nested);
            }
        }

        $this->assertStringContainsString(
            $driver === 'pgsql' ? "ARRAY['form_id']" : '$.form_id',
            $d->jsonExtract('body', '$.form_id')
        );
    }

    /**
     * A path segment can be a form field name, which is user data -- so it must not be able to end the literal it sits in.
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

    /** ADR-0018. */
    #[DataProvider('dialects')]
    public function testJsonColumnTypeIsTheDialectsOwn(SqlDialect $d, string $driver): void
    {
        $this->assertSame(
            ['mysql' => 'JSON', 'pgsql' => 'JSONB', 'sqlite' => 'TEXT'][$driver],
            $d->jsonColumnType()
        );
    }

    /**
     * The seam ADR-0018 turns on: reading a column that is known to be JSON is not the same expression as reading JSON out of a text column, and only PostgreSQL had anything to drop -- the regex guard and the `::jsonb` cast it paid per row *per extracted field*.
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
     * `LIKE` over a whole body -- an administrator's free-text filter, which has no path to extract because it is not asking about a field.
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

        $this->assertSame(0, substr_count($quoted, $delimiter) % 2);
    }
}
