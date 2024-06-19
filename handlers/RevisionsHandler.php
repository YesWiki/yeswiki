<?php

use Tamtamchik\SimpleFlash\Flash;
use YesWiki\Bazar\Service\EntryManager;
use YesWiki\Core\Service\AclService;
use YesWiki\Core\Service\LinkTracker;
use YesWiki\Core\Service\PageManager;
use YesWiki\Core\YesWikiHandler;

class RevisionsHandler extends YesWikiHandler
{
    public function run()
    {
        $this->denyAccessUnlessGranted('read');

        $pageManager = $this->getService(PageManager::class);
        $aclService = $this->getService(AclService::class);
        $linkTracker = $this->getService(LinkTracker::class);

        if ($this->getRequest()->get('restoreRevisionId')) {
            if ($aclService->hasAccess('write')) {
                $page = $pageManager->getById($this->getRequest()->get('restoreRevisionId'));
                $pageManager->save($page['tag'], $page['body'], empty($page['comment_on']) ? '' : $page['comment_on']);
                // save links
                $linkTracker->registerLinks($pageManager->getOne($page['tag']));
                Flash::success(_t('SUCCESS_RESTORE_REVISION'));
            } else {
                Flash::error(_t('DENY_WRITE'));
            }

            return $this->wiki->Redirect($this->wiki->Href());
        } else {
            $revisionsCount = $pageManager->countRevisions($this->wiki->GetPageTag());
            // Limit to 30 revisions otherwise the UI is too crowded
            $revisions = $pageManager->getRevisions($this->wiki->GetPageTag(), $this->params->get('revisionscount'));
            $entryManager = $this->getService(EntryManager::class);

            return $this->renderInSquelette('@core/handlers/revisions.twig', [
                'revisions' => $revisions,
                'revisionsCount' => $revisionsCount,
                'isEntry' => $entryManager->isEntry($this->wiki->GetPageTag()),
            ]);
        }
    }
}
