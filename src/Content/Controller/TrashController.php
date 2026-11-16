<?php

namespace YesWiki\Content\Controller;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use YesWiki\Content\Service\Trash;
use YesWiki\Core\DashboardShell;
use YesWiki\Core\YesWikiController;
use YesWiki\Identity\Action\LoginAction;
use YesWiki\Identity\Service\AclService;
use YesWiki\Identity\Service\AuthenticationService;
use YesWiki\Identity\Service\CsrfTokenChecker;
use YesWiki\Kernel\Service\CurrentRequest;
use YesWiki\Kernel\Service\Flash;
use YesWiki\Kernel\Service\PageContext;
use YesWiki\Kernel\Service\RuntimeConfig;
use YesWiki\Kernel\Service\UrlFormatter;
use YesWiki\Render\Service\TemplateEngine;

/** `/dashboard/trash`: what was deleted in the last days, to put back or erase for good (end-users 13). */
class TrashController extends YesWikiController
{
    use DashboardShell;

    public const ROUTE = 'dashboard/trash';

    /** The items the reader may handle, and what a Restore, Erase or Empty button asked. */
    #[Route('/dashboard/trash', methods: ['GET', 'POST'], options: ['acl' => ['public']])]
    public function index(): Response
    {
        if ($this->getService(AuthenticationService::class)->getLoggedUserName() === '') {
            return new RedirectResponse($this->getService(UrlFormatter::class)->href('', 'user', [LoginAction::RETURN_PARAM => $this->getService(UrlFormatter::class)->href('', self::ROUTE)], false));
        }
        $request = $this->getService(CurrentRequest::class)->get();
        if ($request->isMethod('POST')) {
            $this->getService(CsrfTokenChecker::class)->checkToken('main', 'POST', 'csrf-token', false);
            $this->handle((string)$request->request->get('do', ''), (string)$request->request->get('tag', ''));
            $back = (string)$request->request->get('back', '');
            $base = (string)($this->getService(RuntimeConfig::class)['base_url'] ?? '');

            return new RedirectResponse($back !== '' && $base !== '' && str_starts_with($back, $base)
                ? $back
                : $this->getService(UrlFormatter::class)->href('', self::ROUTE));
        }

        $this->getService(PageContext::class)->setTag(self::ROUTE);
        $templates = $this->getService(TemplateEngine::class);

        return new Response($templates->renderPage($templates->render('@core/dashboard/trash.twig', $this->dashboardShell(self::ROUTE, [
            'items' => $this->visibleItems(),
            'keepDays' => $this->getService(RuntimeConfig::class)['trash_keep_days'] ?? Trash::DEFAULT_KEEP_DAYS,
        ]))));
    }

    /** @return list<array{tag: string, at: string, by: string, type: string, title: string, with: list<string>}> */
    private function visibleItems(): array
    {
        $isAdmin = $this->getService(AclService::class)->isAdmin();

        return $this->getService(Trash::class)->list($isAdmin ? null : $this->getService(AuthenticationService::class)->getLoggedUserName());
    }

    private function handle(string $action, string $tag): void
    {
        $trash = $this->getService(Trash::class);
        try {
            if ($action === 'empty') {
                foreach ($this->visibleItems() as $item) {
                    $trash->erase($item['tag']);
                }
                Flash::success(_t('TRASH_EMPTIED'));

                return;
            }
            if (!$trash->mayHandle($tag)) {
                Flash::error(_t('TRASH_NOT_YOURS'));

                return;
            }
            if ($action === 'restore' && $trash->restore($tag)) {
                Flash::success(_t('TRASH_RESTORED', ['tag' => $tag]));
            } elseif ($action === 'erase' && $trash->erase($tag)) {
                Flash::success(_t('TRASH_ERASED', ['tag' => $tag]));
            }
        } catch (\Throwable $failed) {
            Flash::error($failed->getMessage());
        }
    }
}
