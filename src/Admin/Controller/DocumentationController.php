<?php

namespace YesWiki\Admin\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use YesWiki\Admin\Service\Documentation;
use YesWiki\Core\DashboardShell;
use YesWiki\Core\YesWikiController;
use YesWiki\Render\Service\TemplateEngine;

/** `/doc` -- the documentation, rendered as a page of this wiki. */
class DocumentationController extends YesWikiController
{
    use DashboardShell;

    #[Route('/doc', options: ['acl' => ['public']])]
    #[Route('/doc/{path}', requirements: ['path' => '.+'], options: ['acl' => ['public']])]
    public function show(string $path = ''): Response
    {
        $documentation = $this->getService(Documentation::class);
        $templateEngine = $this->getService(TemplateEngine::class);
        $page = $documentation->page($path);
        $books = $documentation->books();
        unset($books['']);

        return new Response($templateEngine->renderPage($templateEngine->render('@core/doc.twig', $this->dashboardShell('doc', [
            'page' => $page,
            'menu' => $documentation->menu($page['url'] ?? ''),
            'extensions' => array_map(fn (string $book): array => ['name' => $books[$book]['name'], 'url' => $documentation->url($book)], array_keys($books)),
            'home' => $documentation->url(''),
        ]))), $page === null ? Response::HTTP_NOT_FOUND : Response::HTTP_OK);
    }
}
