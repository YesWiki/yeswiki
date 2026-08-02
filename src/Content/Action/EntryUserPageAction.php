<?php

namespace YesWiki\Content\Action;

use YesWiki\Core\YesWikiAction;
use YesWiki\Identity\Service\AuthenticationService;
use YesWiki\Kernel\Performable\RegisteredAction;

class EntryUserPageAction extends YesWikiAction implements RegisteredAction
{
    /** `{{entryuserpage}}` in page content -- stated, not inferred from the filename. */
    public static function performableName(): string
    {
        return 'entryuserpage';
    }

    public function run()
    {
        $this->arguments['user'] = $this->getService(AuthenticationService::class)->getLoggedUserName();

        // returned, not printed: an action that prints lands wherever the output buffer
        // happens to be rather than where it was called from
        return '<h2 class="titre_mes_fiches">' . _t('BAZ_VOS_FICHES') . '</h2>'
            . $this->callAction('entrylist', $this->arguments);
    }
}
