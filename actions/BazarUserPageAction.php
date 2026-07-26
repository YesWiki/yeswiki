<?php

use YesWiki\Core\Controller\AuthController;
use YesWiki\Core\YesWikiAction;

class BazarUserPageAction extends YesWikiAction
{
    public function run()
    {
        echo '<h2 class="titre_mes_fiches">' . _t('BAZ_VOS_FICHES') . '</h2>';

        $this->arguments['user'] = $this->getService(AuthController::class)->getLoggedUserName();

        return $this->callAction('bazarliste', $this->arguments);
    }
}
