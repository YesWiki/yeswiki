<?php

return [
    'HASHCASH_ERROR_PAGE_UNSAVED' => '<strong>La page ne peut pas &ecirc;tre enregistr&eacute;e.</strong><br />Vous avez peut-&ecirc;tre double cliqu&eacute; sur le bouton "Sauver", entrainant 2 sauvegardes cons&eacute;cutives trop rapproch&eacute;es, ou laiss&eacute; la page ouverte en mode &eacute;dition trop longtemps.<br />Pour enregistrer vos modifications, veuillez copier le contenu, rafraichir la page et coller votre page modifi&eacute;e &agrave; nouveau.',
    'HASHCASH_ANTISPAM_ACTIVATED' => 'Protection anti-spam active',
    'HASHCASH_COMMENT_NOT_SAVED_MAYBE_YOU_ARE_A_ROBOT' => 'Votre commentaire n\'a pas &eacute;t&eacute; enregistr&eacute;, le wiki pense que vous êtes un robot.',
    'HASHCASH_GENERAL_PASSWORD' => 'Réponse&nbsp;:&nbsp;',
    'HASHCASH_SEND' => 'Envoyer',
    'CAPTCHA_ERROR_PAGE_UNSAVED' => 'La page n\'a pas été enregistrée car vous n\'avez pas rentré le mot de vérification.',
    'CAPTCHA_ERROR_WRONG_WORD' => 'La page n\'a pas été enregistrée car le mot de vérification rentré n\'est pas correct.',
    'CAPTCHA_VERIFICATION' => 'V&eacute;rification pour sauver la page',
    'CAPTCHA_WRITE' => 'Ecrire ici le mot présent dans l\'image',

    // actions/despam.php
    'DESPAM_PAGES_SELECTION' => 'S&eacute;lection des pages',
    'DESPAM_ALL_CHANGES_FROM' => 'Toutes les modifications depuis',
    'DESPAM_FOR_ONE_HOUR' => 'depuis 1 heure',
    'DESPAM_FOR_X_HOURS' => 'depuis {x} heures',
    'DESPAM_FOR_ONE_WEEK' => 'depuis 1 semaine',
    'DESPAM_FOR_TWO_WEEKS' => 'depuis 2 semaines',
    'DESPAM_FOR_ONE_MONTH' => 'depuis 1 mois',
    'DESPAM_VALIDATE' => 'Valider',
    'DESPAM_CLEAN_SPAMMED_PAGES' => 'Nettoyage des pages vandalisées depuis {x} heure(s)',
    'DESPAM_RESTORE_FROM' => 'Restauration depuis la version du {time} par {user}',
    'DESPAM_RESTORED_PAGES' => 'Pages restaurées',
    'DESPAM_DELETED_PAGES' => 'Pages supprimées',
    'DESPAM_BACK_TO_PREVIOUS_FORM' => 'Retour au formulaire de départ',
    'DESPAM_ONLY_FOR_ADMINS' => 'Action {{despam}} réservée aux administrateurs.',

    // for edit config
    'EDIT_CONFIG_HINT_USE_CAPTCHA' => 'Activer l\'utilisation d\'un captcha avant la sauvegarde (true ou false)',
    'EDIT_CONFIG_HINT_USE_HASHCASH' => 'Activer l\'antispam hashcash du wiki (activé par défaut)',
    'EDIT_CONFIG_HINT_USE_ALERTE' => 'Prévenir si l\'on quitte la page sans sauvegarder (true ou false)',
    'EDIT_CONFIG_HINT_WIKI_STATUS' => 'État du wiki (running ou vide = standard, hibernate = lecture seule)',
    'EDIT_CONFIG_GROUP_SECURITY' => 'Sécurité',
    'EDIT_CONFIG_HINT_ARCHIVE[PRIVATEPATH]' => 'Localisation des sauvegardes (\'%TMP\' = dossier des fichiers temporaires du système)',
    'EDIT_CONFIG_HINT_ARCHIVE[CALL_ARCHIVE_ASYNC]' => 'Lancer les opérations de sauvegardes en arrière-plan (true/false)',
    'EDIT_CONFIG_HINT_ARCHIVE[MAX_NB_FILES]' => 'Nombre maximum de fichiers de sauvegarde à conserver (min. 3)',
    'EDIT_CONFIG_HINT_ARCHIVE[PREUPDATE_BACKUP_ACTIVATED]' => 'Faire une sauvegarde avant chaque mise à jour (true ou false)',

    // security controller
    'WIKI_IN_HIBERNATION' => 'Action désactivée car ce wiki est en lecture seule. Veuillez contacter l\'administrateur pour le réactiver.',
];
