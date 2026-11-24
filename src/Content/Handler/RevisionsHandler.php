<?php

namespace YesWiki\Content\Handler;

use YesWiki\Content\Entity\PageType;
use YesWiki\Content\Service\EntryManager;
use YesWiki\Content\Service\PageManager;
use YesWiki\Content\Service\RevisionView;
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
        $tag = $this->getService(PageContext::class)->getTag();
        $revisionsLimit = $this->params->get('revisionscount');
        $revisions = $pageManager->getRevisions($tag, is_numeric($revisionsLimit) ? (int)$revisionsLimit : 30);
        $revisionView = $this->getService(RevisionView::class);
        $timeline = $revisionView->timeline(array_values($revisions), $pageManager->countRevisions($tag));
        $isTemplate = $pageManager->typeOf($tag) === PageType::TEMPLATE;
        $isEntry = $this->getService(EntryManager::class)->isEntry($tag);

        $query = $this->getRequest()->query;
        $ids = array_column($timeline, 'id');
        $selectedId = in_array($query->getInt('revision'), $ids, true) ? $query->getInt('revision') : ($ids[0] ?? null);
        $position = $selectedId === null ? null : array_search($selectedId, $ids, true);
        $isLatest = $position === 0;
        $view = in_array($query->get('show'), RevisionView::VIEWS, true) ? (string)$query->get('show') : 'current';
        if ($view === 'diff' && $isLatest) {
            $view = 'current';
        }
        $code = $isTemplate || $query->getBoolean('code');

        $page = $selectedId === null ? null : $pageManager->getOne($tag, PageManager::revisionAsked((string)$selectedId, null));

        return $this->renderFullPage('@core/handlers/revisions.twig', [
            'timeline' => $timeline,
            'selected' => $position === null || $position === false ? null : $timeline[$position],
            'newer' => is_int($position) && $position > 0 ? $timeline[$position - 1] : null,
            'older' => is_int($position) ? ($timeline[$position + 1] ?? null) : null,
            'isLatest' => $isLatest,
            'view' => $view,
            'code' => $code,
            'content' => $page === null || ($view === 'current' && !$code && !$isEntry) ? '' : $revisionView->show($page, $view, $code),
            'isEntry' => $isEntry,
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
