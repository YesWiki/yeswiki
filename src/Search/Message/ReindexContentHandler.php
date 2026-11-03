<?php

namespace YesWiki\Search\Message;

use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use YesWiki\Search\Service\SearchIndexer;

/** Reindexes one Content from its latest revision as it is when the message runs; idempotent. */
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
