<?php

use YesWiki\Core\YesWikiAction;

class MapAction extends YesWikiAction
{
    public function run()
    {
        // Retrocompatibility
        return $this->callAction('bazarcarto', $this->arguments);
    }
}
