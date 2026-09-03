<?php

namespace YesWiki\Content\Action;

use YesWiki\Content\Entity\PageBody;
use YesWiki\Content\Service\PageSummary;
use YesWiki\Core\YesWikiAction;
use YesWiki\Files\Service\ProgramFiles;
use YesWiki\Identity\Service\AclService;
use YesWiki\Kernel\Database\SqlFragment;
use YesWiki\Kernel\Database\SqlParameters;
use YesWiki\Kernel\Performable\RegisteredAction;
use YesWiki\Kernel\Service\AssetRegistry;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Kernel\Service\InclusionStack;
use YesWiki\Kernel\Service\PerformableArguments;
use YesWiki\Kernel\Service\RuntimeConfig;
use YesWiki\Kernel\Service\StringUtilService;
use YesWiki\Render\Service\MarkdownFormatterService;
use YesWiki\Render\Service\TemplateEngine;
use YesWiki\Search\Service\SearchIndexSchema;
use YesWiki\Search\Service\TagsManager;

/** `{{filtertags}}` -- converted from the procedural actions/filtertags.php by ticket 06. */
class FiltertagsAction extends YesWikiAction implements RegisteredAction
{
    public static function performableName(): string
    {
        return 'filtertags';
    }

    public function run(): string
    {
        ob_start();
        try {
            $this->emit();
        } catch (\Throwable $t) {
            $this->output .= (string)ob_get_clean();

            throw $t;
        }

        return (string)ob_get_clean();
    }

    private function emit(): void
    {
        $nbcartrunc = 200;

        $elementwidth = $this->getService(PerformableArguments::class)->get('elementwidth');
        if (empty($elementwidth)) {
            $elementwidth = 300;
        }

        $elementoffset = $this->getService(PerformableArguments::class)->get('elementoffset');
        if (empty($elementoffset)) {
            $elementoffset = 10;
        }

        $template = $this->getService(PerformableArguments::class)->get('template');
        if (empty($template) || !$this->getService(ProgramFiles::class)->isFile('templates/' . $template)) {
            $template = 'pages_grid_filter.twig';
        }

        $params = $this->filterParameters();
        if (is_string($params)) {
            echo $params;

            return;
        }
        $taglist = $params['tags'];
        unset($params['tags']);
        if ($taglist === []) {
            return;
        }

        $dbService = $this->getService(DbService::class);
        $userCol = $dbService->quoteIdentifier('user');
        $timeCol = $dbService->quoteIdentifier('time');
        $prefix = $this->getService(RuntimeConfig::class)['table_prefix'];

        $included = $this->getService(InclusionStack::class)->getAll();
        $notIncluded = $included === []
            ? SqlFragment::empty()
            : SqlFragment::of('AND ' . $prefix . 'pages.tag NOT IN (' . SqlParameters::placeholders(count($included)) . ')', $included);

        $filter = SqlFragment::all(
            ' ',
            SqlFragment::of(
                'tags.keyword IN (' . SqlParameters::placeholders(count($taglist)) . ')'
                . ' AND tags.tag = ' . $prefix . 'pages.tag',
                $taglist
            ),
            $notIncluded
        );

        $keywordsTable = $this->getService(SearchIndexSchema::class)->keywordsTable();
        $req = "SELECT DISTINCT {$prefix}pages.tag, {$timeCol}, {$userCol}, owner, body"
            . ' FROM ' . $prefix . 'pages, ' . $keywordsTable . ' tags'
            . " WHERE latest = 'Y' AND parent = '' AND " . $filter->sql
            . ' ORDER BY ' . $prefix . 'pages.tag ASC';
        $aclService = $this->getService(AclService::class);
        $pages = array_filter(
            $this->getService(DbService::class)->loadAll($req, $filter->params),
            function ($page) use ($aclService) {
                return $aclService->hasAccess('read', $page['tag']);
            }
        );

        echo '<div class="well well-sm no-dblclick controls">' . "\n" . '<div class="pull-right muted"><span class="nbfilteredelements">' . count($pages) . '</span> ' . _t('TAGS_RESULTS') . '</div>';
        foreach ($params as $param) {
            echo '<div class="filter-group ' . $param['class'] . '" data-type="' . $param['toggle'] . '">' . "\n" . $param['title'] . "\n" . '<div class="btn-group filter-tags">' . "\n";
            foreach ($param['arraytags'] as $tagname) {
                if ($tagname == 'alaligne') {
                    echo '<br />' . "\n";
                } else {
                    echo '<button type="button" class="btn btn-default filter" data-filter="' . StringUtilService::withoutAccents($tagname) . '">' . $tagname . '</button>' . "\n";
                }
            }
            echo '</div>' . "\n" . '</div>' . "\n";
        }
        echo '</div>';

        $element = [];

        foreach ($pages as $page) {
            $page['body'] = PageBody::decode($page['body']);
            $element[$page['tag']]['tagnames'] = '';
            $element[$page['tag']]['tagbadges'] = '';
            $element[$page['tag']]['body'] = PageBody::content($page['body']);
            $element[$page['tag']]['owner'] = $page['owner'];
            $element[$page['tag']]['user'] = $page['user'];
            $element[$page['tag']]['time'] = $page['time'];
            $element[$page['tag']]['title'] = $this->getService(PageSummary::class)->title($page);
            $element[$page['tag']]['image'] = $this->getService(PageSummary::class)->image($page);
            $this->getService(InclusionStack::class)->register($page['tag']);
            $element[$page['tag']]['desc'] = StringUtilService::truncateOnWord(strip_tags($this->getService(MarkdownFormatterService::class)->format(PageBody::content($page['body']))), $nbcartrunc);
            $this->getService(InclusionStack::class)->unregisterLast();
            foreach (TagsManager::keywordsOf($page) as $keyword) {
                $element[$page['tag']]['tagnames'] .= StringUtilService::withoutAccents($keyword) . ' ';
                $element[$page['tag']]['tagbadges'] .= '<span class="tag-label label label-primary">' . htmlspecialchars($keyword, ENT_QUOTES) . '</span>&nbsp;';
            }
        }

        echo $this->getService(TemplateEngine::class)->renderSafely("@core/$template", [
            'elements' => $element,
            'elementwidth' => $elementwidth,
            'elementoffset' => $elementoffset,
        ]);

        $this->getService(AssetRegistry::class)->addJsFile('javascripts/filtertags.js');
    }

    /**
     * The `filter1`, `filter2`, ...
     *
     * @param array<mixed> $tab
     *
     * @return array<mixed>|string
     */
    private function filterParameters(int $nb = 1, array $tab = [])
    {
        $filter = $this->getService(PerformableArguments::class)->get('filter' . $nb);

        if (empty($filter) && $nb == 1) {
            return '<div class="alert alert-danger"><strong>' . _t('TAGS_ACTION_FILTERTAGS') . '</strong> : ' . _t('TAGS_NO_FILTERS') . '</div>' . "\n";
        } elseif (empty($filter)) {
            return $tab;
        }

        if (!isset($tab['tags'])) {
            $tab['tags'] = [];
        }
        $explodelabel = explode(':', $filter);

        if (count($explodelabel) > 2) {
            return '<div class="alert alert-danger"><strong>' . _t('TAGS_ACTION_FILTERTAGS') . '</strong> : ' . _t('TAGS_ONLY_ONE_DOUBLEPOINT') . '</div>' . "\n";
        } elseif (count($explodelabel) == 2) {
            $tab[$nb]['title'] = '<strong>' . $explodelabel[0] . ' : </strong>' . "\n";
            $tab[$nb]['arraytags'] = explode(',', $explodelabel[1]);
        } else {
            $tab[$nb]['title'] = '';
            $tab[$nb]['arraytags'] = explode(',', $explodelabel[0]);
        }
        $toggle = $this->getService(PerformableArguments::class)->get('select' . $nb);
        if (!empty($toggle) && $toggle == 'checkbox') {
            $tab[$nb]['toggle'] = $toggle;
        } else {
            $tab[$nb]['toggle'] = 'radio';
        }
        $class = $this->getService(PerformableArguments::class)->get('class' . $nb);
        if (!empty($class)) {
            $tab[$nb]['class'] = $class;
        } else {
            $tab[$nb]['class'] = 'filter-inline';
        }
        $tab['tags'] = [...$tab['tags'], ...array_values($tab[$nb]['arraytags'])];
        $nb++;
        $tab = $this->filterParameters($nb, $tab);

        return $tab;
    }
}
