<?php

namespace YesWiki\Kernel\Database;

/** PostgreSQL. */
class PostgreSqlDialect implements SqlDialect
{
    public function driverName(): string
    {
        return 'pgsql';
    }

    public function now(): string
    {
        return 'NOW()';
    }

    public function dateSubDays(int $days): string
    {
        return "NOW() - INTERVAL '" . intval($days) . " days'";
    }

    public function dateSubHours(int $hours): string
    {
        return "NOW() - INTERVAL '" . intval($hours) . " hours'";
    }

    public function jsonColumnType(): string
    {
        return 'JSONB';
    }

    public function jsonAsText(string $column): string
    {
        return "($column::text)";
    }

    /** Straight out of the document, with no guard and no cast. */
    public function jsonExtract(string $column, string $path): string
    {
        return "($column #>> ARRAY[" . $this->pathElements($path) . '])';
    }

    public function jsonExtractText(string $column, string $path): string
    {
        return "(CASE WHEN $column ~ '^\\s*\\{' THEN ($column::jsonb #>> ARRAY["
            . $this->pathElements($path) . ']) ELSE NULL END)';
    }

    /** `$.acls.read` -> `'acls', 'read'`. */
    private function pathElements(string $path): string
    {
        $segments = explode('.', (string)preg_replace('/^\$\./', '', $path));

        return implode(', ', array_map(
            static fn (string $segment): string => "'" . str_replace("'", "''", $segment) . "'",
            $segments
        ));
    }

    public function groupConcat(string $column, ?string $orderBy = null): string
    {
        $orderBy = $orderBy ?? $column;

        return "STRING_AGG(DISTINCT $column, ',' ORDER BY $orderBy)";
    }

    public function quoteIdentifier(string $identifier): string
    {
        return '"' . str_replace('"', '""', $identifier) . '"';
    }

    public function collateClause(): string
    {
        return '';
    }

    public function regexpOperator(bool $not = false): string
    {
        return $not ? '!~' : '~';
    }

    public function findInSet(string $needle, string $haystack, bool $not = false): string
    {
        return $not
            ? "($needle != ALL(string_to_array($haystack, ',')))"
            : "($needle = ANY(string_to_array($haystack, ',')))";
    }

    public function castToInteger(string $expression): string
    {
        return "COALESCE(NULLIF(regexp_replace({$expression}, '\\D', '', 'g'), '')::bigint, 0)";
    }

    public function dumpPreamble(): array
    {
        return ['BEGIN'];
    }

    public function dumpEpilogue(): array
    {
        return ['COMMIT'];
    }

    public function foreignKeyChecks(bool $enabled): ?string
    {
        return null;
    }

    /**
     * PostgreSQL has no `SHOW CREATE TABLE`, so DbService::getTableSchema() cannot produce the structure half of a dump.
     */
    public function supportsDump(): bool
    {
        return true;
    }

    public function journalDdl(string $table): array
    {
        $json = $this->jsonColumnType();

        return [
            "CREATE TABLE IF NOT EXISTS \"{$table}\" (
                \"id\" SERIAL PRIMARY KEY,
                \"at\" TIMESTAMP NOT NULL,
                \"last_at\" TIMESTAMP NOT NULL,
                \"repeat\" INTEGER NOT NULL DEFAULT 1,
                \"channel\" VARCHAR(16) NOT NULL,
                \"level\" VARCHAR(16) NOT NULL,
                \"actor\" VARCHAR(191) NOT NULL DEFAULT '',
                \"action\" VARCHAR(191) NOT NULL,
                \"target\" VARCHAR(191) NOT NULL DEFAULT '',
                \"fingerprint\" CHAR(32) DEFAULT NULL,
                \"day\" CHAR(10) DEFAULT NULL,
                \"context\" {$json} DEFAULT NULL
            )",
            "CREATE INDEX IF NOT EXISTS \"{$table}_idx_at\" ON \"{$table}\" (\"at\")",
            "CREATE INDEX IF NOT EXISTS \"{$table}_idx_channel_at\" ON \"{$table}\" (\"channel\", \"at\")",
            "CREATE INDEX IF NOT EXISTS \"{$table}_idx_actor_at\" ON \"{$table}\" (\"actor\", \"at\")",
            "CREATE UNIQUE INDEX IF NOT EXISTS \"{$table}_uniq_fingerprint_day\" ON \"{$table}\" (\"fingerprint\", \"day\")",
        ];
    }

    public function journalDropDdl(string $table): array
    {
        return ["DROP TABLE IF EXISTS \"{$table}\""];
    }

    public function upsert(string $table, array $values, array $conflictColumns, array $assignments): string
    {
        return SqlUpsert::onConflict($this, $table, $values, $conflictColumns, $assignments);
    }

    /** A generated `tsvector` with a GIN index over it. */
    public function searchIndexDdl(string $table, string $queueTable, string $keywordsTable): array
    {
        return [
            "CREATE TABLE IF NOT EXISTS \"{$table}\" (
                \"id\" SERIAL PRIMARY KEY,
                \"tag\" VARCHAR(191) NOT NULL,
                \"acl\" TEXT NOT NULL,
                \"acl_hash\" CHAR(32) NOT NULL,
                \"page_read_acl\" TEXT NOT NULL,
                \"owner\" VARCHAR(191) NOT NULL DEFAULT '',
                \"content_type\" VARCHAR(30) NOT NULL DEFAULT '',
                \"form_id\" VARCHAR(191) NOT NULL DEFAULT '',
                \"title\" TEXT NOT NULL,
                \"text\" TEXT NOT NULL,
                \"updated_at\" TIMESTAMP NOT NULL,
                \"search_vector\" tsvector GENERATED ALWAYS AS (
                    to_tsvector('simple', coalesce(\"title\", '') || ' ' || coalesce(\"text\", ''))
                ) STORED
            )",
            "CREATE INDEX IF NOT EXISTS \"{$table}_idx_tag\" ON \"{$table}\" (\"tag\")",
            "CREATE INDEX IF NOT EXISTS \"{$table}_idx_acl_hash\" ON \"{$table}\" (\"acl_hash\")",
            "CREATE INDEX IF NOT EXISTS \"{$table}_idx_content_type\" ON \"{$table}\" (\"content_type\")",
            "CREATE INDEX IF NOT EXISTS \"{$table}_idx_form_id\" ON \"{$table}\" (\"form_id\")",
            "CREATE INDEX IF NOT EXISTS \"{$table}_ft_text\" ON \"{$table}\" USING GIN (\"search_vector\")",
            "CREATE TABLE IF NOT EXISTS \"{$queueTable}\" (
                \"tag\" VARCHAR(191) PRIMARY KEY,
                \"queued_at\" TIMESTAMP NOT NULL
            )",

            "CREATE TABLE IF NOT EXISTS \"{$keywordsTable}\" (
                \"tag\" VARCHAR(191) NOT NULL,
                \"keyword\" VARCHAR(191) NOT NULL,
                PRIMARY KEY (\"tag\", \"keyword\")
            )",
            "CREATE INDEX IF NOT EXISTS \"{$keywordsTable}_idx_keyword\" ON \"{$keywordsTable}\" (\"keyword\")",
        ];
    }

    public function searchIndexDropDdl(string $table, string $queueTable, string $keywordsTable): array
    {
        return [
            "DROP TABLE IF EXISTS \"{$table}\"",
            "DROP TABLE IF EXISTS \"{$queueTable}\"",
            "DROP TABLE IF EXISTS \"{$keywordsTable}\"",
        ];
    }

    public function searchMatchExpression(string $table, array $termGroups): string
    {
        $groups = array_map(
            static fn (array $alternatives): string => '(' . implode(' | ', array_map(
                static fn (string $term): string => $term . ':*',
                $alternatives
            )) . ')',
            $termGroups
        );

        return "\"{$table}\".\"search_vector\" @@ to_tsquery('simple', '" . implode(' & ', $groups) . "')";
    }

    public function renameTables(array $renames): array
    {
        $statements = [];
        foreach ($renames as $from => $to) {
            $statements[] = 'ALTER TABLE ' . $this->quoteIdentifier($from) . ' RENAME TO ' . $this->quoteIdentifier($to);
        }

        return $statements;
    }
}
