<?php

namespace YesWiki\Render\Action;

use YesWiki\Core\YesWikiAction;
use YesWiki\Kernel\Performable\RegisteredAction;
use YesWiki\Render\Service\TabsRenderer;

class TabAction extends YesWikiAction implements RegisteredAction
{
    /** `{{tab}}` in page content -- stated, not inferred from the filename. */
    public static function performableName(): string
    {
        return 'tab';
    }

    public function formatArguments($arg)
    {
        return [
        ];
    }

    /**
     * @return string the opening markup for one tab pane
     */
    public function run()
    {
        return $this->getService(TabsRenderer::class)->openATab();
    }

    public function end(): string
    {
        return $this->getService(TabsRenderer::class)->closeATab();
    }
}
