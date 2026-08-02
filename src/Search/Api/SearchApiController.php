<?php

namespace YesWiki\Search\Api;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use YesWiki\Core\YesWikiController;
use YesWiki\Kernel\Service\AssetRegistry;
use YesWiki\Kernel\Service\UrlFormatter;
use YesWiki\Search\Action\SearchAction;
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
        $phrase = trim((string)$request->query->get('q', ''));
        $type = trim((string)$request->query->get('type', ''));
        $limit = max(1, min((int)$request->query->get('limit', SearchIndexQuery::DEFAULT_LIMIT), SearchIndexQuery::MAX_LIMIT));
        $page = max(1, (int)$request->query->get('page', 1));
        $display = trim((string)$request->query->get('display', 'list'));
        if (!in_array($display, SearchAction::DISPLAY_MODES, true)) {
            $display = 'list';
        }

        if ($phrase === '') {
            // the URL is rewritten here too, so clearing the box clears `?q=` rather than
            // leaving the address bar claiming a search that is no longer on screen
            return $this->withSearchUrl(new Response($this->render('@core/search-results.twig', [
                'phrase' => '',
                'results' => [],
                'facets' => [],
                'total' => 0,
                'capped' => false,
                'page' => 1,
                'pages' => 0,
                'limit' => $limit,
                'type' => $type,
                'display' => $display,
                'prompt' => true,
            ])), $phrase, $type, $display, 1);
        }

        $query = $this->getService(SearchIndexQuery::class);
        $found = $query->search($phrase, $type === '' ? null : $type, $limit, ($page - 1) * $limit);
        // computed across every type, not just the selected one -- a facet's job is to show
        // what else is there. Skipped when the type filter is hidden, since nothing would
        // read them.
        $facets = $request->query->get('facets') === '0' ? [] : $query->facets($phrase);

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

        $response = new Response($this->render('@core/search-results.twig', [
            'phrase' => $phrase,
            'results' => $results,
            'facets' => $facets,
            'total' => $found['total'],
            'capped' => $found['capped'],
            'page' => $page,
            'pages' => (int)ceil($found['total'] / $limit),
            'limit' => $limit,
            'type' => $type,
            'display' => $display,
            'prompt' => false,
        ]));

        return $this->withSearchUrl($response, $phrase, $type, $display, $page);
    }

    /**
     * Tell the browser which address this result set corresponds to, so a search can be
     * copied, bookmarked, shared and reloaded.
     *
     * Done with the `HX-Replace-Url` **response header** rather than `hx-push-url` on the
     * elements: the request goes to `/api/search`, and `hx-push-url="true"` would put that
     * API path in the address bar. Only the server knows the user-facing URL its answer
     * belongs to, which is exactly what the header is for.
     *
     * Replace rather than push: typing debounces into a request every 300ms, and pushing
     * would bury the previous page under one history entry per keystroke.
     */
    private function withSearchUrl(Response $response, string $phrase, string $type, string $display, int $page): Response
    {
        // an empty search is the bare page: no point carrying `?q=` with nothing in it
        $params = array_filter([
            'q' => $phrase,
            'type' => $type,
            'display' => $display === 'list' ? '' : $display,
            'page' => $page > 1 ? (string)$page : '',
        ], static fn (string $value): bool => $value !== '');

        $response->headers->set(
            'HX-Replace-Url',
            $this->getService(UrlFormatter::class)->href('', 'search', $params, false)
        );

        return $response;
    }
}
