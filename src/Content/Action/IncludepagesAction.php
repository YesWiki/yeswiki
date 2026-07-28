<?php

namespace YesWiki\Content\Action;

use YesWiki\Core\YesWikiAction;
use YesWiki\Kernel\Performable\RegisteredAction;

/**
 * `{{includepages}}` -- converted from the procedural actions/includepages.php by ticket 06.
 *
 * The body still prints rather than returning, so it runs inside an output buffer in its
 * own method: that is what the old runFileInBuffer() did, and it keeps any early `return;`
 * in the body from discarding output.
 */
class IncludepagesAction extends YesWikiAction implements RegisteredAction
{
    public static function performableName(): string
    {
        return 'includepages';
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
        $output = '';
        $class = $this->wiki->GetParameter('class');
        $pages = $this->wiki->GetParameter('pages');

        if (empty($pages)) {
            $output .= '<div class="alert alert-danger"><strong>' . _t('TAGS_ACTION_INCLUDEPAGES') . '</strong> : ' . _t('TAGS_NO_PARAM_PAGES') . '</div>' . "\n";
        } else {
            $template = $this->wiki->GetParameter('template');
            if (empty($template)) {
                $template = 'pages_list.twig';
            }

            $resultat = explode(',', $pages);
            foreach ($resultat as $page) {
                $page = $this->wiki->LoadPage(trim($page));
                $element[$page['tag']]['tagnames'] = '';
                $element[$page['tag']]['tagbadges'] = '';
                $element[$page['tag']]['body'] = $page['body'];
                $element[$page['tag']]['owner'] = $page['owner'];
                $element[$page['tag']]['user'] = $page['user'];
                $element[$page['tag']]['time'] = $page['time'];
                $element[$page['tag']]['title'] = get_title_from_body($page);
                $element[$page['tag']]['image'] = get_image_from_body($page);
                $element[$page['tag']]['desc'] = tokenTruncate(strip_tags($this->wiki->Format($page['body'], 'wakka', $page['tag'])), $nbcartrunc);
                $pagetags = $this->wiki->GetAllTriplesValues($page['tag'], 'http://outils-reseaux.org/_vocabulary/tag', '', '');
                foreach ($pagetags as $tag) {
                    $element[$page['tag']]['tagnames'] .= sanitizeEntity($tag['value']) . ' ';
                    $element[$page['tag']]['tagbadges'] .= '<span class="label label-info">' . $tag['value'] . '</span>&nbsp;';
                }
            }

            $output .= $this->wiki->render("@core/$template", ['elements' => $element]);
        }

        if (empty($class)) {
            echo $output . "\n";
        } else {
            echo '<div class="' . $class . '">' . "\n" . $output . "\n" . '</div>' . "\n";
        }
    }
}
