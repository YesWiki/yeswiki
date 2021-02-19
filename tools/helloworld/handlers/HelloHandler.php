<?php

use YesWiki\Core\YesWikiHandler;

class HelloHandler extends YesWikiHandler
{
    function run()
    {
        $pageBody = $this->wiki->page['body'];
        throw new \Exception("toto");
        return $this->renderInSquelette('@helloworld/hello.twig', ['body' => $pageBody]);
    }
}
