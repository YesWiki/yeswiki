<?php

namespace YesWiki\Content\Controller;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Tamtamchik\SimpleFlash\Flash;
use YesWiki\Content\Action\BazarAction;
use YesWiki\Content\Service\ListManager;
use YesWiki\Content\Service\ListOverview;
use YesWiki\Core\DashboardShell;
use YesWiki\Core\YesWikiController;
use YesWiki\Identity\Service\AclService;
use YesWiki\Identity\Service\CsrfTokenChecker;
use YesWiki\Kernel\Service\ConfigurationFileProvider;
use YesWiki\Kernel\Service\ConfigurationService;
use YesWiki\Kernel\Service\CurrentRequest;
use YesWiki\Kernel\Service\PageContext;
use YesWiki\Kernel\Service\UrlFormatter;
use YesWiki\Render\Service\TemplateEngine;

/** The one screen for value lists (ticket 64): everybody reads them, and each button shows only to whoever may use it. */
class ListScreensController extends YesWikiController
{
    use DashboardShell;

    /** Every list the visitor may read, with the buttons their rights on each list allow. */
    #[Route('/dashboard/lists', methods: ['GET', 'POST'], options: ['acl' => ['public']])]
    public function lists(): Response
    {
        $request = $this->getService(CurrentRequest::class)->get();

        if ($request->isMethod('POST') && $request->request->has('follow')) {
            if ($this->getService(AclService::class)->isAdmin()) {
                $this->follow($request->request->getString('follow'));
            }

            return new RedirectResponse($this->getService(UrlFormatter::class)->href('', 'dashboard/lists'));
        }

        $this->getService(PageContext::class)->setTag('dashboard/lists');

        return $this->page('@core/dashboard/lists.twig', 'dashboard/lists', [
            'lists' => $this->listScreen($request) ?? $this->getService(ListController::class)->displayAll(),
            'overview' => $this->getService(ListOverview::class)->all(),
        ]);
    }

    /** The administration's former list screen, kept so its links still lead somewhere. */
    #[Route('/admin/lists', options: ['acl' => ['@admins']])]
    public function adminLists(): Response
    {
        return new RedirectResponse($this->getService(UrlFormatter::class)->href(
            '',
            'dashboard/lists',
            array_diff_key($this->getService(CurrentRequest::class)->get()->query->all(), ['wiki' => 1, 'admin/lists' => 1]),
            false
        ));
    }

    /** The screens a single list has, which the retired `BazaR` page used to dispatch and nothing has since. */
    private function listScreen(SymfonyRequest $request): ?string
    {
        if ($request->query->get(BazarAction::URL_VIEW_PARAM) !== BazarAction::VIEW_LISTS) {
            return null;
        }

        $controller = $this->getService(ListController::class);

        return match ($request->query->get(BazarAction::URL_ACTION_PARAM)) {
            BazarAction::ACTION_LIST_CREATE => $controller->create(),
            BazarAction::ACTION_LIST_EDIT => $controller->update($request->query->get('listid')),
            BazarAction::ACTION_LIST_DELETE => $controller->delete($request->query->get('listid')),
            default => null,
        };
    }

    /**
     * Accept that a remote wiki's copy of this list wins from now on.
     *
     * An imported list is an ordinary list that remembers where it came from; this is the moment a
     * webmaster says the next sync may replace what they have edited (ADR-0028's "origin, not a
     * lock"). It writes the same `dataSources` entry the imports screen would.
     */
    private function follow(string $id): void
    {
        try {
            $this->getService(CsrfTokenChecker::class)->checkToken('main', 'POST', 'csrf-token', false);

            $origin = $this->getService(ListManager::class)->originOf($id);
            if ($origin === '') {
                throw new \Exception(_t('ADMIN_LISTS_NO_ORIGIN'));
            }

            $config = $this->getService(ConfigurationService::class)
                ->getConfiguration(ConfigurationFileProvider::getConfigFileFromEnv());
            $config->load();

            $sources = is_array($config['dataSources'] ?? null) ? $config['dataSources'] : [];
            $sources[$id] = [
                'importer' => 'YesWikiList',
                'url' => $origin,
                'listId' => $id,
                'syncOnMaintenance' => true,
            ];
            $config['dataSources'] = $sources;
            $config->write();

            Flash::success(_t('ADMIN_LISTS_FOLLOWED'));
        } catch (\Throwable $failed) {
            Flash::error(_t('ADMIN_LISTS_NOT_FOLLOWED') . ' ' . $failed->getMessage());
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    private function page(string $template, string $current, array $data = []): Response
    {
        $this->getService(PageContext::class)->setTag($current);
        $templateEngine = $this->getService(TemplateEngine::class);

        return new Response($templateEngine->renderPage(
            $templateEngine->render($template, $this->dashboardShell($current, $data))
        ));
    }
}
