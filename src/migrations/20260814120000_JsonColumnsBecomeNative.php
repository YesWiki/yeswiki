<?php

use YesWiki\Admin\Service\AdministrativeLogService;
use YesWiki\Core\YesWikiMigration;
use YesWiki\Kernel\Service\DbService;

/**
 * Ticket 38 / ADR-0018: `pages.body` and `pages.metadata` become the dialect's own JSON type.
 *
 * `JSON` on MySQL, `JSONB` on PostgreSQL, and nothing at all on SQLite, which has no such type
 * -- there both stay `TEXT` and this migration is a deliberate no-op rather than a pretend
 * success.
 *
 * ## Why, in one line each
 *
 * **`body`** has been JSON since ticket 09, but the column has not, so both servers have had to
 * *prove* it on every row they look at. On PostgreSQL that is a regex and a text-to-jsonb cast
 * per row **per extracted field**, which an entry list pays once for every column it displays:
 * 1947 ms against 78 ms on a 200k-row corpus.
 *
 * **`metadata`** is where a page's ACLs live, and `AclService::updateRequestWithACL()` reads
 * `$.acls.read` out of it in a predicate pasted into `PageManager::getAll()` and
 * `SearchManager` -- so it runs on every listing query in the wiki, and the predicate repeats
 * that expression once per needed ACL entry. Four evaluations per row for an anonymous visitor,
 * a dozen or so for an administrator in a few groups. 147 ms against 57 ms on PostgreSQL.
 *
 * Both columns are on the same table, so they are converted in the **same** ALTER pass: this is
 * a full table rebuild and a wiki that had to sit through two of them for one decision would be
 * paying for how the work was scheduled rather than for what it does.
 *
 * ## How long it takes
 *
 * The wiki is unusable for the duration, so it says how long before it starts, from the row
 * count and the rates ticket 19 measured (2.3s per 100k rows on MySQL, 1.0s on PostgreSQL). At
 * a million Contents that is roughly 23s and 10s.
 *
 * ## The row that is not JSON
 *
 * A native column validates on write, so the ALTER fails if any row holds something that is not
 * a JSON document -- and the driver's message for that names neither the row nor the reason in
 * terms anyone running an upgrade can act on. So they are looked for first where the dialect
 * can answer cheaply, and the ALTER is wrapped either way: whichever finds them, this migration
 * refuses, names the pages and leaves the columns as they were. It refuses by **throwing**, so
 * it stays pending rather than being recorded as run.
 *
 * For `body` that is a wiki whose ticket-09 migration did not finish, and
 * `./yeswicli content:migrate-bodies` is what fixes it. For `metadata`, NULL is fine and stays
 * NULL -- the column is nullable and 181 of 3413 rows on a real install carry none -- but an
 * empty string is not a document, and that is what the check is looking for.
 *
 * Idempotent: a column already of the right type is left alone, so a wiki that got half way
 * through can be run again.
 */
class JsonColumnsBecomeNative extends YesWikiMigration
{
    /** Seconds per 100k rows, measured in ticket 19 on the compose stack. */
    private const SECONDS_PER_100K = ['mysql' => 2.3, 'pgsql' => 1.0];

    /** column => whether it is NOT NULL. `metadata` is nullable and stays that way. */
    private const COLUMNS = ['body' => true, 'metadata' => false];

    public function run()
    {
        $db = $this->getService(DbService::class);
        $log = $this->getService(AdministrativeLogService::class);

        $type = $db->jsonColumnType();
        if ($type === 'TEXT') {
            // SQLite. Nothing to do, and saying so beats a silent success that looks like the
            // other two drivers did something they did not.
            $log->log('migration', 'body and metadata stay TEXT on ' . $db->getDriver()
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
            $this->refuseBecauseOfNonJsonValues($db, $log, $pages, $column, $type);
        }

        $rows = (int)$db->scalar("SELECT COUNT(*) FROM {$pages}", 0);
        $log->log('migration', sprintf(
            'Converting %s (%s) from text to %s: %d revisions, expect roughly %.0fs (ADR-0018).',
            trim($pages),
            implode(', ', array_keys($pending)),
            $type,
            $rows,
            $rows / 100000 * (self::SECONDS_PER_100K[$db->getDriver()] ?? 2.3) * count($pending)
        ));

        foreach ($pending as $column => $notNull) {
            try {
                // Outside a transaction on purpose: MySQL implicitly commits on DDL, so
                // wrapping this would leave commit() with nothing to commit -- the same trap
                // InstallationController documents around its CREATE TABLEs. `USING` is
                // PostgreSQL's requirement; it has no implicit text-to-jsonb cast and refuses
                // the ALTER without one.
                $db->schema()->modifyColumn('pages', $column, $type, $notNull, "{$column}::jsonb");
            } catch (Throwable $failure) {
                // The pre-check above is an optimisation -- it saves a doomed table rebuild
                // where the dialect can answer cheaply -- but it is not what makes this
                // correct. This is: whatever the server rejected, the person running the
                // upgrade gets told which pages rather than the driver's own message about a
                // row it will not name.
                $this->refuse($log, $this->diagnose($db, $pages, $column, $type, $failure));
            }
        }
    }

    /**
     * Log it, then throw -- because MigrationService only records a migration as run when it
     * returns, and a refusal that returns normally would mark this one done forever. The whole
     * remedy is "fix the rows and run it again", which has to still be possible.
     *
     * @throws Exception always
     */
    private function refuse(AdministrativeLogService $log, string $message): never
    {
        $log->log('migration', $message);

        throw new Exception($message);
    }

    /**
     * Whether the column is already the type we would set it to.
     *
     * The two drivers spell it differently in their catalogs -- MySQL's `COLUMN_TYPE` is
     * `json`, PostgreSQL's `data_type` is `jsonb` -- and `getColumnInfo()` lowercases both, so
     * the comparison is against the lowercased target and nothing more clever is needed.
     */
    private function alreadyNative(DbService $db, string $column, string $targetType): bool
    {
        $current = $db->schema()->getColumnInfo('pages', $column);

        return $current !== null && (string)$current['type'] === strtolower($targetType);
    }

    /**
     * Refuse before rebuilding the table, where the dialect can say so cheaply.
     *
     * MySQL has had `JSON_VALID()` since 5.7. PostgreSQL got an `IS JSON` predicate only in 16,
     * and there is no safe pre-16 spelling -- a `::jsonb` cast inside a `WHERE` throws on the
     * first bad row instead of returning it -- so on an older server the question simply is not
     * asked, and the `catch` around the ALTER is what handles it.
     *
     * NULL is not a violation in either dialect's spelling: `JSON_VALID(NULL)` is NULL rather
     * than 0, and `NULL IS NOT JSON OBJECT` is false. That is the behaviour `metadata` needs.
     */
    private function refuseBecauseOfNonJsonValues(DbService $db, AdministrativeLogService $log, string $pages, string $column, string $type): void
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

        $this->refuse($log, $this->refusalMessage($column, $type, array_map('strval', $tags)));
    }

    private function postgresUnderstandsIsJson(DbService $db): bool
    {
        return (int)$db->scalar("SELECT current_setting('server_version_num')", 0) >= 160000;
    }

    /**
     * Name the pages the server would not accept, after it has refused the whole ALTER.
     *
     * Decodes in PHP because by definition the dialect had no predicate to offer -- and that is
     * affordable here in a way it would not be as a routine pre-check: this runs only on a wiki
     * that is already broken, and reading its rows once is cheaper than leaving whoever is
     * upgrading with nothing but "invalid JSON text" and a table of unknown size.
     */
    private function diagnose(DbService $db, string $pages, string $column, string $type, Throwable $failure): string
    {
        $tags = [];
        foreach ($db->loadAll("SELECT tag, {$column} AS value FROM {$pages}") as $row) {
            if (count($tags) >= 100) {
                break;
            }
            // NULL is allowed wherever the column is nullable, and is not what broke this
            if ($row['value'] !== null && !is_array(json_decode((string)$row['value'], true))) {
                $tags[] = (string)($row['tag'] ?? '');
            }
        }

        return $tags === []
            // the rows are all fine, so it was something else -- a lock, a disk, a permission.
            // Repeating the driver's message is the useful thing to do here, not diagnosing it.
            ? "{$column} could not become a {$type} column, and every value is valid JSON, so this "
                . 'is not about the data: ' . $failure->getMessage()
            : $this->refusalMessage($column, $type, $tags);
    }

    /** @param list<string> $tags */
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
