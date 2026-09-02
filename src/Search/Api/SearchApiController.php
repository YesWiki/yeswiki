<?php

namespace YesWiki\Search\Api;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use YesWiki\Content\Entity\Item;
use YesWiki\Content\Service\ContentImage;
use YesWiki\Content\Service\ExportLinks;
use YesWiki\Content\Service\FormManager;
use YesWiki\Content\Service\PageManager;
use YesWiki\Core\YesWikiController;
use YesWiki\Kernel\Service\AssetRegistry;
use YesWiki\Kernel\Service\UrlFormatter;
use YesWiki\Render\Service\PresentationRenderer;
use YesWiki\Search\Service\SearchIndexQuery;
use YesWiki\Search\Service\SearchResultPresenter;

/** The results fragment `{{search}}` drives with `hx-get` (ticket 26): the whole wiki as Items, drawn by the shared presentations. */
class SearchApiController extends YesWikiController
{
    #[Route('/api/search', methods: ['GET'], options: ['acl' => ['public']])]
    public function search(Request $request): Response
    {
        $phrase = trim((string)$request->query->get('q', ''));
        $type = trim((string)$request->query->get('type', ''));
        $formId = trim((string)$request->query->get('form', ''));
        if ($formId !== '') {
            $type = 'entry';
        }

        $tags = array_values(array_filter(
            array_map('trim', explode(',', (string)$request->query->get('tags', '')))
        ));
        $limit = max(1, min((int)$request->query->get('limit', SearchIndexQuery::DEFAULT_LIMIT), SearchIndexQuery::MAX_LIMIT));
        $page = max(1, (int)$request->query->get('page', 1));
        $display = trim((string)$request->query->get('display', 'list'));
        if (!PresentationRenderer::knows($display)) {
            $display = 'list';
        }
        $sort = trim((string)$request->query->get('sort', ''));
        if (!isset(SearchIndexQuery::SORTS[$sort])) {
            $sort = '';
        }

        $query = $this->getService(SearchIndexQuery::class);
        $found = $phrase === '' && $tags === []
            ? $query->all($type === '' ? null : $type, $limit, ($page - 1) * $limit, [], $sort, $formId)
            : $query->search($phrase, $type === '' ? null : $type, $limit, ($page - 1) * $limit, $tags, $sort, $formId);

        $withFacets = $request->query->get('facets') !== '0';
        $facets = $withFacets ? $query->facets($phrase) : [];
        $forms = $withFacets && isset($facets['entry']) ? $this->formFacets($query->formFacets($phrase)) : [];

        $rendered = '';
        $this->getService(AssetRegistry::class)->capture(
            fn (): string => $this->getService(PresentationRenderer::class)->render(
                $display,
                $this->items($found['results'], $phrase),
                ['columns' => 3]
            ),
            $rendered
        );

        $response = new Response($this->render('@core/search-results.twig', [
            'phrase' => $phrase,
            'rendered' => $rendered,
            'found' => count($found['results']),
            'facets' => $facets,
            'forms' => $forms,
            'form' => $formId,
            'exports' => $this->exports($phrase, $type, $formId, $sort),
            'total' => $found['total'],
            'capped' => $found['capped'],
            'page' => $page,
            'pages' => (int)ceil($found['total'] / $limit),
            'limit' => $limit,
            'type' => $type,
            'display' => $display,
            'sort' => $sort,
            'tags' => $tags,
        ]));

        return $this->withSearchUrl($response, ['q' => $phrase, 'type' => $type, 'form' => $formId, 'display' => $display === 'list' ? '' : $display, 'sort' => $sort, 'page' => $page > 1 ? (string)$page : '']);
    }

    /**
     * @param array<string, int> $counts form id => matches
     *
     * @return list<array{id: string, label: string, count: int}>
     */
    private function formFacets(array $counts): array
    {
        $forms = $this->getService(FormManager::class)->getMany(array_keys($counts));
        $facets = [];
        foreach ($counts as $id => $count) {
            if (isset($forms[$id])) {
                $facets[] = ['id' => (string)$id, 'label' => (string)$forms[$id]['label'], 'count' => $count];
            }
        }

        return $facets;
    }

    /**
     * The downloads this result set has: a form's own formats when the search is narrowed to one form, every entry otherwise, and nothing for pages alone.
     *
     * @return list<array{label: string, href: string, icon: string}>
     */
    private function exports(string $phrase, string $type, string $formId, string $sort): array
    {
        if ($type !== '' && $type !== 'entry') {
            return [];
        }
        [$field, $order] = SearchIndexQuery::SORTS[$sort] ?? ['', ''];
        $params = ['keywords' => $phrase, 'field' => $field, 'order' => strtolower($order)];
        $links = $this->getService(ExportLinks::class);
        if ($formId !== '') {
            $form = $this->getService(FormManager::class)->getOne($formId);

            return $form === null ? [] : $links->forForm($form, $params);
        }

        return $links->forEntries($params);
    }

    /**
     * @param list<array{tag: string, title: string, content_type: string, form_id: string, updated_at: string}> $rows
     *
     * @return list<Item>
     */
    private function items(array $rows, string $phrase): array
    {
        $items = [];
        $pages = $this->getService(PageManager::class);
        $images = $this->getService(ContentImage::class);
        foreach ($this->getService(SearchResultPresenter::class)->present($rows, $phrase) as $index => $result) {
            $page = $pages->getOne($result['tag']);
            $items[] = new Item(
                id: $result['tag'],
                title: $result['title'],
                description: $result['excerpt'] === '' ? null : htmlspecialchars($result['excerpt'], ENT_QUOTES),
                image: $page === null ? null : $images->urlFor($page, $result['content_type'], (string)($rows[$index]['form_id'] ?? '')),
                url: $result['url'],
                date: ($rows[$index]['updated_at'] ?? '') === '' ? null : str_replace(' ', 'T', (string)$rows[$index]['updated_at']),
                badge: _t('SEARCH_TYPE_' . strtoupper($result['content_type'])),
            );
        }

        return $items;
    }

    /**
     * Tell the browser which address this result set corresponds to, so a search can be copied, bookmarked, shared and reloaded.
     *
     * @param array<string, string> $params
     */
    private function withSearchUrl(Response $response, array $params): Response
    {
        $params = array_filter($params, static fn (string $value): bool => $value !== '');

        $response->headers->set(
            'HX-Replace-Url',
            $this->getService(UrlFormatter::class)->href('', 'search', $params, false)
        );

        return $response;
    }
}
