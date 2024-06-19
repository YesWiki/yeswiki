<?php

use YesWiki\Bazar\Controller\EntryController;
use YesWiki\Bazar\Service\EntryManager;
use YesWiki\Core\Service\AclService;
use YesWiki\Core\Service\ThemeManager;

if (!defined('WIKINI_VERSION')) {
    exit('acc&egrave;s direct interdit');
}

// inclusion de la bibliotheque de fonctions pour l'envoi des mails
include_once 'includes/email.inc.php';
include_once 'tools/contact/libs/contact.functions.php';

$aclService = $this->services->get(AclService::class);
$entryManager = $this->services->get(EntryManager::class);
$entryController = $this->services->get(EntryController::class);
$themeManager = $this->services->get(ThemeManager::class);
$output = '';

// si le handler est appele en ajax, on traite l'envoi de mail et on repond en ajax
if ((!empty($_POST['mail']) || !empty($_POST['email'])) && isset($_SERVER['HTTP_X_REQUESTED_WITH'])
    && ($_SERVER['HTTP_X_REQUESTED_WITH'] == 'XMLHttpRequest')) {
    // entête de mail qd le champ $_GET['field'] est spécifié
    $infomsg = '';

    //initialisation de variables passees en POST
    $mail_sender = (isset($_POST['email'])) ? trim($_POST['email']) : false;
    $hasReadAccess = true;
    if (!empty($_GET['field'])) {
        $hasReadAccess = $aclService->hasAccess('read');
        $mail_receiver = '';
        if ($hasReadAccess) {
            $val = $entryManager->getOne($this->GetPageTag());
            if (is_array($val) and isset($val[$_GET['field']])) {
                $mail_receiver = $val[$_GET['field']];
            }
            $form = baz_valeurs_formulaire($val['id_typeannonce']);
            $infomsg .= '<em>' . _t('CONTACT_THIS_MESSAGE') . ' « <a href="' . $this->href('', $val['id_fiche']) . '">'
                . $val['bf_titre'] . '</a> » ' . _t('CONTACT_FROM_FORM') . ' « ' . $form['bn_label_nature'] . ' » '
                . _t('CONTACT_FROM_WEBSITE') . ' « ' . $this->config['wakka_name'] . ' ». ' .
                ($mail_sender ? _t('CONTACT_REPLY') . ' <strong>' . $mail_sender . '</strong> '
                    . _t('CONTACT_REPLY2') : '') . '.</em><br><br>';
        }
    } else {
        $mail_receiver = (isset($_POST['mail'])) ? trim($_POST['mail']) : false;
    }
    if (!$mail_receiver) {
        $hasReadAccess = $aclService->hasAccess('read');
        if ($hasReadAccess) {
            //on prend le squelette du theme qui pourrait contenir des actions avec des mails
            $chemin = 'themes/' . $themeManager->getFavoriteTheme() . '/squelettes/' . $themeManager->getFavoriteSquelette();
            if (file_exists($chemin)) {
                $file_content = file_get_contents($chemin);
            } elseif (file_exists('tools/templates/' . $chemin)) {
                $file_content = file_get_contents('tools/templates/' . $chemin);
            } else {
                $file_content = '{WIKINI_PAGE}';
            }
            $body = str_replace('{WIKINI_PAGE}', $this->page['body'], $file_content);
            $mail_receiver = (isset($_POST['nbactionmail'])) ?
                FindMailFromWikiPage($body, $_POST['nbactionmail']) : false;
        }
    }
    $name_sender = (isset($_POST['name'])) ? stripslashes($_POST['name']) : false;
    // when a mail is send from a bazar entry (no POST parameter 'type'), the type is ''
    $type = !empty($_POST['type']) ? $_POST['type'] : '';

    // dans le cas d'une page wiki envoyee, on formate le message en html et en txt
    if ($type == 'mail') {
        $hasReadAccess = $aclService->hasAccess('read');
        if ($hasReadAccess) {
            $subject = ((isset($_POST['subject'])) ? stripslashes($_POST['subject']) : false);
            if ($entryManager->isEntry($this->GetPageTag())) {
                $renderedPage = $entryController->view($this->GetPageTag());
            } else {
                $renderedPage = $this->Format($this->page['body'], 'wakka', $this->GetPageTag());
            }
            $message_html = html_entity_decode(_convert($renderedPage, YW_CHARSET));
            $message_txt = strip_tags(_convert($message_html, YW_CHARSET));
        }
    } elseif ($type == 'abonnement' or $type == 'desabonnement') {
        $message_html = $message_txt = 'Mailinglist : ' . $type;
    } else {
        // pour un envoi de mail classique, le message en txt
        $subject = ((isset($_POST['entete'])) ? '[' . trim($_POST['entete']) . '] ' : '') .
          ((isset($_POST['subject'])) ? stripslashes(_convert($_POST['subject'], YW_CHARSET)) : false);
        $message = (isset($_POST['message'])) ? stripslashes(_convert(strip_tags($_POST['message']), YW_CHARSET)) : '';
        $message_txt = trim(strip_tags($message));
        // euro symbol is not replaced by htmlspecialchar
        $message_html = trim(nl2br(str_replace('€', '&euro;', htmlspecialchars($message, ENT_COMPAT, YW_CHARSET))));
    }

    // on verifie si tous les parametres sont bons
    if ($hasReadAccess) {
        $message = check_parameters_mail(
            $type,
            $mail_sender,
            $name_sender,
            $mail_receiver ?? '',
            $subject ?? '',
            $message_txt ?? ''
        );

        // adding the infomsg after checking the size of the message
        if ($type != 'abonnement' && $type != 'desabonnement' && !empty($infomsg)) {
            $message_txt = strip_tags($infomsg) . '\n\n' . $message_txt;
            $message_html = $infomsg . $message_html;
        }
    } else {
        $message = [
            'class' => 'danger',
            'message' => _t('CONTACT_MESSAGE_NOT_SENT') . ' :<br />' . _t('LOGIN_NOT_AUTORIZED'),
        ];
    }

    // si pas d'erreur on envoie
    if ($message['class'] == 'success') {
        // test de presence d'ezmlm, qui necessite de reformater le mail envoyé
        if (isset($_POST['mailinglist']) and $_POST['mailinglist'] == 'ezmlm') {
            $mail_receiver = str_replace('@', '-' . str_replace('@', '=', $mail_sender) . '@', $mail_receiver);
        }

        // test de presence de sympa, qui necessite de reformater le mail envoyé
        if (isset($_POST['mailinglist']) and $_POST['mailinglist'] == 'sympa') {
            $tabmail = explode('@', $mail_receiver);
            $listname = $tabmail[0];
            $listdomain = $tabmail[1];
            $mail_receiver = 'sympa@' . $listdomain;
            if ($type == 'abonnement') {
                $subject = 'subscribe ' . $listname;
            } elseif ($type == 'desabonnement') {
                $subject = 'unsubscribe ' . $listname;
            }
        }

        if (empty($message_txt)) {
            $message_txt = $message_html = 'dummy message';
        }

        if (send_mail($mail_sender, $name_sender, $mail_receiver, $subject, $message_txt, $message_html)) {
            if (empty($type) || $type == 'contact' || $type == 'mail') {
                $message['message'] = _t('CONTACT_MESSAGE_SUCCESSFULLY_SENT');
            } elseif ($type == 'abonnement') {
                $message['message'] = _t('CONTACT_SUBSCRIBE_ORDER_SENT');
            } elseif ($type == 'desabonnement') {
                $message['message'] = _t('CONTACT_UNSUBSCRIBE_ORDER_SENT');
            }
        } else {
            $message['class'] = 'danger';
            $message['message'] = _t('CONTACT_MESSAGE_NOT_SENT');
        }
    }
    echo $this->render('@templates/alert-message.twig', [
        'type' => $message['class'],
        'message' => $message['message'],
    ]);
} else {
    // affichage des formulaire et chargement du js necessaire
    $this->addJavascriptFile('tools/contact/libs/contact.js');
    if ($aclService->hasAccess('read') && isset($_GET['field']) and !empty($_GET['field'])) {
        $output .= '<form id="ajax-mail-form-handler" class="ajax-mail-form" action="' . $this->href('mail', '', 'field=' . $_GET['field']) . '">
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
            <input type="hidden" name="mail" value="' . htmlspecialchars($_GET['field']) . '">
        </form>';
    } elseif ($aclService->hasAccess('read') && $this->GetUser()) {
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
                        value="" placeholder="' . _t('CONTACT_TO_PLACEHOLDER') . '" />
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
        $output .= $this->render('@templates/alert-message.twig', [
            'type' => 'danger',
            'message' => ($this->GetUser())
                ? _t('LOGIN_NOT_AUTORIZED')
                : (_t('CONTACT_HANDLER_MAIL_FOR_CONNECTED') . '<br />'
                . _t('CONTACT_LOGIN_IF_CONNECTED')),
        ]);
        $output .= $this->Format('{{login}}') . "\n";
    }

    //affichage a l'ecran
    echo $this->Header();
    echo "<div class=\"page\">\n$output\n<hr class=\"hr_clear\" />\n</div>\n";
    echo $this->Footer();
}
