<?php

namespace YesWiki\Admin\Message;

use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use YesWiki\Admin\Service\ArchiveService;

/**
 * Puts back the backup a RestoreArchive names, reporting under its uid.
 *
 * Unlike a backup, a restore leaves the wiki's status alone, and so does the configuration it
 * brings back (`wiki_status` is one of the settings that stay this wiki's own): a failed restore
 * has no read-only status of its own to lift, and lifting one would end another run's.
 *
 * A copy retried from the jobs page (one by one or with "retry all") is refused: a restore is
 * never replayed behind the admin's back (see ArchiveService::isUnderWay()).
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
        if (!$this->archives->isUnderWay($restore->uid)) {
            throw new UnrecoverableMessageHandlingException(_t('AU_RUN_NOT_REPLAYED'));
        }
        $this->archives->restoreArchive(
            $restore->filename,
            $restore->restoreFiles,
            $restore->restoreDatabase,
            $restore->rewriteUrls,
            $restore->uid
        );
    }
}
