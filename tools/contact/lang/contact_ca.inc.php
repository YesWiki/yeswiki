<?php
/*vim: set expandtab tabstop=4 shiftwidth=4: */
// +------------------------------------------------------------------------------------------------------+
// | PHP versió 5                                                                                         |
// +------------------------------------------------------------------------------------------------------+
// | Copyright (C) 2012 Outils-Réseaux (accueil@outils-reseaux.org)                                       |
// +------------------------------------------------------------------------------------------------------+
// | Aquesta llibreria és de programari lliure; podeu redistribuir-la i/o                                 |
// | modificar-la d'acord amb els termes del GNU Lesser General Public                                    |
// | License tal com ha estat publicada per la Free Software Foundation; sigui la                         |
// | versió 2.1 de la llicència o bé (opcionalment) qualsevol versió posterior.                           |
// |                                                                                                      |
// | Aquesta llibreria és distribuïda amb l'ànim que sigui útil                                           |
// | però SENSE CAP GARANTIA; fins i tot sense la garantia implícita de                                   |
// | MERCHANTABILITY o de FITNESS FOR A PARTICULAR PURPOSE. Vegeu la GNU                                  |
// | Lesser General Public License per a més detalls.                                                     |
// |                                                                                                      |
// | Amb aquesta llibreria heu d'haver rebut còpia de la GNU Lesser General Public                        |
// | License; altrament, escriviu a la Free Software Foundation,                                          |
// | Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA                                        |
// +------------------------------------------------------------------------------------------------------+
//
/**
* Fitxer de traducció al català de l'extensió Contact
*
*@package 		contact
*@author        Jordi Picart <jordi.picart@aposta.coop>
*@copyright     2014 Outils-Réseaux
*/

return [

    // actions/abonnement.php
    'CONTACT_ACTION_ABONNEMENT' => 'Acció {{abonnement ...}}',
    'CONTACT_MAIL_REQUIRED' => 'L\'adreça email de contacte és obligatòria.',

    // actions/contact.php
    'CONTACT_ACTION_CONTACT' => 'Acció {{contact ...}}',

    // actions/desabonnement.php
    'CONTACT_ACTION_DESABONNEMENT' => 'Acció {{desabonnement ...}}',

    // actions/listsubscription.php
    'CONTACT_ACTION_LISTSUBSCRIPTION' => 'Acció {{listsubscription ...}}',

    // actions/mailinglist.php
    'CONTACT_ACTION_MAILINGLIST' => 'Acció {{mailinglist ...}}',
    'CONTACT_PARAMETER_LIST_REQUIRED' => 'El paràmetre "list" és obligatori (és l\'adreça email de la llista de difusió)',
    'CONTACT_MAILS_TO_ADD_OR_REMOVE' => 'Correus que cal afegir o suprimir de la llista',
    'CONTACT_SENT_TO_THE_LIST' => 'Enviat a la llista',
    'CONTACT_THE_EMAIL' => 'l\'email',
    'CONTACT_SUBMIT_OTHER_EMAILS' => 'Afegiu altres emails',
    'CONTACT_OK' => 'D\'acord',
    'CONTACT_BTN_SUBSCRIBE' => 'Registrar',
    'CONTACT_BTN_UNSUBSCRIBE' => 'Donar de baixa',
    'CONTACT_FOR_ALL_THOSE_EMAILS' => 'Per totes aquestes adreces de correu',
    'CONTACT_TRY_WITH_OTHER_EMAILS' => 'Intentar amb altres adreces al text',
    'CONTACT_NO_EMAILS_FOUND_IN_THIS_TEXT' => 'No s\'han trobat adreces al text',
    'CONTACT_ENTER_TEXT_WITH_EMAILS_INSIDE' => 'Escriviu un text que contingui adreces per extreure-les, els separadors són indiferents (comes, punt-i-comes, dos punts, espais, tabulacions, salts de línia)',
    'CONTACT_YOUR_EMAIL_LIST' => 'La vostra llista d\'adreces',
    'CONTACT_EXTRACT_EMAILS_FROM_TEXT' => 'Extreu els mails d\'aquest text',
    'CONTACT_MUST_BE_ADMIN_TO_USE_THIS_ACTION' => 'necessiteu permisos d\'administrador per completar aquesta acció',


    'CONTACT_YOUR_NAME' => 'El vostre nom',
    'CONTACT_YOUR_MAIL' => 'La vostra adreça de correu',
    'CONTACT_SUBJECT' => 'Tema del missatge',
    'CONTACT_YOUR_MESSAGE' => 'El vostre missatge',
    'CONTACT_SEND_MESSAGE' => 'Envia',
    'CONTACT_LISTSUBSCRIBE_REQUIRED' => 'el paràmetre "list", que conté l\'adreça electrònica de la llista, del tipus nomllista@domini.ext, és obligatori',
    'CONTACT_USER_NOT_LOGGED_IN' => 'Cal estar identificat.',
    'CONTACT_USER_NO_EMAIL' => 'cal estar identificat.',

    'CONTACT_ENTER_NAME' => 'Escriviu un nom.',
    'CONTACT_ENTER_SENDER_MAIL' => 'Escriviu una adreça electrònica del remitent.',
    'CONTACT_SENDER_MAIL_INVALID' => 'L\'adreça de correu del remitent no és vàlida.',
    'CONTACT_ENTER_RECEIVER_MAIL' => 'Escriviu una adreça electrònica de destinació.',
    'CONTACT_RECEIVER_MAIL_INVALID' => 'L\'adreça de correu del destinatari no és vàlida.',
    'CONTACT_ENTER_MESSAGE' => 'Escriviu el cos del missatge; calen 10 caràcters com a mínim.',

    'CONTACT_MESSAGE_SUCCESSFULLY_SENT' => 'El vostre missatge s\'ha enviat amb èxit',
    'CONTACT_MESSAGE_NOT_SENT' => 'El missatge no s\'ha pogut enviar... potser és un problema de la configuració del servidor?',
    'CONTACT_SUBSCRIBE_ORDER_SENT' => 'La petició de registre ha estat enviada. Moltes gràcies!',
    'CONTACT_UNSUBSCRIBE_ORDER_SENT' => 'La petició de baixa ha estat enviada. Moltes gràcies!',

    'CONTACT_HANDLER_MAIL_FOR_ADMINS' => 'El gestor de correu està reservat als administradors.',
    'CONTACT_LOGIN_IF_ADMIN' => 'Si sou membre d\'aquest grup, identifiqueu-vos sisplau.',

    'CONTACT_SUBSCRIBE' => 'Registrar-se',
    'CONTACT_UNSUBSCRIBE' => 'Donar-se de baixa',

    'CONTACT_FROM' => 'De',
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
    // 'AB_abonnement_action_mail_label' => "E-mail de la liste de discussion",
    // 'AB_abonnement_action_label' => "S'abonner à une liste de discussion",
    // 'AB_abonnement_template_label' => "template",
    // 'AB_abonnement_class_label' => "classe",
    // 'AB_abonnement_mailinglist_label' => "Liste de discussion",
    // 'AB_deabonnement_action_label' => "Se désabonner d'une liste de diffusion",
    // 'AB_contact_action_label' => "Afficher un formulaire de contact",
    // 'AB_contact_action_mail_label' => "E-mail du destinataire",
    // 'AB_contact_action_entete_label' => "Préfixe automatique de l'objet du mail envoyé depuis le formulaire de contact",
    // 'AB_contact_action_entete_default' => "Envoyé depuis le site...",
    // 'AB_contact_action_template_label' => "Template personnalisé",
    // 'AB_contact_action_template_hint' => "Ex. : complete-contact-form.tpl.html",
    // 'AB_contact_action_class_label' => "classe css",
    // 'AB_listsubscription_action_label' => "listsubscription",
    // 'AB_mailperiod_action_label' => "S'abonner pour recevoir périodiquement le contenu d'une page par email",
    // 'AB_mailperiod_action_hint' => "Pour que cette action fonctionne vous devez vérifier certains paramètres sur votre serveur. Voir la documentation sur https://yeswiki.net/?MailPeriod",
    // 'AB_mailinglist_action_label' => "Inscrire massivement des mails à une newsletter",
    // 'AB_mailinglist_action_description' => "Action permettant d'inscrire ou désinscrire massivement des mails à une newsletter",


    // for edit config
    // 'EDIT_CONFIG_HINT_CONTACT_USE_LONG_WIKI_URLS_IN_EMAILS' => "Ajouter 'wiki=' aux liens vers ce wiki dans les e-mails",
    // 'EDIT_CONFIG_GROUP_CONTACT' => 'Envoi des e-mails',

];
