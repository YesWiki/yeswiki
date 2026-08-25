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

    /**
     * delete a page from tag but be carefull entry or page.
     *
     * @return bool $done
     *
     * @throws \Exception if in hibernation or if entry not deleted
     */
    public function delete(string $tag): bool
    {
        if ($this->entryManager->isEntry($tag)) {
            return $this->entryController->delete($tag);
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
