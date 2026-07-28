<?php

use YesWiki\Render\Service\TabsRenderer;
use YesWiki\Core\YesWikiAction;

class TabAction extends YesWikiAction
{
    public function formatArguments($arg)
    {
        return [
        ];
    }

    public function run()
    {
        return $this->getService(TabsRenderer::class)->openATab();
    }

    public function end(): string
    {
        return $this->getService(TabsRenderer::class)->closeATab();
    }
}
