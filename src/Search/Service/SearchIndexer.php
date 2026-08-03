<?php

namespace YesWiki\Search\Service;

use YesWiki\Content\Entity\PageBody;
use YesWiki\Content\Service\TripleStore;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Search\Entity\IndexedContent;

/**
 * Writes the search index (ticket 18 / ADR-0015).
 *
 * Two ways in, deliberately:
 *
 * - **`index($tag)`** for one Content, called from an event. Cheap, synchronous, and what
 *   keeps an ordinary edit findable a moment later.
 * - **`enqueue()` / `drain()`** for everything else, because a *form* change invalidates
 *   every entry under it, and on a wiki of hundreds of thousands that cannot happen inside
 *   the request that saved the form.
 *
 * The queue is the source of truth for outstanding work, not the process that drains it.
 * `search:reindex` spawned asynchronously is only an accelerator: on a host where
 * `proc_open` is unavailable the queue is still correct and still drains, just from the
 * maintenance hook instead. Nothing is ever "lost because the spawn failed".
 */
class SearchIndexer
{
    /** Rows written per INSERT. Kept modest: some hosts cap max_allowed_packet hard. */
    private const INSERT_BATCH = 100;

    private DbService $dbService;
    private SearchIndexSchema $schema;
    private SearchableTextExtractor $extractor;

    public function __construct(
        DbService $dbService,
        SearchIndexSchema $schema,
        SearchableTextExtractor $extractor,
    ) {
        $this->dbService = $dbService;
        $this->schema = $schema;
        $this->extractor = $extractor;
    }

    /**
     * Bring one Content's index rows up to date, and take it off the queue.
     *
     * Delete-then-insert rather than update: a Content's *number* of rows changes when a
     * field ACL does, so there is nothing stable to update against.
     */
    public function index(string $tag): void
    {
        if (!$this->schema->exists()) {
            return;
        }

        $row = $this->dbService->loadSingle(
            "SELECT tag, body, owner, {$this->dbService->quoteIdentifier('time')}, metadata, comment_on"
            . " FROM {$this->dbService->prefixTable('pages')}"
            . " WHERE tag = '{$this->dbService->escape($tag)}' AND latest = 'Y' LIMIT 1"
        );

        $this->delete($tag);
        if ($row) {
            $content = $this->extractor->extract($row);
            if ($content !== null) {
                $this->write([$content]);
            }
        }
        $this->dequeue([$tag]);
    }

    /** Remove a Content from the index entirely -- it was deleted. */
    public function delete(string $tag): void
    {
        if (!$this->schema->exists()) {
            return;
        }
        $this->dbService->query(
            "DELETE FROM {$this->schema->table()} WHERE tag = '{$this->dbService->escape($tag)}'"
        );
    }

    /** Follow a rename. Cheaper than reindexing, and a rename changes nothing else. */
    public function rename(string $oldTag, string $newTag): void
    {
        if (!$this->schema->exists()) {
            return;
        }
        $this->dbService->query(
            "UPDATE {$this->schema->table()} SET tag = '{$this->dbService->escape($newTag)}'"
            . " WHERE tag = '{$this->dbService->escape($oldTag)}'"
        );
        // the title of an untitled Content *is* its tag, so it moved too
        $this->enqueue([$newTag]);
    }

    /**
     * Mark Contents as needing reindexing.
     *
     * @param list<string> $tags
     */
    public function enqueue(array $tags): void
    {
        if (!$this->schema->exists() || $tags === []) {
            return;
        }

        $now = $this->dbService->now();
        foreach (array_chunk(array_values(array_unique($tags)), self::INSERT_BATCH) as $chunk) {
            // delete-then-insert instead of an upsert: ON CONFLICT / ON DUPLICATE KEY is
            // spelled three different ways across the drivers, and re-queueing an already
            // queued tag only has to end with exactly one row either way
            $list = implode(', ', array_map(fn (string $tag): string => "'{$this->dbService->escape($tag)}'", $chunk));
            $this->dbService->query("DELETE FROM {$this->schema->queueTable()} WHERE tag IN ({$list})");

            $values = implode(', ', array_map(
                fn (string $tag): string => "('{$this->dbService->escape($tag)}', {$now})",
                $chunk
            ));
            $this->dbService->query("INSERT INTO {$this->schema->queueTable()} (tag, queued_at) VALUES {$values}");
        }
    }

    /** Queue every entry of a form -- what a form save or delete costs. */
    public function enqueueForm(string $formId): int
    {
        if (!$this->schema->exists() || $formId === '') {
            return 0;
        }

        $pages = $this->dbService->prefixTable('pages');
        $formIdExpr = $this->dbService->jsonExtract('body', '$.form_id');
        $rows = $this->dbService->loadAll(
            "SELECT tag FROM {$pages} WHERE latest = 'Y' AND {$formIdExpr} = '{$this->dbService->escape($formId)}'"
        );

        $tags = array_values(array_map(static fn (array $row): string => (string)$row['tag'], $rows));
        $this->enqueue($tags);

        return count($tags);
    }

    /** Queue every Content in the wiki. The migration's first fill, and `--rebuild`. */
    public function enqueueEverything(): int
    {
        if (!$this->schema->exists()) {
            return 0;
        }

        $pages = $this->dbService->prefixTable('pages');
        $queue = $this->schema->queueTable();
        $now = $this->dbService->now();

        // one INSERT ... SELECT rather than a million round trips: this runs inside an
        // upgrade, where the whole point is that it cannot take long enough to time out
        $this->dbService->query("DELETE FROM {$queue}");
        $this->dbService->query(
            "INSERT INTO {$queue} (tag, queued_at) SELECT DISTINCT tag, {$now} FROM {$pages} WHERE latest = 'Y'"
        );

        return $this->pending();
    }

    /**
     * How many Contents the index currently holds.
     *
     * Used to tell "not built yet" from "nothing matched", which have to read differently to
     * a visitor. Note it is NOT the same question as `pending() > 0`: after a form edit the
     * queue is long while the index is perfectly usable, and search must stay available.
     */
    public function indexedCount(): int
    {
        if (!$this->schema->exists()) {
            return 0;
        }

        return (int)$this->dbService->scalar("SELECT COUNT(DISTINCT tag) FROM {$this->schema->table()}", 0);
    }

    /** How much work is outstanding -- what "index building, N of M" counts down. */
    public function pending(): int
    {
        if (!$this->schema->exists()) {
            return 0;
        }

        return (int)$this->dbService->scalar("SELECT COUNT(*) FROM {$this->schema->queueTable()}", 0);
    }

    /**
     * Reindex up to $limit queued Contents, in one pass.
     *
     * Rows are read and written in bulk: the per-Content `index()` path costs a SELECT, a
     * DELETE and an INSERT each, which is fine for one edit and ruinous for a first fill.
     *
     * @param int      $limit         how many Contents to take off the queue
     * @param int|null $timeBudgetSec stop early once this much wall clock has gone, so the
     *                                maintenance hook cannot stall an ordinary page view
     *
     * @return int how many were reindexed
     */
    public function drain(int $limit = 200, ?int $timeBudgetSec = null): int
    {
        if (!$this->schema->exists() || $limit <= 0) {
            return 0;
        }

        $startedAt = microtime(true);
        $queue = $this->schema->queueTable();
        $pages = $this->dbService->prefixTable('pages');
        $triples = $this->dbService->prefixTable('triples');
        $timeCol = $this->dbService->quoteIdentifier('time');
        $typeUri = TripleStore::TYPE_URI;

        $done = 0;
        while ($done < $limit) {
            $chunkSize = min(self::INSERT_BATCH, $limit - $done);
            $queued = $this->dbService->loadAll(
                "SELECT tag FROM {$queue} ORDER BY queued_at ASC, tag ASC LIMIT {$chunkSize}"
            );
            if ($queued === []) {
                break;
            }

            $tags = array_values(array_map(static fn (array $row): string => (string)$row['tag'], $queued));
            $list = implode(', ', array_map(fn (string $tag): string => "'{$this->dbService->escape($tag)}'", $tags));

            // the type triple is joined in rather than looked up per row: at a million
            // Contents that lookup is the whole cost of a rebuild
            $rows = $this->dbService->loadAll(
                "SELECT p.tag, p.body, p.owner, p.{$timeCol} AS {$timeCol}, p.metadata, p.comment_on,"
                . ' tp.value AS type_triple'
                . " FROM {$pages} p"
                . " LEFT JOIN {$triples} tp ON tp.resource = p.tag AND tp.property = '{$typeUri}'"
                . " WHERE p.latest = 'Y' AND p.tag IN ({$list})"
            );

            $contents = [];
            foreach ($rows as $row) {
                $row['body'] = PageBody::decode($row['body'] ?? null);
                $content = $this->extractor->extract($row, (string)($row['type_triple'] ?? ''));
                if ($content !== null) {
                    $contents[] = $content;
                }
            }

            // every queued tag is cleared out of the index first, including the ones that
            // turned out to have no row (deleted since queueing) and the ones that index to
            // nothing -- otherwise a Content that became empty would keep its old text
            $this->dbService->query("DELETE FROM {$this->schema->table()} WHERE tag IN ({$list})");
            $this->write($contents);
            $this->dequeue($tags);

            $done += count($tags);

            if ($timeBudgetSec !== null && (microtime(true) - $startedAt) >= $timeBudgetSec) {
                break;
            }
        }

        return $done;
    }

    /**
     * @param list<IndexedContent> $contents
     */
    private function write(array $contents): void
    {
        $rows = [];
        foreach ($contents as $content) {
            // A Content with a name but no field text still has to be findable *by that
            // name*: an uploaded file has nothing but its filename, an account nothing but
            // its login, and one row per ACL bucket meant zero rows for both -- so no
            // uploaded file and no account was in the index at all, and neither could be
            // searched or counted. The title lives in its own indexed column, so the
            // fallback row is the public bucket with no text; `page_read_acl` still decides
            // who may see it. `extract()` has already dropped the ones that are truly empty.
            $buckets = $content->buckets === [] ? ['' => ''] : $content->buckets;
            foreach ($buckets as $acl => $text) {
                $rows[] = '('
                    . "'{$this->dbService->escape($content->tag)}', "
                    . "'{$this->dbService->escape((string)$acl)}', "
                    . "'" . md5((string)$acl) . "', "
                    . "'{$this->dbService->escape($content->pageReadAcl)}', "
                    . "'{$this->dbService->escape($content->owner)}', "
                    . "'{$this->dbService->escape($content->contentType)}', "
                    . "'{$this->dbService->escape($content->formId)}', "
                    . "'{$this->dbService->escape($content->title)}', "
                    . "'{$this->dbService->escape($text)}', "
                    . "'{$this->dbService->escape($content->updatedAt)}')";
            }
        }

        foreach (array_chunk($rows, self::INSERT_BATCH) as $chunk) {
            $this->dbService->query(
                "INSERT INTO {$this->schema->table()}"
                . ' (tag, acl, acl_hash, page_read_acl, owner, content_type, form_id, title, text, updated_at)'
                . ' VALUES ' . implode(', ', $chunk)
            );
        }
    }

    /** @param list<string> $tags */
    private function dequeue(array $tags): void
    {
        if ($tags === []) {
            return;
        }
        foreach (array_chunk($tags, self::INSERT_BATCH) as $chunk) {
            $list = implode(', ', array_map(fn (string $tag): string => "'{$this->dbService->escape($tag)}'", $chunk));
            $this->dbService->query("DELETE FROM {$this->schema->queueTable()} WHERE tag IN ({$list})");
        }
    }
}
