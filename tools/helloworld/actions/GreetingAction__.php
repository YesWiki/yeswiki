<?php

namespace YesWiki\Helloworld; // recommended for main post action because could be loaded several times

use YesWiki\Core\YesWikiAction;

class GreetingAction__ extends YesWikiAction
{
    /**
     * method to prepare args, optionnal
     * see example in __GreetingAction.
     */
    public function formatArguments($arg)
    {
        return [];
    }

    public function run()
    {
        return $this->render('@helloworld/greeting-suffix.twig');
    }
}
