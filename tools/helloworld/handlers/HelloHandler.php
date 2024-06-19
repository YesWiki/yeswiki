<?php

use YesWiki\Core\YesWikiHandler;

class HelloHandler extends YesWikiHandler
{
    public function run()
    {
        $this->denyAccessUnlessGranted('read');

        $pageBody = $this->wiki->page['body'];

        return $this->renderInSquelette('@helloworld/hello.twig', ['body' => $pageBody]);
    }
}
