<?php

namespace YesWiki\Render\Action;

use YesWiki\Content\Service\LinkTracker;
use YesWiki\Render\Service\TemplateHelperService;
use YesWiki\Core\YesWikiAction;
use YesWiki\Kernel\Performable\RegisteredAction;

/**
 * `{{nav}}` -- converted from the procedural actions/nav.php by ticket 06.
 *
 * The body still prints rather than returning, so it runs inside an output buffer in its
 * own method: that is what the old runFileInBuffer() did, and it keeps any early `return;`
 * in the body from discarding output.
 */
class NavAction extends YesWikiAction implements RegisteredAction
{
    public static function performableName(): string
    {
        return 'nav';
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

        // classe css supplémentaire
        $class = $this->wiki->GetParameter('class');
        $class = ((!empty($class)) ? $class : 'yw-nav');

        // data attributes
        $data = $this->wiki->services->get(TemplateHelperService::class)->getDataParameter();
        $pagetag = $this->wiki->GetPageTag();

        // liens
        $links = $this->wiki->GetParameter('links');
        if (!empty($links)) {
            $links = explode(',', $links);
            $links = array_map('trim', $links);
        }

        // titre des liens
        $titles = $this->wiki->GetParameter('titles');
        if (!empty($titles)) {
            $titles = explode(',', $titles);
            $titles = array_map('trim', $titles);
        }

        // icônes des titres
        $icons = $this->wiki->GetParameter('icons');
        if (!empty($icons)) {
            $icons = explode(',', $icons);
            foreach ($icons as $key => $icon) {
                $icon = $this->wiki->services->get(TemplateHelperService::class)->formatIconHtml($icon);
                if (!empty($icon) && !empty($text)) {
                    $icon = $icon . ' ';
                }
                $icons[$key] = $icon;
            }
        }

        $hideIfNoAccess = $this->wiki->GetParameter('hideifnoaccess');
        $listlinks = '';
        foreach ($titles as $key => $title) {
            $haveAccess = true;
            if (empty($links[$key])) {
                $url = '';
            } else {
                $linkParts = $this->wiki->extractLinkParts($links[$key]);
                [$url, $method, $params] = ['', '', ''];
                if ($linkParts) {
                    $this->wiki->services->get(LinkTracker::class)->forceAddIfNotIncluded($linkParts['tag']);
                    $method = $linkParts['method'];
                    $params = $linkParts['params'];
                    $url = $this->wiki->href($method, $linkParts['tag'], $params);
                    if ($hideIfNoAccess == 'true' && isset($linkParts['tag']) && !$GLOBALS['wiki']->HasAccess('read', $linkParts['tag'])) {
                        $haveAccess = false;
                    }
                } else {
                    $url = $links[$key];
                }
            }
            // class="active" if the url have the same url than the current one (independently of the method and the params)
            if ($haveAccess) {
                $listclass = ($url == $this->wiki->href($method, $this->wiki->GetPageTag(), $params)) ? ' class="active"' : '';
                $listlinks .= '<li' . $listclass . '><a href="' . $url . '">'
                    . (isset($icons[$key]) ? $icons[$key] : '')
                    . $title . '</a></li>' . "\n";
            }
        }

        $navID = uniqid('nav_');
        $data = '';
        if (is_array($data)) {
            foreach ($data as $key => $value) {
                $data .= ' data-' . $key . '="' . $value . '"';
            }
        }

        if (!empty($listlinks)) {
            echo ' <!-- start of nav -->
                <nav><ul class="' . $class . '" id="' . $navID . '" ' . $data . '>' . $listlinks . '</ul></nav>' . "\n";
        }
    }
}
