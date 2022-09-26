<?php

use YesWiki\Core\Controller\SearchAnchorCommons;
use YesWiki\Core\YesWikiHandler;

class ShowHandler__ extends YesWikiHandler
{
    public function run()
    {
        $searchAnchorCommons= $this->getService(SearchAnchorCommons::class);
        $searchAnchorCommons->run($this->output);
    }
}
