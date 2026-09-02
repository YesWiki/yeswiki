<?php

namespace YesWiki\Search\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use YesWiki\Core\YesWikiController;
use YesWiki\Render\Service\ActionRunner;
use YesWiki\Render\Service\TemplateEngine;

/** `/search` (ticket 26). */
class SearchController extends YesWikiController
{
    #[Route('/search', methods: ['GET'], options: ['acl' => ['public']])]
    public function show(): Response
    {
        $content = $this->getService(ActionRunner::class)->action('search', ['autofocus' => '1', 'limit' => '50']);

        return new Response(
            $this->getService(TemplateEngine::class)->renderPage((string)$content)
        );
    }
}
