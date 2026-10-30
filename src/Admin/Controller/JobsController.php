<?php

namespace YesWiki\Admin\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use YesWiki\Admin\Service\JobsOverview;
use YesWiki\Core\DashboardShell;
use YesWiki\Core\YesWikiController;
use YesWiki\Kernel\Service\PageContext;
use YesWiki\Render\Service\TemplateEngine;

/**
 * `/admin/jobs` -- the background jobs: what waits, what failed, whether anything still consumes (messenger / scheduler, spec §11.3).
 *
 * The page renders whole once; its actions and its tabs go through JobsApiController and swap
 * their part of it (ADR-0005).
 */
class JobsController extends YesWikiController
{
    use DashboardShell;

    /** @var list<string> */
    private const ADMIN_ACL = ['@admins'];

    #[Route('/admin/jobs', options: ['acl' => self::ADMIN_ACL])]
    public function jobs(): Response
    {
        $this->denyAccessUnlessAdmin();
        $overview = $this->getService(JobsOverview::class);

        return $this->page('@core/admin/jobs.twig', JobsOverview::ROUTE, [
            'status' => $overview->status(),
            'tab' => $overview->tab(JobsOverview::TABS[0]),
        ]);
    }

    /**
     * @see AdminController::page() -- same shell.
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
