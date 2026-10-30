<?php

namespace YesWiki\Search\Service;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use YesWiki\Kernel\Database\SqlParameters;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Kernel\Service\Queue\QueueStatistics;
use YesWiki\Search\Entity\IndexedContent;
use YesWiki\Search\Message\ReindexContent;

/**
 * Writes the search index (ticket 18 / ADR-0015).
 *
 * Whatever needs a Content reindexed (a save, a rename, a form change, a migration) sends one
 * ReindexContent per Content to the background queue (ADR-0030), whose consumer runs index().
 */
class SearchIndexer
{
    /** How many times a rewrite of the index is replayed after the engine picks it as a deadlock victim. */
    private const CONFLICT_ATTEMPTS = 3;

    private DbService $dbService;
    private SearchIndexSchema $schema;
    private SearchableTextExtractor $extractor;
    private MessageBusInterface $bus;
    private QueueStatistics $queue;

    public function __construct(
        DbService $dbService,
        SearchIndexSchema $schema,
        SearchableTextExtractor $extractor,
        MessageBusInterface $bus,
        QueueStatistics $queue,
    ) {
        $this->dbService = $dbService;
        $this->schema = $schema;
        $this->extractor = $extractor;
        $this->bus = $bus;
        $this->queue = $queue;
    }

    /** Bring one Content's index rows up to date. */
    public function index(string $tag): void
    {
        if (!$this->schema->exists()) {
            return;
        }

        $this->dbService->transactional(function () use ($tag): void {
            $row = $this->dbService->loadSingle(
                "SELECT tag, body, owner, {$this->dbService->quoteIdentifier('time')}, metadata, parent,"
                . " {$this->dbService->quoteIdentifier('type')}"
                . " FROM {$this->dbService->prefixTable('pages')}"
                . " WHERE tag = ? AND latest = 'Y' LIMIT 1"
                . $this->dbService->dialect()->lockRowsClause(),
                [$tag]
            );
            $this->delete($tag);
            $this->write($row ? $this->extractor->extractAll($row) : []);
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
     * Ask the queue to reindex these Contents, one message each.
     *
     * The bus journals a queue it cannot write to and carries on, so saving a Content never fails
     * for want of one.
     *
     * @param list<string> $tags
     */
    public function enqueue(array $tags): void
    {
        if (!$this->schema->exists()) {
            return;
        }

        foreach (array_unique($tags) as $tag) {
            $message = new ReindexContent($tag);
            $this->bus->dispatch(new Envelope($message, [$message->uniqueStamp()]));
        }
    }

    /** Queue every entry of a form -- what a form save or delete costs. */
    public function enqueueForm(string $formId): int
    {
        if (!$this->schema->exists() || $formId === '') {
            return 0;
        }

        $formIdExpr = $this->dbService->jsonExtract('body', '$.form_id');

        return $this->enqueueLatest("{$formIdExpr} = ?", [$formId]);
    }

    /** Queue every Content in the wiki. */
    public function enqueueEverything(): int
    {
        if (!$this->schema->exists()) {
            return 0;
        }

        return $this->enqueueLatest();
    }

    /** How many Contents the index currently holds. */
    public function indexedCount(): int
    {
        if (!$this->schema->exists()) {
            return 0;
        }

        return (int)$this->dbService->scalar("SELECT COUNT(DISTINCT tag) FROM {$this->schema->table()}", 0);
    }

    /** How many Contents wait to be reindexed -- what "index building, N of M" counts down. */
    public function pending(): int
    {
        return $this->queue->waitingOfType(ReindexContent::class);
    }

    /**
     * Queues the latest revision of every Content that matches, and answers how many.
     *
     * @param list<string> $parameters
     */
    private function enqueueLatest(string $condition = '', array $parameters = []): int
    {
        $rows = $this->dbService->loadAll(
            "SELECT DISTINCT tag FROM {$this->dbService->prefixTable('pages')} WHERE latest = 'Y'"
            . ($condition === '' ? '' : " AND {$condition}"),
            $parameters
        );

        $tags = array_map(static fn (array $row): string => (string)$row['tag'], $rows);
        $this->enqueue($tags);

        return count($tags);
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
                    $content->lang,
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
            . ' (tag, lang, acl, acl_hash, page_read_acl, owner, content_type, form_id, title, text, updated_at)'
            . ' VALUES (' . SqlParameters::placeholders(11) . ')'
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
}
