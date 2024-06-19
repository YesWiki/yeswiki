<?php

/*
Permet de faire une redirection vers une autre pages Wiki du site
Parametres : page : nom wiki de la page vers laquelle ont doit rediriger (obligatoire)
exemple : {{redirect page="BacASable"}}
*/
use YesWiki\Core\Service\LinkTracker;

$redirPageName = $this->GetParameter('page');

if (!$redirPageName) {
    echo '<div class="alert alert-danger"><strong>' . _t('ERROR_ACTION_REDIRECT') . '</strong> : ' . _t('MISSING_PAGE_PARAMETER') . '.</div>' . "\n";
} else {
    if ($this->GetMethod() == 'show') {
        $this->services->get(LinkTracker::class)->forceAddIfNotIncluded($redirPageName);
        if (!isset($_SESSION['redirects'])) {
            $_SESSION['redirects'] = [];
        }
        $_SESSION['redirects'][] = strtolower($this->GetPageTag());

        if (in_array(strtolower($redirPageName), $_SESSION['redirects'])) {
            echo '<div class="alert alert-danger"><strong>' . _t('ERROR_ACTION_REDIRECT') . '</strong> : ' . _t('CIRCULAR_REDIRECTION_FROM_PAGE') . " $redirPageName ( "
            . $this->ComposeLinkToPage($redirPageName, 'edit', call_user_func('_t', 'CLICK_HERE')) . ')</div>' . "\n";
        } else {
            $this->Redirect($this->Href('', $redirPageName));
        }
    } else {
        echo '<span style="color: red; weight: bold">' . _t('PRESENCE_OF_REDIRECTION_TO') . ' "' . $this->Link($redirPageName) . '"</span>';
    }
}
