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
        // '$.title' reduces to the bare field name for the ->> operator
        $this->assertStringContainsString("->> 'title'", $d->jsonExtract('body', '$.title'));
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
}
