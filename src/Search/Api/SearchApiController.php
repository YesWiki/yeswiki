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

/** The results fragment `{{search}}` drives with `hx-get` (ticket 26). */
class SearchApiController extends YesWikiController
{
    #[Route('/api/search', methods: ['GET'], options: ['acl' => ['public']])]
    public function search(Request $request): Response
    {
        $phrase = trim((string)$request->query->get('q', ''));
        $type = trim((string)$request->query->get('type', ''));

        $tags = array_values(array_filter(
            array_map('trim', explode(',', (string)$request->query->get('tags', '')))
        ));
        $limit = max(1, min((int)$request->query->get('limit', SearchIndexQuery::DEFAULT_LIMIT), SearchIndexQuery::MAX_LIMIT));
        $page = max(1, (int)$request->query->get('page', 1));
        $display = trim((string)$request->query->get('display', 'list'));
        if (!in_array($display, SearchAction::DISPLAY_MODES, true)) {
            $display = 'list';
        }

        if ($phrase === '' && $tags === []) {
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
                'tags' => [],
                'prompt' => true,
            ])), $phrase, $type, $display, 1);
        }

        $query = $this->getService(SearchIndexQuery::class);
        $found = $query->search($phrase, $type === '' ? null : $type, $limit, ($page - 1) * $limit, $tags);

        $facets = ($request->query->get('facets') === '0' || $phrase === '')
            ? []
            : $query->facets($phrase);

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
            'tags' => $tags,
            'prompt' => false,
        ]));

        return $this->withSearchUrl($response, $phrase, $type, $display, $page);
    }

    /**
     * Tell the browser which address this result set corresponds to, so a search can be copied, bookmarked, shared and reloaded.
     */
    private function withSearchUrl(Response $response, string $phrase, string $type, string $display, int $page): Response
    {
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
