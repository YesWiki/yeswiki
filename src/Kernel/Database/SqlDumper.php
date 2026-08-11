<?php

namespace YesWiki\Kernel\Database;

use YesWiki\Kernel\Service\DbService;

/**
 * The whole wiki's database as a replayable SQL dump.
 *
 * Lived on DbService, which had no business generating dump files -- SqlDialect's own docblock
 * named it as the thing that did not belong: "a MySQL-flavoured dump generator belonging to the
 * archive feature rather than to a dialect".
 *
 * A collaborator constructed from a DbService (like SchemaManager) rather than a method on
 * ArchiveService, its only caller, for a concrete reason: DatabaseRestoreTest builds its own
 * DbService against a throwaway SQLite file *so that pointing it at the development database is
 * impossible rather than merely inadvisable* -- restore drops every prefixed table before
 * replaying. Reaching this through ArchiveService would mean constructing six dependencies out
 * of the live container to get at one method, handing it the real wiki's connection and losing
 * exactly the property that test is built around.
 *
 * Row values are escape()d rather than bound, and that is right here: the output is a *file*, so
 * there is no statement for them to bind to.
 */
class SqlDumper
{
    public function __construct(
        private readonly DbService $dbService,
    ) {
    }

    /**
     * @return array{sql: string, error: string} the dump, or the reason there is none. An empty
     *                                           `sql` with a filled `error` is how a driver that
     *                                           cannot be dumped reports itself, rather than by
     *                                           throwing (ArchiveService turns it into a message).
     */
    public function dump(): array
    {
        $sql = '';
        $error = '';
        try {
            if (!$this->dbService->dialect()->supportsDump()) {
                throw new \Exception("Database backup is not supported on the '{$this->dbService->getDriver()}' driver: its table structure " . 'cannot be exported, so the archive would contain data with no tables to restore it into.');
            }
            $tablesPrefix = trim($this->dbService->prefixTable(''));
            if (empty($tablesPrefix)) {
                throw new \Exception("'table_prefix' is empty in wakka.config.php — cannot determine which tables to back up");
            }
            $tablesPostfix = [];
            // get Tables using the driver-agnostic method
            $tables = $this->dbService->schema()->getTables();

            foreach ($tables as $tableName) {
                if (strpos($tableName, $tablesPrefix) === 0) {
                    $tablesPostfix[] = $tableName;
                }
            }

            // generate file
            $date = (new \DateTime())->format('c');
            $phpVersion = phpversion();

            $driver = $this->dbService->dialect()->driverName();
            $preamble = implode(";\n", $this->dbService->dialect()->dumpPreamble());

            // The dialect marker is load-bearing, not decoration: a dump carries CREATE TABLE
            // statements in its own driver's syntax, so replaying a MySQL dump on SQLite (or
            // the reverse) fails part-way through, leaving the wiki with some tables restored
            // and some dropped. restoreDatabase() refuses on a mismatch (ticket 17).
            $sql =
                <<<SQL
            -- SQL Dump
            -- YesWiki database backup
            -- 
            -- Generated on : $date
            -- PHP version : $phpVersion
            -- YesWiki-Dialect: $driver

            $preamble;

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

                $tableSchema = $this->dbService->schema()->getTableSchema($tableName);
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

                $rawData = $this->dbService->query('select * from ' . $tableName);

                $firstRow = true;
                $columnCount = $rawData->columnCount();
                $columnMeta = [];
                for ($i = 0; $i < $columnCount; $i++) {
                    $columnMeta[$i] = $rawData->getColumnMeta($i);
                }

                while ($row = $rawData->fetch(\PDO::FETCH_NUM)) {
                    if ($firstRow) {
                        $sql .= 'INSERT INTO ' . $this->dbService->quoteIdentifier($tableName) . ' ';
                        $sql .= '(';
                        for ($i = 0; $i < $columnCount; $i++) {
                            if ($i != 0) {
                                $sql .= ', ';
                            }
                            $sql .= $this->dbService->quoteIdentifier((string)$columnMeta[$i]['name']);
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
                        // Quote everything except NULL. Deciding by the driver's reported
                        // native type was MySQL-specific (SQLite and PostgreSQL name their
                        // types differently, so numeric columns came out unquoted and text
                        // columns quoted at random), and `$row[$i] ?? ''` silently turned every
                        // NULL into an empty string on restore. Both databases accept a quoted
                        // literal in a numeric column.
                        $sql .= $row[$i] === null ? 'NULL' : "'" . $this->dbService->escape($row[$i]) . "'";
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

            $sql .= "\n" . implode(";\n", $this->dbService->dialect()->dumpEpilogue()) . ";\n";
        } catch (\Throwable $th) {
            $error = $th->getMessage();
        }

        return compact(['sql', 'error']);
    }
}
