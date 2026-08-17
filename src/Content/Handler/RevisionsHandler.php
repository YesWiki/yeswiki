<?php

namespace YesWiki\Content\Handler;

use Tamtamchik\SimpleFlash\Flash;
use YesWiki\Content\Service\EntryManager;
use YesWiki\Content\Service\PageManager;
use YesWiki\Core\YesWikiHandler;
use YesWiki\Identity\Service\AclService;
use YesWiki\Kernel\Performable\RegisteredHandler;
use YesWiki\Kernel\Service\PageContext;
use YesWiki\Kernel\Service\Redirector;
use YesWiki\Kernel\Service\UrlFormatter;

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

        if ($this->getRequest()->get('restoreRevisionId')) {
            if ($aclService->hasAccess('write')) {
                $tag = $this->getService(PageContext::class)->getTag();
                $fullRevert = (bool)$this->getRequest()->get('fullRevert');
                $pageManager->revertToRevision($tag, $this->getRequest()->get('restoreRevisionId'), $fullRevert);
                Flash::success(_t($fullRevert ? 'SUCCESS_RESTORE_REVISION_FULL' : 'SUCCESS_RESTORE_REVISION'));
            } else {
                Flash::error(_t('DENY_WRITE'));
            }

            return $this->getService(Redirector::class)->redirect($this->getService(UrlFormatter::class)->href());
        }
        $revisionsCount = $pageManager->countRevisions($this->getService(PageContext::class)->getTag());

        $revisions = $pageManager->getRevisions($this->getService(PageContext::class)->getTag(), $this->params->get('revisionscount'));
        $entryManager = $this->getService(EntryManager::class);

        return $this->renderFullPage('@core/handlers/revisions.twig', [
            'revisions' => $revisions,
            'revisionsCount' => $revisionsCount,
            'isEntry' => $entryManager->isEntry($this->getService(PageContext::class)->getTag()),
        ]);
    }
}
