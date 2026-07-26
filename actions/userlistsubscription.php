<?php

// ticket 18: relocated from tools/contact/actions/userlistsubscription.php.
// action permettant l'envoi par mail d'une demande d'inscription ou desinscription a une liste
//
// Note (found, not fixed): same pre-existing, already-incomplete/dead $output-never-
// echoed shape as listsubscription.php -- see that file's comment.

// valable que pour les utilisateurs connectes
if ($user = $this->GetUser()) {
    if ($user['email'] != '') {
        // recuperation des parametres
        $list = $this->GetParameter('list');
        if (!empty($list)) {
            $output = '<div class="note"></div>
				<form id="ajax-abonne-form" class="form-mail" data-page-tag="' . htmlspecialchars($this->GetPageTag()) . '">
					' . $list . ' : ' . "\n" .
                '</form>' . "\n";
        } else {
            echo '<div class="yw-alert yw-alert--danger"><strong>' . _t('CONTACT_ACTION_LISTSUBSCRIPTION') . '</strong> : ' . _t('CONTACT_LIST_REQUIRED') . '.</div>';
        }
    } else {
        echo '<div class="yw-alert yw-alert--danger"><strong>' . _t('CONTACT_ACTION_LISTSUBSCRIPTION') . '</strong> : ' . _t('CONTACT_USER_NO_EMAIL') . '</div>';
    }
} else {
    echo '<div class="yw-alert yw-alert--danger"><strong>' . _t('CONTACT_ACTION_LISTSUBSCRIPTION') . '</strong> : ' . _t('CONTACT_USER_NOT_LOGGED_IN') . '</div>';
}
