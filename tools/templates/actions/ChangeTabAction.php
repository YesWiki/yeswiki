<?php

use YesWiki\Core\YesWikiAction;
use YesWiki\Templates\Service\TabsService;

class ChangeTabAction extends YesWikiAction
{
    public function run()
    {
        $tabsService = $this->getService(TabsService::class);
        $params = $tabsService->getActionData();
        if ($params['counter'] === false) {
            return "";
        }
        return "\n".$this->render('@templates/tab-change.twig', $params);               
    }
}
