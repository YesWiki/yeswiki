<?php

namespace YesWiki\Content\Handler;
use Tamtamchik\SimpleFlash\Flash;
use YesWiki\Identity\Service\AclService;
use YesWiki\Content\Service\EntryManager;
use YesWiki\Content\Service\LinkTracker;
use YesWiki\Content\Service\PageManager;
use YesWiki\Core\YesWikiHandler;
use YesWiki\Kernel\Performable\RegisteredHandler;

class RevisionsHandler extends YesWikiHandler implements RegisteredHandler
{
    /** `/PageName/revisions` -- stated, not inferred from the filename. */
    public static function performableName(): string
    {
        return 'revisions';
    }

    public function run()
    {
        $this->denyAccessUnlessGranted('read');

        $pageManager = $this->getService(PageManager::class);
        $aclService = $this->getService(AclService::class);
        $linkTracker = $this->getService(LinkTracker::class);

        if ($this->getRequest()->get('restoreRevisionId')) {
            if ($aclService->hasAccess('write')) {
                $tag = $this->wiki->GetPageTag();
                $fullRevert = (bool)$this->getRequest()->get('fullRevert');
                $pageManager->revertToRevision($tag, $this->getRequest()->get('restoreRevisionId'), $fullRevert);
                // save links
                $linkTracker->registerLinks($pageManager->getOne($tag));
                Flash::success(_t($fullRevert ? 'SUCCESS_RESTORE_REVISION_FULL' : 'SUCCESS_RESTORE_REVISION'));
            } else {
                Flash::error(_t('DENY_WRITE'));
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
