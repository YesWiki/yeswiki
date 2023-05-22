<?php

namespace YesWiki\Templates\Controller;

use YesWiki\Core\YesWikiController;
use YesWiki\Templates\Service\TabsService;

class TabsController extends YesWikiController
{
    protected $tabsService;

    public function __construct(
        TabsService $tabsService
    ) {
        $this->tabsService = $tabsService;
    }

    /**
     * change tag
     * @param string $mode
     * @return string $output
     */
    public function changeTab(string $mode = 'action'): string
    {
        $params = ($mode === 'form')
        ? $this->tabsService->getFormData()
        : (
            ($mode === 'view')
            ? $this->tabsService->getViewData()
            : $this->tabsService->getActionData()
        );
        if ($params['counter'] === false) {
            return "";
        }
        return "\n".$this->render('@templates/tab-change.twig', $params); 
    }
}
