<?php
/**
* desabonnement.php.
*
* Description : action permettant l'envoi par mail d'une demande de desinscription a une liste de discussion
*/
if (!defined('WIKINI_VERSION')) {
    exit('acc&egrave;s direct interdit');
}

//recuperation des parametres
$listelements['mail'] = $this->GetParameter('mail');
if (empty($listelements['mail'])) {
    echo '<div class="alert alert-danger"><strong>' . _t('CONTACT_ACTION_DESABONNEMENT') . ' :</strong>&nbsp;' . _t('CONTACT_MAIL_REQUIRED') . '</div>';
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
        $template = 'subscribe-form.tpl.html';
    }

    $listelements['hiddeninputs'] = '';
    // on indique quel type de liste est utilisé pour formatter les envois de mail de facon adaptee
    $mailinglist = $this->GetParameter('mailinglist');
    if (!empty($mailinglist) and ($mailinglist == 'ezmlm' or $mailinglist == 'sympa')) {
        $listelements['hiddeninputs'] .= '<input type="hidden" name="mailinglist" value="' . $mailinglist . '">';
    }

    // on peut ajouter des classes à la classe par défaut
    $listelements['class'] = ($this->GetParameter('class') ? 'form-desabonnement ' . $this->GetParameter('class') : 'form-desabonnement');

    // adresse url d'envoi du mail
    $listelements['mailerurl'] = $this->href('mail');

    // type de demande et placeholder
    $listelements['demand'] = 'desabonnement';
    $listelements['placeholder'] = _t('CONTACT_UNSUBSCRIBE');

    echo $this->render("@contact/$template", $listelements);

    $this->addJavascriptFile('tools/contact/libs/contact.js');
}
