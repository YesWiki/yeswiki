<?php

use YesWiki\Core\YesWikiHandler;

class HelloHandler__ extends YesWikiHandler
{
    function run()
    {
        $this->output = str_replace('This is the content', 'This is the wakka content', $this->output);
    }
}
