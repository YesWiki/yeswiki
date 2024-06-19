<?php

use YesWiki\Core\YesWikiAction;
use YesWiki\Templates\Controller\TabsController;

class TabsAction extends YesWikiAction
{
    public function formatArguments($arg)
    {
        $titles = array_map('trim', $this->formatArray($arg['titles'] ?? []));

        return [
            'titles' => $titles,
            'btnsize' => (isset($arg['btnsize']) && $arg['btnsize'] === 'std')
              ? ''
              : 'btn-xs',
            'btncolor' => (!empty($arg['btncolor']) && in_array($arg['btncolor'], ['btn-primary', 'btn-secondary-1', 'btn-secondary-2'], true))
              ? $arg['btncolor']
              : 'btn-primary',
            'bottom_nav' => $this->formatBoolean($arg, true, 'bottom_nav'), // default should be true if empty
            'counter_on_bottom_nav' => $this->formatBoolean($arg, false, 'counter_on_bottom_nav'),
            'selectedtab' => (array_key_exists('selectedtab', $arg) && intval($arg['selectedtab']) > 0 && intval($arg['selectedtab']) <= count($titles)) ? intval($arg['selectedtab']) : 1,
        ];
    }

    public function run()
    {
        return $this->getService(TabsController::class)->openTabs('action', array_merge(
            $this->arguments,
            ['btnClass' => $this->arguments['btncolor'] . ' ' . $this->arguments['btnsize']]
        ));
    }
}
