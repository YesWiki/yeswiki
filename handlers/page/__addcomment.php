<?php

use YesWiki\Core\Service\HashCashService;

if (isset($_POST['action']) && $_POST['action'] == 'addcomment') {
    if (!$this->services->get(HashCashService::class)->checkHashcash()) {
        $this->SetMessage(_t('HASHCASH_COMMENT_NOT_SAVED_MAYBE_YOU_ARE_A_ROBOT'));
        $this->redirect($this->href());
    }
}
