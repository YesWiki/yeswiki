<?php

namespace YesWiki\Kernel\Service;

use Exception;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Kernel\Database\DumpRewriter;
use YesWiki\Kernel\Database\PreparedStatement;
use YesWiki\Kernel\Database\SchemaManager;
use YesWiki\Kernel\Database\SqlDialect;
use YesWiki\Kernel\Database\SqlDialectFactory;
use YesWiki\Kernel\Database\SqlDumper;
use YesWiki\Kernel\Database\SqlParameters;
use YesWiki\Kernel\Database\SqlStatementSplitter;

class DbService
{
    protected ParameterBagInterface $params;

    /** @var \PDO */
    protected $link;
    /** @var list<array{query: string, time: float}> */
    protected $queryLog;
    /** @var string */
    protected $driver;
    /** Per-driver SQL fragments; see YesWiki\Kernel\Database\SqlDialect. */
    protected SqlDialect $dialect;

    /** How many nested `transactional()` scopes are open; only the outermost one is real. */
    private int $transactionDepth = 0;

    /** Set when an inner scope rolled back, so the outermost commit refuses. */
    private bool $transactionRollbackOnly = false;

    /** Lazily built by schema(); see there for why it is not injected. */
    private ?SchemaManager $schemaManager = null;

    /** Lazily built by dumper(). */
    private ?SqlDumper $sqlDumper = null;

    public function __construct(ParameterBagInterface $params)
    {
        $this->params = $params;
        $this->queryLog = [];
        $driver = $this->params->has('db_driver') ? $this->params->get('db_driver') : null;
        $this->driver = (is_string($driver) && $driver !== '') ? $driver : 'mysql';
        $this->dialect = SqlDialectFactory::forDriver($this->driver);

        $this->initSqlConnection();
    }

    protected function initSqlConnection(): void
    {
        try {
            $dsn = $this->buildDsn();
            $username = null;
            $password = null;

            if ($this->driver !== 'sqlite') {
                $username = $this->stringParam('db_user');
                $password = $this->stringParam('db_password');
            }

            $options = [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                \PDO::ATTR_EMULATE_PREPARES => false,
            ];
            $this->link = method_exists(\PDO::class, 'connect')
                ? \PDO::connect($dsn, $username, $password, $options)
                : new \PDO($dsn, $username, $password, $options);
            if (!$this->link) {
                throw new \Exception('Not connected to database');
            }

            $this->initDriverSpecific();
        } catch (\Throwable $th) {
            // The one error the Journal can never record, since the Journal is a table in the
            // database that just refused. Written straight to the stream instead, which is the
            // half of ADR-0025 that exists for exactly this case.
            Journal::toStderr($this->stringParam('base_url'), [
                'channel' => 'diagnostic',
                'level' => 'critical',
                'action' => 'database.unreachable',
                'message' => $th->getMessage(),
            ]);

            if (in_array(php_sapi_name(), ['cli', 'cli-server', ' phpdbg'], true)) {
                throw new \Exception(_t('DB_CONNECT_FAIL') . ': ' . $th->getMessage());
            }
            exit(_t('DB_CONNECT_FAIL'));
        }
    }

    /**
     * A database path as the Instance's own, the way Storage anchors every other path.
     *
     * SQLite resolves a relative path against the process working directory, which is shared by
     * every wiki a farm serves from one process (single-binary 08).
     */
    private function inInstance(string $dbPath): string
    {
        if ($dbPath === '' || $dbPath === ':memory:' || str_starts_with($dbPath, '/')) {
            return $dbPath;
        }

        if (!\defined('YESWIKI_INSTANCE_DIR')) {
            return $dbPath;
        }

        return YESWIKI_INSTANCE_DIR . '/' . $dbPath;
    }

    protected function buildDsn(): string
    {
        switch ($this->driver) {
            case 'sqlite':
                $dbPath = $this->params->has('db_database') && $this->params->get('db_database')
                    ? $this->stringParam('db_database')
                    : 'private/yeswiki.db';

                return 'sqlite:' . $this->inInstance($dbPath);

            case 'pgsql':
                $dsn = 'pgsql:host=' . $this->stringParam('db_host') . ';dbname=' . $this->stringParam('db_database');
                if ($this->params->has('db_port') && $this->params->get('db_port')) {
                    $dsn .= ';port=' . $this->stringParam('db_port');
                }

                return $dsn;

            case 'mysql':
            default:
                $dsn = 'mysql:host=' . $this->stringParam('db_host') . ';dbname=' . $this->stringParam('db_database');
                if ($this->params->has('db_port') && $this->params->get('db_port')) {
                    $dsn .= ';port=' . $this->stringParam('db_port');
                }
                $charset = ($this->params->has('db_charset') && $this->params->get('db_charset'))
                    ? $this->stringParam('db_charset')
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
                $this->link->exec('PRAGMA foreign_keys = ON');
                $this->link->exec('PRAGMA journal_mode = WAL');
                $this->link->exec('PRAGMA busy_timeout = 5000');
                $regexp = function ($pattern, $value) {
                    if ($pattern === null || $value === null) {
                        return false;
                    }

                    return preg_match('/' . $pattern . '/iu', $value) === 1;
                };
                if (method_exists($this->link, 'createFunction')) {
                    $this->link->createFunction('REGEXP', $regexp, 2);
                } else {
                    $this->link->sqliteCreateFunction('REGEXP', $regexp, 2);
                }
                break;

            case 'pgsql':
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
     * Replay a dump beside this wiki's tables and put it in their place only once it is all there.
     *
     * @throws \Exception when the dump cannot be read, or fails before the swap
     */
    public function restoreStagedFromDump(string $sqlContent): void
    {
        $this->restoreStagedFromStream(static function () use ($sqlContent) {
            $handle = fopen('php://temp', 'w+b');
            if ($handle === false) {
                throw new \Exception('Cannot open a buffer for the dump');
            }
            fwrite($handle, $sqlContent);
            rewind($handle);

            return $handle;
        });
    }

    /**
     * The same, reading the dump from a stream twice: once to learn its tables, once to replay it.
     *
     * @param callable(): resource            $open    a fresh stream on the dump, at its start, each time it is called
     * @param (callable(string): string)|null $rewrite applied to each statement before it is replayed
     *
     * @throws \Exception when the dump cannot be read, or fails before the swap
     */
    public function restoreStagedFromStream(callable $open, ?callable $rewrite = null): void
    {
        $livePrefix = trim($this->prefixTable(''));
        if ($livePrefix === '') {
            throw new \Exception('Table prefix is empty — refusing to restore');
        }

        $handle = $open();
        try {
            $this->assertDumpMatchesDriver((string)fread($handle, 8192));
        } finally {
            fclose($handle);
        }

        $tables = [];
        $objects = [];
        $count = 0;
        $handle = $open();
        try {
            foreach (SqlStatementSplitter::fromStream($handle) as $statement) {
                $count++;
                $statement = $this->normalizeDumpStatement($statement);
                foreach (DumpRewriter::tables($statement) as $table) {
                    $tables[$table] = true;
                }
                foreach (DumpRewriter::objects($statement) as $object) {
                    $objects[$object] = true;
                }
                if ($this->driver === 'pgsql') {
                    foreach (DumpRewriter::constraints($statement) as $object) {
                        $objects[$object] = true;
                    }
                }
            }
        } finally {
            fclose($handle);
        }
        if ($count === 0) {
            throw new \Exception('SQL restore failed: the dump contains no statements');
        }

        $tables = array_keys($tables);
        $sourcePrefix = DumpRewriter::prefixOf($tables);
        if ($sourcePrefix === '') {
            throw new \Exception('SQL restore failed: no table in the dump names a prefix, so it is not a wiki backup');
        }
        $own = DumpRewriter::ownTables($tables, $sourcePrefix);
        $foreign = array_fill_keys(array_diff($tables, $own), true);

        $staging = $this->isolatedPrefix($livePrefix, 'staging');
        $replaced = $this->isolatedPrefix($livePrefix, 'replaced');
        $renames = DumpRewriter::renames($own, $sourcePrefix, $staging);
        if ($renames === []) {
            throw new \Exception("SQL restore failed: no table in the dump starts with '$sourcePrefix'");
        }
        $others = DumpRewriter::otherWikiPrefixes($tables, $sourcePrefix);
        $ownObjects = array_filter(array_keys($objects), static function (string $object) use ($others): bool {
            foreach ($others as $other) {
                if (str_starts_with($object, $other)) {
                    return false;
                }
            }

            return true;
        });
        $renames += DumpRewriter::renames(array_values($ownObjects), $sourcePrefix, $staging);

        $this->dropTablesWithPrefix($staging);
        $this->dropTablesWithPrefix($replaced);
        $this->renameStagedObjects($staging, $livePrefix);

        $session = $this->sessionSettings();
        $disable = $this->dialect->foreignKeyChecks(false);
        if ($disable !== null) {
            $this->query($disable);
        }

        try {
            $handle = $open();
            try {
                $index = 0;
                foreach (SqlStatementSplitter::fromStream($handle) as $statement) {
                    $index++;
                    $statement = $this->normalizeDumpStatement($statement);
                    if (DumpRewriter::concerns($statement, $foreign)) {
                        continue;
                    }
                    if ($rewrite !== null) {
                        $statement = $rewrite($statement);
                    }
                    try {
                        $this->query(DumpRewriter::rewrite($statement, $renames));
                    } catch (\Throwable $th) {
                        $excerpt = substr((string)preg_replace('/\s+/', ' ', $statement), 0, 200);

                        throw new \Exception('SQL restore failed on statement ' . $index . ' of ' . $count . ' (' . $excerpt . '): ' . $th->getMessage(), 0, $th);
                    }
                }
            } finally {
                fclose($handle);
            }

            $this->swapTables($livePrefix, $staging, $replaced);
        } catch (\Throwable $th) {
            if ($this->driver === 'pgsql' && $this->transactionDepth === 0) {
                $this->link->exec('ROLLBACK');
            }
            $this->dropTablesWithPrefix($staging);

            throw $th;
        } finally {
            $enable = $this->dialect->foreignKeyChecks(true);
            if ($enable !== null) {
                $this->query($enable);
            }
            $this->restoreSessionSettings($session);
        }

        $this->dropTablesWithPrefix($replaced);
        $this->renameStagedObjects($staging, $livePrefix);
    }

    /**
     * The MySQL session settings a dump's preamble changes, to put back once it is replayed.
     *
     * @return array<string, mixed>|null
     */
    private function sessionSettings(): ?array
    {
        if ($this->driver !== 'mysql') {
            return null;
        }

        return $this->loadSingle('SELECT @@SESSION.sql_mode AS sql_mode, @@SESSION.time_zone AS time_zone, @@SESSION.autocommit AS autocommit');
    }

    /** @param array<string, mixed>|null $session */
    private function restoreSessionSettings(?array $session): void
    {
        if ($session === null) {
            return;
        }
        $this->query('SET SESSION sql_mode = ' . $this->link->quote((string)$session['sql_mode'])
            . ', time_zone = ' . $this->link->quote((string)$session['time_zone'])
            . ', autocommit = ' . ((int)$session['autocommit'] === 0 ? '0' : '1'));
    }

    /** A dump statement as the restore reads it: index names quoted, so that they can be renamed like the tables. */
    private function normalizeDumpStatement(string $statement): string
    {
        return $this->driver === 'mysql' ? $statement : DumpRewriter::quoteIndexNames($statement);
    }

    /** Give the objects whose names the whole database shares the live prefix, once no staged table holds them. */
    private function renameStagedObjects(string $staging, string $livePrefix): void
    {
        if ($this->driver === 'sqlite') {
            $this->renameSqliteObjects($staging, $livePrefix);
        } elseif ($this->driver === 'pgsql') {
            $this->renamePostgreSqlObjects($staging, $livePrefix);
        }
    }

    /** The same on PostgreSQL: key constraints with the indexes behind them, the other indexes, and the sequences behind identity columns. */
    private function renamePostgreSqlObjects(string $staging, string $livePrefix): void
    {
        $live = static fn (string $name): string => $livePrefix . substr($name, \strlen($staging));
        $q = fn (string $name): string => $this->dialect->quoteIdentifier($name);
        $schema = ' JOIN pg_namespace n ON n.oid = c.relnamespace WHERE n.nspname = current_schema()';

        foreach ($this->loadAll(
            'SELECT k.conname, c.relname FROM pg_constraint k JOIN pg_class c ON c.oid = k.conrelid' . $schema . ' AND left(k.conname, ?) = ?',
            [\strlen($staging), $staging]
        ) as $row) {
            $this->query('ALTER TABLE ' . $q((string)$row['relname']) . ' RENAME CONSTRAINT ' . $q((string)$row['conname']) . ' TO ' . $q($live((string)$row['conname'])));
        }
        foreach ($this->loadAll(
            'SELECT c.relname, c.relkind::text AS kind FROM pg_class c' . $schema . " AND c.relkind IN ('i', 'S') AND left(c.relname, ?) = ?",
            [\strlen($staging), $staging]
        ) as $row) {
            $this->query('ALTER ' . ((string)$row['kind'] === 'S' ? 'SEQUENCE ' : 'INDEX ') . $q((string)$row['relname']) . ' RENAME TO ' . $q($live((string)$row['relname'])));
        }
    }

    /** Give the indexes and triggers a SQLite restore created under the staging prefix the live one, once the replaced tables have taken theirs away. */
    private function renameSqliteObjects(string $staging, string $livePrefix): void
    {
        if ($this->driver !== 'sqlite') {
            return;
        }
        foreach ($this->loadAll("SELECT type, name, sql FROM sqlite_master WHERE type IN ('index', 'trigger') AND sql IS NOT NULL") as $object) {
            $name = (string)$object['name'];
            if (!str_starts_with($name, $staging)) {
                continue;
            }
            $this->query('DROP ' . strtoupper((string)$object['type']) . ' IF EXISTS ' . $this->dialect->quoteIdentifier($name));
            $this->query(DumpRewriter::rewrite((string)$object['sql'], [$name => $livePrefix . substr($name, \strlen($staging))]));
        }
    }

    /**
     * The tables SQLite keeps behind a full-text table, which go wherever that table goes and are never named on their own.
     *
     * @param list<string> $tables
     *
     * @return list<string> $tables without them
     */
    private function withoutShadowTables(array $tables): array
    {
        if ($this->driver !== 'sqlite') {
            return $tables;
        }
        $virtual = array_map(
            static fn (array $row): string => (string)$row['name'],
            $this->loadAll("SELECT name FROM sqlite_master WHERE type = 'table' AND sql LIKE 'CREATE VIRTUAL TABLE%'")
        );

        return array_values(array_filter($tables, static function (string $table) use ($virtual): bool {
            foreach ($virtual as $name) {
                if (str_starts_with($table, $name . '_')) {
                    return false;
                }
            }

            return true;
        }));
    }

    /**
     * Put the imported tables in place of the wiki's own, all at once.
     *
     * @throws \Exception when the dump created no table
     */
    private function swapTables(string $livePrefix, string $staging, string $replaced): void
    {
        $staged = $this->withoutShadowTables($this->tablesWithPrefix($staging));
        if ($staged === []) {
            throw new \Exception('The backup created no table.');
        }

        $renames = [];
        foreach ($this->withoutShadowTables($this->tablesWithPrefix($livePrefix)) as $table) {
            $renames[$table] = $replaced . substr($table, \strlen($livePrefix));
        }
        foreach ($staged as $table) {
            $renames[$table] = $livePrefix . substr($table, \strlen($staging));
        }

        $statements = $this->dialect->renameTables($renames);
        if (\count($statements) > 1) {
            $this->transactional(function () use ($statements): void {
                foreach ($statements as $statement) {
                    $this->query($statement);
                }
            });

            return;
        }

        foreach ($statements as $statement) {
            $this->query($statement);
        }
    }

    /** A prefix of its own, that no prefix-wide operation can confuse with the wiki's own tables. */
    private function isolatedPrefix(string $livePrefix, string $tag): string
    {
        $prefix = 'yw' . $tag . substr(sha1($livePrefix), 0, 6) . '_';
        while (str_starts_with($prefix, $livePrefix) || str_starts_with($livePrefix, $prefix)) {
            $prefix = "x$prefix";
        }

        return $prefix;
    }

    /**
     * This wiki's tables under a prefix, leaving out those of another wiki whose prefix starts the same.
     *
     * @return list<string>
     */
    private function tablesWithPrefix(string $prefix): array
    {
        return DumpRewriter::ownTables($this->schema()->getTables(), $prefix);
    }

    private function dropTablesWithPrefix(string $prefix): void
    {
        foreach ($this->withoutShadowTables($this->tablesWithPrefix($prefix)) as $table) {
            $this->query('DROP TABLE IF EXISTS ' . $this->dialect->quoteIdentifier($table));
        }
        foreach ($this->tablesWithPrefix($prefix) as $table) {
            $this->query('DROP TABLE IF EXISTS ' . $this->dialect->quoteIdentifier($table));
        }
    }

    /**
     * Drop this wiki's tables and replay $sqlContent into them.
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

        $tables = DumpRewriter::tables($sqlContent);
        $foreign = array_fill_keys(array_diff($tables, DumpRewriter::ownTables($tables, DumpRewriter::prefixOf($tables))), true);

        $session = $this->sessionSettings();
        $disable = $this->dialect->foreignKeyChecks(false);
        if ($disable !== null) {
            $this->query($disable);
        }

        try {
            foreach ($this->tablesWithPrefix($tablesPrefix) as $tableName) {
                $this->query('DROP TABLE IF EXISTS ' . $this->dialect->quoteIdentifier($tableName));
            }

            foreach ($statements as $index => $statement) {
                if (DumpRewriter::concerns($statement, $foreign)) {
                    continue;
                }
                try {
                    $this->query($statement);
                } catch (\Throwable $th) {
                    $excerpt = substr((string)preg_replace('/\s+/', ' ', $statement), 0, 200);

                    throw new \Exception('SQL restore failed on statement ' . ($index + 1) . ' of ' . count($statements) . ' (' . $excerpt . '): ' . $th->getMessage(), 0, $th);
                }
            }
        } finally {
            $enable = $this->dialect->foreignKeyChecks(true);
            if ($enable !== null) {
                $this->query($enable);
            }
            $this->restoreSessionSettings($session);
        }
    }

    /**
     * Refuse a dump produced by a different database driver.
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

    /** The column type a JSON document is declared as on this driver (ADR-0018). */
    public function jsonColumnType(): string
    {
        return $this->dialect->jsonColumnType();
    }

    /**
     * SQL expression extracting a value from a column declared as `jsonColumnType()`.
     *
     * @param string $column The column, declared as jsonColumnType()
     * @param string $path   The JSON path (e.g., '$.fieldname'), passed RAW: the dialect owns the escaping, because only it knows what syntax the path lands in
     *
     * @return string SQL expression
     */
    public function jsonExtract(string $column, string $path): string
    {
        return $this->dialect->jsonExtract($column, $path);
    }

    /** A JSON column as text, for the string operators (`LIKE`) that have no JSON equivalent. */
    public function jsonAsText(string $column): string
    {
        return $this->dialect->jsonAsText($column);
    }

    /** The same read, for JSON stored in a column that is not declared as JSON. */
    public function jsonExtractText(string $column, string $path): string
    {
        return $this->dialect->jsonExtractText($column, $path);
    }

    /** An expression read as a number, so an ORDER BY on it sorts 9 before 10. */
    public function castToInteger(string $expression): string
    {
        return $this->dialect->castToInteger($expression);
    }

    /**
     * Returns a SQL expression aggregating the distinct values of a column into a single comma-separated string, ordered by $orderBy.
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
     *
     * @return string SQL collation clause (empty string for drivers that don't need it)
     */
    public function collateClause(): string
    {
        return $this->dialect->collateClause();
    }

    /**
     * Returns the REGEXP operator for the current database driver.
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

    /**
     * @return list<array{query: string, time: float}>
     */
    public function getQueryLog()
    {
        return $this->queryLog;
    }

    /**
     * Record a query for the debug footer.
     *
     * @param string                  $query
     * @param float                   $time
     * @param array<array-key, mixed> $params
     */
    public function addQueryLog($query, $time, array $params = []): void
    {
        $this->queryLog[] = [
            'query' => SqlParameters::interpolateForDisplay((string)$query, $params),
            'time' => $time,
        ];
    }

    /**
     * @param string $tableName
     *
     * @return string
     */
    public function prefixTable($tableName)
    {
        return ' ' . $this->stringParam('table_prefix') . $tableName . ' ';
    }

    /** A connection setting the wiki always stores as text, read as text. */
    private function stringParam(string $name): string
    {
        $value = $this->params->get($name);

        return is_scalar($value) ? (string)$value : '';
    }

    /**
     * @param mixed $string anything the caller has, cast to text below
     *
     * @return string
     */
    public function escape($string)
    {
        $quoted = $this->link->quote((string)$string);

        return substr($quoted, 1, -1);
    }

    /**
     * Returns a PDOStatement on success, throws Exception on failure.
     *
     * @param string                  $query
     * @param array<array-key, mixed> $params
     *
     * @return \PDOStatement
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
     * @template T
     *
     * @param callable(): T $work
     *
     * @return T
     *
     * @throws \Throwable whatever $work threw, after rolling back
     */
    public function transactional(callable $work, int $attempts = 1): mixed
    {
        // An inner scope cannot retry: a deadlock takes the whole transaction down with it, so
        // the outermost scope is the only one that still has anything to replay.
        $attempts = $this->transactionDepth > 0 ? 1 : max(1, $attempts);

        for ($attempt = 1;; $attempt++) {
            $this->beginTransaction();

            try {
                $result = $work();
            } catch (\Throwable $failure) {
                $this->rollBack();

                if ($attempt >= $attempts || !self::isRetryableConflict($failure)) {
                    throw $failure;
                }

                continue;
            }

            $this->commit();

            return $result;
        }
    }

    /**
     * Whether the engine is telling us to run the whole transaction again.
     *
     * A deadlock is not a bug in the statement: two transactions wanted the same rows in an order
     * the engine could not satisfy, so it picked one and undid it. It has already rolled that one
     * back, which is what makes replaying safe -- and MySQL says so in as many words ("try
     * restarting transaction"). `40001` is the SQL-standard serialization failure, which MySQL
     * raises for 1213 and PostgreSQL for its own; `40P01` is PostgreSQL's separate deadlock code.
     */
    private static function isRetryableConflict(\Throwable $failure): bool
    {
        for ($cause = $failure; $cause !== null; $cause = $cause->getPrevious()) {
            if ($cause instanceof \PDOException && in_array((string)$cause->getCode(), ['40001', '40P01'], true)) {
                return true;
            }
        }

        return false;
    }

    /** Open a scope. */
    public function beginTransaction(): void
    {
        if ($this->transactionDepth === 0) {
            if ($this->driver === 'sqlite') {
                $this->link->exec('BEGIN IMMEDIATE');
            } else {
                $this->link->beginTransaction();
            }
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
            return;
        }

        if ($this->transactionRollbackOnly) {
            $this->transactionRollbackOnly = false;
            $this->endTransaction('ROLLBACK');

            throw new \Exception('Transaction rolled back: an inner scope failed and its error was swallowed.');
        }

        $this->endTransaction('COMMIT');
    }

    /** Undo a scope. */
    public function rollBack(): void
    {
        if ($this->transactionDepth === 0) {
            return;
        }

        $this->transactionDepth--;
        if ($this->transactionDepth > 0) {
            $this->transactionRollbackOnly = true;

            return;
        }

        $this->endTransaction('ROLLBACK');
    }

    /** Commit or roll back the outermost scope, the way it was opened. */
    private function endTransaction(string $verb): void
    {
        if ($this->driver === 'sqlite') {
            $this->link->exec($verb);
        } elseif ($verb === 'COMMIT') {
            $this->link->commit();
        } else {
            $this->link->rollBack();
        }
    }

    /** Whether any scope is open -- for a caller that must not, say, send mail yet. */
    public function inTransaction(): bool
    {
        return $this->transactionDepth > 0;
    }

    /** A statement prepared once, to be executed many times with different values. */
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

    /** A failing statement, in a form that is safe to put in front of whoever is looking. */
    private function describeQuery(string $query, bool $parameterised = false): string
    {
        $query = trim((string)preg_replace('/\s+/', ' ', $query));
        if ($parameterised || $this->params->get('debug') || preg_match('/^\s*(CREATE|ALTER|DROP|TRUNCATE|RENAME)\b/i', $query)) {
            return $query;
        }

        return mb_strlen($query) > 120 ? mb_substr($query, 0, 120) . ' [...]' : $query;
    }

    /**
     * @return float
     */
    protected function getMicroTime()
    {
        list($usec, $sec) = explode(' ', microtime());

        return (float)$usec + (float)$sec;
    }

    /**
     * Returns the first result of the query If query fails returns null.
     *
     * @param array<array-key, mixed> $params
     * @param string                  $query
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
     * @param string                  $query
     * @param array<array-key, mixed> $params
     *
     * @return list<array<string, mixed>>
     */
    public function loadAll($query, array $params = []): array
    {
        return array_values($this->query($query, $params)->fetchAll(\PDO::FETCH_ASSOC));
    }

    /**
     * How many ROWS the query returned.
     *
     * @param array<array-key, mixed> $params
     */
    public function countRows(string $query, array $params = []): int
    {
        return count($this->query($query, $params)->fetchAll(\PDO::FETCH_ASSOC));
    }

    /**
     * The single value of a one-row, one-column query: `SELECT COUNT(*)`, `SELECT MAX(id)`.
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

    /** The database's own shape -- which tables exist, which columns, of what type. */
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
                return ini_get('date.timezone') ?: null;

            case 'pgsql':
                $result = $this->loadSingle('SHOW timezone');

                return $result['TimeZone'] ?? (ini_get('date.timezone') ?: null);

            case 'mysql':
            default:
                $query = 'SELECT @@SESSION.time_zone as timezone;';
                $result = $this->loadSingle($query);
                $tz = (!empty($result['timezone']))
                    ? $result['timezone']
                    : null;
                if ($tz === 'SYSTEM') {
                    $tz = ini_get('date.timezone') ?: null;
                }
                if (empty($tz)) {
                    $queryBis = 'SELECT NOW() as time;';
                    $result = $this->loadSingle($queryBis);
                    if (empty($result['time'])) {
                        $tz = null;
                    } else {
                        $diff = (new \DateTime())->diff(new \DateTime($result['time']));
                        $diffInMinutes = ($diff->invert ? -1 : 1) * ($diff->i + 60 * $diff->h);
                        $diffInMinutes += intval(floor((new \DateTime())->getOffset() / 60));
                        $diff = new \DateInterval('PT0S');
                        $diff->invert = ($diffInMinutes >= 0) ? 0 : 1;
                        $diff->i = abs($diffInMinutes) % 60;
                        $diff->h = intdiv(abs($diffInMinutes) - $diff->i, 60);

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
