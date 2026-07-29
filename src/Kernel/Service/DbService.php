<?php

namespace YesWiki\Kernel\Service;

use DateInterval;
use DateTime;
use Exception;
use PDO;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Admin\Service\ArchiveService;
use YesWiki\Kernel\Database\SqlDialect;
use YesWiki\Kernel\Database\SqlDialectFactory;

class DbService
{
    protected $params;

    protected $link;
    protected $queryLog;
    protected $driver;
    protected $readCache = [];
    /** Per-driver SQL fragments; see YesWiki\Kernel\Database\SqlDialect. */
    protected SqlDialect $dialect;

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
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            // PDO::connect() (PHP >= 8.4) returns a driver-specific subclass (e.g.
            // Pdo\Sqlite), needed to call createFunction() without a deprecation
            // notice. The minimum supported PHP version (8.3) doesn't have it.
            $this->link = method_exists(PDO::class, 'connect')
                ? PDO::connect($dsn, $username, $password, $options)
                : new \PDO($dsn, $username, $password, $options);
            if (!$this->link) {
                throw new Exception('Not connected to database');
            }

            // Driver-specific initialization
            $this->initDriverSpecific();
        } catch (\Throwable $th) {
            if (in_array(php_sapi_name(), ['cli', 'cli-server', ' phpdbg'], true)) {
                throw new Exception(_t('DB_CONNECT_FAIL') . ': ' . $th->getMessage());
            } else {
                exit(_t('DB_CONNECT_FAIL'));
            }
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
     * @param string $path The JSON path (e.g., '$.fieldname')
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
     * @param string $column The column whose distinct values are aggregated
     * @param string|null $orderBy The column to order values by (defaults to $column)
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
     * @param string $needle The value to search for (should be already escaped/quoted)
     * @param string $haystack The column or expression containing comma-separated values
     * @param bool $not Whether to negate the condition (NOT FIND_IN_SET)
     * @return string SQL expression
     */
    public function findInSet(string $needle, string $haystack, bool $not = false): string
    {
        return $this->dialect->findInSet($needle, $haystack, $not);
    }

    public function getLink()
    {
        return $this->link;
    }

    public function getCollation(): string
    {
        return $this->collation;
    }

    public function getQueryLog()
    {
        return $this->queryLog;
    }

    public function addQueryLog($query, $time)
    {
        $this->queryLog[] = [
            'query' => $query,
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

    /*	Returns a PDOStatement on success, throws Exception on failure.
        For SELECT, SHOW, DESCRIBE or EXPLAIN queries, returns a PDOStatement that can be used to fetch results.
        For other queries (INSERT, UPDATE, DELETE), returns a PDOStatement (use rowCount() for affected rows).
    */
    public function query($query)
    {
        if (!preg_match('/^\s*SELECT\b/i', $query)) {
            $this->readCache = [];
        }

        if ($this->params->get('debug')) {
            $start = $this->getMicroTime();
        }

        try {
            $result = $this->link->query($query);
            if ($result === false) {
                $errorInfo = $this->link->errorInfo();
                throw new Exception('Query failed: ' . $query . ' (' . $errorInfo[2] . ')');
            }
        } finally {
            if ($this->params->get('debug')) {
                $this->addQueryLog($query, $this->getMicroTime() - $start);
            }
        }

        return $result;
    }

    protected function getMicroTime()
    {
        list($usec, $sec) = explode(' ', microtime());

        return (float)$usec + (float)$sec;
    }

    /*
     * Returns the first result of the query
     * If query fails returns null
     */
    public function loadSingle($query): ?array
    {
        if ($data = $this->LoadAll($query)) {
            return $data[0];
        }

        return null;
    }

    /*
     * Fills and returns a table with the results of the query
     * Frees the SQL results set afterwards
     */
    public function loadAll($query): array
    {
        $stmt = $this->query($query);
        if ($stmt) {
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        return [];
    }

    public function count($query): int
    {
        $stmt = $this->query($query);
        if ($stmt) {
            return count($stmt->fetchAll(PDO::FETCH_ASSOC));
        }
        return 0;
    }

    public function columnExists($table, $column): bool
    {
        $tableName = trim($this->prefixTable($table));
        $escapedColumn = $this->escape($column);

        switch ($this->driver) {
            case 'sqlite':
                $result = $this->loadAll("PRAGMA table_info($tableName)");
                foreach ($result as $row) {
                    if (strcasecmp($row['name'], $column) === 0) {
                        return true;
                    }
                }
                return false;

            case 'pgsql':
                $result = $this->loadSingle(
                    "SELECT column_name FROM information_schema.columns " .
                    "WHERE table_name = '$tableName' AND column_name = '$escapedColumn'"
                );
                return !empty($result);

            case 'mysql':
            default:
                return $this->count("SHOW COLUMNS FROM {$this->prefixTable($table)} LIKE '{$escapedColumn}';") > 0;
        }
    }

    public function dropColumn($table, $column)
    {
        if ($this->columnExists($table, $column)) {
            $quotedColumn = $this->quoteIdentifier($this->escape($column));
            $this->query("ALTER TABLE {$this->prefixTable($table)} DROP COLUMN $quotedColumn;");
        }
    }

    /**
     * Returns information about a column (type, nullable, etc.)
     *
     * @param string $table The table name (without prefix)
     * @param string $column The column name
     * @return array|null Column info with 'type', 'nullable', 'default' keys or null if not found
     */
    public function getColumnInfo($table, $column): ?array
    {
        $tableName = trim($this->prefixTable($table));
        $escapedColumn = $this->escape($column);

        switch ($this->driver) {
            case 'sqlite':
                $result = $this->loadAll("PRAGMA table_info($tableName)");
                foreach ($result as $row) {
                    if (strcasecmp($row['name'], $column) === 0) {
                        return [
                            'type' => strtolower($row['type']),
                            'nullable' => $row['notnull'] == 0,
                            'default' => $row['dflt_value'],
                        ];
                    }
                }
                return null;

            case 'pgsql':
                $result = $this->loadSingle(
                    "SELECT data_type, character_maximum_length, is_nullable, column_default " .
                    "FROM information_schema.columns " .
                    "WHERE table_name = '$tableName' AND column_name = '$escapedColumn'"
                );
                if (empty($result)) {
                    return null;
                }
                $type = $result['data_type'];
                if (!empty($result['character_maximum_length'])) {
                    $type .= '(' . $result['character_maximum_length'] . ')';
                }
                return [
                    'type' => strtolower($type),
                    'nullable' => $result['is_nullable'] === 'YES',
                    'default' => $result['column_default'],
                ];

            case 'mysql':
            default:
                $result = $this->loadSingle("SHOW COLUMNS FROM {$this->prefixTable($table)} LIKE '{$escapedColumn}';");
                if (empty($result)) {
                    return null;
                }
                return [
                    'type' => strtolower($result['Type']),
                    'nullable' => $result['Null'] === 'YES',
                    'default' => $result['Default'],
                ];
        }
    }

    /**
     * Modifies a column type.
     * Note: SQLite has limited ALTER TABLE support. For SQLite, this method may need
     * to recreate the table to change column types.
     *
     * @param string $table The table name (without prefix)
     * @param string $column The column name
     * @param string $newType The new column type (e.g., 'varchar(256)')
     * @param bool $notNull Whether the column should be NOT NULL
     * @return bool Success
     */
    public function modifyColumn($table, $column, $newType, $notNull = false): bool
    {
        $quotedColumn = $this->quoteIdentifier($this->escape($column));
        $notNullClause = $notNull ? ' NOT NULL' : '';

        switch ($this->driver) {
            case 'sqlite':
                // SQLite doesn't support ALTER COLUMN, would need table recreation
                // For now, we'll skip this for SQLite as it's complex
                // The column type in SQLite is mostly advisory anyway
                return true;

            case 'pgsql':
                $this->query(
                    "ALTER TABLE {$this->prefixTable($table)} " .
                    "ALTER COLUMN $quotedColumn TYPE $newType"
                );
                if ($notNull) {
                    $this->query(
                        "ALTER TABLE {$this->prefixTable($table)} " .
                        "ALTER COLUMN $quotedColumn SET NOT NULL"
                    );
                }
                return true;

            case 'mysql':
            default:
                $this->query(
                    "ALTER TABLE {$this->prefixTable($table)} " .
                    "MODIFY COLUMN $quotedColumn $newType$notNullClause;"
                );
                return true;
        }
    }

    /**
     * Returns a list of all tables in the database.
     *
     * @return array List of table names
     */
    public function getTables(): array
    {
        switch ($this->driver) {
            case 'sqlite':
                $result = $this->loadAll("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'");
                return array_column($result, 'name');

            case 'pgsql':
                $result = $this->loadAll("SELECT tablename FROM pg_tables WHERE schemaname = 'public'");
                return array_column($result, 'tablename');

            case 'mysql':
            default:
                $result = $this->loadAll('SHOW TABLES');
                return array_map(function ($row) {
                    return array_values($row)[0];
                }, $result);
        }
    }

    /**
     * Returns the CREATE TABLE statement for a table.
     * Note: Only fully supported for MySQL. SQLite returns the original schema.
     * PostgreSQL support is limited.
     *
     * @param string $tableName The table name
     * @return string|null The CREATE TABLE statement or null if not supported
     */
    public function getTableSchema(string $tableName): ?string
    {
        switch ($this->driver) {
            case 'sqlite':
                $result = $this->loadSingle(
                    "SELECT sql FROM sqlite_master WHERE type='table' AND name='$tableName'"
                );
                return $result['sql'] ?? null;

            case 'pgsql':
                // PostgreSQL doesn't have a simple SHOW CREATE TABLE equivalent
                // Return null to indicate this feature is not supported
                return null;

            case 'mysql':
            default:
                $result = $this->loadSingle("SHOW CREATE TABLE $tableName");
                return $result['Create Table'] ?? null;
        }
    }

    public function getDbTimeZone(): ?string
    {
        switch ($this->driver) {
            case 'sqlite':
                // SQLite doesn't have timezone support, use PHP's timezone
                return ini_get('date.timezone') ?? null;

            case 'pgsql':
                $result = $this->loadSingle("SHOW timezone");
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
                        $diff = (new DateTime())->diff(new DateTime($result['time']));
                        // TODO use Carbon
                        $diffInMinutes = ($diff->invert ? -1 : 1) * ($diff->i + 60 * $diff->h);
                        // convert to UTC
                        $diffInMinutes += intval(floor((new DateTime())->getOffset() / 60));
                        // convert in DateInterval
                        $diff = new DateInterval('PT0S');
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

    /**
     * get SQL content : backup method ; prefer mysqldump way if available.
     * Note: This method generates MySQL-compatible SQL dumps.
     * For SQLite, consider using file copy instead.
     *
     * @return array ['sql' => string, 'error' => string]
     */
    public function getSQLContentBackupMethod(): array
    {
        $sql = '';
        $error = '';
        try {
            $tablesPrefix = trim($this->prefixTable(''));
            if (empty($tablesPrefix)) {
                throw new \Exception("'table_prefix' is empty in wakka.config.php — cannot determine which tables to back up");
            }
            $tablesPostfix = [];
            // get Tables using the driver-agnostic method
            $tables = $this->getTables();

            foreach ($tables as $tableName) {
                if (strpos($tableName, $tablesPrefix) === 0) {
                    $tablesPostfix[] = $tableName;
                }
            }

            // generate file
            $date = (new \DateTime())->format('c');
            $phpVersion = phpversion();

            $sql =
                <<<SQL
            -- SQL Dump
            -- ArchiveService:getSQLBackup Version
            -- 
            -- Generated on : $date
            -- PHP version : $phpVersion

            SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
            SET AUTOCOMMIT = 0;
            START TRANSACTION;

            /*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
            /*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
            /*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
            /*!40101 SET NAMES utf8mb4 */;
            /*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
            /*!40103 SET TIME_ZONE='+00:00' */;
            
            -- --------------------------------------------------------

            SQL;

            // For each table
            foreach ($tablesPostfix as $tableName) {
                // DUMP CREATE TABLE

                // HEADER
                $sql .=
                    <<<SQL

                -- 
                -- Structure of table : `$tableName`
                -- 

                SQL;
                // END HEADER

                $tableSchema = $this->getTableSchema($tableName);
                if ($tableSchema) {
                    $sql .= $tableSchema . ";\n\n";
                }

                // DUMP DATA

                //    HEADER
                $sql .=
                    <<<SQL

                -- 
                -- Data of table : `$tableName`
                -- 

                SQL;
                // END HEADER

                $rawData = $this->query('select * from ' . $tableName);

                // Types that need quotes in SQL
                $stringTypes = ['VAR_STRING', 'STRING', 'BLOB', 'DATE', 'TIME', 'DATETIME', 'TIMESTAMP', 'YEAR', 'NEWDATE'];

                $firstRow = true;
                $columnCount = $rawData->columnCount();
                $columnMeta = [];
                for ($i = 0; $i < $columnCount; $i++) {
                    $columnMeta[$i] = $rawData->getColumnMeta($i);
                }

                while ($row = $rawData->fetch(PDO::FETCH_NUM)) {
                    if ($firstRow) {
                        $sql .= "INSERT INTO `$tableName` ";
                        $sql .= '(';
                        for ($i = 0; $i < $columnCount; $i++) {
                            if ($i != 0) {
                                $sql .= ', ';
                            }
                            $sql .= '`' . $columnMeta[$i]['name'] . '`';
                        }
                        $sql .= ") VALUES\n";
                        $firstRow = false;
                    } else {
                        $sql .= ",\n";
                    }
                    $sql .= '(';
                    for ($i = 0; $i < $columnCount; $i++) {
                        if ($i != 0) {
                            $sql .= ', ';
                        }
                        $strAdd = '';
                        $nativeType = $columnMeta[$i]['native_type'] ?? '';
                        if (in_array($nativeType, $stringTypes)) {
                            $strAdd = "'";
                        }
                        $sql .= $strAdd . $this->escape($row[$i] ?? '') . $strAdd;
                    }
                    $sql .= ')';
                }
                if (!$firstRow) {
                    $sql .= ";\n";
                }
                $sql .=
                    <<<SQL

                -- --------------------------------------------------------

                SQL;
            }

            $sql .=
                <<<SQL

            COMMIT;
            
            /*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;
            /*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
            /*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
            /*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

            SQL;
        } catch (\Throwable $th) {
            $error = $th->getMessage();
        }

        return compact(['sql', 'error']);
    }
}
