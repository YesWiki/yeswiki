<?php

namespace YesWiki\Kernel\Database;

use YesWiki\Kernel\Service\DbService;

/** The whole wiki's database as a replayable SQL dump. */
class SqlDumper
{
    /** An INSERT has to reach the server in a single packet, so it stays well under any max_allowed_packet. */
    public const MAX_INSERT_BYTES = 1048576;

    /** And it stays short enough for SQLite and PostgreSQL to parse without fuss. */
    public const MAX_INSERT_ROWS = 500;

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
        $handle = fopen('php://temp', 'w+b');
        if ($handle === false) {
            return ['sql' => '', 'error' => 'Cannot open a buffer for the dump'];
        }
        try {
            $this->dumpTo($handle);
            rewind($handle);
            $sql = (string)stream_get_contents($handle);
        } catch (\Throwable $th) {
            $error = $th->getMessage();
        } finally {
            fclose($handle);
        }

        return compact(['sql', 'error']);
    }

    /**
     * Write the dump to a stream as it is read, a bounded batch of rows per INSERT.
     *
     * @param resource $handle
     *
     * @throws \Exception when the driver cannot be dumped or the stream refuses the bytes
     */
    public function dumpTo($handle): void
    {
        if (!$this->dbService->dialect()->supportsDump()) {
            throw new \Exception("Database backup is not supported on the '{$this->dbService->getDriver()}' driver: its table structure " . 'cannot be exported, so the archive would contain data with no tables to restore it into.');
        }
        $tablesPrefix = trim($this->dbService->prefixTable(''));
        if (empty($tablesPrefix)) {
            throw new \Exception("'table_prefix' is empty in wakka.config.php — cannot determine which tables to back up");
        }

        $tables = DumpRewriter::ownTables($this->dbService->schema()->getTables(), $tablesPrefix);

        $date = (new \DateTime())->format('c');
        $phpVersion = phpversion();
        $driver = $this->dbService->dialect()->driverName();
        $preamble = implode(";\n", $this->dbService->dialect()->dumpPreamble());

        $this->write($handle, <<<SQL
            -- SQL Dump
            -- YesWiki database backup
            --
            -- Generated on : $date
            -- PHP version : $phpVersion
            -- YesWiki-Dialect: $driver

            $preamble;

            -- --------------------------------------------------------

            SQL);

        foreach ($tables as $tableName) {
            $role = $this->dbService->schema()->dumpRoleFor($tableName);
            if ($role === SchemaManager::DUMP_SKIP) {
                continue;
            }

            $this->write($handle, "\n-- \n-- Structure of table : `$tableName`\n-- \n\n");

            $tableSchema = $this->dbService->schema()->getTableSchema($tableName);
            if ($tableSchema) {
                $this->write($handle, $tableSchema . ";\n\n");
            }

            if ($role === SchemaManager::DUMP_STRUCTURE_ONLY) {
                $this->write($handle, "\n-- \n-- Data of table : `$tableName` is derived and rebuilt after the data\n-- \n\n-- --------------------------------------------------------\n");
                continue;
            }

            $this->write($handle, "\n--\n-- Data of table : `$tableName`\n--\n\n");
            $this->dumpRows($handle, $tableName);
            $this->write($handle, "\n-- --------------------------------------------------------\n");
        }

        $postData = $this->dbService->schema()->postDataStatements($tables);
        if ($postData !== []) {
            $this->write($handle, "\n-- \n-- Triggers and derived indexes, replayed after the data\n-- \n\n" . implode(";\n", $postData) . ";\n");
        }

        $this->write($handle, "\n" . implode(";\n", $this->dbService->dialect()->dumpEpilogue()) . ";\n");
    }

    /**
     * One table's rows, as INSERTs of at most MAX_INSERT_ROWS rows and about MAX_INSERT_BYTES each.
     *
     * @param resource $handle
     */
    private function dumpRows($handle, string $tableName): void
    {
        $columnNames = $this->dbService->schema()->dumpableColumns($tableName);
        if ($columnNames === []) {
            return;
        }
        $quotedColumns = array_map(
            fn (string $column): string => $this->dbService->quoteIdentifier($column),
            $columnNames
        );
        $header = 'INSERT INTO ' . $this->dbService->quoteIdentifier($tableName) . ' (' . implode(', ', $quotedColumns) . ") VALUES\n";

        $rawData = $this->dbService->query(
            'SELECT ' . implode(', ', $quotedColumns) . ' FROM ' . $this->dbService->quoteIdentifier($tableName)
        );

        $batch = '';
        $rows = 0;
        while ($row = $rawData->fetch(\PDO::FETCH_NUM)) {
            $values = [];
            foreach ($row as $value) {
                $values[] = $value === null ? 'NULL' : "'" . $this->dbService->escape($value) . "'";
            }
            $line = '(' . implode(', ', $values) . ')';

            if ($rows > 0 && ($rows >= self::MAX_INSERT_ROWS || \strlen($batch) + \strlen($line) > self::MAX_INSERT_BYTES)) {
                $this->write($handle, $header . $batch . ";\n");
                $batch = '';
                $rows = 0;
            }
            $batch .= ($rows > 0 ? ",\n" : '') . $line;
            $rows++;
        }
        if ($rows > 0) {
            $this->write($handle, $header . $batch . ";\n");
        }
    }

    /**
     * @param resource $handle
     */
    private function write($handle, string $bytes): void
    {
        $length = \strlen($bytes);
        $written = 0;
        while ($written < $length) {
            $count = fwrite($handle, substr($bytes, $written));
            if ($count === false || $count === 0) {
                throw new \Exception('Cannot write the SQL dump: is the disk full?');
            }
            $written += $count;
        }
    }
}
