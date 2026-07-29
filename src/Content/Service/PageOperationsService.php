<?php

namespace YesWiki\Content\Service;

use YesWiki\Admin\Service\AdministrativeLogService;
use YesWiki\Content\Controller\EntryController;
use YesWiki\Core\YesWikiController;
use YesWiki\Identity\Service\AuthenticationService;

class PageOperationsService extends YesWikiController
{
    protected $authenticationService;
    protected $entryController;
    protected $entryManager;
    protected $pageManager;
    protected AdministrativeLogService $administrativeLogService;

    public function __construct(
        AuthenticationService $authenticationService,
        EntryController $entryController,
        EntryManager $entryManager,
        PageManager $pageManager,
        AdministrativeLogService $administrativeLogService
    ) {
        $this->authenticationService = $authenticationService;
        $this->entryController = $entryController;
        $this->entryManager = $entryManager;
        $this->pageManager = $pageManager;
        $this->administrativeLogService = $administrativeLogService;
    }

    /**
     * delete a page from tag
     * but be carefull entry or page.
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
        $this->administrativeLogService->log($this->authenticationService->getLoggedUserName(), 'Suppression de la page ->""' . $tag . '""');

        return true;
    }

    public function duplicate(string $sourceTag, string $destinationTag = ''): bool
    {
        if ($this->entryManager->isEntry($sourceTag)) {
            return $this->entryController->duplicate($sourceTag, $destinationTag);
        }

        return $this->pageManager->duplicate($sourceTag, $destinationTag);
    }
}
