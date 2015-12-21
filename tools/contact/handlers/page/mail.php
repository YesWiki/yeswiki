<?php

if (!defined("WIKINI_VERSION")) {
    die("acc&egrave;s direct interdit");
}

// inclusion de la bibliotheque de fonctions pour l'envoi des mails
include_once 'tools/contact/libs/contact.functions.php';

$output = '';

// si le handler est appele en ajax, on traite l'envoi de mail et on repond en ajax
if (isset($_POST['type']) && isset($_SERVER['HTTP_X_REQUESTED_WITH'])
    && ($_SERVER['HTTP_X_REQUESTED_WITH'] == 'XMLHttpRequest')) {
    //initialisation de variables passees en POST
    $mail_sender = (isset($_POST['email'])) ? trim($_POST['email']) : false;
    $mail_receiver = (isset($_POST['mail'])) ? trim($_POST['mail']) : false;
    if (!$mail_receiver) {
        $mail_receiver = (isset($_POST['nbactionmail'])) ?
            FindMailFromWikiPage($this->page["body"], $_POST['nbactionmail']) : false;
    }
    $name_sender = (isset($_POST['name'])) ? stripslashes($_POST['name']) : false;

    // dans le cas d'une page wiki envoyee, on formate le message en html et en txt
    if ($_POST['type'] == 'mail') {
        $subject = ((isset($_POST['subject'])) ? stripslashes($_POST['subject']) : false);
        $message_html = html_entity_decode(_convert($this->Format($this->page["body"]), TEMPLATES_DEFAULT_CHARSET));
        $message_txt = strip_tags(_convert($message_html, TEMPLATES_DEFAULT_CHARSET));
    } else {
        // pour un envoi de mail classique, le message en txt
        $subject = ((isset($_POST['entete'])) ? '[' . trim($_POST['entete']) . '] ' : '') .
            ((isset($_POST['subject'])) ? stripslashes(_convert($_POST['subject'], TEMPLATES_DEFAULT_CHARSET))
               : false) .
            (($name_sender) ? ' ' . _t('CONTACT_FROM') . ' ' . $name_sender : '');
        $message_html = '';
        $message_txt = (isset($_POST['message'])) ? stripslashes(
            _convert($_POST['message'], TEMPLATES_DEFAULT_CHARSET)
        ) : '';
    }

    // on verifie si tous les parametres sont bons
    $message = check_parameters_mail(
        $_POST['type'],
        $mail_sender,
        $name_sender,
        $mail_receiver,
        $subject,
        $message_txt
    );

    // si pas d'erreur on envoie
    if ($message['class'] == 'success') {
        if (send_mail($mail_sender, $name_sender, $mail_receiver, $subject, $message_txt, $message_html)) {
            if ($_POST['type'] == 'contact' || $_POST['type'] == 'mail') {
                $message['message'] = _t('CONTACT_MESSAGE_SUCCESSFULLY_SENT');
            } elseif ($_POST['type'] == 'abonnement') {
                $message['message'] = _t('CONTACT_SUBSCRIBE_ORDER_SENT');
            } elseif ($_POST['type'] == 'desabonnement') {
                $message['message'] = _t('CONTACT_UNSUBSCRIBE_ORDER_SENT');
            }
        } else {
            $message['class'] = "danger";
            $message['message'] = _t('CONTACT_MESSAGE_NOT_SENT');
        }
    }

    echo '<div class="alert alert-' . $message['class'] . '">' . $message['message'] . '</div>';
} else {
    //sinon on affiche le formulaire d'envoi de mail
    if ($this->GetUser()) {
        //si on est identifie
        //on verifie si l'on est bien identifie comme admin, pour eviter le spam
        if ($this->UserIsAdmin()) {
            $output .= '<div class="formulairemail">
            <h1>Envoyer la page par mail</h1>
            <form id="ajax-mail-form-handler" class="ajax-mail-form form-inline" action="' . $this->href('mail') . '">
                <div class="control-group">
                    <div class="controls">
                        <div class="input-prepend">
                            <span class="add-on"><i class="icon-envelope"></i></span>
                            <input required class="input-large" type="email" name="email" value=""
                            placeholder="' . _t('CONTACT_YOUR_MAIL') . '" />
                        </div>
                    </div>
                </div>
                <div class="control-group">
                    <div class="controls">
                        <div class="input-prepend">
                            <span class="add-on"><i class="icon-envelope"></i></span>
                            <input required class="input-large" type="email" name="mail" 
                            value="" placeholder="Adresse mail du destinataire" />
                        </div>
                    </div>
                </div>
                <div class="control-group">
                    <div class="controls">
                        <div class="input-prepend">
                            <input required class="contact-subject input-xlarge" type="text" name="subject"
                            value="" placeholder="' . _t('CONTACT_SUBJECT') . '" />
                        </div>
                    </div>
                </div>
                <div class="control-group">
                    <div class="controls">
                        <button class="btn btn-primary mail-submit" type="submit" name="submit">
                        <i class="icon-envelope icon-white"></i>&nbsp;' . _t('CONTACT_SEND_MESSAGE') . '
                        </button>
                    </div>
                </div>
                <input type="hidden" name="type" value="mail" />
            </form>
            </div>
            ';
            $GLOBALS['js'] = ((isset($GLOBALS['js'])) ? str_replace(
                '    <script src="tools/contact/libs/contact.js"></script>' . "\n",
                '',
                $GLOBALS['js']
            ) : '') . '    <script src="tools/contact/libs/contact.js"></script>' . "\n";
        } else {
            //message d'erreur si pas admin
            $output .= '<div class="alert alert-danger">' . _t('CONTACT_HANDLER_MAIL_FOR_ADMINS') . '</div>' . "\n";
        }
    } else {
        //on affiche le formulaire d'identification sinon
        $output .= '<div class="alert alert-danger">' . _t('CONTACT_HANDLER_MAIL_FOR_ADMINS') . '<br />'
            . _t('CONTACT_LOGIN_IF_ADMIN') . '</div>' . "\n";
        $output .= $this->Format('{{login}}') . "\n";
    }

    //affichage a l'ecran
    echo $this->Header();
    echo "<div class=\"page\">\n$output\n<hr class=\"hr_clear\" />\n</div>\n";
    echo $this->Footer();
}
