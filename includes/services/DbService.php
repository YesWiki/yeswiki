<?php

namespace YesWiki\Core\Service;

use DateInterval;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class DbService
{
    /** an INSERT has to reach the server in a single packet, so it stays well under any max_allowed_packet */
    protected const MAX_INSERT_LENGTH = 1048576;
    protected $params;

    protected $link;
    protected $queryLog;
    protected $collation;
    protected $readCache = [];

    public function __construct(ParameterBagInterface $params)
    {
        $this->params = $params;
        $this->queryLog = [];

        $this->initSqlConnection();
    }

    protected function initSqlConnection()
    {
        try {
            $this->link = @mysqli_connect(
                $this->params->get('mysql_host'),
                $this->params->get('mysql_user'),
                $this->params->get('mysql_password'),
                $this->params->get('mysql_database'),
                $this->params->has('mysql_port') ? $this->params->get('mysql_port') : ini_get('mysqli.default_port')
            );
            if (!$this->link) {
                throw new \Exception('Not connected to sql');
            }
            if ($this->params->has('db_charset') and $this->params->get('db_charset') === 'utf8mb4') {
                // necessaire pour les versions de mysql qui ont un autre encodage par defaut
                mysqli_set_charset($this->link, 'utf8mb4');

                // dans certains cas (ovh), set_charset ne passe pas, il faut faire une requete sql
                $charset = mysqli_character_set_name($this->link);
                if ($charset != 'utf8mb4') {
                    mysqli_query($this->link, 'SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci');
                }
            }
            $this->collation = (mysqli_character_set_name($this->link) === 'utf8mb4')
                ? 'utf8mb4_unicode_ci'
                : 'utf8_unicode_ci';
        } catch (\Throwable $th) {
            if (in_array(php_sapi_name(), ['cli', 'cli-server', ' phpdbg'], true)) {
                throw new \Exception(_t('DB_CONNECT_FAIL'));
            }
            exit(_t('DB_CONNECT_FAIL'));
        }
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
        return mysqli_real_escape_string($this->link, $string);
    }

    /*	Should it Returns FALSE on failure? => For the time being dies in case of failure
        For successful SELECT, SHOW, DESCRIBE or EXPLAIN queries mysqli_query() will return a mysqli_result object.
        For other successful will return TRUE.
        In case of failure $this->error contains the error message
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
            if (!$result = mysqli_query($this->link, $query)) {
                throw new \Exception('Query failed: ' . $query . ' (' . mysqli_error($this->link) . ')');
            }
        } catch (\Exception $e) {
            $this->addQueryLog('ERROR IN QUERY : ' . $query, $this->getMicroTime() - $start);
        } finally {
            if ($this->params->get('debug')) {
                $this->addQueryLog($query, $this->getMicroTime() - $start);
            }
        }

        return $result ?? null;
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
        if (isset($this->readCache[$query])) {
            return $this->readCache[$query];
        }

        $data = [];
        if ($r = $this->query($query)) {
            while ($row = mysqli_fetch_assoc($r)) {
                $data[] = $row;
            }
            mysqli_free_result($r);
        }

        return $this->readCache[$query] = $data;
    }

    public function count($query): int
    {
        return mysqli_num_rows($this->query($query));
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
            if (empty($tablesPrefix)) {
                throw new \Exception("'table_prefix' is empty in wakka.config.php — cannot determine which tables to back up");
            }
            $tablesPostfix = [];
            // get Tables
            $tables = $this->loadAll('show tables');
            if (!is_array($tables)) {
                throw new \Exception("Error in '" . __METHOD__ . "' (line " . __LINE__ . ") : 'show tables' sql command did not return an array !");
            }

            $tableNames = [];
            foreach ($tables as $tableInfo) {
                if (!is_array($tableInfo)) {
                    throw new \Exception("Error in '" . __METHOD__ . "' (line " . __LINE__ . ") : '\$tableInfo' sql command did not return an array !");
                }
                $tableNames[] = array_values($tableInfo)[0];
            }
            $tablesPostfix = DumpRewriter::ownTables($tableNames, $tablesPrefix);

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

                while ($creationTable = mysqli_fetch_array($createTableResult)) {
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

                $columns = [];
                for ($i = 0; $i < mysqli_num_fields($rawData); $i++) {
                    $columns[] = '`' . mysqli_fetch_field_direct($rawData, $i)->name . '`';
                }
                $insertHeader = "INSERT INTO `$tableName` (" . implode(', ', $columns) . ") VALUES\n";

                $statementLength = 0;
                while ($row = mysqli_fetch_array($rawData)) {
                    $values = [];
                    foreach ($columns as $i => $column) {
                        // everything but NULL is quoted: the server casts what belongs to a number
                        // column, while a type left unquoted (json, decimal, enum) breaks the dump
                        $values[] = is_null($row[$i]) ? 'NULL' : "'" . $this->escape($row[$i]) . "'";
                    }
                    $line = '(' . implode(', ', $values) . ')';

                    if ($statementLength === 0) {
                        $sql .= $insertHeader;
                    } else {
                        $sql .= ",\n";
                    }
                    $sql .= $line;
                    $statementLength += strlen($line) + 2;

                    // a single INSERT has to travel in one packet, and max_allowed_packet is small
                    if ($statementLength > self::MAX_INSERT_LENGTH) {
                        $sql .= ";\n";
                        $statementLength = 0;
                    }
                }
                if ($statementLength > 0) {
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
