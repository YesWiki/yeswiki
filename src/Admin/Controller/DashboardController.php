<?php

namespace YesWiki\Admin\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use YesWiki\Admin\Service\DashboardData;
use YesWiki\Content\Action\BazarAction;
use YesWiki\Core\DashboardShell;
use YesWiki\Core\YesWikiController;
use YesWiki\Kernel\Service\PageContext;
use YesWiki\Render\Service\TemplateEngine;

/** `/dashboard` -- what the wiki holds and what it has been up to. */
class DashboardController extends YesWikiController
{
    use DashboardShell;

    /**
     * The dashboard, or the bazar screen a form or list card sent the reader to.
     *
     * The cards' own buttons -- edit, clone, empty, delete, the form designer -- have to land
     * somewhere, and where they land is here, carrying the `view`/`action` pair they always did.
     */
    #[Route('/dashboard', options: ['acl' => ['public']])]
    public function index(): Response
    {
        $query = $this->getRequest()->query;
        $screen = $query->has(BazarAction::URL_VIEW_PARAM) || $query->has(BazarAction::URL_ACTION_PARAM)
            ? '@core/dashboard/bazar.twig'
            : '@core/dashboard/index.twig';

        return $this->page($screen, 'dashboard');
    }

    #[Route('/dashboard/sources', options: ['acl' => ['public']])]
    public function sources(): Response
    {
        return $this->page('@core/dashboard/sources.twig', 'dashboard/sources', [
            'sources' => $this->getService(DashboardData::class)->sources(),
        ]);
    }

    /**
     * A dashboard template inside the wiki's page skeleton.
     *
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
