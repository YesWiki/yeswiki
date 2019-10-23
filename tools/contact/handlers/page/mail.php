<?php

if (!defined("WIKINI_VERSION")) {
    die("acc&egrave;s direct interdit");
}

// inclusion de la bibliotheque de fonctions pour l'envoi des mails
include_once 'includes/email.inc.php';
include_once 'tools/contact/libs/contact.functions.php';

$output = '';

// si le handler est appele en ajax, on traite l'envoi de mail et on repond en ajax
if ((isset($_POST['mail']) or $_POST['email']) && isset($_SERVER['HTTP_X_REQUESTED_WITH'])
    && ($_SERVER['HTTP_X_REQUESTED_WITH'] == 'XMLHttpRequest')) {
    // pied de page pour mails envoyés
    $infomsg = '';

    //initialisation de variables passees en POST
    $mail_sender = (isset($_POST['email'])) ? trim($_POST['email']) : false;
    if (isset($_GET['field']) and !empty($_GET['field'])) {
        $mail_receiver = '';
        $val = baz_valeurs_fiche($this->GetPageTag());
        if (is_array($val) and isset($val[$_GET['field']])) {
            $mail_receiver = $val[$_GET['field']];
        }
        $form = baz_valeurs_formulaire($val['id_typeannonce']);
        $infomsg .= '<i>'._t('CONTACT_THIS_MESSAGE').' "<a href="'.$this->href('', $val['id_fiche']).'">'.$val['bf_titre'].'</a>" '._t('CONTACT_FROM_FORM').' "'.$form['bn_label_nature'].'" '._t('CONTACT_FROM_WEBSITE').' "'.$this->config['wakka_name'].'".</i><br><br>';
    } else {
        $mail_receiver = (isset($_POST['mail'])) ? trim($_POST['mail']) : false;
    }
    if (!$mail_receiver) {
        //on prend le squelette du theme qui pourrait contenir des actions avec des mails
        $chemin = 'themes/'.$this->config['favorite_theme'].'/squelettes/'.$this->config['favorite_squelette'];
        if (file_exists($chemin)) {
            $file_content = file_get_contents($chemin);
        } else {
            $file_content = file_get_contents('tools/templates/'.$chemin);
        }
        $body = str_replace('{WIKINI_PAGE}', $this->page["body"], $file_content);
        $mail_receiver = (isset($_POST['nbactionmail'])) ?
            FindMailFromWikiPage($body, $_POST['nbactionmail']) : false;
    }
    $name_sender = (isset($_POST['name'])) ? stripslashes($_POST['name']) : false;

    // dans le cas d'une page wiki envoyee, on formate le message en html et en txt
    if (isset($_POST['type']) and $_POST['type'] == 'mail') {
        $subject = ((isset($_POST['subject'])) ? stripslashes($_POST['subject']) : false);
        $message_html = $infomsg.html_entity_decode(_convert($this->Format($this->page["body"], 'wakka', $this->GetPageTag()), YW_CHARSET));
        $message_txt = strip_tags(_convert($message_html, YW_CHARSET));
    } elseif (isset($_POST['type']) and ($_POST['type'] == 'abonnement' or $_POST['type'] == 'desabonnement')) {
        $message_html = $message_txt = 'Mailinglist : '.$_POST['type'];
    } else {
        // pour un envoi de mail classique, le message en txt
        $subject = ((isset($_POST['entete'])) ? '[' . trim($_POST['entete']) . '] ' : '') .
          ((isset($_POST['subject'])) ? stripslashes(_convert($_POST['subject'], YW_CHARSET)) : false) .
          (($name_sender) ? ' ' . _t('CONTACT_FROM') . ' ' . $name_sender : '');
        $message = (isset($_POST['message'])) ? $infomsg."\n\n".stripslashes(_convert(strip_tags($_POST['message']), YW_CHARSET)) : '';
        $message_html = trim($message);
        $message_txt = trim(strip_tags($message));
    }

    // on verifie si tous les parametres sont bons
    $message = check_parameters_mail(
        isset($_POST['type']) ? $_POST['type'] : '',
        $mail_sender,
        $name_sender,
        $mail_receiver,
        $subject,
        $message_txt
    );

    // si pas d'erreur on envoie
    if ($message['class'] == 'success') {
        // test de presence d'ezmlm, qui necessite de reformater le mail envoyé
        if (isset($_POST['mailinglist']) and $_POST['mailinglist'] == 'ezmlm') {
            $mail_receiver = str_replace('@', '-'.str_replace('@', '=', $mail_sender).'@', $mail_receiver);
        }

        // test de presence de sympa, qui necessite de reformater le mail envoyé
        if (isset($_POST['mailinglist']) and $_POST['mailinglist'] == 'sympa') {
            $tabmail = explode('@', $mail_receiver);
            $listname = $tabmail[0];
            $listdomain = $tabmail[1];
            $mail_receiver = 'sympa@'.$listdomain;
            if ($_POST['type'] == 'abonnement') {
                $subject = 'subscribe '.$listname;
            } elseif ($_POST['type'] == 'desabonnement') {
                $subject = 'unsubscribe '.$listname;
            }
        }

        if (empty($message_txt)) {
            $message_txt = $message_html = 'dummy message';
        }

        if (send_mail($mail_sender, $name_sender, $mail_receiver, $subject, $message_txt, $message_html)) {
            if (!isset($_POST['type']) or $_POST['type'] == 'contact' or $_POST['type'] == 'mail') {
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
    // affichage des formulaire et chargement du js necessaire
    $this->addJavascriptFile('tools/contact/libs/contact.js');
    if (isset($_GET['field']) and !empty($_GET['field'])) {
        $output .= '<form id="ajax-mail-form-handler" class="ajax-mail-form" action="' . $this->href('mail', '', 'field='.$_GET['field']) . '">
            <div class="form-group">
              <div class="input-group">
                <div class="input-group-addon"><i class="fa fa-envelope"></i></div>
                <input required class="form-control" type="email" name="email" value=""
                    placeholder="' . _t('CONTACT_YOUR_MAIL') . '" />
              </div>
            </div>
            <div class="form-group">
              <input required class="contact-subject form-control" type="text" name="subject"
                    value="" placeholder="' . _t('CONTACT_SUBJECT') . '" />
            </div>
            <div class="form-group">
              <textarea required rows="6" class="form-control" name="message"
                    placeholder="' . _t('CONTACT_YOUR_MESSAGE') . '"></textarea>
            </div>
            <button class="btn btn-lg btn-block btn-primary mail-submit" type="submit" name="submit">
              <i class="fa fa-envelope"></i>&nbsp;' . _t('CONTACT_SEND_MESSAGE') . '
            </button>
            <input type="hidden" name="mail" value="'.htmlspecialchars($_GET['field']).'">
        </form>';
    } elseif ($this->GetUser()) {
        //sinon on affiche le formulaire d'envoi de mail
        //si on est identifie
        //on verifie si l'on est bien identifie comme admin, pour eviter le spam
        $output .= '<h1>Envoyer la page par mail</h1>
        <form id="ajax-mail-form-handler" class="ajax-mail-form" action="' . $this->href('mail') . '">
          <div class="form-group">
            <div class="input-group">
              <div class="input-group-addon"><i class="fa fa-envelope"></i></div>
              <input required class="form-control" type="email" name="email" value=""
                    placeholder="' . _t('CONTACT_YOUR_MAIL') . '" />
            </div>
          </div>
          <div class="form-group">
            <div class="input-group">
              <div class="input-group-addon"><i class="fa fa-envelope"></i></div>
              <input required class="form-control" type="email" name="mail"
                        value="" placeholder="Adresse mail du destinataire" />
            </div>
          </div>
          <div class="form-group">
            <input required class="contact-subject form-control" type="text" name="subject"
                  value="" placeholder="' . _t('CONTACT_SUBJECT') . '" />
          </div>
          <button class="btn btn-lg btn-block btn-primary mail-submit" type="submit" name="submit">
            <i class="fa fa-envelope"></i>&nbsp;' . _t('CONTACT_SEND_MESSAGE') . '
          </button>
          <input type="hidden" name="type" value="mail" />
        </form>';
    } else {
        //on affiche le formulaire d'identification sinon
        $output .= '<div class="alert alert-danger">' . _t('CONTACT_HANDLER_MAIL_FOR_CONNECTED') . '<br />'
            . _t('CONTACT_LOGIN_IF_CONNECTED') . '</div>' . "\n";
        $output .= $this->Format('{{login}}') . "\n";
    }

    //affichage a l'ecran
    echo $this->Header();
    echo "<div class=\"page\">\n$output\n<hr class=\"hr_clear\" />\n</div>\n";
    echo $this->Footer();
}
