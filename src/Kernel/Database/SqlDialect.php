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

    /**
     * SQL expression extracting $path from a JSON $column, e.g. `$.form_id` or `$.acls.read`.
     *
     * **The implementation owns the escaping of $path.** A segment can be a form field name,
     * which is user data, and only the dialect knows the syntax it is being embedded in --
     * MySQL and SQLite take the path whole inside a string literal, PostgreSQL needs one quoted
     * element per segment. Callers must therefore pass the path RAW; pre-escaping it here would
     * double-escape and look for a key that does not exist.
     */
    public function jsonExtract(string $column, string $path): string;

    /** SQL aggregating a column's distinct values into one comma-separated string. */
    public function groupConcat(string $column, ?string $orderBy = null): string;

    /**
     * Quote a table or column name, for reserved words like `user`, `time`, `order`.
     *
     * Quotes the name and escapes the quote character within it. What it does NOT do is make an
     * arbitrary string into a safe identifier: an identifier cannot be bound as a parameter, so
     * a name that came from user data has to be constrained before it gets here (see
     * `SearchManager::asSafeIdentifier()`, which does that for form field names).
     */
    public function quoteIdentifier(string $identifier): string;

    /** Collation clause for case-insensitive comparison ('' where the driver needs none). */
    public function collateClause(): string;

    /** The driver's REGEXP operator. */
    public function regexpOperator(bool $not = false): string;

    /** SQL testing whether $needle appears in the comma-separated list $haystack. */
    public function findInSet(string $needle, string $haystack, bool $not = false): string;

    /**
     * SQL reading a text expression as an integer, for ordering by it.
     *
     * MySQL coerces silently -- `substring(tag, 8) + 0` sorts comment tags numerically and
     * has done for years. PostgreSQL refuses outright ("operator does not exist: text +
     * integer") and takes the whole page down with it, which is how this arrived: the
     * comment list ran on MySQL and SQLite forever and had simply never met a third driver.
     * The three spellings of the cast are different enough to need a dialect.
     */
    public function castToInteger(string $expression): string;

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

    // ---------------------------------------------------------------- search index
    //
    // Ticket 18 / ADR-0015. Text search reads a derived index table, and the full-text
    // index over it is the one part of that table whose *shape* differs per driver:
    // MySQL adds a FULLTEXT KEY to the ordinary table, PostgreSQL a generated tsvector
    // with a GIN index, SQLite a separate FTS5 virtual table kept in sync by triggers.
    //
    // Still string generation only -- the statements are executed by the migration and by
    // the reindex command, both of which hold the PDO link.

    /**
     * Every statement needed to create the search index table, its ordinary indexes, its
     * full-text index and the queue of Contents awaiting reindexing, in execution order.
     *
     * The queue lives here rather than in a driver-neutral CREATE because its column types
     * differ like every other table's, and because a schema half-declared in the dialect and
     * half in a service is how the two drift apart.
     *
     * @param string $table      already prefixed, unquoted
     * @param string $queueTable already prefixed, unquoted
     *
     * @return list<string>
     */
    public function searchIndexDdl(string $table, string $queueTable): array;

    /**
     * Every statement needed to remove what searchIndexDdl() created, in execution order.
     * Used by the reindex command's full rebuild and by an uninstall.
     *
     * @return list<string>
     */
    public function searchIndexDropDdl(string $table, string $queueTable): array;

    /**
     * A boolean SQL expression selecting the index rows that match the query, each term
     * matched as a prefix (`atelier` matches `ateliers`).
     *
     * The query arrives as **groups**: every group must match (AND), and within a group any
     * alternative will do (OR). That shape exists for one reason -- a searched word can also
     * be an enum option's *label*, and the index stores that option's key. So the word and
     * the keys it names travel together as alternatives, and a search for "Atelier" finds
     * entries storing `3`. See FormOptionTranslator.
     *
     * The expression references the index table by name rather than by alias, so callers
     * must not alias it.
     *
     * **Terms are interpolated, not bound.** Every dialect here builds a search-engine query
     * string rather than an SQL scalar, and none of MySQL's boolean mode, PostgreSQL's
     * `to_tsquery` or FTS5's grammar can be parameterised safely from a caller's raw input.
     * The contract is therefore that callers pass terms already reduced to `[\p{L}\p{N}_]+`
     * -- see SearchIndexQuery::parseQuery(), which is the only thing that should ever build
     * them. Anything else is an injection.
     *
     * @param list<list<string>> $termGroups non-empty; every group non-empty; every term
     *                                       already sanitised as above
     */
    public function searchMatchExpression(string $table, array $termGroups): string;
}
