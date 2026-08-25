<?php

namespace YesWiki\Kernel\Database;

/** SQL fragments that differ per database driver. */
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

    /** The column type a JSON document is declared as: native where the dialect has one. */
    public function jsonColumnType(): string;

    /** SQL expression extracting $path from a $column **declared as jsonColumnType()**, e.g. */
    public function jsonExtract(string $column, string $path): string;

    /** A JSON column as text, for the string operators that have no JSON equivalent. */
    public function jsonAsText(string $column): string;

    /** The same read, for JSON stored in a column that is **not** declared as JSON. */
    public function jsonExtractText(string $column, string $path): string;

    /** SQL aggregating a column's distinct values into one comma-separated string. */
    public function groupConcat(string $column, ?string $orderBy = null): string;

    /** Quote a table or column name, for reserved words like `user`, `time`, `order`. */
    public function quoteIdentifier(string $identifier): string;

    /**
     * Statements that rename tables, given `[oldName => newName]`.
     *
     * @param array<string, string> $renames
     *
     * @return list<string>
     */
    public function renameTables(array $renames): array;

    /** Collation clause for case-insensitive comparison ('' where the driver needs none). */
    public function collateClause(): string;

    /** The driver's REGEXP operator. */
    public function regexpOperator(bool $not = false): string;

    /** SQL testing whether $needle appears in the comma-separated list $haystack. */
    public function findInSet(string $needle, string $haystack, bool $not = false): string;

    /** SQL reading a text expression as an integer, for ordering by it. */
    public function castToInteger(string $expression): string;

    /**
     * Statements a dump opens with -- session settings and the transaction, joined by ";\n".
     *
     * @return list<string>
     */
    public function dumpPreamble(): array;

    /**
     * @return list<string> statements a dump closes with, commit included
     */
    public function dumpEpilogue(): array;

    /**
     * Toggle foreign-key enforcement while tables are dropped and re-created, or null where the driver has no session-level switch for it.
     */
    public function foreignKeyChecks(bool $enabled): ?string;

    /** Whether a dump produced by this dialect can be generated at all (see ticket 17). */
    public function supportsDump(): bool;

    /**
     * Every statement needed to create the Journal table and its indexes, in execution order (ticket 51 / ADR-0025).
     *
     * @param string $table already prefixed, unquoted
     *
     * @return list<string>
     */
    public function journalDdl(string $table): array;

    /**
     * Every statement needed to remove what journalDdl() created, in execution order.
     *
     * @return list<string>
     */
    public function journalDropDdl(string $table): array;

    /**
     * An INSERT that, when the unique key over $conflictColumns is already taken, applies $assignments to the row that is there instead of failing.
     *
     * @param string                $table           already prefixed, unquoted
     * @param array<string, string> $values          column => the SQL expression to write, `?` for a bound one
     * @param list<string>          $conflictColumns the columns the unique key is over
     * @param array<string, string> $assignments     column => SQL expression, where `:new.<column>`
     *                                               stands for the value the insert would have written
     */
    public function upsert(string $table, array $values, array $conflictColumns, array $assignments): string;

    /**
     * Every statement needed to create the search index table, its ordinary indexes, its full-text index and the queue of Contents awaiting reindexing, in execution order.
     *
     * @param string $table      already prefixed, unquoted
     * @param string $queueTable already prefixed, unquoted
     *
     * @return list<string>
     */
    public function searchIndexDdl(string $table, string $queueTable, string $keywordsTable): array;

    /**
     * Every statement needed to remove what searchIndexDdl() created, in execution order.
     *
     * @return list<string>
     */
    public function searchIndexDropDdl(string $table, string $queueTable, string $keywordsTable): array;

    /**
     * A boolean SQL expression selecting the index rows that match the query, each term matched as a prefix (`atelier` matches `ateliers`).
     *
     * @param list<list<string>> $termGroups non-empty; every group non-empty; every term
     *                                       already sanitised as above
     */
    public function searchMatchExpression(string $table, array $termGroups): string;
}
