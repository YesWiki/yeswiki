<?php

use YesWiki\Core\YesWikiAction;
use YesWiki\HelloWorld\Service\GreetingService;

class GreetingAction extends YesWikiAction 
{
    function run()
    {
        $greeting = $this->getService(GreetingService::class);
        $userName = $greeting->getUserName();
        return $this->render('@helloworld/greeting.twig', ['userName' => $userName]);
    }
}