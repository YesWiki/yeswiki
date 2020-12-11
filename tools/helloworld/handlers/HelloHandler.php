<?php

use YesWiki\Core\YesWikiHandler;

class HelloHandler extends YesWikiHandler
{
    function run()
    {
        $this->arguments['msg'] .= ', then it\'s completed in the <em>HelloHandler.php</em>';

        $pageBody = $this->wiki->page['body'];
        return $this->renderInSquelette('@helloworld/hello.twig', ['body' => $pageBody]);
    }
}
