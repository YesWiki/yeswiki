<?php

namespace YesWiki\Admin\Message;

use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use YesWiki\Admin\Service\ArchiveService;

/**
 * Makes the backup a CreateArchive asks for, reporting under its uid.
 * Should archive() throw, `archiving` is lifted here, unless the wiki was already `archiving` before this run began.
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
