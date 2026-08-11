<?php

namespace YesWiki\Test\Kernel\Database;

use YesWiki\Kernel\Database\SchemaManager;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * `SchemaManager`, extracted from DbService along with 216 lines of `switch ($this->driver)`.
 *
 * These ran against whichever driver the suite is pointed at and had **no tests at all** while
 * they lived on DbService -- which is how `columnExists()` and `getColumnInfo()` came to ask the
 * same question of the same three drivers in two separate places, free to drift.
 *
 * The MySQL lookups moved from `SHOW COLUMNS FROM t LIKE '<column>'` to `information_schema`,
 * because MySQL cannot bind in a SHOW statement (`SHOW COLUMNS FROM t LIKE ?` is a syntax error,
 * measured) and that forced the column name -- an identifier -- to be escape()d as if it were a
 * value. `COLUMN_TYPE` is byte-identical to SHOW COLUMNS' `Type`, so `type` reads the same;
 * testTheReportedTypeMatchesWhatWasDeclared is what holds that.
 */
class SchemaManagerTest extends YesWikiTestCase
{
    private const TABLE = 'schemamanager_probe';

    private static function db(): DbService
    {
        return self::getWiki()->services->get(DbService::class);
    }

    private static function schema(): SchemaManager
    {
        return self::db()->schema();
    }

    /** The probe table, created with the same prefix real tables use so it cannot collide. */
    public static function setUpBeforeClass(): void
    {
        $db = self::db();
        $table = trim($db->prefixTable(self::TABLE));
        $db->query('DROP TABLE IF EXISTS ' . $table);
        $db->query(
            "CREATE TABLE {$table} ("
            . 'tag VARCHAR(191) NOT NULL,'
            . ' note TEXT,'
            . ' counter INTEGER'
            . ')'
        );
    }

    public static function tearDownAfterClass(): void
    {
        $db = self::db();
        $db->query('DROP TABLE IF EXISTS ' . trim($db->prefixTable(self::TABLE)));
    }

    public function testTheAccessorIsMemoised(): void
    {
        $this->assertSame(self::db()->schema(), self::db()->schema(), 'schema() must not rebuild');
    }

    public function testAColumnThatExistsIsFound(): void
    {
        $this->assertTrue(self::schema()->columnExists(self::TABLE, 'tag'));
        $this->assertTrue(self::schema()->columnExists(self::TABLE, 'note'));
    }

    public function testAColumnThatDoesNotExistIsNotFound(): void
    {
        $this->assertFalse(self::schema()->columnExists(self::TABLE, 'no_such_column'));
    }

    /** All three drivers' lookups were case-insensitive; keep it that way. */
    public function testColumnLookupIsCaseInsensitive(): void
    {
        $this->assertTrue(self::schema()->columnExists(self::TABLE, 'TAG'));
    }

    public function testAMissingTableIsNotAnError(): void
    {
        $this->assertFalse(
            self::schema()->columnExists('schemamanager_no_such_table', 'tag'),
            'asking about a table that is not there must answer no, not throw'
        );
    }

    /**
     * The property the MySQL rewrite had to preserve: `type` still spells the declared type.
     *
     * PasswordHasherFactory compares it against the literal `varchar(256)` (or PostgreSQL's
     * `character varying(256)`), so a change of shape here would silently change behaviour there.
     */
    public function testTheReportedTypeMatchesWhatWasDeclared(): void
    {
        $info = self::schema()->getColumnInfo(self::TABLE, 'tag');

        $this->assertNotNull($info);
        $this->assertMatchesRegularExpression(
            '/^(varchar|character varying)\(191\)$/',
            $info['type'],
            'the declared length must survive -- MySQL COLUMN_TYPE, SQLite PRAGMA type, '
            . 'PostgreSQL data_type + character_maximum_length'
        );
        $this->assertFalse($info['nullable'], 'tag is NOT NULL');
    }

    public function testNullabilityIsReported(): void
    {
        $info = self::schema()->getColumnInfo(self::TABLE, 'note');

        $this->assertNotNull($info);
        $this->assertTrue($info['nullable'], 'note has no NOT NULL');
    }

    public function testGetColumnInfoReturnsNullForAMissingColumn(): void
    {
        $this->assertNull(self::schema()->getColumnInfo(self::TABLE, 'no_such_column'));
    }

    /**
     * columnExists() and getColumnInfo() must agree, because they now share one lookup -- which
     * is the reason to have extracted them: two copies of the same three-driver switch is two
     * copies free to disagree.
     */
    public function testTheTwoLookupsAgree(): void
    {
        foreach (['tag', 'note', 'counter', 'no_such_column'] as $column) {
            $this->assertSame(
                self::schema()->columnExists(self::TABLE, $column),
                self::schema()->getColumnInfo(self::TABLE, $column) !== null,
                "columnExists() and getColumnInfo() disagree about '{$column}'"
            );
        }
    }

    public function testGetTablesReportsRealPrefixedNames(): void
    {
        $tables = self::schema()->getTables();

        $this->assertContains(trim(self::db()->prefixTable(self::TABLE)), $tables);
        $this->assertContains(trim(self::db()->prefixTable('pages')), $tables, 'and the real ones');
    }

    /** Dropping is idempotent, so a migration that re-runs does not fail on it. */
    public function testDroppingAColumnIsIdempotent(): void
    {
        $schema = self::schema();
        $this->assertTrue($schema->columnExists(self::TABLE, 'counter'));

        $schema->dropColumn(self::TABLE, 'counter');
        $this->assertFalse($schema->columnExists(self::TABLE, 'counter'));

        // second time: a no-op rather than an error
        $schema->dropColumn(self::TABLE, 'counter');
        $this->assertFalse($schema->columnExists(self::TABLE, 'counter'));

        // put it back for any test ordering that follows
        self::db()->query('ALTER TABLE ' . trim(self::db()->prefixTable(self::TABLE)) . ' ADD COLUMN counter INTEGER');
    }

    /**
     * A column name is an identifier, and one of these branches has to interpolate it (PRAGMA
     * takes no parameters). So it must not be able to end the statement it sits in.
     */
    public function testAHostileColumnNameCannotBreakTheQuery(): void
    {
        $schema = self::schema();

        foreach (["tag' OR '1'='1", 'tag"; DROP TABLE x; --', 'tag`'] as $hostile) {
            $this->assertFalse(
                $schema->columnExists(self::TABLE, $hostile),
                "a column named {$hostile} does not exist, and asking must not corrupt the query"
            );
        }

        // the table is still there and still answers, which is the real assertion
        $this->assertTrue($schema->columnExists(self::TABLE, 'tag'));
    }

    /** PostgreSQL cannot produce a CREATE TABLE, and says so with null rather than throwing. */
    public function testGetTableSchemaEitherDescribesTheTableOrDeclinesCleanly(): void
    {
        $ddl = self::schema()->getTableSchema(trim(self::db()->prefixTable(self::TABLE)));

        if (self::db()->getDriver() === 'pgsql') {
            $this->assertNull($ddl, 'pgsql has no SHOW CREATE TABLE equivalent');

            return;
        }

        $this->assertNotNull($ddl);
        $this->assertStringContainsStringIgnoringCase('create table', $ddl);
        $this->assertStringContainsString(self::TABLE, $ddl);
    }
}
