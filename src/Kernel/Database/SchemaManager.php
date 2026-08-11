<?php

namespace YesWiki\Kernel\Database;

use YesWiki\Kernel\Service\DbService;

/**
 * Asks the database about its own shape: which tables exist, which columns, of what type.
 *
 * Split out of DbService, which was doing four jobs at 1010 lines -- connection and execution,
 * dialect passthrough, this, and backup/restore. `SqlDialect` had already taken the per-driver
 * *string generation* and said why it left introspection behind: it needs to run queries, and a
 * dialect holds no connection. That is an argument for a second collaborator, not for leaving
 * 216 lines of `switch ($this->driver)` on the execution class -- so here it is, composed on
 * DbService's public API rather than on its PDO link.
 *
 * Reached as `$dbService->schema()`. Constructed with `new` rather than injected: it needs
 * DbService and DbService owns it, so registering it as a service would only create a cycle for
 * the container to work around.
 *
 * **Every method here takes an UNPREFIXED table name** and prefixes it itself, matching what the
 * DbService methods it replaces did. `getTables()` is the exception and returns real, prefixed
 * names, because its callers compare against them.
 */
class SchemaManager
{
    public function __construct(
        private readonly DbService $dbService,
    ) {
    }

    /** Whether $table has a column called $column, case-insensitively. */
    public function columnExists(string $table, string $column): bool
    {
        return $this->columnInfo($table, $column) !== null;
    }

    /**
     * Type, nullability and default of one column, or null if there is no such column.
     *
     * `type` is the driver's own spelling, lowercased -- `varchar(191)` on MySQL and SQLite,
     * `character varying(191)` on PostgreSQL. A caller comparing it to a literal has to accept
     * both, as PasswordHasherFactory does.
     *
     * @return array{type: string, nullable: bool, default: mixed}|null
     */
    public function getColumnInfo(string $table, string $column): ?array
    {
        return $this->columnInfo($table, $column);
    }

    /**
     * One lookup behind both columnExists() and getColumnInfo(), which asked the same question
     * of the same three drivers in two places and could drift apart.
     *
     * @return array{type: string, nullable: bool, default: mixed}|null
     */
    private function columnInfo(string $table, string $column): ?array
    {
        $tableName = trim($this->dbService->prefixTable($table));

        if ($this->dbService->getDriver() === 'sqlite') {
            // PRAGMA takes no parameters, and the table name is not a value -- so this is the
            // one branch that interpolates, and it interpolates an identifier the caller named.
            foreach ($this->dbService->loadAll('PRAGMA table_info(' . $this->dbService->quoteIdentifier($tableName) . ')') as $row) {
                if (strcasecmp((string)$row['name'], $column) === 0) {
                    return [
                        'type' => strtolower((string)$row['type']),
                        'nullable' => $row['notnull'] == 0,
                        'default' => $row['dflt_value'],
                    ];
                }
            }

            return null;
        }

        if ($this->dbService->getDriver() === 'pgsql') {
            // LOWER() on both sides, like the MySQL branch: the SQLite branch compares with
            // strcasecmp(), so case-insensitive is the intent -- but PostgreSQL's `=` is
            // case-sensitive and MySQL's only agreed by virtue of its information_schema
            // collation. Measured: `column_name = 'TAG'` found nothing on pgsql while the same
            // question answered yes on the other two. Stating it beats inheriting it.
            $row = $this->dbService->loadSingle(
                'SELECT data_type, character_maximum_length, is_nullable, column_default'
                . ' FROM information_schema.columns'
                . ' WHERE LOWER(table_name) = LOWER(?) AND LOWER(column_name) = LOWER(?)',
                [$tableName, $column]
            );
            if (empty($row)) {
                return null;
            }
            $type = (string)$row['data_type'];
            if (!empty($row['character_maximum_length'])) {
                $type .= '(' . $row['character_maximum_length'] . ')';
            }

            return [
                'type' => strtolower($type),
                'nullable' => $row['is_nullable'] === 'YES',
                'default' => $row['column_default'],
            ];
        }

        // MySQL. This was `SHOW COLUMNS FROM t LIKE '<column>'`, which is why the column name
        // had to be escape()d as if it were a value: MySQL cannot bind in a SHOW statement
        // (`SHOW COLUMNS FROM t LIKE ?` is a syntax error, measured). information_schema takes
        // the same question as an ordinary SELECT, so the name binds -- and COLUMN_TYPE is
        // byte-identical to SHOW COLUMNS' `Type` (`varchar(191)`, `int unsigned`,
        // `enum('Y','N')`, all verified), so nothing downstream sees a different string.
        $row = $this->dbService->loadSingle(
            'SELECT COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT'
            . ' FROM information_schema.COLUMNS'
            . ' WHERE TABLE_SCHEMA = DATABASE()'
            . ' AND LOWER(TABLE_NAME) = LOWER(?) AND LOWER(COLUMN_NAME) = LOWER(?)',
            [$tableName, $column]
        );
        if (empty($row)) {
            return null;
        }

        return [
            'type' => strtolower((string)$row['COLUMN_TYPE']),
            'nullable' => $row['IS_NULLABLE'] === 'YES',
            'default' => $row['COLUMN_DEFAULT'],
        ];
    }

    /** Drop $column if it is there; a no-op if it is not, so a migration can be re-run. */
    public function dropColumn(string $table, string $column): void
    {
        if (!$this->columnExists($table, $column)) {
            return;
        }

        $this->dbService->query(
            'ALTER TABLE ' . $this->dbService->prefixTable($table)
            . ' DROP COLUMN ' . $this->dbService->quoteIdentifier($column)
        );
    }

    /**
     * Change a column's type.
     *
     * SQLite has no ALTER COLUMN and changing a type there means rebuilding the table; its
     * column types are advisory anyway, so this reports success without doing anything -- which
     * is the pre-existing behaviour, kept deliberately rather than turned into a failure.
     */
    public function modifyColumn(string $table, string $column, string $newType, bool $notNull = false): bool
    {
        $quotedColumn = $this->dbService->quoteIdentifier($column);
        $table = $this->dbService->prefixTable($table);

        switch ($this->dbService->getDriver()) {
            case 'sqlite':
                return true;

            case 'pgsql':
                $this->dbService->query("ALTER TABLE {$table} ALTER COLUMN {$quotedColumn} TYPE {$newType}");
                if ($notNull) {
                    $this->dbService->query("ALTER TABLE {$table} ALTER COLUMN {$quotedColumn} SET NOT NULL");
                }

                return true;

            case 'mysql':
            default:
                $this->dbService->query(
                    "ALTER TABLE {$table} MODIFY COLUMN {$quotedColumn} {$newType}" . ($notNull ? ' NOT NULL' : '')
                );

                return true;
        }
    }

    /**
     * Every table in the database, by its real (prefixed) name.
     *
     * @return list<string>
     */
    public function getTables(): array
    {
        switch ($this->dbService->getDriver()) {
            case 'sqlite':
                $rows = $this->dbService->loadAll("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'");

                return array_map(static fn (array $row): string => (string)$row['name'], $rows);

            case 'pgsql':
                $rows = $this->dbService->loadAll("SELECT tablename FROM pg_tables WHERE schemaname = 'public'");

                return array_map(static fn (array $row): string => (string)$row['tablename'], $rows);

            case 'mysql':
            default:
                $rows = $this->dbService->loadAll('SHOW TABLES');

                return array_map(static fn (array $row): string => (string)array_values($row)[0], $rows);
        }
    }

    /**
     * The CREATE TABLE statement for a table, by its real (prefixed) name, or null where the
     * driver cannot produce one.
     *
     * PostgreSQL has no `SHOW CREATE TABLE` equivalent and returns null; the archive code reads
     * that as "this driver cannot be dumped this way" (ticket 17, `SqlDialect::supportsDump()`).
     */
    public function getTableSchema(string $tableName): ?string
    {
        switch ($this->dbService->getDriver()) {
            case 'sqlite':
                $row = $this->dbService->loadSingle(
                    "SELECT sql FROM sqlite_master WHERE type='table' AND name = ?",
                    [$tableName]
                );

                return $row['sql'] ?? null;

            case 'pgsql':
                return null;

            case 'mysql':
            default:
                // the table name is an identifier here, not a value: SHOW CREATE TABLE takes no
                // parameters at all, so it is quoted rather than bound
                $row = $this->dbService->loadSingle(
                    'SHOW CREATE TABLE ' . $this->dbService->quoteIdentifier($tableName)
                );

                return $row['Create Table'] ?? null;
        }
    }
}
