<?php

namespace YesWiki\Render\Action;

use YesWiki\Core\YesWikiAction;
use YesWiki\Kernel\Performable\RegisteredAction;
use YesWiki\Kernel\Service\PageContext;
use YesWiki\Kernel\Service\PerformableArguments;
use YesWiki\Kernel\Service\RuntimeConfig;
use YesWiki\Kernel\Service\UrlFormatter;

/** `{{breadcrumb}}` -- converted from the procedural actions/ariane.php by ticket 06. */
class BreadcrumbAction extends YesWikiAction implements RegisteredAction
{
    public static function performableName(): string
    {
        return 'breadcrumb';
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
        if ($max = $this->getService(PerformableArguments::class)->get('nb')) {
            $max = (int)$max;
        } else {
            $max = 4;
        }

        $crumbs = [];

        $wikireq = $_REQUEST['wiki'];

        $wikireq = preg_replace("/^\//", '', $wikireq);

        if (preg_match(
            '`^([A-Za-z0-9]+)/([A-Za-z0-9_-]*)$`',
            $wikireq,
            $matches
        )) {
            list($PageTag, $method) = $matches;
        } elseif (preg_match('`^[A-Za-z0-9]+$`', $wikireq)) {
            $PageTag = $wikireq;
        } else {
            $PageTag = $this->getService(PageContext::class)->getTag();
        }

        if (!isset($_SESSION['breadcrumbs'])) {
            $crumbs[0] = $PageTag;
        } else {
            $crumbs = $_SESSION['breadcrumbs'];

            if ($crumbs[count($crumbs) - 1] != $this->getService(PageContext::class)->getTag()) {
                if (count($crumbs) >= $max and $PageTag != $crumbs[$max - 1]) {
                    array_shift($crumbs);

                    $crumbs[$max - 1] = $PageTag;
                } else {
                    $crumbs[count($crumbs)] = $PageTag;
                }
            }
        }

        $count = 1;
        $temp = [];
        $target = [];

        if (count($crumbs) > 2) {
            while ($count <= (count($crumbs) - 1)) {
                $temp[$count - 1] = $crumbs[$count - 1];
                $temp[$count] = $crumbs[$count];
                $temp = array_unique($temp);
                $target = $target + $temp;
                $temp = [];
                $count++;
            }
            $crumbs = $target;
        } else {
            $crumbs = array_unique($crumbs);
        }

        $_SESSION['breadcrumbs'] = $crumbs;

        $page_trail = "<ol class=\"yw-breadcrumb\">\n"
            . '<li><a href="'
            . $this->getService(UrlFormatter::class)->href('', $this->getService(RuntimeConfig::class)['root_page'])
            . '"><span class="fa fa-home"></span></a></li>'
            . "\n";

        foreach ($crumbs as $this_crumb) {
            if ($this->getService(PageContext::class)->getTag() == $this_crumb) {
                $page_trail .= '<li class="yw-breadcrumb__active">' . $this_crumb . '</li>' . "\n";
            } else {
                $page_trail .= '<li><a href="'
                    . $this->getService(UrlFormatter::class)->href('', $this_crumb)
                    . '">' . $this_crumb
                    . "</a></li>\n";
            }
        }
        $page_trail .= "</ol>\n";

        echo $page_trail;
    }
}
