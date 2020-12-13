<?php

use YesWiki\Core\YesWikiHandler;

class HelloHandler extends YesWikiHandler
{
    function run()
    {
        $this->arguments['msg'] .= 'but it\'s depreciated.';

        $pageBody = $this->wiki->page['body'];
        return $this->renderInSquelette('@helloworld/hello.twig', ['body' => $pageBody]);
    }
}
