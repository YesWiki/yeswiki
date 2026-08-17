<?php

namespace YesWiki\Render\Action;

use YesWiki\Core\YesWikiAction;
use YesWiki\Kernel\Performable\RegisteredAction;
use YesWiki\Kernel\Service\PageContext;
use YesWiki\Kernel\Service\RuntimeConfig;
use YesWiki\Kernel\Service\UrlFormatter;
use YesWiki\Render\Service\TemplateHelperService;

/** `{{metarobots}}` -- converted from the procedural actions/metarobots.php by ticket 06. */
class MetarobotsAction extends YesWikiAction implements RegisteredAction
{
    public static function performableName(): string
    {
        return 'metarobots';
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
        if ($this->getService(PageContext::class)->getMethod() != 'show' || empty($this->getService(PageContext::class)->getPage())) {
            echo '<meta name="robots" content="noindex, nofollow">' . "\n";
        } else {
            if (isset($this->getService(RuntimeConfig::class)['meta']['robots'])) {
                echo '<meta name="robots" content="'
                    . $this->getService(RuntimeConfig::class)['meta']['robots'] . '">' . "\n";
            }

            $url = $this->getService(UrlFormatter::class)->href('', $this->getService(PageContext::class)->getTag());
            echo '<link rel="canonical" href="' . $url . '">' . "\n";

            echo "\n" . '  <!-- opengraph -->' . "\n";
            echo '  <meta property="og:site_name" content="'
                . $this->getService(RuntimeConfig::class)['yeswiki_name'] . '" />' . "\n";
            $utils = $this->getService(TemplateHelperService::class);
            $title = $utils->getTitleFromBody($this->getService(PageContext::class)->getPage());
            echo '  <meta property="og:title" content="' . (!empty($title) ? $title : $GLOBALS['yeswikiServices']->get(RuntimeConfig::class)['yeswiki_name']) . '" />' . "\n";
            $desc = htmlspecialchars($utils->getDescriptionFromBody($this->getService(PageContext::class)->getPage(), $title), ENT_COMPAT | ENT_HTML5);
            if ($desc) {
                echo '  <meta property="og:description" content="' . $desc . '" />' . "\n";
            }
            echo '  <meta property="og:type" content="article" />' . "\n";
            echo '  <meta property="og:url" content="' . $url . '" />' . "\n";

            $w = 1200;
            $h = 630;
            $img = $this->getService(TemplateHelperService::class)->getImageFromBody($this->getService(PageContext::class)->getPage(), strval($w), strval($h));
            if (!empty($img)) {
                echo '  <meta property="og:image" content="' . $img . '" />' . "\n";
                echo '  <meta property="og:image:width" content="' . $w . '" />' . "\n";
                echo '  <meta property="og:image:height" content="' . $h . '" />' . "\n";
            }
        }
    }
}
