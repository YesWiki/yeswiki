<?php

namespace YesWiki\Content\Controller;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Tamtamchik\SimpleFlash\Flash;
use YesWiki\Content\Service\ListManager;
use YesWiki\Content\Service\ListOverview;
use YesWiki\Core\DashboardShell;
use YesWiki\Core\YesWikiController;
use YesWiki\Identity\Service\CsrfTokenChecker;
use YesWiki\Kernel\Service\ConfigurationFileProvider;
use YesWiki\Kernel\Service\ConfigurationService;
use YesWiki\Kernel\Service\CurrentRequest;
use YesWiki\Kernel\Service\PageContext;
use YesWiki\Kernel\Service\UrlFormatter;
use YesWiki\Render\Service\TemplateEngine;

/**
 * The screens a value list has (ticket 64).
 *
 * It had none that anybody could reach: the only links to `view=listes` were inside the list screen
 * itself, and the `BazaR` page that used to host it was retired. There are two now, because there
 * are two audiences: everybody may see what vocabulary the wiki has, and whoever may write one
 * edits it beside the rest of the Content administration.
 */
class ListScreensController extends YesWikiController
{
    use DashboardShell;

    /** Every list the visitor may read, with no controls. */
    #[Route('/dashboard/lists', options: ['acl' => ['public']])]
    public function publicLists(): Response
    {
        return $this->page('@core/dashboard/lists.twig', 'dashboard/lists', [
            'overview' => $this->getService(ListOverview::class)->all(),
        ]);
    }

    /** The same lists, with the buttons, beside the rest of the Content administration. */
    #[Route('/admin/lists', methods: ['GET', 'POST'], options: ['acl' => ['@admins']])]
    public function adminLists(): Response
    {
        $request = $this->getService(CurrentRequest::class)->get();

        if ($request->isMethod('POST') && $request->request->has('follow')) {
            $this->follow($request->request->getString('follow'));

            return new RedirectResponse($this->getService(UrlFormatter::class)->href('', 'admin/lists'));
        }

        return $this->page('@core/admin/lists.twig', 'admin/lists', [
            'lists' => $this->getService(ListController::class)->displayAll(),
            'overview' => $this->getService(ListOverview::class)->all(),
        ]);
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
