<?php

use YesWiki\Core\YesWikiHandler;

class HelloHandler extends YesWikiHandler
{
    public function run()
    {
        // check access to not give access to protected pages
        if ($this->wiki->HasAccess("read")) {
            // be carefull hello handler aws added in tools/bara/services/Guard to secured handlers
            $pageBody = $this->wiki->page['body'];
            return $this->renderInSquelette('@helloworld/hello.twig', ['body' => $pageBody]);
        } else {
            return $this->renderInSquelette('@templates/alert-message-with-back.twig', [
                'type' => 'info',
                'message' => _t('HELLOWORLD_PAGE_NOT_ACCESSIBLE'),
            ]);
        }
    }
}
