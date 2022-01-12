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
* Fichier de traduction en francais de l'extension Contact
*
*@package 		contact
*@author        Florian Schmitt <florian@outils-reseaux.org>
*@copyright     2012 Outils-Réseaux
*/

return [

    // actions/abonnement.php
    'CONTACT_ACTION_ABONNEMENT' => 'Actie {{inschrijven...}}',
    'CONTACT_MAIL_REQUIRED' => 'de verplichte parameter e-mail ontbreekt.',

    // actions/contact.php
    'CONTACT_ACTION_CONTACT' => 'Actie {{contact ...}}',

    // actions/desabonnement.php
    'CONTACT_ACTION_DESABONNEMENT' => 'Actie {{uitschrijven...}}',

    // actions/listsubscription.php
    'CONTACT_ACTION_LISTSUBSCRIPTION' => 'Actie {{listsubscription ...}}',

    // actions/mailinglist.php
    'CONTACT_ACTION_MAILINGLIST' => 'Actie {{mailinglist ...}}',
    'CONTACT_PARAMETER_LIST_REQUIRED' => 'parameter "list" is verplicht (dit is het e-mailadres van de mailinglist)',
    'CONTACT_MAILS_TO_ADD_OR_REMOVE' => 'Mails toevoegen aan of wissen van de lijst',
    'CONTACT_SENT_TO_THE_LIST' => 'Verzenden naar de lijst',
    'CONTACT_THE_EMAIL' => 'E-mail',
    'CONTACT_SUBMIT_OTHER_EMAILS' => 'Andere mails invoeren',
    'CONTACT_OK' => 'OK',
    'CONTACT_BTN_SUBSCRIBE' => 'Inschrijven',
    'CONTACT_BTN_UNSUBSCRIBE' => 'Uitschrijven',
    'CONTACT_FOR_ALL_THOSE_EMAILS' => 'Voor al deze e-mailadressen',
    'CONTACT_TRY_WITH_OTHER_EMAILS' => 'Proberen met andere e-mails in de tekst',
    'CONTACT_NO_EMAILS_FOUND_IN_THIS_TEXT' => 'Geen e-mailadressen gevonden in de aangeleverde tekst',
    'CONTACT_ENTER_TEXT_WITH_EMAILS_INSIDE' => 'Voer een tekst met e-mailadressen in om de e-mailadressen te extraheren. De scheidingstekens (komma, puntkomma, dubbele punt, spatie, tab, enter) en de ingevoerde tekst doen weinig ter zake.',
    'CONTACT_YOUR_EMAIL_LIST' => 'Uw lijst met e-mailadressen',
    'CONTACT_EXTRACT_EMAILS_FROM_TEXT' => 'De e-mailadressen uit deze tekst extraheren',
    'CONTACT_MUST_BE_ADMIN_TO_USE_THIS_ACTION' => 'U moet deel uitmaken van de beheerdersgroep om deze actie te gebruiken',


    'CONTACT_YOUR_NAME' => 'Uw naam',
    'CONTACT_YOUR_MAIL' => 'Uw e-mailadres',
    'CONTACT_SUBJECT' => 'Onderwerp van het bericht',
    'CONTACT_YOUR_MESSAGE' => 'Uw bericht',
    'CONTACT_SEND_MESSAGE' => 'Het bericht verzenden',
    'CONTACT_LISTSUBSCRIBE_REQUIRED' => 'de parameter "list", met het e-mailadres van de lijst (type lijstnaam@domein.ext), is verplicht',
    'CONTACT_USER_NOT_LOGGED_IN' => 'u moet aangemeld zijn om toegang te hebben tot de mogelijke acties.',
    'CONTACT_USER_NO_EMAIL' => 'u moet aangemeld zijn om toegang te hebben tot de mogelijke acties.',

    'CONTACT_ENTER_NAME' => 'U dient een naam in te voeren.',
    'CONTACT_ENTER_SENDER_MAIL' => 'U dient een e-mailadres voor de verzender in te voeren.',
    'CONTACT_SENDER_MAIL_INVALID' => 'U dient een geldig e-mailadres voor de verzender in te voeren.',
    'CONTACT_ENTER_RECEIVER_MAIL' => 'U dient een e-mailadres voor de bestemmeling in te voeren.',
    'CONTACT_RECEIVER_MAIL_INVALID' => 'U dient een geldig e-mailadres voor de bestemmeling in te voeren.',
    'CONTACT_ENTER_MESSAGE' => 'Gelieve een bericht in te voeren. Dat moet minimaal 10 karakters lang zijn.',

    'CONTACT_MESSAGE_SUCCESSFULLY_SENT' => 'Uw bericht werd goed verzonden. Bedankt!',
    'CONTACT_MESSAGE_NOT_SENT' => 'Het bericht kon niet worden verzonden... Probleem met de serverconfiguratie?',
    'CONTACT_SUBSCRIBE_ORDER_SENT' => 'We hebben uw vraag tot inschrijving goed ontvangen. Bedankt!',
    'CONTACT_UNSUBSCRIBE_ORDER_SENT' => 'We hebben uw vraag tot uitschrijving goed ontvangen. Bedankt!',

    'CONTACT_HANDLER_MAIL_FOR_ADMINS' => 'De mail handler is voorbehouden aan de beheerdersgroep.',
    'CONTACT_LOGIN_IF_ADMIN' => 'Meld u aan als u deel uitmaakt van deze groep.',

    'CONTACT_SUBSCRIBE' => 'Inschrijven',
    'CONTACT_UNSUBSCRIBE' => 'Uitschrijven',

    'CONTACT_FROM' => 'van',
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
    // 'CONTACT_NEW_USER_MESSAGE' => "Bonjour!\n\n".
        // "Votre inscription sur le site a ete finalisee, dorenavant vous pouvez vous identifier avec les informations suivantes :\n\n".
        // "Url : {{ baseUrl }}\n\n".
        // "Votre identifiant NomWiki : {{ wikiName }}\n\n".
        // "Votre email : {{ email }}\n\n".
        // "Votre mot de passe : (le mot de passe que vous avez choisi)\n\n".
        // "Pour reinitialiser votre mot de passe : {{ urlForPasswordRenewal }}\n\n".
        // "A tres bientot ! ",

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
