<?php

namespace YesWiki\Content\Action;

use YesWiki\Content\Entity\PageBody;
use YesWiki\Content\Service\TripleStore;
use YesWiki\Core\YesWikiAction;
use YesWiki\Kernel\Performable\RegisteredAction;
use YesWiki\Kernel\Service\PageContext;
use YesWiki\Kernel\Service\PerformableArguments;
use YesWiki\Render\Service\MarkdownFormatterService;
use YesWiki\Render\Service\TemplateEngine;
use YesWiki\Search\Service\TagsManager;

/**
 * `{{listpagestag}}` -- converted from the procedural actions/listpagestag.php by ticket 06.
 *
 * The body still prints rather than returning, so it runs inside an output buffer in its
 * own method: that is what the old runFileInBuffer() did, and it keeps any early `return;`
 * in the body from discarding output.
 */
class ListpagestagAction extends YesWikiAction implements RegisteredAction
{
    public static function performableName(): string
    {
        return 'listpagestag';
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
        $tagsManager = $this->getService(TagsManager::class);

        include_once YESWIKI_SOURCE_DIR . '/src/Content/tags.functions.php';
        $nbcartrunc = 200;
        $tags = $this->getService(PerformableArguments::class)->get('tags');
        $type = $this->getService(PerformableArguments::class)->get('type');
        // recuperation de tous les parametres
        $lienedit = '';
        $class = $this->getService(PerformableArguments::class)->get('class');
        $nb = $this->getService(PerformableArguments::class)->get('nb');
        $tri = $this->getService(PerformableArguments::class)->get('tri');
        $template = $this->getService(PerformableArguments::class)->get('template');
        if (empty($template)) {
            $template = 'pages_list.twig';
        }

        $output = '';

        // affiche le resultat de la recherche
        $resultat = $tagsManager->getPagesByTags($tags, $type, $nb, $tri);
        if ($resultat) {
            $nb_total = count($resultat);
            // affichage des resultats
            foreach ($resultat as $page) {
                // on inclue pas la page en elle meme, sinon boucle infinie
                if ($page['tag'] != $this->getService(PageContext::class)->getTag()) {
                    // rows come straight from SQL, so the body is still encoded here
                    $page['body'] = PageBody::decode($page['body']);
                    $element[$page['tag']]['tagnames'] = '';
                    $element[$page['tag']]['tagbadges'] = '';
                    $element[$page['tag']]['body'] = PageBody::content($page['body']);
                    $element[$page['tag']]['owner'] = $page['owner'];
                    $element[$page['tag']]['user'] = $page['user'];
                    $element[$page['tag']]['time'] = $page['time'];
                    $element[$page['tag']]['title'] = get_title_from_body($page);
                    $element[$page['tag']]['image'] = get_image_from_body($page);
                    $pagetags = $this->getService(TripleStore::class)->getAll($page['tag'], 'http://outils-reseaux.org/_vocabulary/tag', '', '');
                    foreach ($pagetags as $tag) {
                        $element[$page['tag']]['tagnames'] .= sanitizeEntity($tag['value']) . ' ';
                        $element[$page['tag']]['tagbadges'] .= '<span class="label label-info">' . $tag['value'] . '</span>&nbsp;';
                    }
                }
            }
            $output = $this->getService(TemplateEngine::class)->renderSafely("@core/$template", ['elements' => $element]);
        } else {
            $nb_total = 0;
        }

        $shownumberinfo = $this->getService(PerformableArguments::class)->get('shownumberinfo');
        if (!empty($shownumberinfo) && $shownumberinfo == 1) {
            $info = '<div class="alert alert-info">' . "\n";
            if ($nb_total > 1) {
                $info .= _t('TAGS_TOTAL_NB_PAGES', ['nb_total' => $nb_total]);
            } elseif ($nb_total == 1) {
                $info .= _t('TAGS_ONE_PAGE_FOUND');
            } else {
                $info .= _t('TAGS_NO_PAGE');
            }
            $info .= (!empty($tags) ? ' ' . _t('TAGS_WITH_KEYWORD') . ' <span class="label label-info">' . $tags . '</span>' : '') . '.';
            $info .= $this->getService(MarkdownFormatterService::class)->format('{{rss tags="' . $tags . '" class="pull-right"}}') . "\n" . '</div>' . "\n";
            $output = $info . $output;
        }

        echo $output . "\n";
    }
}
