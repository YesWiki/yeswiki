<?php

namespace YesWiki\Content\Action;

use YesWiki\Core\YesWikiAction;
use YesWiki\Kernel\Performable\RegisteredAction;
use YesWiki\Kernel\Service\AssetRegistry;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Kernel\Service\PageContext;
use YesWiki\Kernel\Service\RuntimeConfig;
use YesWiki\Kernel\Service\UrlFormatter;

class TagCloudAction extends YesWikiAction implements RegisteredAction
{
    /** `{{tagcloud}}` in page content -- stated, not inferred from the filename. */
    public static function performableName(): string
    {
        return 'tagcloud';
    }

    public const TAG_PROPERTY = 'http://outils-reseaux.org/_vocabulary/tag';

    public function formatArguments($args)
    {
        $class = $args['class'] ?? '';
        $tags = trim($args['tags'] ?? '');
        $classcount = $args['classcount'] ?? '';

        return [
            'class' => empty($class) ? '' : ' ' . $class,
            'tags' => empty($tags) ? [] : array_filter(array_map('trim', explode(' ', $tags)), 'strlen'),
            'classcount' => empty($classcount) ? 6 : (int)$classcount,
            'sort' => $args['sort'] ?? '',
        ];
    }

    public function run()
    {
        $this->getService(AssetRegistry::class)->addJsFile('javascripts/tag.js');

        $selectiontags = $this->buildSelectionTagsClause($this->arguments['tags']);
        $tablePrefix = $this->getService(RuntimeConfig::class)['table_prefix'];

        // on récupère le nb maximum et le nb minimum d'occurences
        $sql = 'SELECT COUNT(value) AS nb FROM ' . $tablePrefix . 'triples WHERE property="' . self::TAG_PROPERTY . '" ' . $selectiontags . ' GROUP BY value';
        $min_max = $this->getService(DbService::class)->loadAll($sql);
        $min = 100000000;
        $max = 0;
        foreach ($min_max as $tab_min_max) {
            if ($tab_min_max['nb'] > $max) {
                $max = $tab_min_max['nb'];
            } elseif ($tab_min_max['nb'] < $min) {
                $min = $tab_min_max['nb'];
            }
        }
        // permettra de fixer une classe pour la taille du tag
        $mult = $max / $this->arguments['classcount'];
        if ($mult < 1) {
            $mult = 1;
        }

        // on récupère tous les tags existants
        $sql = 'SELECT value, resource FROM ' . $tablePrefix . 'triples WHERE property="' . self::TAG_PROPERTY . '" ' . $selectiontags . ' ORDER BY value ASC, resource ASC';
        $tab_tous_les_tags = $this->getService(DbService::class)->loadAll($sql);

        $output = '';
        if ($tab_tous_les_tags !== []) {
            $i = 1;
            $nb_pages = 0;
            $liste_page = '';
            $tag_precedent = '';
            $tab_tag = [];
            $tab_tous_les_tags['dummy']['value'] = 'fin'; // on ajoute un element au tableau pour boucler une derniere fois
            $tab_tous_les_tags['dummy']['resource'] = 'fin';
            foreach ($tab_tous_les_tags as $tab_les_tags) {
                $tagstripped = stripslashes($tab_les_tags['value']);
                // both "resource" (a page tag) and "value" (a tag name) come from the triples
                // table and must be escaped : they end up either as direct page HTML, or inside
                // data-title/data-content attributes that Bootstrap's popover renders as HTML
                // (tag.js initializes it with html:true), so an unescaped "<" there is also live.
                $resourceEscaped = htmlspecialchars($tab_les_tags['resource']);
                if ($tagstripped == $tag_precedent || $tag_precedent == '') {
                    $nb_pages++;
                    $liste_page .= '<li class="pagewiki-link"><a class="link_pagewiki" href="' . htmlspecialchars($this->getService(UrlFormatter::class)->href('', $tab_les_tags['resource'])) . '">' . $resourceEscaped . '</a></li>';
                } else {
                    // on affiche les informations pour ce tag
                    if ($nb_pages > 1) {
                        $texte_page = $nb_pages . ' ' . _t('TAGS_PAGES');
                    } else {
                        $texte_page = _t('TAGS_ONE_PAGE');
                    }
                    $tagPrecedentEscaped = htmlspecialchars($tag_precedent);
                    $texte_liste = '<li class="tag-list">' . "\n" . '<a class="tag-link size' . ceil($nb_pages / $mult) . '" id="j' . $i . '" data-title="' . htmlspecialchars('<button class="btn-close-popover pull-right close" type="button">&times;</button>' . $texte_page . ' ' . _t('TAGS_CONTAINING_TAG') . ' : <a href="' . htmlspecialchars($this->getService(UrlFormatter::class)->href('listpages', $this->getService(PageContext::class)->getTag(), 'tags=' . $tag_precedent, true)) . '" class="tag-label label label-primary">' . $tagPrecedentEscaped . '</a>') . '" data-content="' . htmlspecialchars('<ul class="unstyled list-unstyled">' . $liste_page . '</ul>', ENT_QUOTES, $this->getService(RuntimeConfig::class)['charset']) . '">' . $tagPrecedentEscaped . '</a>' . "\n";
                    $texte_liste .= '</li>' . "\n";
                    $tab_tag[] = $texte_liste;

                    // on reinitialise les variables
                    $nb_pages = 1;
                    $liste_page = '<li><a class="pagewiki-link" href="' . htmlspecialchars($this->getService(UrlFormatter::class)->href('', $tab_les_tags['resource'])) . '">' . $resourceEscaped . '</a></li>' . "\n";
                    $i++;
                }
                $tag_precedent = $tagstripped;
            }

            if (count($tab_tag) > 0) {
                $output .= '<div class="no-dblclick boite_nuage' . $this->arguments['class'] . '">
			<ul class="nuage">' . "\n";
                // on regarde s'il faut trier alphabetiquement
                if ($this->arguments['sort'] === 'alpha') {
                } else {
                    shuffle($tab_tag);
                }
                foreach ($tab_tag as $tag) {
                    $output .= $tag;
                }
                $output .= '</ul><div class="clear"></div>' . "\n" . '</div>' . "\n";
            }
        }

        return $output;
    }

    /**
     * Builds the "AND value IN (...)" SQL clause from the (already trimmed/filtered) tag
     * tokens, escaping each one individually to prevent SQL injection.
     */
    private function buildSelectionTagsClause(array $tags): string
    {
        if (empty($tags)) {
            return '';
        }
        $dbService = $this->getService(DbService::class);
        // single-quoted, not double-quoted: DbService::escape() (\PDO::quote()) only
        // guarantees safety inside a single-quoted SQL literal (see
        // AclService::updateRequestWithACL() and get_filtertags_parameters_recursive()
        // for the same class of driver-dependent bug, fixed the same way)
        $escapedTags = array_map(function ($tag) use ($dbService) {
            return "'" . $dbService->escape($tag) . "'";
        }, $tags);

        return ' AND value IN (' . implode(',', $escapedTags) . ')';
    }
}
