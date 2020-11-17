<?php

use YesWiki\Core\YesWikiAction;

class HelloHandler extends YesWikiAction 
{
    function run($arguments) 
    {
        $pageBody = $this->wiki->page['body'];
        return $this->renderInSquelette('@helloworld/hello.twig', ['body' => $pageBody]);
    }
}