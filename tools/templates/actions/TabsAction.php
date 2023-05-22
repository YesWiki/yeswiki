<?php

use YesWiki\Core\YesWikiAction;
use YesWiki\Templates\Service\TabsService;

class TabsAction extends YesWikiAction
{
  public function formatArguments($arg) {
    return [
      'titles' => array_map('trim',$this->formatArray($arg['titles'] ?? [])),
      'btnsize' => (isset($arg['btnsize']) && $arg['btnsize'] === 'std')
        ? ''
        : 'btn-xs',
      'btncolor' => (!empty($arg['btncolor']) && in_array($arg['btncolor'],["btn-primary","btn-secondary-1","btn-secondary-2"],true))
        ? $arg['btncolor']
        : "btn-primary",
      'bottom_nav' =>  (empty($arg['bottom_nav']) ? true : (in_array($arg['bottom_nav'],["oui", "yes", "1", "true"]) ? true : false)), # default should be true if empty
      'counter_on_bottom_nav' => (!empty($arg['counter_on_bottom_nav']) && in_array($arg['counter_on_bottom_nav'],["oui", "yes", "1", "true"]) ? true : false)
    ];
  }

  public function run()
  {
    $tabsService = $this->getService(TabsService::class);
    $tabsService->setActionTitles([
      'titles' => $this->arguments['titles'],
      'btnClass' => $this->arguments['btncolor'] . ' ' .$this->arguments['btnsize'],
      'bottom_nav' => $this->arguments['bottom_nav'],
      'counter_on_bottom_nav' => $this->arguments['counter_on_bottom_nav'],
    ]);
    return $this->render('@templates/tabs.twig',[
      'titles' => $this->arguments['titles'],
      'prefix' => $tabsService->getPrefix('action')
    ]);                 
  }
}
