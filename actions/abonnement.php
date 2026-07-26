<?php

// ticket 18: relocated from tools/contact/actions/abonnement.php.
// action permettant l'envoi par mail d'une demande d'inscription a une liste de discussion

// recuperation des parametres
$listelements['mail'] = $this->GetParameter('mail');
if (empty($listelements['mail'])) {
    echo '<div class="yw-alert yw-alert--danger"><strong>' . _t('CONTACT_ACTION_ABONNEMENT') . ' :</strong>&nbsp;' . _t('CONTACT_MAIL_REQUIRED') . '</div>';
} else {
    // on utilise une variable globale pour savoir de quel formulaire la demande est envoyee, s'il y en a plusieurs sur la meme page
    if (isset($GLOBALS['nbactionmail'])) {
        $GLOBALS['nbactionmail']++;
    } else {
        $GLOBALS['nbactionmail'] = 1;
    }
    $listelements['nbactionmail'] = $GLOBALS['nbactionmail'];

    // on choisit le template utilisé
    $template = $this->GetParameter('template');
    if (empty($template)) {
        $template = 'subscribe-form.twig';
    }

    // on peut ajouter des classes à la classe par défaut
    $listelements['class'] = ($this->GetParameter('class') ? 'form-abonnement ' . $this->GetParameter('class') : 'form-abonnement');

    $listelements['hiddeninputs'] = '';
    // on indique quel type de liste est utilisé pour formatter les envois de mail de facon adaptee
    $mailinglist = $this->GetParameter('mailinglist');
    if (!empty($mailinglist) and ($mailinglist == 'ezmlm' or $mailinglist == 'sympa')) {
        $listelements['hiddeninputs'] .= '<input type="hidden" name="mailinglist" value="' . $mailinglist . '">';
    }

    // page context for the /api/contact/mail route (javascripts/contact.js)
    $listelements['pageTag'] = $this->GetPageTag();

    // type de demande et placeholder
    $listelements['demand'] = 'abonnement';
    $listelements['placeholder'] = _t('CONTACT_SUBSCRIBE');

    echo $this->render("@core/$template", $listelements);

    $this->addJavascriptFile('javascripts/contact.js');
}
