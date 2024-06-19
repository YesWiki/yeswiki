<?php

namespace YesWiki\Helloworld; // recommended for main pre action because could be loaded several times

use YesWiki\Core\YesWikiAction;

class __GreetingAction extends YesWikiAction
{
    /**
     * method to prepare args, optionnal.
     */
    public function formatArguments($args)
    {
        // if message is defined, modify the argument before main action is run
        // else notify the parameter is missing
        return [
            'message' => (!empty($args['message'])
                ? $args['message'] . ' ' . _t('HELLOWORD_CALLBACK_MSG')
                : _t('HELLOWORD_NO_MSG_PARAM')),
        ];
    }

    public function run()
    {
    }
}
