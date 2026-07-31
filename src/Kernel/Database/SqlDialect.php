<?php

namespace YesWiki\Kernel\Database;

/**
 * SQL fragments that differ per database driver.
 *
 * Split out of DbService by wave-two ticket 05 (CP3). DbService was 1042 lines in which
 * 30 of 43 methods branched on `$this->driver`, so adding or fixing a driver meant editing
 * switch statements scattered through the whole class. A dialect is now one file per driver
 * and implements nothing but string generation -- no connection, no state, nothing to mock.
 *
 * Deliberately excludes:
 *  - connection, execution and schema introspection, which need the PDO link and stay on
 *    DbService;
 *  - `getSQLContentBackupMethod()`, which is a MySQL-flavoured dump generator belonging to
 *    the archive feature rather than to a dialect.
 */
interface SqlDialect
{
    /** The PDO driver name this dialect serves. */
    public function driverName(): string;

    /** SQL expression for the current timestamp. */
    public function now(): string;

    /** SQL expression for "current timestamp minus $days days". */
    public function dateSubDays(int $days): string;

    /** SQL expression for "current timestamp minus $hours hours". */
    public function dateSubHours(int $hours): string;

    /** SQL expression extracting $path from a JSON $column. */
    public function jsonExtract(string $column, string $path): string;

    /** SQL aggregating a column's distinct values into one comma-separated string. */
    public function groupConcat(string $column, ?string $orderBy = null): string;

    /** Quote a table or column name, for reserved words like `user`, `time`, `order`. */
    public function quoteIdentifier(string $identifier): string;

    /** Collation clause for case-insensitive comparison ('' where the driver needs none). */
    public function collateClause(): string;

    /** The driver's REGEXP operator. */
    public function regexpOperator(bool $not = false): string;

    /** SQL testing whether $needle appears in the comma-separated list $haystack. */
    public function findInSet(string $needle, string $haystack, bool $not = false): string;

    // ---------------------------------------------------------------- dump / restore
    //
    // Ticket 17: archive restore used to be a raw `mysqli_multi_query()`, so it only ever
    // worked on MySQL. A dump is replayed as ordinary statements now, which means the
    // driver-specific parts of it have to be stateable per dialect.

    /**
     * Statements a dump opens with -- session settings and the transaction, joined by ";\n".
     *
     * @return list<string>
     */
    public function dumpPreamble(): array;

    /** @return list<string> statements a dump closes with, commit included */
    public function dumpEpilogue(): array;

    /**
     * Toggle foreign-key enforcement while tables are dropped and re-created, or null where
     * the driver has no session-level switch for it.
     */
    public function foreignKeyChecks(bool $enabled): ?string;

    /** Whether a dump produced by this dialect can be generated at all (see ticket 17). */
    public function supportsDump(): bool;
}
