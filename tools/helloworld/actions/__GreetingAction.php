<?php

use YesWiki\Core\YesWikiAction;

class __GreetingAction extends YesWikiAction
{
    function run()
    {
        // if message is defined, modify the argument before main action is run
        // else notify the parameter is missing
        $this->arguments['message'] = !empty($this->arguments['message']) ?
            $this->arguments['message'] . ' ' . _t('HELLOWORD_CALLBACK_MSG') :
            _t('HELLOWORD_NO_MSG_PARAM');
    }
}