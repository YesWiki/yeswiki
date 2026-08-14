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
     *
     * `$using` is PostgreSQL's, and only PostgreSQL's: it refuses any type change it has no
     * implicit cast for, which is most of the interesting ones -- `TEXT` to `JSONB` among them
     * (ADR-0018). MySQL casts whatever it can and truncates the rest, so it is given nothing
     * to do here; passing an expression it cannot use would be a silent difference between the
     * two, which is why it is documented as PostgreSQL's rather than accepted and dropped.
     */
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

    /**
     * The DDL that recreates a table, by its real (prefixed) name, or null if there is no such
     * table.
     *
     * May return more than one statement, separated by `;` -- indexes are their own statements
     * everywhere except MySQL, where `SHOW CREATE TABLE` folds them into the table. The dump is
     * replayed through SqlStatementSplitter, which handles that.
     *
     * All three drivers produce a complete answer now (ticket 32). PostgreSQL used to return
     * null, which `SqlDialect::supportsDump()` reported as "this driver cannot be dumped" -- so
     * a PostgreSQL wiki could not be backed up at all, including in the one moment a backup
     * exists for, just before an upgrade.
     */
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

                // sqlite_master holds indexes as their own rows, and this used to return only
                // the table -- so a restored SQLite wiki came back with none of its indexes.
                // Silent, and invisible until the wiki got big enough to feel it. `sql` is NULL
                // for the implicit indexes behind UNIQUE/PRIMARY KEY, which the table already
                // recreates; those rows are skipped rather than emitted as broken DDL.
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
                // the table name is an identifier here, not a value: SHOW CREATE TABLE takes no
                // parameters at all, so it is quoted rather than bound. Its output already
                // carries the indexes as KEY clauses, and the AUTO_INCREMENT counter with them.
                try {
                    $row = $this->dbService->loadSingle(
                        'SHOW CREATE TABLE ' . $this->dbService->quoteIdentifier($tableName)
                    );
                } catch (\Throwable $noSuchTable) {
                    // SHOW CREATE TABLE *raises* for a table that is not there, where SQLite's
                    // sqlite_master query simply finds no row. This method promises null for a
                    // table it cannot describe, and that promise held on exactly one of the three
                    // drivers until a test asked each of them the question.
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

    /** @var list<string>|null memoised, because SqlDumper asks once per table */
    private ?array $virtualTables = null;

    /**
     * How a dump should treat one table: fully, structure only, or not at all.
     *
     * This exists because SQLite's search index is an FTS5 virtual table (ADR-0015), and a
     * virtual table is not a table you can dump. Sweeping the prefix picked up five extra names
     * -- the virtual table plus its four shadow tables (`_data`, `_idx`, `_docsize`, `_config`)
     * -- and dumped them as though they were ordinary storage. The result was an archive that
     * **could not be restored**: replaying it died on
     * `INSERT INTO "x_fts" ("title", "text", "x_fts", "rank")`, because `x_fts` and `rank` are
     * FTS5 pseudo-columns rather than real ones.
     *
     * That is a worse failure than the PostgreSQL one ticket 32 was raised for. PostgreSQL
     * refused to make a backup, loudly, at backup time; SQLite made one that failed at restore
     * time, which is the moment you have nothing else left.
     *
     * MySQL and PostgreSQL have no such tables -- their full-text support is an index on a real
     * table (InnoDB FULLTEXT, a GIN over a generated tsvector) -- so both answer FULL for
     * everything.
     */
    public function dumpRoleFor(string $tableName): string
    {
        if ($this->dbService->getDriver() !== 'sqlite') {
            return self::DUMP_FULL;
        }

        foreach ($this->sqliteVirtualTables() as $virtual) {
            if ($tableName === $virtual) {
                return self::DUMP_STRUCTURE_ONLY;
            }
            // FTS5 keeps its storage in `<name>_data`, `<name>_idx`, `<name>_docsize` and
            // `<name>_config`. Matched by prefix rather than by that list: the set is an
            // implementation detail of whichever FTS version is in use, and a dump that misses
            // a new one produces the unrestorable archive above.
            if (str_starts_with($tableName, $virtual . '_')) {
                return self::DUMP_SKIP;
            }
        }

        return self::DUMP_FULL;
    }

    /**
     * Statements a dump must replay *after* all the data: triggers, and FTS index rebuilds.
     *
     * Both have to come last, for different reasons.
     *
     * **Triggers**, because SQLite resolves the tables a trigger body names when the trigger is
     * created -- so a trigger on the search index that writes to the FTS table cannot be created
     * before that table exists -- and because a trigger that is already in place while the data
     * is being inserted would fire for every row, populating the FTS index a second time.
     * Triggers were previously not dumped at all, so a restored SQLite wiki stopped maintaining
     * its search index from that moment on, silently.
     *
     * **The rebuild**, because the FTS content is derived: `INSERT INTO x(x) VALUES('rebuild')`
     * is FTS5's own way of repopulating an index from the content table it shadows, and it needs
     * that content to be there.
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
     * Generated columns are excluded, and that exclusion is not cosmetic: PostgreSQL rejects an
     * INSERT that supplies any value at all for one -- "cannot insert a non-DEFAULT value into
     * column" -- so a dump listing every column cannot be replayed. The search index has exactly
     * such a column on PostgreSQL (a `tsvector` generated from title and text), which is how a
     * pgsql restore failed on its own data even once the DDL was right.
     *
     * Asked of all three drivers rather than special-cased for PostgreSQL: MySQL and SQLite both
     * support generated columns too, so a schema that grows one on any driver stays dumpable
     * instead of quietly producing an unreplayable archive.
     *
     * @param string $tableName real, prefixed table name
     *
     * @return list<string> empty if the table has no columns this driver can report
     */
    public function dumpableColumns(string $tableName): array
    {
        switch ($this->dbService->getDriver()) {
            case 'sqlite':
                // table_xinfo, not table_info: only the extended form reports generated columns,
                // as hidden = 2 (VIRTUAL) or 3 (STORED). A table name cannot be bound into a
                // PRAGMA, so it is quoted as the identifier it is.
                $rows = $this->dbService->loadAll(
                    'PRAGMA table_xinfo(' . $this->dbService->quoteIdentifier($tableName) . ')'
                );

                // array_filter preserves keys, so the values are re-indexed before mapping --
                // a list is what the return type promises
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

    /**
     * Rebuild a PostgreSQL table's DDL from the system catalogs.
     *
     * PostgreSQL has no `SHOW CREATE TABLE`, so this is assembled rather than asked for. It is
     * less hand-rolled than that sounds, because the catalog will hand back finished SQL for the
     * hard parts: `format_type()` gives the exact type including its modifiers,
     * `pg_get_expr()` the defaults and generated-column expressions, `pg_get_constraintdef()`
     * whole constraint clauses, and `pg_indexes.indexdef` whole CREATE INDEX statements. What is
     * left here is assembly and two decisions.
     *
     * **Decision 1: identity, not a separate sequence.** A `SERIAL` column appears in the catalog
     * as `integer DEFAULT nextval('t_id_seq')` plus a sequence object plus an ownership link --
     * three statements that have to be emitted in the right order. This emits one
     * `GENERATED BY DEFAULT AS IDENTITY (START WITH n)` clause instead, which creates and owns
     * its sequence implicitly. `n` is the table's current max + 1, read at dump time: without it
     * the restored sequence would start at 1 while the restored rows already occupy 1..max, and
     * the first insert after a restore would collide on the primary key.
     *
     * `BY DEFAULT` rather than `ALWAYS`, even for a column the catalog reports as `ALWAYS`: the
     * dump's own INSERTs supply explicit ids, and an ALWAYS identity rejects those without
     * `OVERRIDING SYSTEM VALUE`. A restore that cannot replay its own data is not a restore.
     *
     * **Decision 2: foreign keys are skipped.** A `REFERENCES` clause inside CREATE TABLE
     * requires the referenced table to exist already, so emitting them would make the dump
     * order-dependent. YesWiki's schema has none -- verified against a real install -- so this
     * buys correct output today rather than an ordering algorithm for a case that does not
     * arise. An extension that adds one gets its table back without the constraint, which is
     * worth knowing if that ever changes.
     */
    private function postgreSqlTableSchema(string $tableName): ?string
    {
        // `to_regclass(?)`, never `?::regclass`: the cast RAISES for a name that is not there
        // ("relation does not exist"), while the function returns NULL and the query simply finds
        // no columns. `getTableSchema()` promises null for a table it cannot describe, and a dump
        // only asks about names it found itself -- but a promise that throws instead is worse than
        // no promise, and this is exactly what a test for the missing-table case caught.
        //
        // Booleans and the `"char"` catalog columns are cast in SQL rather than read raw: what
        // PDO hands back for a pgsql boolean depends on the driver build, and `attgenerated` /
        // `attidentity` are single-byte `"char"` values, not text.
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

        // PRIMARY KEY first, then UNIQUE, then CHECK -- readability only, the order carries no
        // meaning to PostgreSQL. Foreign keys ('f') are deliberately absent; see the docblock.
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

        // Indexes that back a constraint are already created by the constraint clause above;
        // emitting them again fails with "relation already exists".
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
            // a stored generated column: its expression lives in pg_attrdef like a default, but
            // it is not one, and it must not also get a DEFAULT clause
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
     * Where a restored identity column has to start counting: one past the largest value in the
     * table, so the first insert after a restore does not collide with a restored row.
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
