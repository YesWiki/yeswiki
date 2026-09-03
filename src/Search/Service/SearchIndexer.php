<?php

namespace YesWiki\Search\Service;

use YesWiki\Content\Entity\PageBody;
use YesWiki\Kernel\Database\SqlParameters;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Search\Entity\IndexedContent;

/** Writes the search index (ticket 18 / ADR-0015). */
class SearchIndexer
{
    /** How many tags one statement handles. */
    private const INSERT_BATCH = 100;

    /**
     * How many times a rewrite of the index may be replayed after the engine picks it as a
     * deadlock victim.
     *
     * Claiming (ticket 54) is what stops two drains holding the same tags, so this is the backstop
     * it was meant to be rather than the mechanism: the engine has already rolled the victim back,
     * and the undone one is what this replays.
     */
    private const CONFLICT_ATTEMPTS = 3;

    /**
     * How long a claim is honoured before another drain may take the rows back.
     *
     * A drain that is killed mid-pass -- a page view that timed out, a container that went away --
     * leaves its chunk stamped with nobody left to work it. Expiring the claim is what keeps that
     * from being a queue entry nothing ever picks up again.
     */
    private const CLAIM_TTL_HOURS = 1;

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

    /** Bring one Content's index rows up to date, and take it off the queue. */
    public function index(string $tag): void
    {
        if (!$this->schema->exists()) {
            return;
        }

        $row = $this->dbService->loadSingle(
            "SELECT tag, body, owner, {$this->dbService->quoteIdentifier('time')}, metadata, parent,"
            . " {$this->dbService->quoteIdentifier('type')}"
            . " FROM {$this->dbService->prefixTable('pages')}"
            . " WHERE tag = ? AND latest = 'Y' LIMIT 1",
            [$tag]
        );

        $content = $row ? $this->extractor->extract($row) : null;

        $this->dbService->transactional(function () use ($tag, $content): void {
            $this->delete($tag);
            if ($content !== null) {
                $this->write([$content]);
            }
            $this->dequeue([$tag]);
        }, self::CONFLICT_ATTEMPTS);
    }

    /** Remove a Content from the index entirely -- it was deleted. */
    public function delete(string $tag): void
    {
        if (!$this->schema->exists()) {
            return;
        }
        $this->dbService->query(
            "DELETE FROM {$this->schema->table()} WHERE tag = ?",
            [$tag]
        );

        $this->dbService->query(
            "DELETE FROM {$this->schema->keywordsTable()} WHERE tag = ?",
            [$tag]
        );
    }

    /** Follow a rename. */
    public function rename(string $oldTag, string $newTag): void
    {
        if (!$this->schema->exists()) {
            return;
        }
        $this->dbService->query(
            "UPDATE {$this->schema->table()} SET tag = ? WHERE tag = ?",
            [$newTag, $oldTag]
        );

        $this->dbService->query(
            "UPDATE {$this->schema->keywordsTable()} SET tag = ? WHERE tag = ?",
            [$newTag, $oldTag]
        );

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
        $insert = $this->dbService->prepare(
            "INSERT INTO {$this->schema->queueTable()} (tag, queued_at) VALUES (?, {$now})"
        );

        foreach (array_chunk(array_values(array_unique($tags)), self::INSERT_BATCH) as $chunk) {
            $this->dbService->query(
                "DELETE FROM {$this->schema->queueTable()} WHERE tag IN (" . SqlParameters::placeholders(count($chunk)) . ')',
                $chunk
            );

            foreach ($chunk as $tag) {
                $insert->execute([$tag]);
            }
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
            "SELECT tag FROM {$pages} WHERE latest = 'Y' AND {$formIdExpr} = ?",
            [$formId]
        );

        $tags = array_map(static fn (array $row): string => (string)$row['tag'], $rows);
        $this->enqueue($tags);

        return count($tags);
    }

    /** Queue every Content in the wiki. */
    public function enqueueEverything(): int
    {
        if (!$this->schema->exists()) {
            return 0;
        }

        $pages = $this->dbService->prefixTable('pages');
        $queue = $this->schema->queueTable();
        $now = $this->dbService->now();

        $this->dbService->query("DELETE FROM {$queue}");
        $this->dbService->query(
            "INSERT INTO {$queue} (tag, queued_at) SELECT DISTINCT tag, {$now} FROM {$pages} WHERE latest = 'Y'"
        );

        return $this->pending();
    }

    /** How many Contents the index currently holds. */
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
        $pages = $this->dbService->prefixTable('pages');
        $timeCol = $this->dbService->quoteIdentifier('time');
        $typeCol = $this->dbService->quoteIdentifier('type');

        $done = 0;
        while ($done < $limit) {
            $chunkSize = min(self::INSERT_BATCH, $limit - $done);
            $claim = bin2hex(random_bytes(16));
            $tags = $this->claim($chunkSize, $claim);
            if ($tags === []) {
                break;
            }

            $inList = SqlParameters::placeholders(count($tags));

            $rows = $this->dbService->loadAll(
                "SELECT tag, body, owner, {$timeCol} AS {$timeCol}, metadata, parent, {$typeCol}"
                . " FROM {$pages}"
                . " WHERE latest = 'Y' AND tag IN ({$inList})",
                $tags
            );

            $contents = [];
            foreach ($rows as $row) {
                $row['body'] = PageBody::decode($row['body'] ?? null);
                $content = $this->extractor->extract($row);
                if ($content !== null) {
                    $contents[] = $content;
                }
            }

            $this->dbService->transactional(function () use ($inList, $tags, $contents, $claim): void {
                $this->dbService->query("DELETE FROM {$this->schema->table()} WHERE tag IN ({$inList})", $tags);

                $this->dbService->query("DELETE FROM {$this->schema->keywordsTable()} WHERE tag IN ({$inList})", $tags);
                $this->write($contents);
                $this->dequeue($tags, $claim);
            }, self::CONFLICT_ATTEMPTS);

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
            $buckets = $content->buckets === [] ? ['' => ''] : $content->buckets;
            foreach ($buckets as $acl => $text) {
                $rows[] = [
                    $content->tag,
                    (string)$acl,
                    md5((string)$acl),
                    $content->pageReadAcl,
                    $content->owner,
                    $content->contentType,
                    $content->formId,
                    $content->title,
                    (string)$text,
                    $content->updatedAt,
                ];
            }
        }

        if ($rows === []) {
            return;
        }

        $insert = $this->dbService->prepare(
            "INSERT INTO {$this->schema->table()}"
            . ' (tag, acl, acl_hash, page_read_acl, owner, content_type, form_id, title, text, updated_at)'
            . ' VALUES (' . SqlParameters::placeholders(10) . ')'
        );
        foreach ($rows as $row) {
            $insert->execute($row);
        }

        $this->writeKeywords($contents);
    }

    /**
     * The (Content, keyword) rows behind the `tags=` filter.
     *
     * @param list<IndexedContent> $contents
     */
    private function writeKeywords(array $contents): void
    {
        $pairs = [];
        foreach ($contents as $content) {
            foreach ($content->keywords as $keyword) {
                $pairs[$content->tag . "\0" . $keyword] = [$content->tag, $keyword];
            }
        }
        if ($pairs === []) {
            return;
        }

        $insert = $this->dbService->prepare(
            "INSERT INTO {$this->schema->keywordsTable()} (tag, keyword) VALUES ("
            . SqlParameters::placeholders(2) . ')'
        );
        foreach ($pairs as $pair) {
            $insert->execute($pair);
        }
    }

    /**
     * @param list<string> $tags
     */
    private function dequeue(array $tags, ?string $claim = null): void
    {
        if ($tags === []) {
            return;
        }
        // With a claim, only the rows this drain took are removed: a save that re-queued a tag
        // while it was being indexed replaces the row and clears the claim, so its work survives
        // instead of being deleted by the pass that started before it.
        $held = $claim === null ? '' : ' AND claimed_by = ?';
        foreach (array_chunk($tags, self::INSERT_BATCH) as $chunk) {
            $this->dbService->query(
                "DELETE FROM {$this->schema->queueTable()} WHERE tag IN (" . SqlParameters::placeholders(count($chunk)) . ')' . $held,
                $claim === null ? $chunk : [...$chunk, $claim]
            );
        }
    }

    /**
     * Take the next chunk of the queue for this drain alone, and answer what it got.
     *
     * Two drains used to pick their chunk before opening a transaction, so a visitor's maintenance
     * sweep, `search:reindex` and the browser suite could all hold the same tags and deadlock over
     * them (ticket 54). Claiming is one UPDATE, which every dialect applies atomically, so the
     * second drain reads back nothing and moves on to the next chunk instead of the same one.
     *
     * @return list<string>
     */
    private function claim(int $chunkSize, string $claim): array
    {
        $queue = $this->schema->queueTable();
        $free = '(claimed_at IS NULL OR claimed_at < ' . $this->dbService->dateSubHours(self::CLAIM_TTL_HOURS) . ')';

        return $this->dbService->transactional(function () use ($queue, $free, $chunkSize, $claim): array {
            $candidates = $this->dbService->loadAll(
                "SELECT tag FROM {$queue} WHERE {$free} ORDER BY queued_at ASC, tag ASC LIMIT {$chunkSize}"
            );
            if ($candidates === []) {
                return [];
            }

            $tags = array_map(static fn (array $row): string => (string)$row['tag'], $candidates);
            $this->dbService->query(
                "UPDATE {$queue} SET claimed_at = {$this->dbService->now()}, claimed_by = ?"
                . ' WHERE tag IN (' . SqlParameters::placeholders(count($tags)) . ") AND {$free}",
                [$claim, ...$tags]
            );

            $taken = $this->dbService->loadAll("SELECT tag FROM {$queue} WHERE claimed_by = ?", [$claim]);

            return array_map(static fn (array $row): string => (string)$row['tag'], $taken);
        }, self::CONFLICT_ATTEMPTS);
    }
}
