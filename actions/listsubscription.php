<?php

// ticket 18: relocated from tools/contact/actions/listsubscription.php.
// action permettant l'envoi par mail d'une demande d'inscription ou desinscription a une liste
//
// Note (found, not fixed): the success branch below builds $output but never echoes
// it, and the built markup has no submit button or ajax-mail-form/mail-submit wiring
// anyway -- this looks like pre-existing, already-incomplete/dead functionality, not
// something this ticket's Mailer/API-route consolidation broke. Left byte-for-byte
// as before rather than "fixed" into a still-non-functional visible form.

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
            $this->addJavascriptFile('javascripts/contact.js');
        } else {
            echo '<div class="yw-alert yw-alert--danger"><strong>' . _t('CONTACT_ACTION_LISTSUBSCRIPTION') . '</strong> : ' . _t('CONTACT_LIST_REQUIRED') . '.</div>';
        }
    } else {
        echo '<div class="yw-alert yw-alert--danger"><strong>' . _t('CONTACT_ACTION_LISTSUBSCRIPTION') . '</strong> : ' . _t('CONTACT_USER_NO_EMAIL') . '</div>';
    }
} else {
    echo '<div class="yw-alert yw-alert--danger"><strong>' . _t('CONTACT_ACTION_LISTSUBSCRIPTION') . '</strong> : ' . _t('CONTACT_USER_NOT_LOGGED_IN') . '</div>';
}
