<?php

namespace YesWiki\Content\Handler;

use YesWiki\Content\Entity\PageType;
use YesWiki\Content\Service\EntryManager;
use YesWiki\Content\Service\PageManager;
use YesWiki\Core\YesWikiHandler;
use YesWiki\Identity\Service\AclService;
use YesWiki\Identity\Service\CsrfTokenChecker;
use YesWiki\Kernel\Performable\RegisteredHandler;
use YesWiki\Kernel\Service\Flash;
use YesWiki\Kernel\Service\PageContext;
use YesWiki\Kernel\Service\Redirector;
use YesWiki\Kernel\Service\UrlFormatter;
use YesWiki\Render\Service\TemplateOverrides;

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
                    if ($pageManager->typeOf($tag) === PageType::TEMPLATE) {
                        $this->restoreTemplate($tag, $restoreRevisionId);
                    } else {
                        $pageManager->revertToRevision($tag, $restoreRevisionId, $fullRevert);
                    }
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

        $tag = $this->getService(PageContext::class)->getTag();
        $isTemplate = $pageManager->typeOf($tag) === PageType::TEMPLATE;

        return $this->renderFullPage('@core/handlers/revisions.twig', [
            'revisions' => $revisions,
            'revisionsCount' => $revisionsCount,
            'isEntry' => $entryManager->isEntry($tag),
            'isTemplate' => $isTemplate,
            'templatePath' => $isTemplate ? (string)($pageManager->getOne($tag, null, false, true)['body']['path'] ?? '') : '',
        ]);
    }

    /** A template's old revision comes back as a new one, through the same Twig check as a save from the editor (ticket 18). */
    private function restoreTemplate(string $tag, string $revisionId): void
    {
        $revision = $this->getService(PageManager::class)->getById($revisionId, true);
        $body = $revision['body'] ?? [];
        if (($revision['tag'] ?? null) !== $tag || !is_string($body['path'] ?? null) || !is_string($body['twig'] ?? null)) {
            throw new \RuntimeException(_t('ERROR_NO_ACCESS'));
        }
        try {
            $this->getService(TemplateOverrides::class)->save($body['path'], $body['twig']);
        } catch (\InvalidArgumentException $refused) {
            throw new \RuntimeException($refused->getMessage(), 0, $refused);
        }
    }
}
