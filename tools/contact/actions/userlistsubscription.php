<?php
/**
* listsubscription.php.
*
* Description : action permettant l'envoi par mail d'une demande d'inscription ou desinscription a une liste
*/
if (!defined('WIKINI_VERSION')) {
    exit('acc&egrave;s direct interdit');
}

// valable que pour les utilisateurs connectes
if ($user = $this->GetUser()) {
    if ($user['email'] != '') {
        //recuperation des parametres
        $list = $this->GetParameter('list');
        if (!empty($list)) {
            $output = '<div class="note"></div>
				<form id="ajax-abonne-form" class="form-mail" action="' . $this->href('mail') . '">
					' . $list . ' : ' . "\n" .
                '</form>' . "\n";
        } else {
            echo '<div class="alert alert-danger"><strong>' . _t('CONTACT_ACTION_LISTSUBSCRIPTION') . '</strong> : ' . _t('CONTACT_LIST_REQUIRED') . '.</div>';
        }
    } else {
        echo '<div class="alert alert-danger"><strong>' . _t('CONTACT_ACTION_LISTSUBSCRIPTION') . '</strong> : ' . _t('CONTACT_USER_NO_EMAIL') . '</div>';
    }
} else {
    echo '<div class="alert alert-danger"><strong>' . _t('CONTACT_ACTION_LISTSUBSCRIPTION') . '</strong> : ' . _t('CONTACT_USER_NOT_LOGGED_IN') . '</div>';
}
