<?php

use YesWiki\Core\YesWikiAction;

// backward compatibilty
// TODO to delete for ectoplasme

class BazarListeExterneAction extends YesWikiAction
{
    public function run()
    {
        return $this->callAction('bazarliste', $this->arguments);
    }
}
