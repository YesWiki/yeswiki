<?php

namespace YesWiki\Admin\Message;

use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use YesWiki\Admin\Service\ArchiveService;

/**
 * Makes the backup a CreateArchive asks for, reporting under its uid.
 *
 * ArchiveService::archive() sets the wiki read-only (`archiving`) while it works and lifts it on
 * every way out it knows of. Should it throw past that, the status is lifted here, so a failed
 * backup never leaves the wiki read-only -- unless the wiki was already `archiving` before this
 * run began: that status belongs to another run, which this one must not cut short.
 *
 * A copy retried from the jobs page is refused before anything is touched (see
 * ArchiveService::isUnderWay()): nothing follows it, so nothing would lift what it sets.
 */
#[AsMessageHandler]
final class CreateArchiveHandler
{
    public function __construct(
        private readonly ArchiveService $archives,
    ) {
    }

    public function __invoke(CreateArchive $archive): void
    {
        if (!$this->archives->isUnderWay($archive->uid)) {
            throw new UnrecoverableMessageHandlingException(_t('AU_RUN_NOT_REPLAYED'));
        }
        $alreadyArchiving = $this->archives->getWikiStatus() === 'archiving';
        $output = '';
        $done = false;
        try {
            $this->archives->archive(
                $output,
                $archive->saveFiles,
                $archive->saveDatabase,
                $archive->foldersToInclude,
                $archive->foldersToExclude,
                null,
                $archive->uid,
                $archive->onlyFolders
            );
            $done = true;
        } finally {
            if (!$done && !$alreadyArchiving) {
                $this->archives->releaseArchivingStatus();
            }
        }
    }
}
