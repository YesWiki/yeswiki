<?php

use YesWiki\Core\YesWikiHandler;

class HelloHandler extends YesWikiHandler
{
    function run()
    {
        $pageBody = $this->wiki->page['body'];
        return $this->renderInSquelette('@helloworld/hello.twig', ['body' => $pageBody]);
    }
}
