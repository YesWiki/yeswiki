<?php

namespace YesWiki\Kernel\Service;

use DateInterval;
use Exception;
use PDO;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Kernel\Database\PreparedStatement;
use YesWiki\Kernel\Database\SchemaManager;
use YesWiki\Kernel\Database\SqlDialect;
use YesWiki\Kernel\Database\SqlDialectFactory;
use YesWiki\Kernel\Database\SqlDumper;
use YesWiki\Kernel\Database\SqlParameters;
use YesWiki\Kernel\Database\SqlStatementSplitter;

class DbService
{
    protected $params;

    protected $link;
    protected $queryLog;
    protected $driver;
    /** Per-driver SQL fragments; see YesWiki\Kernel\Database\SqlDialect. */
    protected SqlDialect $dialect;

    /**
     * How many nested `transactional()` scopes are open; only the outermost one is real.
     *
     * PDO::beginTransaction() throws if a transaction is already active, and these writes nest
     * for real -- AclService::writeMetadataAcls() and PageManager::save() both revision a row,
     * and either can be reached from inside the other. Counting scopes lets an inner one say
     * "all of this together" without needing to know whether it is the outermost.
     */
    private int $transactionDepth = 0;

    /**
     * Set when an inner scope rolled back, so the outermost commit refuses.
     *
     * Without this, an inner failure whose exception something swallowed would be committed by
     * the outer scope -- which is the one outcome a transaction exists to prevent, and it would
     * look like success.
     */
    private bool $transactionRollbackOnly = false;

    /** Lazily built by schema(); see there for why it is not injected. */
    private ?SchemaManager $schemaManager = null;

    /** Lazily built by dumper(). */
    private ?SqlDumper $sqlDumper = null;

    public function __construct(ParameterBagInterface $params)
    {
        $this->params = $params;
        $this->queryLog = [];
        $this->driver = $this->params->has('db_driver') ? $this->params->get('db_driver') : 'mysql';
        $this->dialect = SqlDialectFactory::forDriver((string)$this->driver);

        $this->initSqlConnection();
    }

    protected function initSqlConnection()
    {
        try {
            $dsn = $this->buildDsn();
            $username = null;
            $password = null;

            // SQLite doesn't need username/password
            if ($this->driver !== 'sqlite') {
                $username = $this->params->get('db_user');
                $password = $this->params->get('db_password');
            }

            $options = [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                \PDO::ATTR_EMULATE_PREPARES => false,
            ];
            // PDO::connect() (PHP >= 8.4) returns a driver-specific subclass (e.g.
            // Pdo\Sqlite), needed to call createFunction() without a deprecation
            // notice. The minimum supported PHP version (8.3) doesn't have it.
            $this->link = method_exists(\PDO::class, 'connect')
                ? \PDO::connect($dsn, $username, $password, $options)
                : new \PDO($dsn, $username, $password, $options);
            if (!$this->link) {
                throw new \Exception('Not connected to database');
            }

            // Driver-specific initialization
            $this->initDriverSpecific();
        } catch (\Throwable $th) {
            if (in_array(php_sapi_name(), ['cli', 'cli-server', ' phpdbg'], true)) {
                throw new \Exception(_t('DB_CONNECT_FAIL') . ': ' . $th->getMessage());
            }
            exit(_t('DB_CONNECT_FAIL'));

            exit(_t('DB_CONNECT_FAIL'));
        }
    }

    protected function buildDsn(): string
    {
        switch ($this->driver) {
            case 'sqlite':
                // SQLite uses a fixed path in the private directory
                $dbPath = $this->params->has('db_database') && $this->params->get('db_database')
                    ? $this->params->get('db_database')
                    : 'private/yeswiki.db';

                return 'sqlite:' . $dbPath;

            case 'pgsql':
                $dsn = 'pgsql:host=' . $this->params->get('db_host') . ';dbname=' . $this->params->get('db_database');
                if ($this->params->has('db_port') && $this->params->get('db_port')) {
                    $dsn .= ';port=' . $this->params->get('db_port');
                }

                return $dsn;

            case 'mysql':
            default:
                $dsn = 'mysql:host=' . $this->params->get('db_host') . ';dbname=' . $this->params->get('db_database');
                if ($this->params->has('db_port') && $this->params->get('db_port')) {
                    $dsn .= ';port=' . $this->params->get('db_port');
                }
                $charset = ($this->params->has('db_charset') && $this->params->get('db_charset'))
                    ? $this->params->get('db_charset')
                    : 'utf8mb4';
                $dsn .= ';charset=' . $charset;

                return $dsn;
        }
    }

    protected function initDriverSpecific(): void
    {
        switch ($this->driver) {
            case 'mysql':
                $charset = ($this->params->has('db_charset') && $this->params->get('db_charset'))
                    ? $this->params->get('db_charset')
                    : 'utf8mb4';
                if ($charset === 'utf8mb4') {
                    $this->link->exec('SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci');
                }
                break;

            case 'sqlite':
                // Enable foreign keys for SQLite
                $this->link->exec('PRAGMA foreign_keys = ON');
                // Add REGEXP function for SQLite (not built-in)
                $regexp = function ($pattern, $value) {
                    if ($pattern === null || $value === null) {
                        return false;
                    }

                    return preg_match('/' . $pattern . '/iu', $value) === 1;
                };
                // PDO::sqliteCreateFunction() is deprecated since PHP 8.5 in favor of
                // Pdo\Sqlite::createFunction() (available since PHP 8.4), but the minimum
                // supported PHP version (8.3) only has the former.
                if (method_exists($this->link, 'createFunction')) {
                    $this->link->createFunction('REGEXP', $regexp, 2);
                } else {
                    $this->link->sqliteCreateFunction('REGEXP', $regexp, 2);
                }
                break;

            case 'pgsql':
                // Set client encoding for PostgreSQL
                $this->link->exec("SET client_encoding TO 'UTF8'");
                break;
        }
    }

    public function getDriver(): string
    {
        return $this->driver;
    }

    /** The per-driver SQL fragments this connection speaks (ticket 17 exposed it for restore). */
    public function dialect(): SqlDialect
    {
        return $this->dialect;
    }

    /**
     * Drop this wiki's tables and replay $sqlContent into them.
     *
     * Ticket 17: archive restore used to hand the whole dump to `mysqli_multi_query()` over a
     * second, raw connection -- which is why it only ever worked on MySQL, and why an SQLite
     * install could take a backup it could never put back. It runs here now, on the ordinary
     * PDO connection, one statement at a time (see SqlStatementSplitter), with the
     * driver-specific parts coming from the dialect.
     *
     * Lives on DbService rather than ArchiveService so it can be exercised against a scratch
     * database: a test for this must never be able to point it at the running wiki.
     *
     * @throws \Exception
     */
    public function restoreFromDump(string $sqlContent): void
    {
        $tablesPrefix = trim($this->prefixTable(''));
        if (empty($tablesPrefix)) {
            throw new \Exception('Table prefix is empty — refusing to drop all tables');
        }

        $this->assertDumpMatchesDriver($sqlContent);

        $statements = SqlStatementSplitter::split($sqlContent);
        if ($statements === []) {
            throw new \Exception('SQL restore failed: the dump contains no statements');
        }

        $disable = $this->dialect->foreignKeyChecks(false);
        if ($disable !== null) {
            $this->query($disable);
        }

        try {
            foreach ($this->schema()->getTables() as $tableName) {
                if (str_starts_with($tableName, $tablesPrefix)) {
                    $this->query('DROP TABLE IF EXISTS ' . $this->dialect->quoteIdentifier($tableName));
                }
            }

            foreach ($statements as $index => $statement) {
                try {
                    $this->query($statement);
                } catch (\Throwable $th) {
                    // the statement number and its opening are what make this diagnosable;
                    // a single INSERT can be megabytes long
                    $excerpt = substr((string)preg_replace('/\s+/', ' ', $statement), 0, 200);

                    throw new \Exception('SQL restore failed on statement ' . ($index + 1) . ' of ' . count($statements) . ' (' . $excerpt . '): ' . $th->getMessage(), 0, $th);
                }
            }
        } finally {
            $enable = $this->dialect->foreignKeyChecks(true);
            if ($enable !== null) {
                $this->query($enable);
            }
        }
    }

    /**
     * Refuse a dump produced by a different database driver.
     *
     * A dump carries CREATE TABLE statements in its own driver's syntax, so replaying a MySQL
     * dump on SQLite fails somewhere in the middle -- after the tables have already been
     * dropped. Refusing up front leaves the wiki as it was; the alternative is a half-restored
     * database and no way back. Dumps written before ticket 17 carry no marker and are assumed
     * to be MySQL, the only driver that could produce one.
     *
     * @throws \Exception
     */
    private function assertDumpMatchesDriver(string $sqlContent): void
    {
        $dumpDriver = preg_match('/^--\s*YesWiki-Dialect:\s*(\w+)\s*$/m', $sqlContent, $matches)
            ? $matches[1]
            : 'mysql';

        if ($dumpDriver !== $this->driver) {
            throw new \Exception("This archive was created on a '$dumpDriver' database and cannot be restored onto a '{$this->driver}' one: the dump describes its tables in '$dumpDriver' syntax. Restore it onto a '$dumpDriver' database, or re-create the wiki and import its content instead.");
        }
    }

    /**
     * Returns a SQL expression for the current timestamp.
     * This is database-driver agnostic.
     *
     * @return string SQL expression
     */
    public function now(): string
    {
        return $this->dialect->now();
    }

    /**
     * Returns a SQL expression for "current timestamp minus X days"
     * This is database-driver agnostic.
     *
     * @param int $days Number of days to subtract
     *
     * @return string SQL expression
     */
    public function dateSubDays(int $days): string
    {
        return $this->dialect->dateSubDays($days);
    }

    /**
     * Returns a SQL expression for "current timestamp minus X hours"
     * This is database-driver agnostic.
     *
     * @param int $hours Number of hours to subtract
     *
     * @return string SQL expression
     */
    public function dateSubHours(int $hours): string
    {
        return $this->dialect->dateSubHours($hours);
    }

    /**
     * Returns a SQL expression for extracting a value from a JSON column.
     * This is database-driver agnostic.
     *
     * @param string $column The column containing JSON data
     * @param string $path   The JSON path (e.g., '$.fieldname')
     *
     * @return string SQL expression
     */
    public function jsonExtract(string $column, string $path): string
    {
        return $this->dialect->jsonExtract($column, $path);
    }

    /**
     * Returns a SQL expression aggregating the distinct values of a column
     * into a single comma-separated string, ordered by $orderBy.
     * This is database-driver agnostic.
     *
     * @param string      $column  The column whose distinct values are aggregated
     * @param string|null $orderBy The column to order values by (defaults to $column)
     *
     * @return string SQL expression
     */
    public function groupConcat(string $column, ?string $orderBy = null): string
    {
        return $this->dialect->groupConcat($column, $orderBy);
    }

    /**
     * Quotes an identifier (table or column name) for the current database driver.
     * Use this for reserved keywords like 'user', 'time', 'order', etc.
     *
     * @param string $identifier The identifier to quote
     *
     * @return string The quoted identifier
     */
    public function quoteIdentifier(string $identifier): string
    {
        return $this->dialect->quoteIdentifier($identifier);
    }

    /**
     * Returns the collation clause for case-insensitive string comparisons.
     * This is database-driver agnostic.
     *
     * @return string SQL collation clause (empty string for drivers that don't need it)
     */
    public function collateClause(): string
    {
        return $this->dialect->collateClause();
    }

    /**
     * Returns the REGEXP operator for the current database driver.
     * This is database-driver agnostic.
     *
     * @param bool $not Whether to negate the condition (NOT REGEXP)
     *
     * @return string The REGEXP operator
     */
    public function regexpOperator(bool $not = false): string
    {
        return $this->dialect->regexpOperator($not);
    }

    /**
     * Returns a SQL expression for FIND_IN_SET (checking if a value exists in a comma-separated list).
     * This is database-driver agnostic.
     *
     * @param string $needle   The value to search for (should be already escaped/quoted)
     * @param string $haystack The column or expression containing comma-separated values
     * @param bool   $not      Whether to negate the condition (NOT FIND_IN_SET)
     *
     * @return string SQL expression
     */
    public function findInSet(string $needle, string $haystack, bool $not = false): string
    {
        return $this->dialect->findInSet($needle, $haystack, $not);
    }

    public function getCollation(): string
    {
        return $this->collation;
    }

    public function getQueryLog()
    {
        return $this->queryLog;
    }

    /**
     * Record a query for the debug footer.
     *
     * A parameterised statement is logged with its values spliced back in, because a footer
     * showing `WHERE tag = ?` and never saying which tag would take away the only reason the
     * log is there. That rendering is for reading only and is never executed --
     * SqlParameters::interpolateForDisplay() says so at more length.
     *
     * @param array<array-key, mixed> $params
     */
    public function addQueryLog($query, $time, array $params = [])
    {
        $this->queryLog[] = [
            'query' => SqlParameters::interpolateForDisplay((string)$query, $params),
            'time' => $time,
        ];
    }

    public function prefixTable($tableName)
    {
        return ' ' . $this->params->get('table_prefix') . $tableName . ' ';
    }

    public function escape($string)
    {
        // PDO::quote adds quotes around the string, so we strip them.
        // Cast first: callers legitimately pass null for an absent filter (e.g.
        // TripleStore::delete() with no value), and PDO::quote(null) is deprecated.
        $quoted = $this->link->quote((string)$string);

        return substr($quoted, 1, -1);
    }

    /**
     * Returns a PDOStatement on success, throws Exception on failure.
     * For SELECT, SHOW, DESCRIBE or EXPLAIN queries, returns a PDOStatement that can be used to fetch results.
     * For other queries (INSERT, UPDATE, DELETE), returns a PDOStatement (use rowCount() for affected rows).
     *
     * Pass $params to send values as values instead of splicing them into the SQL text. A
     * query with placeholders is not merely safer than one built with escape() -- the values
     * also reach the database as the types they are, which escape()'s `(string)` cast cannot
     * do (see SqlParameters). Both placeholder styles work:
     *
     *     query('... WHERE tag = ? AND latest = ?', [$tag, 'Y'])
     *     query('... WHERE tag = :tag', ['tag' => $tag])
     *
     * Omitting $params runs exactly the statement it is given, unchanged: the parameterless
     * path below is byte-for-byte what it always was, so no existing caller is affected.
     *
     * @param array<array-key, mixed> $params
     */
    public function query($query, array $params = [])
    {
        if ($this->params->get('debug')) {
            $start = $this->getMicroTime();
        }

        try {
            if ($params === []) {
                $result = $this->link->query($query);
            } else {
                SqlParameters::assertPlaceholderCount($query, $params);
                $result = $this->link->prepare($query);
                SqlParameters::bind($result, $params);
                $result->execute();
            }
            if ($result === false) {
                $errorInfo = $this->link->errorInfo();
                throw new \Exception('Query failed: ' . $query . ' (' . $errorInfo[2] . ')');
            }
        } catch (\PDOException $failed) {
            // PDO is in ERRMODE_EXCEPTION, so query() throws rather than returning false and
            // the branch above never runs: every database failure reached the operator as a
            // bare "SQLSTATE[42S21]: Duplicate column name 'tag'" with no hint of which
            // statement, in which table, from which migration. Say what failed.
            throw new \Exception($failed->getMessage() . ' -- while running: ' . $this->describeQuery($query, $params !== []), (int)$failed->getCode(), $failed);
        } finally {
            if ($this->params->get('debug')) {
                $this->addQueryLog($query, $this->getMicroTime() - $start, $params);
            }
        }

        return $result;
    }

    /**
     * Run $work as one atomic unit: commit if it returns, roll back if it throws.
     *
     * Every write that revisions a `pages` row is two statements -- mark the current revision
     * `latest = 'N'`, then INSERT the new one. A failure between them leaves the row with **no
     * `latest = 'Y'` revision at all**, which is not a visibly broken page but an invisible one:
     * every read filters on `latest = 'Y'`, so the Content simply stops existing while all its
     * history is still there. That is the failure this exists to prevent (PageManager::save(),
     * PageManager::setMetadata(), AclService::writeMetadataAcls(), SearchIndexer::index()).
     *
     * Nests. An inner scope joins the outer one rather than starting a second transaction, so a
     * caller does not have to know whether it is the outermost -- see $transactionDepth.
     *
     * Keep DDL out of it. MySQL commits implicitly on CREATE/ALTER/DROP, so a migration that
     * mixed schema changes into a transaction would get a silent partial commit rather than an
     * error; migrations are not wrapped for that reason.
     *
     * What this does NOT undo is anything outside the database: a rolled-back scope has still
     * sent whatever mail its listeners sent and still mutated whatever per-request cache it
     * touched. So cache updates and event dispatch belong *after* the scope, not inside it.
     *
     * @template T
     *
     * @param callable(): T $work
     *
     * @return T
     *
     * @throws \Throwable whatever $work threw, after rolling back
     */
    public function transactional(callable $work): mixed
    {
        $this->beginTransaction();

        try {
            $result = $work();
        } catch (\Throwable $failure) {
            $this->rollBack();

            throw $failure;
        }

        $this->commit();

        return $result;
    }

    /** Open a scope. Prefer transactional(), which cannot forget to close it. */
    public function beginTransaction(): void
    {
        if ($this->transactionDepth === 0) {
            $this->link->beginTransaction();
            $this->transactionRollbackOnly = false;
        }
        $this->transactionDepth++;
    }

    /**
     * Close a scope, committing only when the outermost one closes.
     *
     * @throws \Exception if an inner scope had rolled back -- the work is undone either way, and
     *                    saying so is better than returning as though it had been kept
     */
    public function commit(): void
    {
        if ($this->transactionDepth === 0) {
            throw new \Exception('DbService::commit() called with no transaction open.');
        }

        $this->transactionDepth--;
        if ($this->transactionDepth > 0) {
            // an inner scope: whether this is kept is the outermost scope's decision
            return;
        }

        if ($this->transactionRollbackOnly) {
            $this->transactionRollbackOnly = false;
            $this->link->rollBack();

            throw new \Exception('Transaction rolled back: an inner scope failed and its error was swallowed.');
        }

        $this->link->commit();
    }

    /** Undo a scope. An inner one marks the whole transaction rollback-only. */
    public function rollBack(): void
    {
        if ($this->transactionDepth === 0) {
            // nothing open: a caller unwinding after a failure that happened before the scope
            // started should not itself fail
            return;
        }

        $this->transactionDepth--;
        if ($this->transactionDepth > 0) {
            $this->transactionRollbackOnly = true;

            return;
        }

        $this->link->rollBack();
    }

    /** Whether any scope is open -- for a caller that must not, say, send mail yet. */
    public function inTransaction(): bool
    {
        return $this->transactionDepth > 0;
    }

    /**
     * A statement prepared once, to be executed many times with different values.
     *
     * For loops only -- a one-off query should call query($sql, $params), which prepares and
     * executes in one step. See PreparedStatement for why a loop wants the difference.
     */
    public function prepare(string $query): PreparedStatement
    {
        $statement = $this->link->prepare($query);
        if ($statement === false) {
            $errorInfo = $this->link->errorInfo();

            throw new \Exception('Prepare failed: ' . $query . ' (' . $errorInfo[2] . ')');
        }

        return new PreparedStatement(
            $statement,
            $query,
            function (string $sql, float $elapsed, array $params): void {
                if ($this->params->get('debug')) {
                    $this->addQueryLog($sql, $elapsed, $params);
                }
            }
        );
    }

    /**
     * A failing statement, in a form that is safe to put in front of whoever is looking.
     *
     * Schema statements go in whole -- they are the ones worth reading, and they carry no
     * data. Anything else is cut to its opening clause: an operator needs to know that an
     * UPDATE on `pages` failed, not what was being written into it, and this message reaches
     * the browser of whoever tripped over it. In debug the whole query goes, as it already
     * does in the query log at the foot of every page.
     *
     * A parameterised statement needs none of that care and gets none: its text holds
     * placeholders where the data would be, so there is nothing in it to withhold. That is
     * the second thing bindings buy -- an error message that names the whole query.
     */
    private function describeQuery(string $query, bool $parameterised = false): string
    {
        $query = trim((string)preg_replace('/\s+/', ' ', $query));
        if ($parameterised || $this->params->get('debug') || preg_match('/^\s*(CREATE|ALTER|DROP|TRUNCATE|RENAME)\b/i', $query)) {
            return $query;
        }

        return mb_strlen($query) > 120 ? mb_substr($query, 0, 120) . ' [...]' : $query;
    }

    protected function getMicroTime()
    {
        list($usec, $sec) = explode(' ', microtime());

        return (float)$usec + (float)$sec;
    }

    /**
     * Returns the first result of the query
     * If query fails returns null.
     *
     * $params is optional and behaves as in query(): supplied, the values are bound; omitted,
     * the statement runs exactly as given.
     *
     * @param array<array-key, mixed> $params
     *
     * @return array<string, mixed>|null
     */
    public function loadSingle($query, array $params = []): ?array
    {
        if ($data = $this->loadAll($query, $params)) {
            return $data[0];
        }

        return null;
    }

    /**
     * Fills and returns a table with the results of the query
     * Frees the SQL results set afterwards.
     *
     * @param array<array-key, mixed> $params
     *
     * @return list<array<string, mixed>>
     */
    public function loadAll($query, array $params = []): array
    {
        $stmt = $this->query($query, $params);
        if ($stmt) {
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        }

        return [];
    }

    /**
     * How many ROWS the query returned.
     *
     * Named `countRows` and not `count` because the short name invited exactly the mistake the
     * old docblock had to warn about: handed a `SELECT COUNT(*)` this returns **1**, that query
     * having returned one row -- and 1 is plausible enough to survive review. `scalar()` reads an
     * aggregate. A warning is weaker than a name that cannot be misread.
     *
     * @param array<array-key, mixed> $params
     */
    public function countRows(string $query, array $params = []): int
    {
        $stmt = $this->query($query, $params);
        if ($stmt) {
            return count($stmt->fetchAll(\PDO::FETCH_ASSOC));
        }

        return 0;
    }

    /**
     * The single value of a one-row, one-column query: `SELECT COUNT(*)`, `SELECT MAX(id)`.
     *
     * Added by ticket 18, whose "result counts are exact" claim rests on actually reading the
     * aggregate rather than counting the row it arrives in (see count() above).
     *
     * $params comes last here rather than second, because $default was already there. The rule
     * across all of these is the same read either way: the values are the final argument.
     *
     * @param array<array-key, mixed> $params
     */
    public function scalar(string $query, mixed $default = null, array $params = []): mixed
    {
        $row = $this->loadSingle($query, $params);
        if ($row === null || $row === []) {
            return $default;
        }

        return reset($row);
    }

    /**
     * The database's own shape -- which tables exist, which columns, of what type.
     *
     * Was six methods and 216 lines of `switch ($this->driver)` on this class, which is three
     * jobs too many for the thing that runs statements (see SchemaManager). Constructed lazily
     * and memoised: it needs this service, so injecting it would be a cycle.
     */
    public function schema(): SchemaManager
    {
        return $this->schemaManager ??= new SchemaManager($this);
    }

    /**
     * The database as a replayable SQL dump -- see SqlDumper, which used to be 138 lines of this
     * class and is the archive feature's concern, not the query runner's.
     */
    public function dumper(): SqlDumper
    {
        return $this->sqlDumper ??= new SqlDumper($this);
    }

    public function getDbTimeZone(): ?string
    {
        switch ($this->driver) {
            case 'sqlite':
                // SQLite doesn't have timezone support, use PHP's timezone
                return ini_get('date.timezone') ?? null;

            case 'pgsql':
                $result = $this->loadSingle('SHOW timezone');

                return $result['TimeZone'] ?? (ini_get('date.timezone') ?? null);

            case 'mysql':
            default:
                $query = 'SELECT @@SESSION.time_zone as timezone;';
                $result = $this->loadSingle($query);
                $tz = (!empty($result['timezone']))
                    ? $result['timezone']
                    : null;
                if ($tz === 'SYSTEM') {
                    $tz = ini_get('date.timezone') ?? null;
                }
                if (empty($tz)) {
                    $queryBis = 'SELECT NOW() as time;';
                    $result = $this->loadSingle($queryBis);
                    if (empty($result['time'])) {
                        $tz = null;
                    } else {
                        $diff = (new \DateTime())->diff(new \DateTime($result['time']));
                        // TODO use Carbon
                        $diffInMinutes = ($diff->invert ? -1 : 1) * ($diff->i + 60 * $diff->h);
                        // convert to UTC
                        $diffInMinutes += intval(floor((new \DateTime())->getOffset() / 60));
                        // convert in DateInterval
                        $diff = new \DateInterval('PT0S');
                        $diff->invert = ($diffInMinutes >= 0) ? 0 : 1;
                        $diff->i = abs($diffInMinutes) % 60;
                        $diff->h = (abs($diffInMinutes) - $diff->i) / 60;

                        $tz = $diff->format('%R%H:%I');
                    }
                }

                return $tz;
        }
    }

    // ========================================================================
    // Schema generation helpers for CREATE TABLE statements
    // ========================================================================

    /*
     * get SQL content : backup method ; prefer mysqldump way if available.
     * Note: This method generates MySQL-compatible SQL dumps.
     * For SQLite, consider using file copy instead.
     *
     * @return array ['sql' => string, 'error' => string]
     */
}
