<?php

use YesWiki\Core\Controller\TabsController;
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
        return $this->getService(TabsController::class)->openATab();
    }

    public function end(): string
    {
        return $this->getService(TabsController::class)->closeATab();
    }
}
