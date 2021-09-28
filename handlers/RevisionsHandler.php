<?php

use YesWiki\Core\Service\PageManager;
use YesWiki\Core\YesWikiHandler;
use \Tamtamchik\SimpleFlash\Flash;
class RevisionsHandler extends YesWikiHandler
{
    function run()
    {
        $pageManager = $this->getService(PageManager::class);

        if (!empty($_REQUEST['restoreRevisionId'])) {
            $page = $pageManager->getById($_REQUEST['restoreRevisionId']);
            $pageManager->save($page['tag'], $page['body']);
            Flash::success(_t('SUCCESS_RESTORE_REVISION'));
            return $this->wiki->Redirect($this->wiki->Href());
        } else {
            $revisionsCount = $pageManager->countRevisions($this->wiki->GetPageTag());
            // Limit to 30 revisions otherwise the UI is too crowded
            $revisions = $pageManager->getRevisions($this->wiki->GetPageTag(), 30);
            return $this->renderInSquelette('@core/handlers/revisions.twig', [
                'revisions' => $revisions,
                'revisionsCount' => $revisionsCount
            ]);
        }
    }
}
