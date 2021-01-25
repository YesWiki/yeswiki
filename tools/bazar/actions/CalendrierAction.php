<?php

use YesWiki\Core\YesWikiAction;

class CalendrierAction extends YesWikiAction
{
    function formatArguments($arg)
    {
        return([
            'minical' => $arg['minical'] ?? null
        ]);
    }

    function run()
    {
        $this->arguments['template'] = 'calendar.tpl.html';

        return $this->callAction('bazarliste', $this->arguments);
    }
}
