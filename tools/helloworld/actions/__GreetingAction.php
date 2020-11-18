<?php

use YesWiki\Core\YesWikiAction;

class __GreetingAction extends YesWikiAction
{
    function run($parent) 
    {
        // modify argument before main action is run
        $parent->arguments['message'] = $parent->arguments['message'] . " (changed in before callback)";
    }
}