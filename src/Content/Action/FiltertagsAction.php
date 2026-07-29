<?php

namespace YesWiki\Content\Action;

use YesWiki\Content\Service\TripleStore;
use YesWiki\Core\YesWikiAction;
use YesWiki\Identity\Service\AclService;
use YesWiki\Kernel\Performable\RegisteredAction;
use YesWiki\Kernel\Service\AssetsManager;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Kernel\Service\InclusionStack;
use YesWiki\Kernel\Service\PerformableArguments;
use YesWiki\Kernel\Service\RuntimeConfig;
use YesWiki\Render\Service\MarkdownFormatterService;
use YesWiki\Render\Service\TemplateEngine;

/**
 * `{{filtertags}}` -- converted from the procedural actions/filtertags.php by ticket 06.
 *
 * The body still prints rather than returning, so it runs inside an output buffer in its
 * own method: that is what the old runFileInBuffer() did, and it keeps any early `return;`
 * in the body from discarding output.
 */
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
            // Several of these bodies end in $this->exit(), which throws. The old
            // runFileInBuffer() accumulated output into a by-reference variable, so a throw
            // did not discard what had already been printed; keep that by flushing into the
            // shared output before rethrowing -- and close the buffer either way.
            $this->output .= (string)ob_get_clean();

            throw $t;
        }

        return (string)ob_get_clean();
    }

    private function emit(): void
    {
        include_once YESWIKI_SOURCE_DIR . '/src/tags.functions.php';
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

        // requete avec toutes les pages contenants les mots cles
        $dbService = $this->getService(DbService::class);
        $userCol = $dbService->quoteIdentifier('user');
        $req = "SELECT DISTINCT tag, time, $userCol, owner, body
        FROM " . $this->getService(RuntimeConfig::class)['table_prefix'] . 'pages, ' . $this->getService(RuntimeConfig::class)['table_prefix'] . "triples tags
        WHERE latest = 'Y' AND comment_on = '' AND tags.value IN (" . $taglist . ") AND tags.property = 'http://outils-reseaux.org/_vocabulary/tag' AND tags.resource = tag AND tag NOT IN ('" . implode("','", $this->getService(InclusionStack::class)->getAll()) . "') ORDER BY tag ASC";
        $pages = $this->getService(DbService::class)->loadAll($req);

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
        // affichage des resultats
        foreach ($pages as $page) {
            if ($aclService->hasAccess('read', $page['tag'])) {
                $element[$page['tag']]['tagnames'] = '';
                $element[$page['tag']]['tagbadges'] = '';
                $element[$page['tag']]['body'] = $page['body'];
                $element[$page['tag']]['owner'] = $page['owner'];
                $element[$page['tag']]['user'] = $page['user'];
                $element[$page['tag']]['time'] = $page['time'];
                $element[$page['tag']]['title'] = get_title_from_body($page);
                $element[$page['tag']]['image'] = get_image_from_body($page);
                $this->getService(InclusionStack::class)->register($page['tag']);
                $element[$page['tag']]['desc'] = tokenTruncate(strip_tags($this->getService(MarkdownFormatterService::class)->format($page['body'])), $nbcartrunc);
                $this->getService(InclusionStack::class)->unregisterLast();
                $pagetags = $this->getService(TripleStore::class)->getAll($page['tag'], 'http://outils-reseaux.org/_vocabulary/tag', '', '');
                foreach ($pagetags as $tag) {
                    $tag['value'] = stripslashes($tag['value']);
                    $element[$page['tag']]['tagnames'] .= sanitizeEntity($tag['value']) . ' ';
                    $element[$page['tag']]['tagbadges'] .= '<span class="tag-label label label-primary">' . $tag['value'] . '</span>&nbsp;';
                }
            }
        }

        echo $this->getService(TemplateEngine::class)->renderSafely("@core/$template", [
            'elements' => $element,
            'elementwidth' => $elementwidth,
            'elementoffset' => $elementoffset,
        ]);

        // ajout du javascript gerant le filtrage
        $this->getService(AssetsManager::class)->AddJavascriptFile('javascripts/filtertags.js');
    }
}
