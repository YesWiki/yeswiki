<?php

namespace YesWiki\Helloworld; // optionnal for action because loaded once

use YesWiki\Core\YesWikiAction;
use YesWiki\HelloWorld\Service\GreetingService;

class GreetingAction extends YesWikiAction
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
        $greeting = $this->getService(GreetingService::class);
        $userName = $greeting->getUserName();

        return $this->render('@helloworld/greeting.twig', ['userName' => $userName]);
    }
}
