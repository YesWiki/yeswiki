<?php

use YesWiki\Core\YesWikiAction;

class GreetingAction__ extends YesWikiAction
{
    function run($parent) 
    {
        return $this->render('@helloworld/greeting-suffix.twig');
    }
}