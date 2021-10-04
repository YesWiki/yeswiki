<?php

use YesWiki\Core\Service\PageManager;
use YesWiki\Core\YesWikiHandler;
use \Tamtamchik\SimpleFlash\Flash;
use YesWiki\Bazar\Service\EntryManager;
use YesWiki\Core\Service\AclService;

class RevisionsHandler extends YesWikiHandler
{
    function run()
    {
        $this->denyAccessUnlessGranted('read');
        
        $pageManager = $this->getService(PageManager::class);
        $aclService = $this->getService(AclService::class);

        if ($this->getRequest()->get('restoreRevisionId')) {
            if ($aclService->hasAccess('write')) {
                $page = $pageManager->getById($this->getRequest()->get('restoreRevisionId'));
                $pageManager->save($page['tag'], $page['body']);
                Flash::success(_t('SUCCESS_RESTORE_REVISION'));
            } else {
                Flash::error(_t('DENY_WRITE'));
            }            
            return $this->wiki->Redirect($this->wiki->Href());
        } else {
            $revisionsCount = $pageManager->countRevisions($this->wiki->GetPageTag());
            // Limit to 30 revisions otherwise the UI is too crowded
            $revisions = $pageManager->getRevisions($this->wiki->GetPageTag(), 30);
            $entryManager = $this->getService(EntryManager::class);
            return $this->renderInSquelette('@core/handlers/revisions.twig', [
                'revisions' => $revisions,
                'revisionsCount' => $revisionsCount,
                'isEntry' => $entryManager->isEntry($this->wiki->GetPageTag())
            ]);
        }
    }
}
