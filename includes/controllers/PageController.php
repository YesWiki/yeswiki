<?php

namespace YesWiki\Core\Controller;

use Exception;
use YesWiki\Bazar\Controller\EntryController;
use YesWiki\Bazar\Service\EntryManager;
use YesWiki\Core\Service\PageManager;
use YesWiki\Core\YesWikiController;

class PageController extends YesWikiController
{
    protected $authController;
    protected $entryController;
    protected $entryManager;
    protected $pageManager;

    public function __construct(
        AuthController $authController,
        EntryController $entryController,
        EntryManager $entryManager,
        PageManager $pageManager
    ) {
        $this->authController = $authController;
        $this->entryController = $entryController;
        $this->entryManager = $entryManager;
        $this->pageManager = $pageManager;
    }

    /**
     * delete a page from tag
     * but be carefull entry or page.
     *
     * @return bool $done
     *
     * @throws Exception if in hibernation or if entry not deleted
     */
    public function delete(string $tag): bool
    {
        if ($this->entryManager->isEntry($tag)) {
            return $this->entryController->delete($tag);
        } else {
            $this->pageManager->deleteOrphaned($tag);
            $this->wiki->LogAdministrativeAction($this->authController->getLoggedUserName(), 'Suppression de la page ->""' . $tag . '""');

            return true;
        }
    }
}
