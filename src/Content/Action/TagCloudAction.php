<?php

namespace YesWiki\Content\Action;

use YesWiki\Core\YesWikiAction;
use YesWiki\Kernel\Component\Category;
use YesWiki\Kernel\Component\Component;
use YesWiki\Kernel\Component\ProvidesComponents;
use YesWiki\Kernel\Component\Setting;
use YesWiki\Kernel\Database\SqlFragment;
use YesWiki\Kernel\Database\SqlParameters;
use YesWiki\Kernel\Performable\RegisteredAction;
use YesWiki\Kernel\Service\AssetRegistry;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Kernel\Service\PageContext;
use YesWiki\Kernel\Service\RuntimeConfig;
use YesWiki\Kernel\Service\UrlFormatter;

class TagCloudAction extends YesWikiAction implements RegisteredAction, ProvidesComponents
{
    /** `{{tagcloud}}` in page content -- stated, not inferred from the filename. */
    public static function performableName(): string
    {
        return 'tagcloud';
    }

    public function components(): array
    {
        return [
            Component::for('tagcloud')
                ->category(Category::Navigation)
                ->label(_t('AB_tags_listpagestag_nuagetag_label'))
                ->icon('tags')
                ->previewHeight('200px')
                ->settings(
                    Setting::text('tags')
                        ->label(_t('AB_tags_listpagestag_tags_label'))
                        ->hint(_t('AB_tags_listpagestag_tags_hint')),
                    Setting::choice('sort', [
                        '' => _t('AB_tags_nuagetag_tri_shuffle'),
                        'apha' => _t('AB_tags_listpagestag_tri_alpha'),
                    ])
                        ->label(_t('AB_tags_listpagestag_tri_label'))
                        ->default(''),
                    Setting::number('classcount')
                        ->label(_t('AB_tags_listpagestag_nbclasses_label'))
                        ->min(0),
                ),
        ];
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

        // `property="..."` was a DOUBLE-quoted literal, which MySQL reads as a string and
        // PostgreSQL as an identifier -- so this action failed outright on one of the three
        // supported drivers. Bound now, so no driver's quoting rules apply to it.
        $tagProperty = SqlFragment::of('property = ?', [self::TAG_PROPERTY]);
        $filter = SqlFragment::all(' ', $tagProperty, $selectiontags);

        // on récupère le nb maximum et le nb minimum d'occurences
        $sql = 'SELECT COUNT(value) AS nb FROM ' . $tablePrefix . 'triples WHERE ' . $filter->sql . ' GROUP BY value';
        $min_max = $this->getService(DbService::class)->loadAll($sql, $filter->params);
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
        $sql = 'SELECT value, resource FROM ' . $tablePrefix . 'triples WHERE ' . $filter->sql . ' ORDER BY value ASC, resource ASC';
        $tab_tous_les_tags = $this->getService(DbService::class)->loadAll($sql, $filter->params);

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
     * The `AND value IN (...)` clause for the (already trimmed/filtered) tag tokens.
     *
     * A SqlFragment since ticket 31: the tags are webmaster-typed action arguments, and this
     * clause is pasted into two different queries, so the values have to travel with it.
     */
    private function buildSelectionTagsClause(array $tags): SqlFragment
    {
        if (empty($tags)) {
            return SqlFragment::empty();
        }

        return SqlFragment::of(
            'AND value IN (' . SqlParameters::placeholders(count($tags)) . ')',
            array_values($tags)
        );
    }
}
