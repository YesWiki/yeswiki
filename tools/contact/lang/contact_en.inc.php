<?php
/*vim: set expandtab tabstop=4 shiftwidth=4: */
// +------------------------------------------------------------------------------------------------------+
// | PHP version 5                                                                                        |
// +------------------------------------------------------------------------------------------------------+
// | Copyright (C) 2012 Outils-Réseaux (accueil@outils-reseaux.org)                                       |
// +------------------------------------------------------------------------------------------------------+
// | This library is free software; you can redistribute it and/or                                        |
// | modify it under the terms of the GNU Lesser General Public                                           |
// | License as published by the Free Software Foundation; either                                         |
// | version 2.1 of the License, or (at your option) any later version.                                   |
// |                                                                                                      |
// | This library is distributed in the hope that it will be useful,                                      |
// | but WITHOUT ANY WARRANTY; without even the implied warranty of                                       |
// | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU                                    |
// | Lesser General Public License for more details.                                                      |
// |                                                                                                      |
// | You should have received a copy of the GNU Lesser General Public                                     |
// | License along with this library; if not, write to the Free Software                                  |
// | Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA                            |
// +------------------------------------------------------------------------------------------------------+
//
/**
* Fichier de traduction en anglais de l'extension Contact
*
*@package       contact
*@author        Florian Schmitt <florian@outils-reseaux.org>
*@copyright     2012 Outils-Réseaux
*/

return [

    // actions/abonnement.php
    'CONTACT_ACTION_ABONNEMENT' => 'Action {{abonnement ...}}',
    'CONTACT_MAIL_REQUIRED' => 'the email, required, is missing',

    // actions/contact.php
    'CONTACT_ACTION_CONTACT' => 'Action {{contact ...}}',

    // actions/desabonnement.php
    'CONTACT_ACTION_DESABONNEMENT' => 'Action {{desabonnement ...}}',

    // actions/listsubscription.php
    'CONTACT_ACTION_LISTSUBSCRIPTION' => 'Action {{listsubscription ...}}',

    // actions/mailinglist.php
    'CONTACT_ACTION_MAILINGLIST' => 'Action {{mailinglist ...}}',
    'CONTACT_PARAMETER_LIST_REQUIRED' => 'parameter list required (list\'s email)',
    'CONTACT_MAILS_TO_ADD_OR_REMOVE' => 'emails to add or remove of the list',
    'CONTACT_SENT_TO_THE_LIST' => 'send to the list',
    'CONTACT_THE_EMAIL' => 'the email',
    'CONTACT_SUBMIT_OTHER_EMAILS' => 'submit other emails',
    'CONTACT_OK' => 'OK',
    'CONTACT_BTN_SUBSCRIBE' => 'Suscribe',
    'CONTACT_BTN_UNSUBSCRIBE' => 'Unsubscribe',
    'CONTACT_FOR_ALL_THOSE_EMAILS' => 'For all those emails',
    'CONTACT_TRY_WITH_OTHER_EMAILS' => 'Try with other emails in the text',
    'CONTACT_NO_EMAILS_FOUND_IN_THIS_TEXT' => 'No email found in this text',
    'CONTACT_ENTER_TEXT_WITH_EMAILS_INSIDE' => 'Enter text with emails inside to extract them, no matter the splitters (comma, semi-colon, colon, tab, wordwrap)',
    'CONTACT_YOUR_EMAIL_LIST' => 'Your emails list',
    'CONTACT_EXTRACT_EMAILS_FROM_TEXT' => 'Extract emails from the text',
    'CONTACT_MUST_BE_ADMIN_TO_USE_THIS_ACTION' => 'You have to be in the admins group to use this action',


    'CONTACT_YOUR_NAME' => 'Your name',
    'CONTACT_YOUR_MAIL' => 'Your email',
    'CONTACT_SUBJECT' => 'message subject',
    'CONTACT_YOUR_MESSAGE' => 'Your message',
    'CONTACT_SEND_MESSAGE' => 'Send message',
    'CONTACT_LISTSUBSCRIBE_REQUIRED' => ' The "list" parameter, which have the list\'s email, like namelist@domain.ext, is required',
    'CONTACT_USER_NOT_LOGGED_IN' => 'You have to be logged to make any action',
    'CONTACT_USER_NO_EMAIL' => 'You have to be logged to make any action',

    'CONTACT_ENTER_NAME' => 'Enter a name',
    'CONTACT_ENTER_SENDER_MAIL' => 'Your email is needed as sender',
    'CONTACT_SENDER_MAIL_INVALID' => 'Valid email is needed as sender',
    'CONTACT_ENTER_RECEIVER_MAIL' => 'Recipient email is needed',
    'CONTACT_RECEIVER_MAIL_INVALID' => 'Recipient valid email is needed',
    'CONTACT_ENTER_MESSAGE' => 'Please enter a message, containing at least 10 characters',

    'CONTACT_MESSAGE_SUCCESSFULLY_SENT' => 'Your message have been sent. Thank you !',
    'CONTACT_MESSAGE_NOT_SENT' => 'Your message could not be sent... Server configuration problem ?',
    'CONTACT_SUBSCRIBE_ORDER_SENT' => 'Subscribe order sent. Thank you!',
    'CONTACT_UNSUBSCRIBE_ORDER_SENT' => 'Unsubscribe order sent. Thank you!',

    'CONTACT_HANDLER_MAIL_FOR_ADMINS' => 'Handler mail is only for admins group',
    'CONTACT_LOGIN_IF_ADMIN' => 'Please login if you are admin',

    'CONTACT_SUBSCRIBE' => 'Subscribe',
    'CONTACT_UNSUBSCRIBE' => 'Unsubscribe',

    'CONTACT_FROM' => 'from',
    // 'CONTACT_TEMPLATE_NOT_FOUND' => 'Fichier de template non trouvé',
    // 'CONTACT_MESSAGE_SENT_FROM' => 'Message envoyé à partir de',

    // 'CONTACT_PERIOD' => 'Recevoir le contenu de cette page par email de manière',
    // 'CONTACT_DAILY' => 'Journalière',
    // 'CONTACT_WEEKLY' => 'Hebdomadaire',
    // 'CONTACT_MONTHLY' => 'Mensuelle',
    // 'CONTACT_UNSUBSCRIBE' => 'Se désabonner',
    // 'CONTACT_SUCCESS_SUBSCRIBE' => 'Vous êtes maintenant abonné de manière ',
    // 'CONTACT_SUCCESS_UNSUBSCRIBE' => 'Vous avez bien été désabonné',

    // 'CONTACT_THIS_MESSAGE' => 'Ce message est envoyé par un visiteur depuis la fiche',
    // 'CONTACT_FROM_FORM' => 'du formulaire',
    // 'CONTACT_FROM_WEBSITE' => 'du site',
    // 'CONTACT_REPLY' => 'Vous pouvez lui écrire un message à',
    // 'CONTACT_REPLY2' => 'en répondant à ce mail',

    // handlers/page/mail.php
    // 'CONTACT_TO_PLACEHOLDER' => 'Adresse mail du destinataire',

    // handlers/page/sendmail.php
    // 'CONTACT_SENDMAIL_INFO' => 'On envoie les mails pour la période',
    // 'CONTACT_SENDMAIL_ERROR' => 'La période n\'a pas été renseignée ou n\'a pas de valeur standard (month, week ou day).',

    // libs/contact.functions.js
    // 'CONTACT_DAILY_REPORT' => 'rapport journalier du',
    // 'CONTACT_WEEKLY_REPORT' => 'rapport hebdomadaire du',
    // 'CONTACT_MONTHLY_REPORT' => 'rapport mensuel du',

    // templates/notify-admins-email-subject.twig (no special chars)
    // 'CONTACT_ENTRY_ADDED' => 'nouvelle fiche ajoutee',
    // 'CONTACT_ENTRY_CHANGED' => 'fiche modifiee',
    // 'CONTACT_IN_FORM' => 'dans le formulaire',

    // templates/notify-admins-list-deleted-email-subject.twig (no special chars)
    // 'CONTACT_DELETED_LIST' => 'liste supprimee',

    // templates/notify-admins-list-deleted-email-text.twig (no special chars)
    // 'CONTACT_USED_IP' => 'IP utilisee',

    // templates/notify-email-html.twig and templates/notify-email-text.twig (no special chars)
    // 'CONTACT_WELCOME_ON' => 'Bienvenue sur',

    // templates/notify-email-subject.twig (no special chars)
    // 'CONTACT_YOUR_ENTRY' => 'Votre fiche',

    // templates/notify-email-text.twig (no special chars)
    // 'CONTACT_HELP_IN_NOTIFICATION' => 'allez sur le site pour gérer votre inscription',

    // templates/notify-newuser-email-subject.twig (no special chars)
    // 'CONTACT_NEW_USER_SUBJECT' => 'Vos nouveaux identifiants sur le site',


    // templates/notify-newuser-email-text.twig (no special chars)
    'CONTACT_NEW_USER_MESSAGE' => "Hello!\n\n".
        "Your subscription on the website is finished, nw you can sign-in with following information :\n\n".
        "Url : {{ baseUrl }}\n\n".
        "Your ID WikiName : {{ wikiName }}\n\n".
        "Your email : {{ email }}\n\n".
        "Your password : (the password you have choosen)\n\n".
        "To reinitiate your password : {{ urlForPasswordRenewal }}\n\n".
        "See you soon ! ",

    // action-builder Contact
    // 'AB_contact_group_label' => "Actions d'envoi d'e-mail/listes",
    // 'AB_abonnement_action_label' => "S'abonner à une liste de diffusion",
    // 'AB_abonnement_template_label' => "template",
    // 'AB_abonnement_class_label' => "classe",
    // 'AB_abonnement_mailinglist_label' => "Liste de diffusion",
    // 'AB_deabonnement_action_label' => "Se désabonner d'une liste de diffusion",
    // 'AB_contact_action_label' => "Afficher un formulaire de contact",
    // 'AB_contact_action_mail_label' => "E-mail du destinataire",
    // 'AB_contact_action_entete_label' => "En-tête",
    // 'AB_contact_action_template_label' => "Template personnalisé",
    // 'AB_contact_action_template_hint' => "Ex. : complete-contact-form.tpl.html",
    // 'AB_contact_action_class_label' => "classe css",
    // 'AB_listsubscription_action_label' => "listsubscription",
    // 'AB_mailperiod_action_label' => "S'abonner pour recevoir périodiquement le contenu d'une page par email",
    // 'AB_mailperiod_action_hint' => "Pour que cette aciton fonctionne vous devez vérifier certains paramètres sur votre serveur. Voir la documentation sur https://yeswiki.net/?MailPeriod",
    // 'AB_mailinglist_action_label' => "Inscrire massivement des mails à une newsletter",
    // 'AB_mailinglist_action_description' => "Action permettant d'inscrire ou désinscrire massivement des mails à une newsletter",


    // for edit config
    // 'EDIT_CONFIG_HINT_CONTACT_USE_LONG_WIKI_URLS_IN_EMAILS' => "Ajouter 'wiki=' aux liens vers ce wiki dans les e-mails",
    // 'EDIT_CONFIG_GROUP_CONTACT' => 'Envoi des e-mails',

];
