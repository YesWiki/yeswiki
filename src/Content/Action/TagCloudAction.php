<?php

namespace YesWiki\Content\Action;

use YesWiki\Core\YesWikiAction;
use YesWiki\Kernel\Component\Category;
use YesWiki\Kernel\Component\Component;
use YesWiki\Kernel\Component\ProvidesComponents;
use YesWiki\Kernel\Component\Setting;
use YesWiki\Kernel\Performable\RegisteredAction;
use YesWiki\Kernel\Service\AssetRegistry;
use YesWiki\Kernel\Service\RuntimeConfig;
use YesWiki\Kernel\Service\UrlFormatter;
use YesWiki\Search\Service\TagsManager;

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

    public function formatArguments($args)
    {
        $class = $args['class'] ?? '';
        $tags = trim($args['tags'] ?? '');
        $classcount = $args['classcount'] ?? '';

        return [
            'class' => empty($class) ? '' : ' ' . $class,
            'tags' => empty($tags) ? [] : array_filter(array_map('trim', explode(' ', $tags)), fn (string $tag): bool => $tag !== ''),
            'classcount' => empty($classcount) ? 6 : (int)$classcount,
            'sort' => $args['sort'] ?? '',
        ];
    }

    /** @return string */
    public function run()
    {
        $this->getService(AssetRegistry::class)->addJsFile('javascripts/tag.js');

        // One read, through TagsManager, rather than this action's own copy of the keyword SQL and
        // of the vocabulary URI (ticket 62). The counts are worked out from the pairs it already
        // has to load, so the sizing no longer costs a second scan.
        $tab_tous_les_tags = array_map(
            static fn (array $pair): array => ['value' => $pair['keyword'], 'resource' => $pair['tag']],
            $this->getService(TagsManager::class)->pairs($this->arguments['tags'])
        );

        $counts = array_count_values(array_column($tab_tous_les_tags, 'value'));
        $max = $counts === [] ? 0 : max($counts);

        $mult = $max / $this->arguments['classcount'];
        if ($mult < 1) {
            $mult = 1;
        }

        $output = '';
        if ($tab_tous_les_tags !== []) {
            $i = 1;
            $nb_pages = 0;
            $liste_page = '';
            $tag_precedent = '';
            $tab_tag = [];
            // The sentinel that flushes the last keyword's list on the final pass.
            $tab_tous_les_tags[] = ['value' => 'fin', 'resource' => 'fin'];
            foreach ($tab_tous_les_tags as $tab_les_tags) {
                $tagstripped = stripslashes($tab_les_tags['value']);

                $resourceEscaped = htmlspecialchars($tab_les_tags['resource']);
                if ($tagstripped == $tag_precedent || $tag_precedent == '') {
                    $nb_pages++;
                    $liste_page .= '<li class="pagewiki-link"><a class="link_pagewiki" href="' . htmlspecialchars($this->getService(UrlFormatter::class)->href('', $tab_les_tags['resource'])) . '">' . $resourceEscaped . '</a></li>';
                } else {
                    if ($nb_pages > 1) {
                        $texte_page = $nb_pages . ' ' . _t('TAGS_PAGES');
                    } else {
                        $texte_page = _t('TAGS_ONE_PAGE');
                    }
                    $tagPrecedentEscaped = htmlspecialchars($tag_precedent);
                    $texte_liste = '<li class="tag-list">' . "\n" . '<a class="tag-link size' . ceil($nb_pages / $mult) . '" id="j' . $i . '" data-title="' . htmlspecialchars('<button class="btn-close-popover pull-right close" type="button">&times;</button>' . $texte_page . ' ' . _t('TAGS_CONTAINING_TAG') . ' : <a href="' . htmlspecialchars($this->getService(UrlFormatter::class)->href('', 'search', ['tags' => $tag_precedent])) . '" class="tag-label label label-primary">' . $tagPrecedentEscaped . '</a>') . '" data-content="' . htmlspecialchars('<ul class="unstyled list-unstyled">' . $liste_page . '</ul>', ENT_QUOTES, $this->getService(RuntimeConfig::class)['charset']) . '">' . $tagPrecedentEscaped . '</a>' . "\n";
                    $texte_liste .= '</li>' . "\n";
                    $tab_tag[] = $texte_liste;

                    $nb_pages = 1;
                    $liste_page = '<li><a class="pagewiki-link" href="' . htmlspecialchars($this->getService(UrlFormatter::class)->href('', $tab_les_tags['resource'])) . '">' . $resourceEscaped . '</a></li>' . "\n";
                    $i++;
                }
                $tag_precedent = $tagstripped;
            }

            if (count($tab_tag) > 0) {
                $output .= '<div class="no-dblclick boite_nuage' . $this->arguments['class'] . '">
			<ul class="nuage">' . "\n";

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
}
