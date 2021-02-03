<?php

use YesWiki\Core\YesWikiAction;

class BazarListeExterneAction extends YesWikiAction
{
    function formatArguments($arg)
    {
        return([
            'url' => $arg['url']
        ]);
    }

    function run()
    {
        if (empty($this->arguments['url'])) {
            exit('<div class="alert alert-danger">Action bazarlisteexterne : parametre url obligatoire.</div>');
        }

        return $this->callAction('bazarliste', $this->arguments);
    }
}
