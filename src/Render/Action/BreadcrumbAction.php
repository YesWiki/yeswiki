<?php

namespace YesWiki\Render\Action;

use YesWiki\Core\YesWikiAction;
use YesWiki\Kernel\Performable\RegisteredAction;
use YesWiki\Kernel\Service\AssetRegistry;
use YesWiki\Kernel\Service\PageContext;
use YesWiki\Kernel\Service\PerformableArguments;
use YesWiki\Kernel\Service\RuntimeConfig;
use YesWiki\Kernel\Service\UrlFormatter;

/** `{{breadcrumb}}`: the current page, and the trail before it kept per tab by javascripts/breadcrumbs.js rather than in a session (replicas 15). */
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

        $tag = $this->getService(PageContext::class)->getTag();
        $urls = $this->getService(UrlFormatter::class);
        $this->getService(AssetRegistry::class)->addJsFile('javascripts/breadcrumbs.js');

        $page_trail = '<ol class="yw-breadcrumb" data-yw-breadcrumbs'
            . ' data-yw-breadcrumbs-current="' . htmlspecialchars($tag, ENT_QUOTES) . '"'
            . ' data-yw-breadcrumbs-max="' . $max . '"'
            . ' data-yw-breadcrumbs-href="' . htmlspecialchars($urls->href('', '__YW_BREADCRUMB_TAG__', null, false), ENT_QUOTES) . '">' . "\n"
            . '<li><a href="' . $urls->href('', $this->getService(RuntimeConfig::class)['root_page']) . '"><span class="fa fa-home"></span></a></li>' . "\n"
            . '<li class="yw-breadcrumb__active">' . htmlspecialchars($tag) . '</li>' . "\n"
            . "</ol>\n";

        echo $page_trail;
    }
}
