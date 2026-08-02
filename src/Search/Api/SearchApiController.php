<?php

namespace YesWiki\Search\Api;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use YesWiki\Core\YesWikiController;
use YesWiki\Kernel\Service\AssetRegistry;
use YesWiki\Search\Service\SearchIndexQuery;
use YesWiki\Search\Service\SearchResultPresenter;

/**
 * The results fragment `{{search}}` drives with `hx-get` (ticket 26).
 *
 * Returns **HTML, not JSON**, following `GET /api/admin/pages`: the results are a rendered
 * list, htmx swaps them in, and nothing on the client has to know how to build a result row.
 * A JSON endpoint would mean a second renderer in JavaScript and two places for a content
 * type's presentation to drift apart.
 *
 * Public by ACL, and safely so: SearchIndexQuery evaluates the current visitor's page-level
 * and field-level rights inside the SQL, so an unauthenticated request simply matches fewer
 * rows rather than being refused.
 */
class SearchApiController extends YesWikiController
{
    #[Route('/api/search', methods: ['GET'], options: ['acl' => ['public']])]
    public function search(Request $request): Response
    {
        $phrase = trim((string)$request->query->get('phrase', ''));
        $type = trim((string)$request->query->get('type', ''));
        $limit = max(1, min((int)$request->query->get('limit', SearchIndexQuery::DEFAULT_LIMIT), SearchIndexQuery::MAX_LIMIT));
        $page = max(1, (int)$request->query->get('page', 1));

        if ($phrase === '') {
            return new Response($this->render('@core/search-results.twig', [
                'phrase' => '',
                'results' => [],
                'total' => 0,
                'capped' => false,
                'page' => 1,
                'pages' => 0,
                'limit' => $limit,
                'type' => $type,
                'prompt' => true,
            ]));
        }

        $found = $this->getService(SearchIndexQuery::class)->search(
            $phrase,
            $type === '' ? null : $type,
            $limit,
            ($page - 1) * $limit
        );

        // Rendered inside a capture scope whose result is deliberately thrown away, the way
        // {{newtextsearch}} did before it (ADR-0014 records the reasoning): a result row can
        // render field values, and a field registers its libraries when it renders -- leaflet
        // for a map, an editor for a long text. A results list shows extracts, not working
        // inputs, and has no use for any of them.
        $results = [];
        $this->getService(AssetRegistry::class)->capture(
            fn (): array => $this->getService(SearchResultPresenter::class)->present($found['results'], $phrase),
            $results
        );

        return new Response($this->render('@core/search-results.twig', [
            'phrase' => $phrase,
            'results' => $results,
            'total' => $found['total'],
            'capped' => $found['capped'],
            'page' => $page,
            'pages' => (int)ceil($found['total'] / $limit),
            'limit' => $limit,
            'type' => $type,
            'prompt' => false,
        ]));
    }
}
