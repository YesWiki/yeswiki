<?php

namespace YesWiki\Kernel\Database;

use YesWiki\Kernel\Service\DbService;

/** Asks the database about its own shape: which tables exist, which columns, of what type. */
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
     * @return array{type: string, nullable: bool, default: mixed}|null
     */
    public function getColumnInfo(string $table, string $column): ?array
    {
        return $this->columnInfo($table, $column);
    }

    /**
     * One lookup behind both columnExists() and getColumnInfo(), which asked the same question of the same three drivers in two places and could drift apart.
     *
     * @return array{type: string, nullable: bool, default: mixed}|null
     */
    private function columnInfo(string $table, string $column): ?array
    {
        $tableName = trim($this->dbService->prefixTable($table));

        if ($this->dbService->getDriver() === 'sqlite') {
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

    /** Change a column's type. */
    public function modifyColumn(string $table, string $column, string $newType, bool $notNull = false, ?string $using = null): bool
    {
        $quotedColumn = $this->dbService->quoteIdentifier($column);
        $table = $this->dbService->prefixTable($table);

        switch ($this->dbService->getDriver()) {
            case 'sqlite':
                return true;

            case 'pgsql':
                $this->dbService->query("ALTER TABLE {$table} ALTER COLUMN {$quotedColumn} TYPE {$newType}"
                    . ($using === null ? '' : " USING {$using}"));
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

    /** The DDL that recreates a table, by its real (prefixed) name, or null if there is no such table. */
    public function getTableSchema(string $tableName): ?string
    {
        switch ($this->dbService->getDriver()) {
            case 'sqlite':
                $row = $this->dbService->loadSingle(
                    "SELECT sql FROM sqlite_master WHERE type='table' AND name = ?",
                    [$tableName]
                );
                if (empty($row['sql'])) {
                    return null;
                }

                $indexes = $this->dbService->loadAll(
                    "SELECT sql FROM sqlite_master WHERE type='index' AND tbl_name = ?"
                    . ' AND sql IS NOT NULL ORDER BY name',
                    [$tableName]
                );

                return implode(";\n", [
                    (string)$row['sql'],
                    ...array_map(static fn (array $index): string => (string)$index['sql'], $indexes),
                ]);

            case 'pgsql':
                return $this->postgreSqlTableSchema($tableName);

            case 'mysql':
            default:
                try {
                    $row = $this->dbService->loadSingle(
                        'SHOW CREATE TABLE ' . $this->dbService->quoteIdentifier($tableName)
                    );
                } catch (\Throwable $noSuchTable) {
                    return null;
                }

                return $row['Create Table'] ?? null;
        }
    }

    /** A table whose structure and data both belong in a dump -- almost everything. */
    public const DUMP_FULL = 'full';

    /** Structure only: the data is derived and will be rebuilt (an FTS index). */
    public const DUMP_STRUCTURE_ONLY = 'structure-only';

    /** Not a table anyone may dump or restore directly (a virtual table's shadow storage). */
    public const DUMP_SKIP = 'skip';

    /**
     * @var list<string>|null memoised, because SqlDumper asks once per table
     */
    private ?array $virtualTables = null;

    /** How a dump should treat one table: fully, structure only, or not at all. */
    public function dumpRoleFor(string $tableName): string
    {
        if ($this->dbService->getDriver() !== 'sqlite') {
            return self::DUMP_FULL;
        }

        foreach ($this->sqliteVirtualTables() as $virtual) {
            if ($tableName === $virtual) {
                return self::DUMP_STRUCTURE_ONLY;
            }

            if (str_starts_with($tableName, $virtual . '_')) {
                return self::DUMP_SKIP;
            }
        }

        return self::DUMP_FULL;
    }

    /**
     * Statements a dump must replay *after* all the data: triggers, and FTS index rebuilds.
     *
     * @param list<string> $prefixedTables the tables being dumped, real names
     *
     * @return list<string> statements, without trailing semicolons
     */
    public function postDataStatements(array $prefixedTables): array
    {
        if ($this->dbService->getDriver() !== 'sqlite') {
            return [];
        }

        $statements = [];
        foreach ($this->dbService->loadAll(
            "SELECT sql FROM sqlite_master WHERE type = 'trigger' AND sql IS NOT NULL ORDER BY name"
        ) as $trigger) {
            $statements[] = (string)$trigger['sql'];
        }

        foreach ($prefixedTables as $tableName) {
            if ($this->dumpRoleFor($tableName) === self::DUMP_STRUCTURE_ONLY) {
                $quoted = $this->dbService->quoteIdentifier($tableName);
                $statements[] = 'INSERT INTO ' . $quoted . '(' . $quoted . ") VALUES('rebuild')";
            }
        }

        return $statements;
    }

    /**
     * The names of this SQLite database's virtual tables.
     *
     * @return list<string>
     */
    private function sqliteVirtualTables(): array
    {
        if ($this->virtualTables === null) {
            $rows = $this->dbService->loadAll(
                "SELECT name FROM sqlite_master WHERE type = 'table'"
                . " AND sql LIKE 'CREATE VIRTUAL TABLE%' ORDER BY name"
            );
            $this->virtualTables = array_map(
                static fn (array $row): string => (string)$row['name'],
                $rows
            );
        }

        return $this->virtualTables;
    }

    /**
     * The columns of a table that a dump may write values for, in declaration order.
     *
     * @param string $tableName real, prefixed table name
     *
     * @return list<string> empty if the table has no columns this driver can report
     */
    public function dumpableColumns(string $tableName): array
    {
        switch ($this->dbService->getDriver()) {
            case 'sqlite':
                $rows = $this->dbService->loadAll(
                    'PRAGMA table_xinfo(' . $this->dbService->quoteIdentifier($tableName) . ')'
                );

                return array_map(
                    static fn (array $row): string => (string)$row['name'],
                    array_values(array_filter(
                        $rows,
                        static fn (array $row): bool => !in_array((int)$row['hidden'], [2, 3], true)
                    ))
                );

            case 'pgsql':
                $rows = $this->dbService->loadAll(
                    'SELECT a.attname AS name FROM pg_attribute a'
                    . ' WHERE a.attrelid = to_regclass(?) AND a.attnum > 0 AND NOT a.attisdropped'
                    . " AND a.attgenerated::text = ''"
                    . ' ORDER BY a.attnum',
                    [$tableName]
                );

                return array_map(static fn (array $row): string => (string)$row['name'], $rows);

            case 'mysql':
            default:
                $rows = $this->dbService->loadAll(
                    'SELECT COLUMN_NAME AS name FROM information_schema.COLUMNS'
                    . ' WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
                    . " AND EXTRA NOT LIKE '%GENERATED%'"
                    . ' ORDER BY ORDINAL_POSITION',
                    [$tableName]
                );

                return array_map(static fn (array $row): string => (string)$row['name'], $rows);
        }
    }

    /** Rebuild a PostgreSQL table's DDL from the system catalogs. */
    private function postgreSqlTableSchema(string $tableName): ?string
    {
        $columns = $this->dbService->loadAll(
            'SELECT a.attname AS name,'
            . ' format_type(a.atttypid, a.atttypmod) AS type,'
            . ' CASE WHEN a.attnotnull THEN 1 ELSE 0 END AS notnull,'
            . ' pg_get_expr(d.adbin, d.adrelid) AS default_expr,'
            . ' a.attidentity::text AS identity,'
            . ' a.attgenerated::text AS generated'
            . ' FROM pg_attribute a'
            . ' LEFT JOIN pg_attrdef d ON d.adrelid = a.attrelid AND d.adnum = a.attnum'
            . ' WHERE a.attrelid = to_regclass(?) AND a.attnum > 0 AND NOT a.attisdropped'
            . ' ORDER BY a.attnum',
            [$tableName]
        );
        if ($columns === []) {
            return null;
        }

        $definitions = [];
        foreach ($columns as $column) {
            $definitions[] = $this->postgreSqlColumnDefinition($tableName, $column);
        }

        $constraints = $this->dbService->loadAll(
            'SELECT conname, pg_get_constraintdef(oid) AS def FROM pg_constraint'
            . " WHERE conrelid = to_regclass(?) AND contype IN ('p', 'u', 'c')"
            . " ORDER BY CASE contype WHEN 'p' THEN 1 WHEN 'u' THEN 2 ELSE 3 END, conname",
            [$tableName]
        );
        foreach ($constraints as $constraint) {
            $definitions[] = 'CONSTRAINT ' . $this->dbService->quoteIdentifier((string)$constraint['conname'])
                . ' ' . (string)$constraint['def'];
        }

        $statements = [
            'CREATE TABLE ' . $this->dbService->quoteIdentifier($tableName) . " (\n  "
            . implode(",\n  ", $definitions) . "\n)",
        ];

        $indexes = $this->dbService->loadAll(
            'SELECT i.indexdef FROM pg_indexes i'
            . ' WHERE i.schemaname = current_schema() AND i.tablename = ?'
            . ' AND NOT EXISTS ('
            . '   SELECT 1 FROM pg_constraint c'
            . '   WHERE c.conrelid = to_regclass(i.tablename) AND c.conname = i.indexname'
            . ' ) ORDER BY i.indexname',
            [$tableName]
        );
        foreach ($indexes as $index) {
            $statements[] = (string)$index['indexdef'];
        }

        return implode(";\n", $statements);
    }

    /**
     * One column's clause inside CREATE TABLE.
     *
     * @param array<string, mixed> $column as selected by postgreSqlTableSchema()
     */
    private function postgreSqlColumnDefinition(string $tableName, array $column): string
    {
        $name = (string)$column['name'];
        $default = $column['default_expr'] === null ? '' : (string)$column['default_expr'];
        $clause = $this->dbService->quoteIdentifier($name) . ' ' . (string)$column['type'];

        if ((string)$column['generated'] === 's') {
            return $clause . ' GENERATED ALWAYS AS (' . $default . ') STORED';
        }

        $isIdentity = (string)$column['identity'] !== '';
        $isSerial = str_starts_with($default, 'nextval(');
        if ($isIdentity || $isSerial) {
            $clause .= ' GENERATED BY DEFAULT AS IDENTITY (START WITH '
                . $this->postgreSqlNextIdentityValue($tableName, $name) . ')';
        } elseif ($default !== '') {
            $clause .= ' DEFAULT ' . $default;
        }

        if ((int)$column['notnull'] === 1) {
            $clause .= ' NOT NULL';
        }

        return $clause;
    }

    /**
     * Where a restored identity column has to start counting: one past the largest value in the table, so the first insert after a restore does not collide with a restored row.
     */
    private function postgreSqlNextIdentityValue(string $tableName, string $column): int
    {
        $row = $this->dbService->loadSingle(
            'SELECT COALESCE(MAX(' . $this->dbService->quoteIdentifier($column) . '), 0) + 1 AS next'
            . ' FROM ' . $this->dbService->quoteIdentifier($tableName)
        );

        return max(1, (int)($row['next'] ?? 1));
    }
}
