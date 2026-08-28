<?php

namespace YesWiki\Content\Handler;

use Tamtamchik\SimpleFlash\Flash;
use YesWiki\Content\Service\EntryManager;
use YesWiki\Content\Service\PageManager;
use YesWiki\Core\YesWikiHandler;
use YesWiki\Identity\Service\AclService;
use YesWiki\Identity\Service\CsrfTokenChecker;
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

    /**
     * @return string
     */
    public function run()
    {
        $this->denyAccessUnlessGranted('read');

        $pageManager = $this->getService(PageManager::class);
        $aclService = $this->getService(AclService::class);

        $restoreRevisionId = $this->getRequest()->isMethod('POST')
            ? $this->getRequest()->request->getString('restoreRevisionId')
            : '';

        if ($restoreRevisionId !== '') {
            if (!$aclService->hasAccess('write')) {
                Flash::error(_t('DENY_WRITE'));
            } else {
                try {
                    $this->getService(CsrfTokenChecker::class)->checkToken('main', 'POST', 'csrf-token', false);
                    $tag = $this->getService(PageContext::class)->getTag();
                    $fullRevert = (bool)$this->getRequest()->request->get('fullRevert');
                    $pageManager->revertToRevision($tag, $restoreRevisionId, $fullRevert);
                    Flash::success(_t($fullRevert ? 'SUCCESS_RESTORE_REVISION_FULL' : 'SUCCESS_RESTORE_REVISION'));
                } catch (\Throwable $error) {
                    Flash::error($error->getMessage());
                }
            }

            return $this->getService(Redirector::class)->redirect($this->getService(UrlFormatter::class)->href());
        }
        $revisionsCount = $pageManager->countRevisions($this->getService(PageContext::class)->getTag());

        $revisionsLimit = $this->params->get('revisionscount');
        $revisions = $pageManager->getRevisions(
            $this->getService(PageContext::class)->getTag(),
            is_numeric($revisionsLimit) ? (int)$revisionsLimit : 30
        );
        $entryManager = $this->getService(EntryManager::class);

        return $this->renderFullPage('@core/handlers/revisions.twig', [
            'revisions' => $revisions,
            'revisionsCount' => $revisionsCount,
            'isEntry' => $entryManager->isEntry($this->getService(PageContext::class)->getTag()),
        ]);
    }
}
