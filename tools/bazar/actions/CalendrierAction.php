<?php

use YesWiki\Core\YesWikiAction;

require_once('BazarListeAction.php');

class CalendrierAction extends YesWikiAction
{
    public function formatArguments($arg)
    {
        return([
            'minical' => $arg['minical'] ?? null,
            //template - default value calendar
            'template' => (isset($arg['template']) &&
                BazarListeAction::specialActionFromTemplate($arg['template'], 'CALENDRIER_TEMPLATES'))
                ? $arg['template']
                : 'calendar.tpl.html',
        ]);
    }

    public function run()
    {
        return $this->callAction('bazarliste', $this->arguments);
    }
}
