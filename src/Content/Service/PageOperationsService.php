<?php

namespace YesWiki\Content\Service;

use YesWiki\Content\Controller\EntryController;
use YesWiki\Core\YesWikiController;

class PageOperationsService extends YesWikiController
{
    protected EntryController $entryController;
    protected EntryManager $entryManager;
    protected PageManager $pageManager;

    public function __construct(
        EntryController $entryController,
        EntryManager $entryManager,
        PageManager $pageManager
    ) {
        $this->entryController = $entryController;
        $this->entryManager = $entryManager;
        $this->pageManager = $pageManager;
    }

    /** Deletes a page or an entry, into the trash unless told otherwise (end-users 13); throws when hibernated or when the entry may not be deleted. */
    public function delete(string $tag, bool $toTrash = true): bool
    {
        if ($this->entryManager->isEntry($tag)) {
            return $this->entryController->delete($tag, false, $toTrash);
        }
        if ($toTrash) {
            return $this->getService(Trash::class)->trash($tag);
        }
        $this->pageManager->deleteOrphaned($tag);

        return true;
    }

    public function duplicate(string $sourceTag, string $destinationTag = ''): bool
    {
        if ($this->entryManager->isEntry($sourceTag)) {
            return $this->entryManager->duplicate($sourceTag, $destinationTag);
        }

        return $this->pageManager->duplicate($sourceTag, $destinationTag);
    }
}
