<?php

namespace YesWiki\Admin\Message;

use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use YesWiki\Admin\Service\ArchiveService;

/**
 * Makes the backup a CreateArchive asks for, reporting under its uid.
 * archive() lifts the `archiving` it set however it ends; a run killed midway leaves it to the next reader, who finds the archive lock free (HibernationService).
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
        $output = '';
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
    }
}
