<?php

namespace YesWiki\Content\Action;

use YesWiki\Content\Entity\PageBody;
use YesWiki\Core\YesWikiAction;
use YesWiki\Identity\Service\AclService;
use YesWiki\Kernel\Database\SqlFragment;
use YesWiki\Kernel\Database\SqlParameters;
use YesWiki\Kernel\Performable\RegisteredAction;
use YesWiki\Kernel\Service\AssetRegistry;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Kernel\Service\InclusionStack;
use YesWiki\Kernel\Service\PerformableArguments;
use YesWiki\Kernel\Service\RuntimeConfig;
use YesWiki\Render\Service\MarkdownFormatterService;
use YesWiki\Render\Service\TemplateEngine;
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
        include_once YESWIKI_SOURCE_DIR . '/src/Content/tags.functions.php';
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
        if (empty($template) || !file_exists('templates/' . $template)) {
            $template = 'pages_grid_filter.twig';
        }

        $params = get_filtertags_parameters_recursive();
        if (!is_array($params) && strstr($params, 'alert-danger')) {
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
            : SqlFragment::of('AND tag NOT IN (' . SqlParameters::placeholders(count($included)) . ')', $included);

        $filter = SqlFragment::all(
            ' ',
            SqlFragment::of(
                'tags.value IN (' . SqlParameters::placeholders(count($taglist)) . ')'
                . " AND tags.property = 'http://outils-reseaux.org/_vocabulary/tag'"
                . ' AND tags.resource = tag',
                $taglist
            ),
            $notIncluded
        );

        $req = "SELECT DISTINCT tag, {$timeCol}, {$userCol}, owner, body"
            . ' FROM ' . $prefix . 'pages, ' . $prefix . 'triples tags'
            . " WHERE latest = 'Y' AND parent = '' AND " . $filter->sql
            . ' ORDER BY tag ASC';
        $pages = $this->getService(DbService::class)->loadAll($req, $filter->params);

        echo '<div class="well well-sm no-dblclick controls">' . "\n" . '<div class="pull-right muted"><span class="nbfilteredelements">' . count($pages) . '</span> ' . _t('TAGS_RESULTS') . '</div>';
        foreach ($params as $param) {
            echo '<div class="filter-group ' . $param['class'] . '" data-type="' . $param['toggle'] . '">' . "\n" . $param['title'] . "\n" . '<div class="btn-group filter-tags">' . "\n";
            foreach ($param['arraytags'] as $tagname) {
                if ($tagname == 'alaligne') {
                    echo '<br />' . "\n";
                } else {
                    echo '<button type="button" class="btn btn-default filter" data-filter="' . sanitizeEntity($tagname) . '">' . $tagname . '</button>' . "\n";
                }
            }
            echo '</div>' . "\n" . '</div>' . "\n";
        }
        echo '</div>';

        $aclService = $this->getService(AclService::class);
        $element = [];

        foreach ($pages as $page) {
            if ($aclService->hasAccess('read', $page['tag'])) {
                $page['body'] = PageBody::decode($page['body']);
                $element[$page['tag']]['tagnames'] = '';
                $element[$page['tag']]['tagbadges'] = '';
                $element[$page['tag']]['body'] = PageBody::content($page['body']);
                $element[$page['tag']]['owner'] = $page['owner'];
                $element[$page['tag']]['user'] = $page['user'];
                $element[$page['tag']]['time'] = $page['time'];
                $element[$page['tag']]['title'] = get_title_from_body($page);
                $element[$page['tag']]['image'] = get_image_from_body($page);
                $this->getService(InclusionStack::class)->register($page['tag']);
                $element[$page['tag']]['desc'] = tokenTruncate(strip_tags($this->getService(MarkdownFormatterService::class)->format(PageBody::content($page['body']))), $nbcartrunc);
                $this->getService(InclusionStack::class)->unregisterLast();
                foreach (TagsManager::keywordsOf($page) as $keyword) {
                    $element[$page['tag']]['tagnames'] .= sanitizeEntity($keyword) . ' ';
                    $element[$page['tag']]['tagbadges'] .= '<span class="tag-label label label-primary">' . htmlspecialchars($keyword, ENT_QUOTES) . '</span>&nbsp;';
                }
            }
        }

        echo $this->getService(TemplateEngine::class)->renderSafely("@core/$template", [
            'elements' => $element,
            'elementwidth' => $elementwidth,
            'elementoffset' => $elementoffset,
        ]);

        $this->getService(AssetRegistry::class)->addJsFile('javascripts/filtertags.js');
    }
}
