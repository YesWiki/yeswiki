<?php

namespace YesWiki\Kernel\Message;

use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use YesWiki\Kernel\Service\Journal;

/** Deletes the Journal entries that have outlived their retention (a year of audit, a fortnight of diagnostics, unless the configuration says otherwise). */
#[AsMessageHandler]
final class PruneJournalHandler
{
    public function __construct(
        private readonly Journal $journal,
    ) {
    }

    public function __invoke(PruneJournal $task): void
    {
        $this->journal->prune();
    }
}
