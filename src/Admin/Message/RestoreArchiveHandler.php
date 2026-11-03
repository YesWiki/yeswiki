<?php

namespace YesWiki\Admin\Message;

use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use YesWiki\Admin\Service\ArchiveService;

/**
 * Puts back the backup a RestoreArchive names, reporting under its uid.
 * It leaves the wiki's status alone, as does the configuration it brings back: a failed restore has no status to lift.
 */
#[AsMessageHandler]
final class RestoreArchiveHandler
{
    public function __construct(
        private readonly ArchiveService $archives,
    ) {
    }

    public function __invoke(RestoreArchive $restore): void
    {
        $this->archives->restoreArchive(
            $restore->filename,
            $restore->restoreFiles,
            $restore->restoreDatabase,
            $restore->rewriteUrls,
            $restore->uid
        );
    }
}
