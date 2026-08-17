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
