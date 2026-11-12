<?php

namespace YesWiki\Admin\Api;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use YesWiki\Admin\Service\Documentation;
use YesWiki\Core\YesWikiController;
use YesWiki\Render\Service\TemplateEngine;

/** The results the documentation's search box fetches as you type: sections of the docs, drawn as a fragment. */
class DocSearchApiController extends YesWikiController
{
    #[Route('/api/doc/search', methods: ['GET'], options: ['acl' => ['public']])]
    public function search(Request $request): Response
    {
        $query = trim((string)$request->query->get('q', ''));

        return new Response($this->getService(TemplateEngine::class)->render('@core/doc-search-results.twig', [
            'query' => $query,
            'results' => $this->getService(Documentation::class)->search($query),
        ]));
    }
}
