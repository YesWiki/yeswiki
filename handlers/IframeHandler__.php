<?php

use YesWiki\Core\Controller\SearchAnchorCommons;
use YesWiki\Core\YesWikiHandler;

class IframeHandler__ extends YesWikiHandler
{
    public function run()
    {
        $searchAnchorCommons= $this->getService(SearchAnchorCommons::class);
        $searchAnchorCommons->run($this->output);
    }
}
