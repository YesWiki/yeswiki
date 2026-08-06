<?php

namespace YesWiki\Test\Kernel\Database;

use YesWiki\Kernel\Database\SqlParameters;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * What binding actually does differently from DbService::escape(), against a live connection.
 *
 * These run on whichever driver the suite is pointed at -- SQLite locally, MySQL in CI -- which
 * is the point: every claim here is about behaviour that differs *between* drivers, and the
 * three of them (sqlite/mysql/pgsql) are exactly where "works on my machine" has been coming
 * from. A pure unit test cannot see any of it; SqlParametersTest covers what it can.
 *
 * The scratch table is created and dropped per test class and carries the wiki's own prefix, so
 * it can never collide with a real table and never survives the run.
 */
class BoundValuesTest extends YesWikiTestCase
{
    private static DbService $db;
    private static string $table;

    public static function setUpBeforeClass(): void
    {
        self::$db = self::getWiki()->services->get(DbService::class);
        self::$table = trim(self::$db->prefixTable('binding_probe'));

        self::$db->query('DROP TABLE IF EXISTS ' . self::$table);
        // INTEGER and TEXT are spelled the same by all three drivers, so the fixture needs no
        // dialect of its own
        self::$db->query('CREATE TABLE ' . self::$table . ' (id INTEGER, label TEXT)');
    }

    public static function tearDownAfterClass(): void
    {
        self::$db->query('DROP TABLE IF EXISTS ' . self::$table);
    }

    protected function setUp(): void
    {
        self::$db->query('DELETE FROM ' . self::$table);
    }

    /**
     * The headline claim: a value that looks like SQL is data, not SQL.
     *
     * Written as a SELECT rather than a "did it drop the table" scare test, because this is the
     * shape the bug would really take -- a WHERE that matches everything instead of one row.
     */
    public function testAValueThatLooksLikeSqlMatchesNothingInsteadOfEverything(): void
    {
        self::$db->query('INSERT INTO ' . self::$table . ' (id, label) VALUES (?, ?)', [1, 'ordinary']);
        self::$db->query('INSERT INTO ' . self::$table . ' (id, label) VALUES (?, ?)', [2, 'also ordinary']);

        $rows = self::$db->loadAll(
            'SELECT id FROM ' . self::$table . ' WHERE label = ?',
            ["x' OR '1'='1"]
        );

        $this->assertSame([], $rows, 'A bound value must be compared as text, never parsed as SQL.');
    }

    /** Quotes, backslashes and comment markers survive a round trip byte for byte. */
    public function testAwkwardTextRoundTripsUnchanged(): void
    {
        $awkward = "O'Brien \\ -- /* \" ; DROP éàü 😀";

        self::$db->query('INSERT INTO ' . self::$table . ' (id, label) VALUES (?, ?)', [1, $awkward]);
        $row = self::$db->loadSingle('SELECT label FROM ' . self::$table . ' WHERE id = ?', [1]);

        $this->assertNotNull($row, 'the row bound with awkward text should be found by its id');
        $this->assertSame($awkward, $row['label']);
    }

    /**
     * The difference escape() cannot express: null is NULL, not ''.
     *
     * escape() casts through (string), so a null filter reached the database as the empty
     * string -- which is a value, matches `= ''`, and does not match `IS NULL`. MySQL and
     * SQLite let that slide in most columns; a NOT NULL or a strict column does not.
     */
    public function testNullIsStoredAsNullAndNotAsAnEmptyString(): void
    {
        self::$db->query('INSERT INTO ' . self::$table . ' (id, label) VALUES (?, ?)', [1, null]);

        $this->assertSame(
            1,
            (int)self::$db->scalar('SELECT COUNT(*) FROM ' . self::$table . ' WHERE label IS NULL', 0),
            'A bound null must be a real NULL.'
        );
        $this->assertSame(
            0,
            (int)self::$db->scalar('SELECT COUNT(*) FROM ' . self::$table . " WHERE label = ''", 0),
            "A bound null must not become '' -- that is the escape() behaviour this replaces."
        );
    }

    /**
     * An int binds as an int, which is what makes `LIMIT ?` legal.
     *
     * PDO::ATTR_EMULATE_PREPARES is false (DbService::initSqlConnection), so placeholders are
     * the server's, not PDO's string substitution. That is what makes the type matter: a
     * string-bound LIMIT is a syntax error on MySQL rather than a silently coerced number.
     */
    public function testAnIntBindsAsAnIntSoLimitCanBeBound(): void
    {
        foreach ([1, 2, 3, 4, 5] as $id) {
            self::$db->query('INSERT INTO ' . self::$table . ' (id, label) VALUES (?, ?)', [$id, 'row ' . $id]);
        }

        $rows = self::$db->loadAll('SELECT id FROM ' . self::$table . ' ORDER BY id ASC LIMIT ?', [2]);

        $this->assertCount(2, $rows);
        $this->assertSame([1, 2], array_map(static fn (array $r): int => (int)$r['id'], $rows));
    }

    /** Named placeholders, since a ten-column INSERT is unreadable positionally. */
    public function testNamedPlaceholdersBindWithOrWithoutTheirColon(): void
    {
        self::$db->query(
            'INSERT INTO ' . self::$table . ' (id, label) VALUES (:id, :label)',
            ['id' => 7, 'label' => 'named']
        );
        self::$db->query(
            'INSERT INTO ' . self::$table . ' (id, label) VALUES (:id, :label)',
            [':id' => 8, ':label' => 'colonised']
        );

        $this->assertSame('named', self::$db->scalar('SELECT label FROM ' . self::$table . ' WHERE id = ?', null, [7]));
        $this->assertSame('colonised', self::$db->scalar('SELECT label FROM ' . self::$table . ' WHERE id = ?', null, [8]));
    }

    /** A prepared statement reused across values -- the reindexer's write loop. */
    public function testAPreparedStatementCanBeExecutedRepeatedly(): void
    {
        $insert = self::$db->prepare('INSERT INTO ' . self::$table . ' (id, label) VALUES (?, ?)');
        foreach (range(1, 20) as $id) {
            $insert->execute([$id, 'row ' . $id]);
        }

        $this->assertSame(20, (int)self::$db->scalar('SELECT COUNT(*) FROM ' . self::$table, 0));
        $this->assertSame(
            'row 20',
            self::$db->scalar('SELECT label FROM ' . self::$table . ' WHERE id = ?', null, [20])
        );
    }

    /**
     * An IN list built from a count, with the values still bound.
     *
     * This is the one place a parameterised query still assembles SQL, so it is worth pinning
     * that what it assembles is placeholders and not data.
     */
    public function testAnInListBindsEveryValue(): void
    {
        foreach (range(1, 5) as $id) {
            self::$db->query('INSERT INTO ' . self::$table . ' (id, label) VALUES (?, ?)', [$id, 'row ' . $id]);
        }

        $wanted = ['row 2', "row 3' OR 1=1 --", 'row 4'];
        $rows = self::$db->loadAll(
            'SELECT id FROM ' . self::$table . ' WHERE label IN (?, ?, ?) ORDER BY id ASC',
            $wanted
        );

        $this->assertSame([2, 4], array_map(static fn (array $r): int => (int)$r['id'], $rows));
    }

    /**
     * A defused LIKE matches the term literally, on this driver.
     *
     * The pure-function half is in SqlParametersTest; this is the half that cannot be unit
     * tested, because whether `ESCAPE` is honoured is the database's business. SQLite has no
     * default escape character, so an implementation that forgot the clause would pass on MySQL
     * and quietly fail here -- which is why this assertion exists at all.
     */
    public function testADefusedLikeMatchesWildcardsLiterally(): void
    {
        self::$db->query('INSERT INTO ' . self::$table . ' (id, label) VALUES (?, ?)', [1, '100% cotton']);
        self::$db->query('INSERT INTO ' . self::$table . ' (id, label) VALUES (?, ?)', [2, '100 percent wool']);

        $sql = 'SELECT id FROM ' . self::$table . ' WHERE label LIKE ?' . SqlParameters::LIKE_CLAUSE_SUFFIX;

        $literal = self::$db->loadAll($sql, [SqlParameters::likeContains('100%')]);
        $this->assertSame(
            [1],
            array_map(static fn (array $r): int => (int)$r['id'], $literal),
            "'100%' must match the row that literally contains '100%', not every row starting 100."
        );

        // and the wildcard still works when it is the query's own, not the user's
        $wild = self::$db->loadAll($sql, ['100%']);
        $this->assertCount(2, $wild, 'an undefused % is still a wildcard -- the defusing is opt-in');
    }

    /** An underscore is the other wildcard, and the one nobody remembers. */
    public function testADefusedUnderscoreIsNotASingleCharacterWildcard(): void
    {
        self::$db->query('INSERT INTO ' . self::$table . ' (id, label) VALUES (?, ?)', [1, 'a_b']);
        self::$db->query('INSERT INTO ' . self::$table . ' (id, label) VALUES (?, ?)', [2, 'aXb']);

        $rows = self::$db->loadAll(
            'SELECT id FROM ' . self::$table . ' WHERE label LIKE ?' . SqlParameters::LIKE_CLAUSE_SUFFIX,
            [SqlParameters::likeContains('a_b')]
        );

        $this->assertSame([1], array_map(static fn (array $r): int => (int)$r['id'], $rows));
    }

    /** A statement with placeholders and no values still runs unchanged -- every old caller. */
    public function testOmittingValuesRunsTheStatementExactlyAsGiven(): void
    {
        self::$db->query('INSERT INTO ' . self::$table . " (id, label) VALUES (1, 'inline')");

        $this->assertSame('inline', self::$db->scalar('SELECT label FROM ' . self::$table . ' WHERE id = 1'));
    }
}
