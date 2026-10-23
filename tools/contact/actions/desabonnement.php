<?php

use YesWiki\Core\Service\BotGuard;

/*
 * desabonnement.php.
 *
 * Description : action permettant l'envoi par mail d'une demande de desinscription a une liste de discussion
 */

$listelements['mail'] = $this->GetParameter('mail');
if (empty($listelements['mail'])) {
    echo '<div class="alert alert-danger"><strong>' . _t('CONTACT_ACTION_DESABONNEMENT') . ' :</strong>&nbsp;' . _t('CONTACT_MAIL_REQUIRED') . '</div>';
} else {
    if (isset($GLOBALS['nbactionmail'])) {
        $GLOBALS['nbactionmail']++;
    } else {
        $GLOBALS['nbactionmail'] = 1;
    }
    $listelements['nbactionmail'] = $GLOBALS['nbactionmail'];

    $template = $this->GetParameter('template');
    if (empty($template)) {
        $template = 'subscribe-form.twig';
    }
    if (!$this->services->get(YesWiki\Core\Service\TemplateEngine::class)->hasTemplate("@contact/$template")) {
        $template = 'subscribe-form.twig';
    }

    $listelements['hiddeninputs'] = '';
    $mailinglist = $this->GetParameter('mailinglist');
    if (!empty($mailinglist) and ($mailinglist == 'ezmlm' or $mailinglist == 'sympa')) {
        $listelements['hiddeninputs'] .= '<input type="hidden" name="mailinglist" value="' . $mailinglist . '">';
    }

    $listelements['class'] = ($this->GetParameter('class') ? 'form-desabonnement ' . $this->GetParameter('class') : 'form-desabonnement');

    $listelements['mailerurl'] = $this->href('mail');

    $listelements['demand'] = 'desabonnement';
    $listelements['placeholder'] = _t('CONTACT_UNSUBSCRIBE');

    echo $this->services->get(BotGuard::class)->insertInto($this->render("@contact/$template", $listelements));

    $this->addJavascriptFile('tools/contact/libs/contact.js');
}
