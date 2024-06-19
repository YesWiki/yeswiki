<?php

use YesWiki\Core\Controller\CsrfTokenController;

// Vérification de sécurité
if (!defined('WIKINI_VERSION')) {
    exit('acc&egrave;s direct interdit');
}

if (($this->UserIsOwner() || $this->UserIsAdmin())
        && isset($_GET['eraselink'])
        && $_GET['eraselink'] === 'oui'
        && isset($_GET['confirme'])
        && ($_GET['confirme'] === 'oui')
) {
    try {
        if ($this->services->get(CsrfTokenController::class)->checkToken('main', 'POST', 'csrf-token', false)) {
            $tag = $this->GetPageTag();
            $this->Query("DELETE FROM {$this->config['table_prefix']}links WHERE to_tag = '$tag'");
        }
    } catch (Throwable $th) {
        // do nothing
    }
}
