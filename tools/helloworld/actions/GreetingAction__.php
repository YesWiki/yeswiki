<?php

use YesWiki\Core\YesWikiAction;

class GreetingAction__ extends YesWikiAction
{
    function run()
    {
        return $this->render('@helloworld/greeting-suffix.twig');
    }
}