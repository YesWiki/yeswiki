<?php

namespace YesWiki\Core\Service;

use DateInterval;
use DateTime;
use Exception;
use PDO;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Throwable;

class DbService
{
    protected $params;

    protected $link;
    protected $queryLog;
    protected $driver;

    public function __construct(ParameterBagInterface $params)
    {
        $this->params = $params;
        $this->queryLog = [];
        $this->driver = $this->params->has('db_driver') ? $this->params->get('db_driver') : 'mysql';

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

            $this->link = new \PDO(
                $dsn,
                $username,
                $password,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );
            if (!$this->link) {
                throw new Exception('Not connected to database');
            }

            // Driver-specific initialization
            $this->initDriverSpecific();
        } catch (Throwable $th) {
            if (in_array(php_sapi_name(), ['cli', 'cli-server', ' phpdbg'], true)) {
                throw new Exception(_t('DB_CONNECT_FAIL') . ': ' . $th->getMessage());
            } else {
                exit(_t('DB_CONNECT_FAIL'));
            }
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
     * Returns a SQL expression for "current timestamp minus X days"
     * This is database-driver agnostic.
     *
     * @param int $days Number of days to subtract
     * @return string SQL expression
     */
    public function dateSubDays(int $days): string
    {
        switch ($this->driver) {
            case 'sqlite':
                return "datetime('now', '-" . intval($days) . " days')";
            case 'pgsql':
                return "NOW() - INTERVAL '" . intval($days) . " days'";
            case 'mysql':
            default:
                return "DATE_SUB(NOW(), INTERVAL " . intval($days) . " DAY)";
        }
    }

    public function getLink()
    {
        return $this->link;
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
        // PDO::quote adds quotes around the string, so we strip them
        $quoted = $this->link->quote($string);
        return substr($quoted, 1, -1);
    }

    /*	Returns a PDOStatement on success, throws Exception on failure.
        For SELECT, SHOW, DESCRIBE or EXPLAIN queries, returns a PDOStatement that can be used to fetch results.
        For other queries (INSERT, UPDATE, DELETE), returns a PDOStatement (use rowCount() for affected rows).
    */
    public function query($query)
    {
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

    public function columnExists($table, $column)
    {
        return $this->count("SHOW COLUMNS FROM {$this->prefixTable($table)} LIKE '{$this->escape($column)}';") > 0;
    }

    public function dropColumn($table, $column)
    {
        if ($this->columnExists($table, $column)) {
            $this->query("ALTER TABLE {$this->prefixTable($table)} DROP `{$this->escape($column)}`;");
        }
    }

    public function getDbTimeZone(): ?string
    {
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

    /**
     * get SQL content : backup method ; preferer mysqldump way it available.
     *
     * @return array ['sql' => string, 'error' => string]
     */
    public function getSQLContentBackupMethod(): array
    {
        $sql = '';
        $error = '';
        try {
            $tablesPrefix = trim($this->prefixTable(''));
            $tablesPostfix = [];
            // get Tables
            $tables = $this->loadAll('show tables');
            if (!is_array($tables)) {
                throw new Exception("Error in '" . __METHOD__ . "' (line " . __LINE__ . ") : 'show tables' sql command did not return an array !");
            }

            foreach ($tables as $tableInfo) {
                if (!is_array($tableInfo)) {
                    throw new Exception("Error in '" . __METHOD__ . "' (line " . __LINE__ . ") : '\$tableInfo' sql command did not return an array !");
                }
                $tableName = array_values($tableInfo)[0];
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

                $createTableResult = $this->query('show create table ' . $tableName);

                while ($creationTable = $createTableResult->fetch(PDO::FETCH_NUM)) {
                    $sql .= $creationTable[1] . ";\n\n";
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
                $sql .= ";\n";
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
        } catch (Throwable $th) {
            $error = $th->getMessage();
        }

        return compact(['sql', 'error']);
    }
}
