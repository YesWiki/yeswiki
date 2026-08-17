<?php

namespace YesWiki\Kernel\Database;

use YesWiki\Kernel\Service\DbService;

/** The whole wiki's database as a replayable SQL dump. */
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

            $tables = $this->dbService->schema()->getTables();

            foreach ($tables as $tableName) {
                if (strpos($tableName, $tablesPrefix) === 0) {
                    $tablesPostfix[] = $tableName;
                }
            }

            $date = (new \DateTime())->format('c');
            $phpVersion = phpversion();

            $driver = $this->dbService->dialect()->driverName();
            $preamble = implode(";\n", $this->dbService->dialect()->dumpPreamble());

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

            foreach ($tablesPostfix as $tableName) {
                $role = $this->dbService->schema()->dumpRoleFor($tableName);
                if ($role === SchemaManager::DUMP_SKIP) {
                    continue;
                }

                $sql .=
                    <<<SQL

                -- 
                -- Structure of table : `$tableName`
                -- 

                SQL;

                $tableSchema = $this->dbService->schema()->getTableSchema($tableName);
                if ($tableSchema) {
                    $sql .= $tableSchema . ";\n\n";
                }

                if ($role === SchemaManager::DUMP_STRUCTURE_ONLY) {
                    $sql .= "\n-- \n-- Data of table : `$tableName` is derived and rebuilt after the data\n-- \n";
                    $sql .= "\n-- --------------------------------------------------------\n";
                    continue;
                }

                $sql .=
                    <<<SQL

                --
                -- Data of table : `$tableName`
                --

                SQL;

                $columnNames = $this->dbService->schema()->dumpableColumns($tableName);
                if ($columnNames === []) {
                    continue;
                }
                $quotedColumns = array_map(
                    fn (string $column): string => $this->dbService->quoteIdentifier($column),
                    $columnNames
                );

                $rawData = $this->dbService->query(
                    'SELECT ' . implode(', ', $quotedColumns)
                    . ' FROM ' . $this->dbService->quoteIdentifier($tableName)
                );

                $firstRow = true;
                $columnCount = count($columnNames);

                while ($row = $rawData->fetch(\PDO::FETCH_NUM)) {
                    if ($firstRow) {
                        $sql .= 'INSERT INTO ' . $this->dbService->quoteIdentifier($tableName) . ' ';
                        $sql .= '(' . implode(', ', $quotedColumns) . ") VALUES\n";
                        $firstRow = false;
                    } else {
                        $sql .= ",\n";
                    }
                    $sql .= '(';
                    for ($i = 0; $i < $columnCount; $i++) {
                        if ($i != 0) {
                            $sql .= ', ';
                        }

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

            $postData = $this->dbService->schema()->postDataStatements($tablesPostfix);
            if ($postData !== []) {
                $sql .= "\n-- \n-- Triggers and derived indexes, replayed after the data\n-- \n\n"
                    . implode(";\n", $postData) . ";\n";
            }

            $sql .= "\n" . implode(";\n", $this->dbService->dialect()->dumpEpilogue()) . ";\n";
        } catch (\Throwable $th) {
            $error = $th->getMessage();
        }

        return compact(['sql', 'error']);
    }
}
