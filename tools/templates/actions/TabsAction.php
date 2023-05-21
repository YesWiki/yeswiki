<?php

use YesWiki\Core\YesWikiAction;
use YesWiki\Templates\Service\TabsService;

class TabsAction extends YesWikiAction
{
  public function formatArguments($arg){
    return [
      'titles' => array_map('trim',$this->formatArray($arg['titles'] ?? [])),
      'btnsize' => (isset($arg['btnsize']) && $arg['btnsize'] === 'std')
        ? ''
        : 'btn-xs',
      'btncolor' => (!empty($arg['btncolor']) && in_array($arg['btncolor'],["btn-primary","btn-secondary-1","btn-secondary-2"],true))
        ? $arg['btncolor']
        : "btn-primary"
    ];
  }

  public function run()
  {
    $tabsService = $this->getService(TabsService::class);
    $tabsService->setActionTitles([
      'titles' => $this->arguments['titles'],
      'btnClass' => $this->arguments['btncolor'] . ' ' .$this->arguments['btnsize']
    ]);
    return $this->render('@templates/tabs.twig',[
      'titles' => $this->arguments['titles'],
      'prefix' => $tabsService->getPrefix()
    ]);                 
  }
}
