<?php

namespace YesWiki\Search\Message;

use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use YesWiki\Search\Service\SearchIndexer;

/**
 * Reindexes one Content.
 *
 * Idempotent: it reads the Content's latest revision when it runs, and one that is gone by then
 * leaves no rows. A backlog (a new form, `search:reindex --all`) is spread over passes by the
 * consumer's budget, one message at a time.
 */
#[AsMessageHandler]
final class ReindexContentHandler
{
    public function __construct(private readonly SearchIndexer $indexer)
    {
    }

    public function __invoke(ReindexContent $message): void
    {
        $this->indexer->index($message->tag);
    }
}
