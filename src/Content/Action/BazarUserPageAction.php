<?php

namespace YesWiki\Content\Action;
use YesWiki\Identity\Service\AuthenticationService;
use YesWiki\Core\YesWikiAction;
use YesWiki\Kernel\Performable\RegisteredAction;

class BazarUserPageAction extends YesWikiAction implements RegisteredAction
{
    /** `{{bazaruserpage}}` in page content -- stated, not inferred from the filename. */
    public static function performableName(): string
    {
        return 'bazaruserpage';
    }

    public function run()
    {
        echo '<h2 class="titre_mes_fiches">' . _t('BAZ_VOS_FICHES') . '</h2>';

        $this->arguments['user'] = $this->getService(AuthenticationService::class)->getLoggedUserName();

        return $this->callAction('bazarliste', $this->arguments);
    }
}
