<?php

return [

    'HASHCASH_ERROR_PAGE_UNSAVED' => '<strong>This page could not be saved.</strong><br />You may have clicked two times on the "Save" button, making two saved in an interval consired as too short, or leaved the page opened in edit mode for a too long period.<br />To save this page, please copy your content, refresh your browser, and paste the content before saving again.',
    'HASHCASH_ANTISPAM_ACTIVATED' => 'Antispam protection activated',
    'HASHCASH_COMMENT_NOT_SAVED_MAYBE_YOU_ARE_A_ROBOT' => 'Your comment was not saved, maybe you are a robot.',
    'HASHCASH_GENERAL_PASSWORD' => 'Global password for editing',
    'HASHCASH_SEND' => 'Send',
    'CAPTCHA_ERROR_PAGE_UNSAVED' => 'This page was not saved because you didn\'t enter the verification word.',
    'CAPTCHA_ERROR_WRONG_WORD' => 'This page was not saved because the verification word was wrong...',
    'CAPTCHA_VERIFICATION' => 'V&eacute;rification for saving page',
    'CAPTCHA_WRITE' => 'Type the word written in the picture',

    // actions/despam.php
    // 'DESPAM_PAGES_SELECTION' => 'S&eacute;lection des pages',
    // 'DESPAM_ALL_CHANGES_FROM' => 'Toutes les modifications depuis',
    // 'DESPAM_FOR_ONE_HOUR' => 'depuis 1 heure',
    // 'DESPAM_FOR_X_HOURS' => 'depuis {x} heures',
    // 'DESPAM_FOR_ONE_WEEK' => 'depuis 1 semaine',
    // 'DESPAM_FOR_TWO_WEEKS' => 'depuis 2 semaines',
    // 'DESPAM_FOR_ONE_MONTH' => 'depuis 1 mois',
    // 'DESPAM_VALIDATE' => 'Valider',
    // 'DESPAM_CLEAN_SPAMMED_PAGES' => 'Nettoyage des pages vandalisées depuis {x} heure(s)',
    // 'DESPAM_RESTORE_FROM' => 'Restauration depuis la version du {time} par {user}',
    // 'DESPAM_RESTORED_PAGES' => 'Pages restaurées',
    // 'DESPAM_DELETED_PAGES' => 'Pages supprimées',
    // 'DESPAM_BACK_TO_PREVIOUS_FORM' => 'Retour au formulaire de départ',
    // 'DESPAM_ONLY_FOR_ADMINS' => 'Action {{despam}} réservée aux administrateurs.',

    // for edit config
    'EDIT_CONFIG_HINT_USE_CAPTCHA' => 'Activate usage of captcha before save (true or false)',
    'EDIT_CONFIG_HINT_USE_HASHCASH' => 'Activate wiki antispam hashcash (activated by default)',
    'EDIT_CONFIG_HINT_USE_ALERTE' => 'Warn before leaving a page without saving (true or false)',
    'EDIT_CONFIG_HINT_WIKI_STATUS' => 'Wiki status (running or empty = standard, hibernate = read only)',
    'EDIT_CONFIG_GROUP_SECURITY' => 'Security',
    'EDIT_CONFIG_HINT_ARCHIVE[PRIVATEPATH]' => 'Backups\'folder path (\'%TMP\' = system temporary folder)',
    'EDIT_CONFIG_HINT_ARCHIVE[CALL_ARCHIVE_ASYNC]' => 'Maximum number of backup\'s files to keep (min. 3)',
    'EDIT_CONFIG_HINT_ARCHIVE[PREUPDATE_BACKUP_ACTIVATED]' => 'Make a backup before each update (true or false)',

    // security controller
    // 'WIKI_IN_HIBERNATION' => 'Action désactivée car ce wiki est en lecture seule. Veuillez contacter l\'administrateur pour le réactiver.',
];
