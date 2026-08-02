<?php

namespace YesWiki\Search\Action;

use YesWiki\Core\YesWikiAction;
use YesWiki\Kernel\Performable\RegisteredAction;
use YesWiki\Kernel\Service\PerformableArguments;
use YesWiki\Kernel\Service\UrlFormatter;
use YesWiki\Render\Service\TemplateEngine;
use YesWiki\Search\Service\SearchIndexer;
use YesWiki\Search\Service\SearchIndexQuery;
use YesWiki\Search\Service\SearchIndexSchema;
use YesWiki\Search\Service\SearchResultPresenter;

/**
 * `{{search}}` -- the search surface (ticket 26).
 *
 * The **action** is the unit and the `/search` route is a convenience over it, not the other
 * way round. Actions are this codebase's composition primitive, and "results filtered by
 * content type" is something a webmaster will want inside a page of their own, next to other
 * things. A route that owned the feature would make that impossible.
 *
 * The shape is `AdminContentAction`'s, simplified: a filter form, and a container that
 * `hx-get`s the results fragment with `hx-include` on the form. Filtering, paging and
 * type-narrowing are then one endpoint away from each other, with no page reload and no
 * second rendering path.
 *
 * Parameters, all optional:
 *   q=             what to search for. Also read from `?q=` -- the field is named `q` so a
 *                  shared search URL stays short and looks like every other search on the web
 *   type=          restrict to one content type: page, entry, comment, file, user, form
 *   display=       list (default), accordion or cards
 *   limit=         results per page
 *   filters="0"    hide the content-type filter, for an embedded box that only searches
 *   autofocus="1"  put the cursor in the box on arrival. Off by default and deliberately so:
 *                  {{search}} embedded in a page of prose must not steal focus from the
 *                  reader. The /search route turns it on, because there the box IS the page.
 */
class SearchAction extends YesWikiAction implements RegisteredAction
{
    /** The layouts the results fragment knows how to render. */
    public const DISPLAY_MODES = ['list', 'accordion', 'cards'];

    public static function performableName(): string
    {
        return 'search';
    }

    public function run(): string
    {
        $arguments = $this->getService(PerformableArguments::class);
        $schema = $this->getService(SearchIndexSchema::class);
        $templateEngine = $this->getService(TemplateEngine::class);

        // "not built yet" and "nothing matched" have to read differently: an upgraded wiki
        // serves the first until the drain finishes, and "no results" there would send a
        // webmaster hunting for content that is present. Deliberately NOT keyed on a
        // non-empty queue -- editing one form queues every entry under it, and search going
        // dark on a usable index would be far worse than results a few minutes stale.
        $indexer = $this->getService(SearchIndexer::class);
        if ($schema->exists() && $indexer->indexedCount() === 0 && $indexer->pending() > 0) {
            // An index that is empty *and* has work queued is a wiki nobody has drained yet.
            // Fill a bounded slice here rather than showing "still building" to whoever
            // happens to search first.
            //
            // This is not belt-and-braces over the maintenance hook, it is the case that hook
            // cannot cover: maintenance is gated by a 30-minute lock file living in `cache/`,
            // which survives a reinstall -- so a wiki installed inside that window is never
            // drained by it at all, and sat on "index building" forever. Found by running the
            // browser suite on MySQL, where every test reinstalls.
            //
            // Bounded by rows and by wall clock, so the answer is right at both ends: a fresh
            // install (a few dozen seeded pages) fills completely and the visitor never sees
            // the notice, while a just-upgraded million-row wiki stays honestly "building"
            // because 200 rows cannot make it useful.
            $indexer->drain(200, 2);
        }
        if (!$schema->exists() || $indexer->indexedCount() === 0) {
            return $templateEngine->render('@core/search-building.twig', [
                'exists' => $schema->exists(),
                'pending' => $schema->exists() ? $indexer->pending() : 0,
            ]);
        }

        $phrase = trim((string)($arguments->get('q', '') ?: ($_GET['q'] ?? '')));
        $type = trim((string)($arguments->get('type', '') ?: ($_GET['type'] ?? '')));
        $display = trim((string)($arguments->get('display', '') ?: ($_GET['display'] ?? 'list')));

        return $templateEngine->render('@core/search-action.twig', [
            'phrase' => $phrase,
            'type' => $type,
            // validated here as well as in the fragment: it reaches a class name either way
            'display' => in_array($display, self::DISPLAY_MODES, true) ? $display : 'list',
            'limit' => (int)$arguments->get('limit', SearchIndexQuery::DEFAULT_LIMIT),
            'showFilters' => (string)$arguments->get('filters', '1') !== '0',
            'autofocus' => (string)$arguments->get('autofocus', '0') === '1',
            'types' => SearchResultPresenter::filterableTypes(),
            'apiUrl' => $this->getService(UrlFormatter::class)->href('', 'api/search', null, false),
        ]);
    }
}
