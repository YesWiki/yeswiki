<?php

use YesWiki\Core\YesWikiMigration;
use YesWiki\Kernel\Service\DbService;

/** Ticket 38 / ADR-0018: `pages.body` and `pages.metadata` become the dialect's own JSON type. */
class JsonColumnsBecomeNative extends YesWikiMigration
{
    /** Seconds per 100k rows, measured in ticket 19 on the compose stack. */
    private const SECONDS_PER_100K = ['mysql' => 2.3, 'pgsql' => 1.0];

    /** column => whether it is NOT NULL. */
    private const COLUMNS = ['body' => true, 'metadata' => false];

    public function run()
    {
        $db = $this->getService(DbService::class);

        $type = $db->jsonColumnType();
        if ($type === 'TEXT') {
            $this->say('body and metadata stay TEXT on ' . $db->getDriver()
                . ': this dialect has no JSON column type (ADR-0018).');

            return;
        }

        $pending = array_filter(
            self::COLUMNS,
            fn (bool $notNull, string $column) => !$this->alreadyNative($db, $column, $type),
            ARRAY_FILTER_USE_BOTH
        );
        if ($pending === []) {
            return;
        }

        $pages = $db->prefixTable('pages');
        foreach (array_keys($pending) as $column) {
            $this->refuseBecauseOfNonJsonValues($db, $pages, $column, $type);
        }

        $rows = (int)$db->scalar("SELECT COUNT(*) FROM {$pages}", 0);
        $this->say(sprintf(
            'Converting %s (%s) from text to %s: %d revisions, expect roughly %.0fs (ADR-0018).',
            trim($pages),
            implode(', ', array_keys($pending)),
            $type,
            $rows,
            $rows / 100000 * (self::SECONDS_PER_100K[$db->getDriver()] ?? 2.3) * count($pending)
        ));

        foreach ($pending as $column => $notNull) {
            try {
                $db->schema()->modifyColumn('pages', $column, $type, $notNull, "{$column}::jsonb");
            } catch (Throwable $failure) {
                $this->refuse($this->diagnose($db, $pages, $column, $type, $failure));
            }
        }
    }

    /**
     * Say it, then throw -- because MigrationService only records a migration as run when it returns, and a refusal that returns normally would mark this one done forever.
     *
     * @throws Exception always
     */
    private function refuse(string $message): never
    {
        $this->say($message);

        throw new Exception($message);
    }

    /** Whether the column is already the type we would set it to. */
    private function alreadyNative(DbService $db, string $column, string $targetType): bool
    {
        $current = $db->schema()->getColumnInfo('pages', $column);

        return $current !== null && (string)$current['type'] === strtolower($targetType);
    }

    /** Refuse before rebuilding the table, where the dialect can say so cheaply. */
    private function refuseBecauseOfNonJsonValues(DbService $db, string $pages, string $column, string $type): void
    {
        $predicate = match ($db->getDriver()) {
            'mysql' => "JSON_VALID({$column}) = 0",
            'pgsql' => $this->postgresUnderstandsIsJson($db) ? "{$column} IS NOT JSON OBJECT" : null,
            default => null,
        };
        if ($predicate === null) {
            return;
        }

        $tags = array_column($db->loadAll("SELECT tag FROM {$pages} WHERE {$predicate} LIMIT 100"), 'tag');
        if ($tags === []) {
            return;
        }

        $this->refuse($this->refusalMessage($column, $type, array_map('strval', $tags)));
    }

    private function postgresUnderstandsIsJson(DbService $db): bool
    {
        return (int)$db->scalar("SELECT current_setting('server_version_num')", 0) >= 160000;
    }

    /** Name the pages the server would not accept, after it has refused the whole ALTER. */
    private function diagnose(DbService $db, string $pages, string $column, string $type, Throwable $failure): string
    {
        $tags = [];
        foreach ($db->loadAll("SELECT tag, {$column} AS value FROM {$pages}") as $row) {
            if (count($tags) >= 100) {
                break;
            }

            if ($row['value'] !== null && !is_array(json_decode((string)$row['value'], true))) {
                $tags[] = (string)($row['tag'] ?? '');
            }
        }

        return $tags === []

            ? "{$column} could not become a {$type} column, and every value is valid JSON, so this "
                . 'is not about the data: ' . $failure->getMessage()
            : $this->refusalMessage($column, $type, $tags);
    }

    /**
     * @param list<string> $tags
     */
    private function refusalMessage(string $column, string $type, array $tags): string
    {
        return sprintf(
            '%s could not become a %s column: %d row(s) hold something that is not a JSON '
            . 'document, and the column would reject them. Pages: %s. %sThen run this migration again.',
            $column,
            $type,
            count($tags),
            implode(', ', array_slice($tags, 0, 20)) . (count($tags) > 20 ? ', ...' : ''),
            $column === 'body' ? 'Run `./yeswicli content:migrate-bodies`. ' : ''
        );
    }
}
