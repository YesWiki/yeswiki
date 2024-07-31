<?php

return [
    // actions/abonnement.php
    'CONTACT_ACTION_ABONNEMENT' => 'Action {{abonnement ...}}',
    'CONTACT_MAIL_REQUIRED' => 'le param&egrave;tre mail, obligatoire, est manquant.',

    // actions/contact.php
    'CONTACT_ACTION_CONTACT' => 'Action {{contact ...}}',

    // actions/desabonnement.php
    'CONTACT_ACTION_DESABONNEMENT' => 'Action {{desabonnement ...}}',

    // actions/listsubscription.php
    'CONTACT_ACTION_LISTSUBSCRIPTION' => 'Action {{listsubscription ...}}',

    // actions/mailinglist.php
    'CONTACT_ACTION_MAILINGLIST' => 'Action {{mailinglist ...}}',
    'CONTACT_PARAMETER_LIST_REQUIRED' => 'param&egrave;tre "list" obligatoire (il s\'agit de l\'adresse mail de la liste de diffusion)',
    'CONTACT_MAILS_TO_ADD_OR_REMOVE' => 'Mails &agrave; ajouter ou &agrave; supprimer de la liste',
    'CONTACT_SENT_TO_THE_LIST' => 'Envoi &agrave; la liste',
    'CONTACT_THE_EMAIL' => 'l\'email',
    'CONTACT_SUBMIT_OTHER_EMAILS' => 'Entrer d\'autres mails',
    'CONTACT_OK' => 'OK',
    'CONTACT_BTN_SUBSCRIBE' => 'Abonner',
    'CONTACT_BTN_UNSUBSCRIBE' => 'D&eacute;sabonner',
    'CONTACT_FOR_ALL_THOSE_EMAILS' => 'Pour toutes ces adresses mails',
    'CONTACT_TRY_WITH_OTHER_EMAILS' => 'Essayer avec d\'autres mails dans le texte',
    'CONTACT_NO_EMAILS_FOUND_IN_THIS_TEXT' => 'Pas d\'adresses mails trouv&eacute;es dans le texte fourni',
    'CONTACT_ENTER_TEXT_WITH_EMAILS_INSIDE' => 'Entrez un texte contenant des mails dedans pour les extraire, qu\'importe les s&eacute;parateurs (virgules, point-virgules, deux points, espaces, tabulations, retours &agrave; la ligne) ou le texte entre',
    'CONTACT_YOUR_EMAIL_LIST' => 'Votre liste d\'adresses mails',
    'CONTACT_EXTRACT_EMAILS_FROM_TEXT' => 'Extraire les mails de ce texte',
    'CONTACT_MUST_BE_ADMIN_TO_USE_THIS_ACTION' => 'il faut &ecirc;tre dans le groupe admins pour utiliser cette action',

    'CONTACT_YOUR_NAME' => 'Votre nom',
    'CONTACT_YOUR_MAIL' => 'Votre adresse mail',
    'CONTACT_SUBJECT' => 'Sujet du message',
    'CONTACT_YOUR_MESSAGE' => 'Votre message',
    'CONTACT_SEND_MESSAGE' => 'Envoyer le message',
    'CONTACT_LISTSUBSCRIBE_REQUIRED' => 'le param&egrave;tre "list", contenant l\'adresse &eacute;lectronique de la liste, du type nomliste@domaine.ext, est obligatoire',
    'CONTACT_USER_NOT_LOGGED_IN' => 'il faut &ecirc;tre identifi&eacute; pour acc&eacute;der aux actions possibles.',
    'CONTACT_USER_NO_EMAIL' => 'il faut &ecirc;tre identifi&eacute; pour acc&eacute;der aux actions possibles.',

    'CONTACT_ENTER_NAME' => 'Vous devez entrer un nom.',
    'CONTACT_ENTER_SENDER_MAIL' => 'Vous devez entrer une adresse mail pour l\'exp&eacute;diteur.',
    'CONTACT_SENDER_MAIL_INVALID' => 'Vous devez entrer une adresse mail valide pour l\'exp&eacute;diteur.',
    'CONTACT_ENTER_RECEIVER_MAIL' => 'Vous devez entrer une adresse mail pour le destinataire.',
    'CONTACT_RECEIVER_MAIL_INVALID' => 'Vous devez entrer une adresse mail valide pour le destinataire.',
    'CONTACT_ENTER_MESSAGE' => 'Veuillez entrer un message. Il doit faire au minimum 10 caract&egrave;res.',

    'CONTACT_MESSAGE_SUCCESSFULLY_SENT' => 'Votre message a bien &eacute;t&eacute; envoy&eacute;. Merci!',
    'CONTACT_MESSAGE_NOT_SENT' => 'Une erreur s\'est produite lors de l\'envoi du message. Veuillez contacter l\'administrateur du site pour qu\'il puisse régler le problème.',
    'CONTACT_SUBSCRIBE_ORDER_SENT' => 'Votre demande concernant votre abonnement a bien &eacute;t&eacute; prise en compte. Merci!',
    'CONTACT_UNSUBSCRIBE_ORDER_SENT' => 'Votre demande concernant votre d&eacute;sabonnement a bien &eacute;t&eacute; prise en compte. Merci!',

    'CONTACT_HANDLER_MAIL_FOR_CONNECTED' => 'L\'envoi de mail est r&eacute;serv&eacute; aux personnes identifiées.',
    'CONTACT_LOGIN_IF_CONNECTED' => 'Si vous avez un identifiant, veuillez vous identifier.',

    'CONTACT_SUBSCRIBE' => 'S\'abonner',
    'CONTACT_UNSUBSCRIBE' => 'Se d&eacute;sabonner',

    'CONTACT_FROM' => 'de',
    'CONTACT_TEMPLATE_NOT_FOUND' => 'Fichier de template non trouvé',
    'CONTACT_MESSAGE_SENT_FROM' => 'Message envoyé à partir de',

    'CONTACT_PERIOD' => 'Recevoir le contenu de cette page par email de manière',
    'CONTACT_DAILY' => 'Journalière',
    'CONTACT_WEEKLY' => 'Hebdomadaire',
    'CONTACT_MONTHLY' => 'Mensuelle',
    'CONTACT_UNSUBSCRIBE' => 'Se désabonner',
    'CONTACT_SUCCESS_SUBSCRIBE' => 'Vous êtes maintenant abonné de manière ',
    'CONTACT_SUCCESS_UNSUBSCRIBE' => 'Vous avez bien été désabonné',

    'CONTACT_THIS_MESSAGE' => 'Ce message est envoyé par un visiteur depuis la fiche',
    'CONTACT_FROM_FORM' => 'du formulaire',
    'CONTACT_FROM_WEBSITE' => 'du site',
    'CONTACT_REPLY' => 'Vous pouvez lui écrire un message à',
    'CONTACT_REPLY2' => 'en répondant à ce mail',

    // handlers/page/mail.php
    'CONTACT_TO_PLACEHOLDER' => 'Adresse mail du destinataire',

    // handlers/page/sendmail.php
    'CONTACT_SENDMAIL_INFO' => 'On envoie les mails pour la période',
    'CONTACT_SENDMAIL_ERROR' => 'La période n\'a pas été renseignée ou n\'a pas de valeur standard (month, week ou day).',

    // libs/contact.functions.js
    'CONTACT_DAILY_REPORT' => 'rapport journalier du',
    'CONTACT_WEEKLY_REPORT' => 'rapport hebdomadaire du',
    'CONTACT_MONTHLY_REPORT' => 'rapport mensuel du',

    // templates/notify-admins-email-subject.twig (no special chars)
    'CONTACT_ENTRY_ADDED' => 'nouvelle fiche ajoutee',
    'CONTACT_ENTRY_CHANGED' => 'fiche modifiee',
    'CONTACT_IN_FORM' => 'dans le formulaire',

    // templates/notify-admins-list-deleted-email-subject.twig (no special chars)
    'CONTACT_DELETED_LIST' => 'liste supprimee',

    // templates/notify-admins-list-deleted-email-text.twig (no special chars)
    'CONTACT_USED_IP' => 'IP utilisee',

    // templates/notify-email-html.twig and templates/notify-email-text.twig (no special chars)
    'CONTACT_WELCOME_ON' => 'Bienvenue sur',

    // templates/notify-email-subject.twig (no special chars)
    'CONTACT_YOUR_ENTRY' => 'Votre fiche',

    // templates/notify-email-text.twig (no special chars)
    'CONTACT_HELP_IN_NOTIFICATION' => 'allez sur le site pour gérer votre inscription',

    // templates/notify-newuser-email-subject.twig (no special chars)
    'CONTACT_NEW_USER_SUBJECT' => 'Vos nouveaux identifiants sur le site',

    // templates/notify-newuser-email-text.twig (no special chars)
    'CONTACT_NEW_USER_MESSAGE' => "Bonjour!\n\n" .
        "Votre inscription sur le site a ete finalisee, dorenavant vous pouvez vous identifier avec les informations suivantes :\n\n" .
        "Url : {{ baseUrl }}\n\n" .
        "Votre identifiant NomWiki : {{ wikiName }}\n\n" .
        "Votre email : {{ email }}\n\n" .
        "Votre mot de passe : (le mot de passe que vous avez choisi)\n\n" .
        "Pour reinitialiser votre mot de passe : {{ urlForPasswordRenewal }}\n\n" .
        'A tres bientot ! ',

    // action-builder Contact
    'AB_contact_group_label' => "Actions d'envoi d'e-mail/listes",
    'AB_abonnement_action_mail_label' => 'E-mail de la liste de discussion',
    'AB_abonnement_action_label' => "S'abonner à une liste de discussion",
    'AB_abonnement_template_label' => 'template',
    'AB_abonnement_class_label' => 'classe',
    'AB_abonnement_mailinglist_label' => 'Liste de discussion',
    'AB_deabonnement_action_label' => "Se désabonner d'une liste de discussion",
    'AB_contact_action_label' => 'Afficher un formulaire de contact',
    'AB_contact_action_mail_label' => 'E-mail du destinataire',
    'AB_contact_action_entete_label' => "Préfixe automatique de l'objet du mail",
    'AB_contact_action_entete_default' => 'Envoyé depuis le site...',
    'AB_contact_action_template_label' => 'Template personnalisé',
    'AB_contact_action_template_hint' => 'Ex. : complete-contact-form.tpl.html',
    'AB_contact_action_class_label' => 'classe css',
    'AB_listsubscription_action_label' => 'listsubscription',
    'AB_mailperiod_action_label' => "S'abonner pour recevoir périodiquement le contenu d'une page par email",
    'AB_mailperiod_action_hint' => 'Pour que cette action fonctionne vous devez vérifier certains paramètres sur votre serveur. Voir la documentation sur https://yeswiki.net/?MailPeriod',
    'AB_mailinglist_action_label' => 'Inscrire massivement des mails à une newsletter',
    'AB_mailinglist_action_description' => "Action permettant d'inscrire ou désinscrire massivement des mails à une newsletter",

    // for edit config
    'EDIT_CONFIG_HINT_CONTACT_USE_LONG_WIKI_URLS_IN_EMAILS' => "Ajouter 'wiki=' aux liens vers ce wiki dans les e-mails",
    'EDIT_CONFIG_HINT_CONTACT_MAIL_FUNC' => 'Mode d\'envoi des mails ("smtp" ou "mail")',
    'EDIT_CONFIG_HINT_CONTACT_SMTP_HOST' => 'Serveur SMTP (ex: "smtp.mondomaine.ext")',
    'EDIT_CONFIG_HINT_CONTACT_SMTP_PORT' => 'Port SMTP (généralement 465 ou 587)',
    'EDIT_CONFIG_HINT_CONTACT_SMTP_USER' => 'Utilisateur SMTP (souvent le mail)',
    'EDIT_CONFIG_HINT_CONTACT_SMTP_PASS' => 'Mot de passe SMTP',
    'EDIT_CONFIG_HINT_CONTACT_FROM' => 'Utilisateur indiqué comme émetteur du mail (pour éviter les spams doit être le même que l\'utilisateur smtp)',
    'EDIT_CONFIG_HINT_CONTACT_REPLY_TO' => 'Utilisateur auquel la réponse mail sera envoyée',
    'EDIT_CONFIG_HINT_CONTACT_DEBUG' => 'Mode verbeux pour débugguer (mettre 2 pour avoir des informations)',
    'EDIT_CONFIG_GROUP_CONTACT' => 'Envoi des e-mails',
    'EDIT_CONFIG_HINT_CONTACT_DISABLE_EMAIL_FOR_PASSWORD' => 'Désactiver l\'envoie d\'email pour ré-initaliser un mot de passe (ex: LDAP, SSO)',
];
