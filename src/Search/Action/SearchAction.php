<?php

namespace YesWiki\Search\Action;

use YesWiki\Core\YesWikiAction;
use YesWiki\Kernel\Component\Category;
use YesWiki\Kernel\Component\Component;
use YesWiki\Kernel\Component\ProvidesComponents;
use YesWiki\Kernel\Component\Setting;
use YesWiki\Kernel\Performable\RegisteredAction;
use YesWiki\Kernel\Service\PerformableArguments;
use YesWiki\Kernel\Service\UrlFormatter;
use YesWiki\Render\Service\PresentationCatalog;
use YesWiki\Render\Service\PresentationRenderer;
use YesWiki\Render\Service\TemplateEngine;
use YesWiki\Search\Service\SearchIndexer;
use YesWiki\Search\Service\SearchIndexQuery;
use YesWiki\Search\Service\SearchIndexSchema;
use YesWiki\Search\Service\SearchResultPresenter;

/** `{{search}}` -- the search surface (ticket 26). */
class SearchAction extends YesWikiAction implements RegisteredAction, ProvidesComponents
{
    public static function performableName(): string
    {
        return 'search';
    }

    public function components(): array
    {
        return [
            Component::for('search')
                ->category(Category::Forms)
                ->label(_t('AB_advanced_action_search_label'))
                ->icon('loupe')
                ->previewHeight('200px')
                ->settings(
                    Setting::text('phrase')
                        ->label(_t('AB_advanced_action_search_phrase_label')),
                    Setting::text('type')
                        ->label(_t('AB_advanced_action_search_type_label')),
                    Setting::number('limit')
                        ->label(_t('AB_advanced_action_search_limit_label'))
                        ->default('')
                        ->min(1),
                    Setting::text('filters')
                        ->label(_t('AB_advanced_action_search_filters_label')),
                ),
        ];
    }

    public function run(): string
    {
        $arguments = $this->getService(PerformableArguments::class);
        $schema = $this->getService(SearchIndexSchema::class);
        $templateEngine = $this->getService(TemplateEngine::class);

        $indexer = $this->getService(SearchIndexer::class);
        if ($schema->exists() && $indexer->indexedCount() === 0 && $indexer->pending() > 0) {
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
        $sort = trim((string)($_GET['sort'] ?? ''));
        $form = trim((string)($_GET['form'] ?? ''));

        $tags = trim((string)($arguments->get('tags', '') ?: ($_GET['tags'] ?? '')));

        return $templateEngine->render('@core/search-action.twig', [
            'phrase' => $phrase,
            'type' => $type,
            'tags' => $tags,

            'display' => PresentationRenderer::knows($display) ? $display : 'list',
            'sort' => isset(SearchIndexQuery::SORTS[$sort]) ? $sort : '',
            'form' => $form,
            'switcher' => $this->getService(PresentationCatalog::class)->sharedSwitcher(),
            'limit' => (int)$arguments->get('limit', SearchIndexQuery::DEFAULT_LIMIT),
            'showFilters' => (string)$arguments->get('filters', '1') !== '0',
            'autofocus' => (string)$arguments->get('autofocus', '0') === '1',
            'types' => SearchResultPresenter::filterableTypes(),
            'apiUrl' => $this->getService(UrlFormatter::class)->href('', 'api/search', null, false),
        ]);
    }
}
