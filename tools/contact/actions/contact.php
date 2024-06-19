<?php

//recuperation des parametres
$contactelements['mail'] = $this->GetParameter('mail');
if (empty($contactelements['mail'])) {
    echo '<div class="alert alert-danger"><strong>' . _t('CONTACT_ACTION_CONTACT') . ' :</strong>&nbsp;' . _t('CONTACT_MAIL_REQUIRED') . '</div>';
} else {
    // on utilise une variable globale pour savoir de quel formulaire la demande est envoyee, s'il y en a plusieurs sur la meme page
    if (isset($GLOBALS['nbactionmail'])) {
        $GLOBALS['nbactionmail']++;
    } else {
        $GLOBALS['nbactionmail'] = 1;
    }
    $contactelements['nbactionmail'] = $GLOBALS['nbactionmail'];

    $contactelements['entete'] = $this->GetParameter('entete');
    if (empty($contactelements['entete'])) {
        $contactelements['entete'] = $this->config['wakka_name'];
    }

    // on choisit le template utilisé
    $template = $this->GetParameter('template');
    if (empty($template)) {
        $template = 'complete-contact-form.tpl.html';
    }

    // on peut ajouter des classes à la classe par défaut
    $contactelements['class'] = ($this->GetParameter('class') ? 'form-contact ' . $this->GetParameter('class') : 'form-contact');

    // adresse url d'envoi du mail
    $contactelements['mailerurl'] = $this->href('mail');

    echo $this->render("@contact/$template", $contactelements);

    $this->addJavascriptFile('tools/contact/libs/contact.js');
}
