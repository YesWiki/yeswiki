<?php

use YesWiki\Core\YesWikiAction;
use YesWiki\Templates\Controller\TabsController;

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
}
