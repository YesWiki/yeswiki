<?php

use YesWiki\Core\YesWikiAction;

class DocumentationAction extends YesWikiAction
{
    public function formatArguments($arg)
    {
        return [
        ];
    }

    public function run()
    {
        return $this->render('@core/documentation.twig', [
            'isIframe' => (testUrlInIframe() == 'iframe'),
        ]) ;
    }
}
