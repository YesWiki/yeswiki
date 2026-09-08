<?php

use Tamtamchik\SimpleFlash\Flash;
use YesWiki\Bazar\Service\EntryManager;
use YesWiki\Core\Controller\CsrfTokenController;
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

        $restoreRevisionId = $this->getRequest()->isMethod('POST')
            ? $this->getRequest()->request->get('restoreRevisionId')
            : null;

        if ($restoreRevisionId) {
            if (!$aclService->hasAccess('write')) {
                Flash::error(_t('DENY_WRITE'));
            } else {
                try {
                    $this->getService(CsrfTokenController::class)->checkToken('main', 'POST', 'csrf-token', false);
                    $page = $pageManager->getById($restoreRevisionId);
                    if (empty($page) || $page['tag'] !== $this->wiki->GetPageTag()) {
                        Flash::error(_t('REVISION_NOT_FOUND'));
                    } else {
                        $pageManager->save($page['tag'], $page['body'], empty($page['comment_on']) ? '' : $page['comment_on']);
                        // save links
                        $linkTracker->registerLinks($pageManager->getOne($page['tag']));
                        Flash::success(_t('SUCCESS_RESTORE_REVISION'));
                    }
                } catch (Throwable $th) {
                    Flash::error($th->getMessage());
                }
            }

            return $this->wiki->Redirect($this->wiki->Href());
        }
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
