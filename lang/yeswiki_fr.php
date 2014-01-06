<?php

// wakka.php
$GLOBALS['translations']['UNKNOWN_ACTION'] = 'Action inconnue';
$GLOBALS['translations']['INVALID_ACTION'] = 'Action invalide';
$GLOBALS['translations']['ERROR_NO_ACCESS'] = 'Erreur: vous n\'avez pas acc&egrave;s &agrave; l\'action';
$GLOBALS['translations']['INCORRECT_CLASS'] = 'classe incorrecte';
$GLOBALS['translations']['UNKNOWN_METHOD'] = 'M&eacute;thode inconnue';
$GLOBALS['translations']['FORMATTER_NOT_FOUND'] = 'Impossible de trouver le formateur';
$GLOBALS['translations']['HANDLER_NO_ACCESS'] = 'Vous ne pouvez pas acc&eacute;der &agrave; cette page par le handler sp&eacute;cifi&eacute;.';
$GLOBALS['translations']['NO_REQUEST_FOUND'] = '$_REQUEST[] pas trouv&eacute;. Wakka n&eacute;cessite PHP 4.1.0 ou plus r&eacute;cent!';
$GLOBALS['translations']['SITE_BEING_UPDATED'] = 'Ce site est en cours de mise &agrave; jour. Veuillez essayer plus tard.';
$GLOBALS['translations']['INVALID_ACTION'] = 'Action invalide';
$GLOBALS['translations']['INCORRECT_PAGENAME'] = 'Le nom de la page est incorrect.';
$GLOBALS['translations']['DB_CONNECT_FAIL'] = 'Pour des raisons ind&eacute;pendantes de notre volont&eacute;, le contenu de ce YesWiki est temporairement inaccessible. Veuillez r&eacute;essayer ult&eacute;rieurement, merci de votre compr&eacute;hension.';
$GLOBALS['translations']['LOG_DB_CONNECT_FAIL'] = 'YesWiki : la connexion BDD a echouee'; // sans accents car commande systeme
$GLOBALS['translations']['INCORRECT_PAGENAME'] = 'Le nom de la page est incorrect.';
$GLOBALS['translations']['MY_YESWIKI_SITE'] = 'Mon site YesWiki';

// tools.php
$GLOBALS['translations']['YESWIKI_TOOLS_CONFIG'] = 'Configuration extension(s) de YesWiki';
$GLOBALS['translations']['DISCONNECT'] = 'D&eacute;connexion';
$GLOBALS['translations']['RETURN_TO_EXTENSION_LIST'] = 'Retour &agrave; la liste des extensions actives';
$GLOBALS['translations']['NO_TOOL_AVAILABLE'] = 'Aucun outil n\'est disponible ou actif';
$GLOBALS['translations']['LIST_OF_ACTIVE_TOOLS'] = 'Liste des extensions actives';

// actions/backlinks.php
$GLOBALS['translations']['PAGES_WITH_LINK'] = 'Pages ayant un lien vers';
$GLOBALS['translations']['PAGES_WITH_LINK_TO_CURRENT_PAGE'] = 'Pages ayant un lien vers la page courante';
$GLOBALS['translations']['NO_PAGES_WITH_LINK_TO'] = 'Aucune page n\'a de lien vers';

// actions/changestyle.php ignoree...

// actions/editactionsacls.class.php
$GLOBALS['translations']['ACTION_RIGHTS'] = 'Droits de l\'action';
$GLOBALS['translations']['SEE'] = 'Voir';
$GLOBALS['translations']['ERROR_WHILE_SAVING_ACL'] = 'Une erreur s\'est produite pendant l\'enregistrement de l\'ACL pour l\'action';
$GLOBALS['translations']['ERROR_CODE'] = 'code d\'erreur';
$GLOBALS['translations']['NEW_ACL_FOR_ACTION'] = 'Nouvelle ACL pour l\'action';
$GLOBALS['translations']['NEW_ACL_SUCCESSFULLY_SAVED_FOR_ACTION'] = 'Nouvelle ACL enregistr&eacute;e avec succ&egrave;s pour l\'action';
$GLOBALS['translations']['EDIT_RIGHTS_FOR_ACTION'] = '&Eacute;diter les droits de l\'action';
$GLOBALS['translations']['SAVE'] = 'Enregistrer';

// actions/editgroups.class.php
$GLOBALS['translations']['DEFINITION_OF_THE_GROUP'] = 'D&eacute;finition du groupe';
$GLOBALS['translations']['DEFINE'] = 'D&eacute;finir';
$GLOBALS['translations']['CREATE_NEW_GROUP'] = 'Ou cr&eacute;er un nouveau groupe';
$GLOBALS['translations']['ONLY_ADMINS_CAN_CHANGE_MEMBERS'] = 'Vous ne pouvez pas changer les membres du groupe des administrateurs car vous n\'&ecirc;tes pas administrateur';
$GLOBALS['translations']['YOU_CANNOT_REMOVE_YOURSELF'] = 'Vous ne pouvez pas vous retirer vous-m&ecirc;me du groupe des administrateurs';
$GLOBALS['translations']['ERROR_RECURSIVE_GROUP'] = 'Erreur: vous ne pouvez pas d&eacute;finir un groupe r&eacute;cursivement';
$GLOBALS['translations']['ERROR_WHILE_SAVING_GROUP'] = 'Une erreur s\'est produite pendant l\'enregistrement du groupe';
$GLOBALS['translations']['NEW_ACL_FOR_GROUP'] = 'Nouvelle ACL pour le groupe';
$GLOBALS['translations']['NEW_ACL_SUCCESSFULLY_SAVED_FOR_THE_GROUP'] = 'Nouvelle ACL enr&eacute;gistr&eacute;e avec succ&egrave;s pour le groupe';
$GLOBALS['translations']['EDIT_GROUP'] = '&Eacute;diter le groupe';
$GLOBALS['translations']['ONLY_ALPHANUM_FOR_GROUP_NAME'] = 'Les noms de groupes ne peuvent contenir que des caract&egrave;res alphanum&eacute;riques';

// actions/edithandlersacls.class.php
$GLOBALS['translations']['HANDLER_RIGHTS'] = 'Droits du handler';
$GLOBALS['translations']['ERROR_WHILE_SAVING_HANDLER_ACL'] = 'Une erreur s\'est produite pendant l\'enregistrement de l\'ACL pour le handler';
$GLOBALS['translations']['NEW_ACL_FOR_HANDLER'] = 'Nouvelle ACL pour le handler';
$GLOBALS['translations']['NEW_ACL_SUCCESSFULLY_SAVED_FOR_HANDLER'] = 'Nouvelle ACL enregistr&eacute;e avec succ&egrave;s pour le handler';
$GLOBALS['translations']['EDIT_RIGHTS_FOR_HANDLER'] = '&Eacute;diter les droits du handler';

// actions/erasespamedcomments.class.php ignoree...
// actions/footer.php ignoree, car le tools templates court circuite
// actions/header.php ignoree, car le tools templates court circuite

// actions/include.php
$GLOBALS['translations']['ERROR'] = 'Erreur';
$GLOBALS['translations']['ACTION'] = 'Action';
$GLOBALS['translations']['MISSING_PAGE_PARAMETER'] = 'le param&egrave;tre "page" est manquant';
$GLOBALS['translations']['IMPOSSIBLE_FOR_THIS_PAGE'] = 'Impossible pour la page';
$GLOBALS['translations']['TO_INCLUDE_ITSELF'] = 'de s\'inclure en elle m&ecirc;me';
$GLOBALS['translations']['INCLUSIONS_CHAIN'] = 'Chaine d\'inclusions';
$GLOBALS['translations']['EDITION'] = '&Eacute;dition';
$GLOBALS['translations']['READING_OF_INCLUDED_PAGE'] = 'Lecture de la page inclue';
$GLOBALS['translations']['NOT_ALLOWED'] = 'non autoris&eacute;e';
$GLOBALS['translations']['INCLUDED_PAGE'] = 'La page inclue';
$GLOBALS['translations']['DOESNT_EXIST'] = 'ne semble pas exister';

// actions/listpages.php
$GLOBALS['translations']['THE_PAGE'] = 'La page';
$GLOBALS['translations']['BELONGING_TO'] = 'appartenant &agrave;';
$GLOBALS['translations']['UNKNOWN'] = 'Inconnu';
$GLOBALS['translations']['LAST_CHANGE_BY'] = 'derni&egrave;re modification par';
$GLOBALS['translations']['LAST_CHANGE'] = 'derni&egrave;re modification';
$GLOBALS['translations']['PAGE_LIST_WHERE'] = 'Liste des pages auxquelles';
$GLOBALS['translations']['HAS_PARTICIPATED'] = 'a particip&eacute;';
$GLOBALS['translations']['EXCLUDING_EXCLUSIONS'] = 'hors exclusions';
$GLOBALS['translations']['INCLUDING'] = 'et dont';
$GLOBALS['translations']['IS_THE_OWNER'] = 'est le propri&eacute;taire';
$GLOBALS['translations']['NO_PAGE_FOUND'] = 'Aucune page trouv&eacute;e';
$GLOBALS['translations']['IN_THIS_WIKI'] = 'dans ce wiki';
$GLOBALS['translations']['LIST_PAGES_BELONGING_TO'] = 'Liste des pages appartenant &agrave;';
$GLOBALS['translations']['THIS_USER_HAS_NO_PAGE'] = 'Cet utilisateur ne poss&egrave;de aucune page';
$GLOBALS['translations']['UNKNOWN'] = 'Inconnu';
$GLOBALS['translations']['BY'] = 'par';

// actions/mychanges.php
$GLOBALS['translations']['YOUR_MODIFIED_PAGES_ORDERED_BY_MODIFICATION_DATE'] = 'Liste des pages que vous avez modifi&eacute;es, tri&eacute;e par date de modification';
$GLOBALS['translations']['YOUR_MODIFIED_PAGES_ORDERED_BY_NAME'] = 'Liste des pages que vous avez modifi&eacute;es, tri&eacute;e par ordre alphab&eacute;tique';
$GLOBALS['translations']['YOU_DIDNT_MODIFY_ANY_PAGE'] = 'Vous n\'avez pas modifi&eacute; de page';
$GLOBALS['translations']['YOU_ARENT_LOGGED_IN'] = 'Vous n\'&ecirc;tes pas identifi&eacute;';
$GLOBALS['translations']['IMPOSSIBLE_TO_SHOW_YOUR_MODIFIED_PAGES'] = 'impossible d\'afficher la liste des pages que vous avez modifi&eacute;es';
$GLOBALS['translations']['LIST_OF_PAGES_WHERE_YOU_ARE_THE_OWNER'] = 'Liste des pages dont vous &ecirc;tes le propri&eacute;taire';
$GLOBALS['translations']['YOU_DONT_OWN_ANY_PAGE'] = 'Vous n\'&ecirc;tes le propri&eacute;taire d\'aucune page';

// actions/orphanedpages.php
$GLOBALS['translations']['NO_ORPHAN_PAGES'] = 'Pas de pages orphelines';

// actions/recentchanges.php
$GLOBALS['translations']['HISTORY'] = 'historique';

// actions/recentchangesrss.php
$GLOBALS['translations']['TO_OBTAIN_RSS_FEED_TO_GO_THIS_ADDRESS'] = 'Pour obtenir le fil RSS des derniers changements, utilisez l\'adresse suivante';
$GLOBALS['translations']['LATEST_CHANGES_ON'] = 'Derniers changements sur';

// actions/recentcomments.php
$GLOBALS['translations']['NO_RECENT_COMMENTS'] = 'Pas de commentaires r&eacute;cents';

// actions/recentcommentsrss.php
$GLOBALS['translations']['TO_OBTAIN_COMMENTS_RSS_FEED_TO_GO_THIS_ADDRESS'] = 'Pour obtenir le fil RSS des derniers commentaires, utilisez l\'adresse suivante';
$GLOBALS['translations']['LATEST_COMMENTS_ON'] = 'Derniers commentaires sur';

// actions/recentlycommented.php
$GLOBALS['translations']['NO_RECENT_COMMENTS_ON_PAGES'] = 'Aucune page n\'a &eacute;t&eacute; comment&eacute;e r&eacute;cemment';

// actions/redirect.php
$GLOBALS['translations']['ERROR_ACTION_REDIRECT'] = 'Erreur action {{redirect ...}}';
$GLOBALS['translations']['CIRCULAR_REDIRECTION_FROM_PAGE'] = 'redirection circulaire depuis la page';
$GLOBALS['translations']['CLICK_HERE_TO_EDIT'] = 'cliquer ici pour l\'&eacute;diter';
$GLOBALS['translations']['PRESENCE_OF_REDIRECTION_TO'] = 'Pr&eacute;sence d\'une redirection vers';

// actions/resetpassword.php
$GLOBALS['translations']['ACTION_RESETPASSWORD'] = 'Action {{resetpassword ...}}';
$GLOBALS['translations']['PASSWORD_UPDATED'] = 'Mot de passe r&eacute;initialis&eacute;';
$GLOBALS['translations']['RESETTING_THE_PASSWORD'] = 'R&eacute;initialisation du mot de passe';
$GLOBALS['translations']['WIKINAME'] = 'NomWiki';
$GLOBALS['translations']['NEW_PASSWORD'] = 'Nouveau mot de passe';
$GLOBALS['translations']['RESET_PASSWORD'] = 'Reset password';
$GLOBALS['translations']['NO_PERMISSIONS_TO_EXECUTE_THIS_ACTION'] = 'vous n\'avez pas les permissions n&eacute;cessaires pour ex&eacute;cuter cette action';

// actions/textsearch.php
$GLOBALS['translations']['WHAT_YOU_SEARCH'] = 'Ce que vous souhaitez chercher';
$GLOBALS['translations']['SEARCH'] = 'Chercher';
$GLOBALS['translations']['SEARCH_RESULT_OF'] = 'R&eacute;sultat(s) de la recherche de';
$GLOBALS['translations']['NO_RESULT_FOR'] = 'Aucun r&eacute;sultat pour';

// actions/trail.php
$GLOBALS['translations']['ERROR_ACTION_TRAIL'] = 'Erreur action {{trail ...}}';
$GLOBALS['translations']['INDICATE_THE_PARAMETER_TOC'] = 'Indiquez le nom de la page sommaire, param&egrave;tre "toc"';

// actions/usersettings.php
$GLOBALS['translations']['YOU_ARE_NOW_DISCONNECTED'] = 'Vous &ecirc;tes maintenant d&eacute;connect&eacute;';
$GLOBALS['translations']['PARAMETERS_SAVED'] = 'Param&egrave;tres sauvegard&eacute;s';
$GLOBALS['translations']['NO_SPACES_IN_PASSWORD'] = 'Les espaces ne sont pas permis dans les mots de passe';
$GLOBALS['translations']['PASSWORD_TOO_SHORT'] = 'Mot de passe trop court';
$GLOBALS['translations']['WRONG_PASSWORD'] = 'Mauvais mot de passe';
$GLOBALS['translations']['PASSWORD_CHANGED'] = 'Mot de passe chang&eacute;';
$GLOBALS['translations']['GREETINGS'] = 'Bonjour';
$GLOBALS['translations']['YOUR_EMAIL_ADDRESS'] = 'Votre adresse de messagerie &eacute;lectronique';
$GLOBALS['translations']['DOUBLE_CLICK_TO_EDIT'] = '&Eacute;dition en double-cliquant';
$GLOBALS['translations']['SHOW_COMMENTS_BY_DEFAULT'] = 'Par d&eacute;faut, montrer les commentaires';
$GLOBALS['translations']['MAX_NUMBER_OF_LASTEST_COMMENTS'] = 'Nombre maximum de derniers commentaires';
$GLOBALS['translations']['MAX_NUMBER_OF_VERSIONS'] = 'Nombre maximum de versions';
$GLOBALS['translations']['YOUR_MOTTO'] = 'Votre devise';
$GLOBALS['translations']['UPDATE'] = 'Mise &agrave; jour';
$GLOBALS['translations']['CHANGE_THE_PASSWORD'] = 'Changement de mot de passe';
$GLOBALS['translations']['YOUR_OLD_PASSWORD'] = 'Votre ancien mot de passe';
$GLOBALS['translations']['NEW_PASSWORD'] = 'Nouveau mot de passe';
$GLOBALS['translations']['CHANGE'] = 'Changer';
$GLOBALS['translations']['USERNAME_MUST_BE_WIKINAME'] = 'Votre nom d\'utilisateur doit &ecirc;tre format&eacute; en NomWiki';
$GLOBALS['translations']['YOU_MUST_SPECIFY_AN_EMAIL'] = 'Vous devez sp&eacute;cifier une adresse de messagerie &eacute;lectronique';
$GLOBALS['translations']['THIS_IS_NOT_A_VALID_EMAIL'] = 'Ceci ne ressemble pas &agrave; une adresse de messagerie &eacute;lectronique';
$GLOBALS['translations']['PASSWORDS_NOT_IDENTICAL'] = 'Les mots de passe n\'&eacute;taient pas identiques';
$GLOBALS['translations']['PASSWORD_SHOULD_HAVE_5_CHARS_MINIMUM'] = 'doit contenir au minimum 5 caract&egrave;res alphanum&eacute;riques';
$GLOBALS['translations']['YOU_MUST_ACCEPT_COOKIES_TO_GET_CONNECTED'] = 'Vous devez accepter les cookies pour pouvoir vous connecter';
$GLOBALS['translations']['IF_YOU_ARE_REGISTERED_LOGGIN_HERE'] = 'Si vous &ecirc;tes d&eacute;j&agrave; enregistr&eacute;, identifiez-vous ici';
$GLOBALS['translations']['YOUR_WIKINAME'] = 'Votre NomWiki';
$GLOBALS['translations']['PASSWORD_5_CHARS_MINIMUM'] = 'Mot de passe (5 caract&egrave;res minimum)';
$GLOBALS['translations']['REMEMBER_ME'] = 'Se souvenir de moi';
$GLOBALS['translations']['IDENTIFICATION'] = 'Identification';
$GLOBALS['translations']['FILL_THE_NEXT_FIELDS_IF_YOU_LOGGIN_FOR_THE_FIRST_TIME_AND_REGISTER'] = 'Les champs suivants sont &agrave; remplir si vous vous identifiez pour la premi&egrave;re fois (vous cr&eacute;erez ainsi un compte)';
$GLOBALS['translations']['PASSWORD_CONFIRMATION'] = 'Confirmation du mot de passe';
$GLOBALS['translations']['NEW_ACCOUNT'] = 'Nouveau compte';


// actions/wantedpages.php 
$GLOBALS['translations']['NO_PAGE_TO_CREATE'] = 'Aucune page &agrave; cr&eacute;er';

// setup/header.php
$GLOBALS['translations']['OK'] = 'OK';
$GLOBALS['translations']['FAIL'] = 'ECHEC';
$GLOBALS['translations']['END_OF_INSTALLATION_BECAUSE_OF_ERRORS'] = 'Fin de l\'installation suite &agrave; des erreurs dans la configuration';

// setup/default.php
$GLOBALS['translations']['INSTALLATION_OF_YESWIKI'] = 'Installation de YesWiki';
$GLOBALS['translations']['YOUR_SYSTEM'] = 'Votre syst&egrave;me';
$GLOBALS['translations']['EXISTENT_SYSTEM_RECOGNISED_AS_VERSION'] = 'existant a &eacute;t&eacute;  reconnu comme &eacute;tant la version';
$GLOBALS['translations']['YOU_ARE_UPDATING_YESWIKI_TO_VERSION'] = 'Vous &ecirc;tes sur le point de mettre &agrave; jour YesWiki pour la version';
$GLOBALS['translations']['CHECK_YOUR_CONFIG_INFORMATION_BELOW'] = 'Veuillez revoir vos informations de configuration ci-dessous';
$GLOBALS['translations']['FILL_THE_FORM_BELOW'] = 'Veuillez compl&eacute;ter le formulaire suivant';
$GLOBALS['translations']['DATABASE_CONFIGURATION'] = 'Configuration de la base de donn&eacute;es';
$GLOBALS['translations']['MORE_INFOS'] = '+ Infos';
$GLOBALS['translations']['MYSQL_SERVER'] = 'Machine MySQL';
$GLOBALS['translations']['MYSQL_SERVER_INFOS'] = 'L\'adresse IP ou le nom r&eacute;seau de la machine sur laquelle se trouve votre serveur MySQL';
$GLOBALS['translations']['MYSQL_DATABASE'] = 'Base de donn&eacute;es MySQL';
$GLOBALS['translations']['MYSQL_DATABASE_INFOS'] = 'Cette base de donn&eacute;es doit d&eacute;j&agrave; exister avant de pouvoir continuer';
$GLOBALS['translations']['MYSQL_USERNAME'] = 'Nom de l\'utilisateur MySQL';
$GLOBALS['translations']['MYSQL_USERNAME_INFOS'] = 'N&eacute;cessaire pour se connecter &agrave; votre base de donn&eacute;es';
$GLOBALS['translations']['TABLE_PREFIX'] = 'Pr&eacute;fixe des tables';
$GLOBALS['translations']['TABLE_PREFIX_INFOS'] = 'Permet d\'utiliser plusieurs YesWiki sur une m&ecirc;me base de donn&eacute;es : chaque nouveau YesWiki install&eacute; devra avoir un pr&eacute;fixe des tables diff&eacute;rent';
$GLOBALS['translations']['MYSQL_PASSWORD'] = 'Mot de passe MySQL';
$GLOBALS['translations']['YESWIKI_WEBSITE_CONFIGURATION'] = 'Configuration de votre site YesWiki';
$GLOBALS['translations']['YOUR_WEBSITE_NAME'] = 'Nom de votre site';
$GLOBALS['translations']['YOUR_WEBSITE_NAME_INFOS'] = 'Ceci peut &ecirc;tre un NomWiki ou tout autre titre qui apparaitra sur les onglets et fen&ecirc;tres';
$GLOBALS['translations']['HOMEPAGE'] = 'Page d\'accueil';
$GLOBALS['translations']['HOMEPAGE_INFOS'] = 'La page d\'accueil de votre YesWiki. Elle doit &ecirc;tre un NomWiki';
$GLOBALS['translations']['KEYWORDS'] = 'Mots cl&eacute;s';
$GLOBALS['translations']['KEYWORDS_INFOS'] = 'Mots cl&eacute;s qui seront ins&eacute;r&eacute;s dans les codes HTML (m&eacute;ta-donn&eacute;es)';
$GLOBALS['translations']['DESCRIPTION'] = 'Description';
$GLOBALS['translations']['DESCRIPTION_INFOS'] = 'La description de votre site  qui sera ins&eacute;r&eacute; dans les codes HTML (m&eacute;ta-donn&eacute;es)';
$GLOBALS['translations']['CREATION_OF_ADMIN_ACCOUNT'] = 'Cr&eacute;ation d\'un compte administrateur';
$GLOBALS['translations']['ADMIN_ACCOUNT_CAN'] = 'Le compte administrateur permet de';
$GLOBALS['translations']['MODIFY_AND_DELETE_ANY_PAGE'] = 'Modifier et supprimer n\'importe quelle page';
$GLOBALS['translations']['MODIFY_ACCESS_RIGHTS_ON_ANY_PAGE'] = 'Modifier les droits d\'acc&egrave;s &agrave; n\'importe quelle page';
$GLOBALS['translations']['GENERATE_ACCESS_RIGHTS_ON_ANY_ACTION_OR_HANDLER'] = 'G&eacute;rer les droits d\'acc&egrave;s &agrave; n\'importe quelle action ou handler';
$GLOBALS['translations']['GENERATE_GROUPS'] = 'G&eacute;rer les groupes, ajouter/supprimer des utilisateurs au groupe administrateur (ayant les m&ecirc;mes droits que lui)';
$GLOBALS['translations']['ALL_ADMIN_TASKS_ARE_DESCRIBED_IN_THE_PAGE'] = 'Toutes les t&acirc;ches d\'administration sont d&eacute;crites dans la page "AdministrationDeYesWiki" accessible depuis la page d\'accueil';
$GLOBALS['translations']['USE_AN_EXISTING_ACCOUNT'] = 'Utiliser un compte existant';
$GLOBALS['translations']['NO'] = 'Non';
$GLOBALS['translations']['OR_CREATE_NEW_ACCOUNT'] = 'Ou cr&eacute;er un nouveau compte';
$GLOBALS['translations']['ADMIN'] = 'Administrateur';
$GLOBALS['translations']['MUST_BE_WIKINAME'] = 'doit &ecirc;tre un NomWiki';
$GLOBALS['translations']['PASSWORD'] = 'Mot de passe';
$GLOBALS['translations']['EMAIL_ADDRESS'] = 'Adresse e-mail';
$GLOBALS['translations']['MORE_OPTIONS'] = 'Options suppl&eacute;mentaires';
$GLOBALS['translations']['ADVANCED_CONFIGURATION'] = '+ Configuration avanc&eacute;e';
$GLOBALS['translations']['URL_REDIRECTION'] = 'Redirection d\'URL';
$GLOBALS['translations']['NEW_INSTALL_VALUES_CHANGE_ONLY_IF_YOU_KNOW_WHAT_YOU_ARE_DOING'] = 'Ceci est une nouvelle installation. Le programme d\'installation va essayer de trouver les valeurs appropri&eacute;es. Changez-les uniquement si vous savez ce que vous faites';
$GLOBALS['translations']['PAGENAME_WILL_BE_ADDED_AFTER_CHANGE_JUST_FOR_REDIRECTION'] = 'Les noms des pages seront directement rajout&eacute;s &agrave; l\'URL de base de votre site YesWiki. Supprimez la partie "?wiki=" uniquement si vous utilisez la redirection (voir ci apr&egrave;s)';
$GLOBALS['translations']['BASE_URL'] = 'URL de base';
$GLOBALS['translations']['REDIRECTION_SHOULD_BE_ACTIVE_ONLY_IF_USED_IN_YESWIKI'] = 'Le mode "redirection automatique" doit &ecirc;tre s&eacute;lectionn&eacute; uniquement si vous utilisez YesWiki avec la redirection d\'URL (si vous ne savez pas ce qu\'est la redirection d\'URL n\'activez pas cette option)';
$GLOBALS['translations']['ACTIVATE_REDIRECTION_MODE'] = 'Activation du mode "redirection automatique"';
$GLOBALS['translations']['OTHER_OPTIONS'] = 'Autres options';
$GLOBALS['translations']['OBLIGE_TO_PREVIEW_BEFORE_SAVING_PAGE'] = 'Imposer de faire un aper&ccedil;u avant de pouvoir sauver une page';
$GLOBALS['translations']['AUTHORIZE_HTML_INSERTION'] = 'Autoriser l\'insertion de HTML brut';
$GLOBALS['translations']['CONTINUE'] = 'Continuer';

// setup/install.php
$GLOBALS['translations']['PROBLEM_WHILE_INSTALLING'] = 'probl&egrave;me dans la proc&eacute;dure d\'installation';
$GLOBALS['translations']['VERIFICATION_OF_DATAS_AND_DATABASE_INSTALLATION'] = 'Test de la configuration et installation de la base de donn&eacute;es';
$GLOBALS['translations']['VERIFY_MYSQL_PASSWORD'] = 'V&eacute;rification mot de passe MySQL';
$GLOBALS['translations']['INCORRECT_MYSQL_PASSWORD'] = 'Le mot de passe MySQL est incorrect';
$GLOBALS['translations']['TEST_MYSQL_CONNECTION'] = 'Test connexion MySQL';
$GLOBALS['translations']['SEARCH_FOR_DATABASE'] = 'Recherche base de donn&eacute;es';
$GLOBALS['translations']['GO_BACK'] = 'Revenir';
$GLOBALS['translations']['NO_DATABASE_FOUND_TRY_TO_CREATE'] = 'La base de donn&eacute;es que vous avez choisie n\'existe pas. Nous allons tenter de la cr&eacute;er';
$GLOBALS['translations']['TRYING_TO_CREATE_DATABASE'] = 'Tentative de cr&eacute;ation de la base de donn&eacute;es';
$GLOBALS['translations']['DATABASE_COULD_NOT_BE_CREATED_YOU_MUST_CREATE_IT_MANUALLY'] = 'Cr&eacute;ation de la base impossible. Vous devez cr&eacute;er cette base manuellement avant d\'installer YesWiki';
$GLOBALS['translations']['SEARCH'] = 'Recherche base de donn&eacute;es';
$GLOBALS['translations']['DATABASE_DOESNT_EXIST_YOU_MUST_CREATE_IT'] = 'La base de donn&eacute;es que vous avez choisie n\'existe pas, vous devez la cr&eacute;er avant d\'installer YesWiki';
$GLOBALS['translations']['CHECKING_THE_ADMIN_PASSWORD'] = 'V&eacute;rification du mot de passe Administrateur';
$GLOBALS['translations']['CHECKING_THE_ADMIN_PASSWORD_CONFIRMATION'] = 'V&eacute;rification de l\'identit&eacute; des mots de passes administrateurs';
$GLOBALS['translations']['ADMIN_PASSWORD_ARE_DIFFERENT'] = 'Les mots de passe Aaministrateur sont diff&eacute;rents';
$GLOBALS['translations']['DATABASE_INSTALLATION'] = 'Installation de la base de donn&eacute;es';
$GLOBALS['translations']['CREATION_OF_TABLE'] = 'Cr&eacute;ation de la table';
$GLOBALS['translations']['ALREADY_CREATED'] = 'D&eacute;j&agrave; cr&eacute;&eacute;e';
$GLOBALS['translations']['ADMIN_ACCOUNT_CREATION'] = 'Cr&eacute;ation du compte Administrateur';
$GLOBALS['translations']['INSERTION_OF_PAGE'] = 'Insertion de la page';
$GLOBALS['translations']['ALREADY_EXISTING'] = 'Existe d&eacute;j&agrave;';
$GLOBALS['translations']['UPDATING_FROM_WIKINI_0_1'] = 'En cours de mise &agrave; jour de WikiNi 0.1';
$GLOBALS['translations']['TINY_MODIFICATION_OF_PAGES_TABLE'] = 'Modification très légère de la table des pages';
$GLOBALS['translations']['ALREADY_DONE'] = 'Already done? Hmm!';
$GLOBALS['translations']['INSERTION_OF_USER_IN_ADMIN_GROUP'] = 'Insertion de l\'utilisateur sp&eacute;cifi&eacute; dans le groupe admin';
$GLOBALS['translations']['NEXT_STEP_WRITE_CONFIGURATION_FILE'] = 'A l\'&eacute;tape suivante, le programme d\'installation va essayer d\'&eacute;crire le fichier de configuration ';
$GLOBALS['translations']['VERIFY_YOU_HAVE_RIGHTS_TO_WRITE_FILE'] = 'Assurez vous que le serveur web a bien le droit d\'&eacute;crire dans ce fichier, sinon vous devrez le modifier manuellement';

// setup/writeconfig.php
$GLOBALS['translations']['WRITING_CONFIGURATION_FILE'] = '&Eacute;criture du fichier de configuration';
$GLOBALS['translations']['CREATED'] = 'cr&eacute;&eacute;e';
$GLOBALS['translations']['DONT_CHANGE_YESWIKI_VERSION_MANUALLY'] = 'ne changez pas la yeswiki_version manuellement';
$GLOBALS['translations']['WRITING_CONFIGURATION_FILE_WIP'] = 'Cr&eacute;ation du fichier de configuration en cours';
$GLOBALS['translations']['FINISHED_CONGRATULATIONS'] = 'Voila c\'est termin&eacute;, f&eacute;licitations';
$GLOBALS['translations']['GO_TO_YOUR_NEW_YESWIKI_WEBSITE'] = 'Aller sur votre nouveau site YesWiki';
$GLOBALS['translations']['IT_IS_RECOMMANDED_TO_REMOVE_WRITE_ACCESS_TO_CONFIG_FILE'] = 'Il est conseill&eacute; de retirer l\'acc&egrave;s en &eacute;criture au fichier';
$GLOBALS['translations']['THIS_COULD_BE_UNSECURE'] = 'ceci peut &ecirc;tre une faille dans la s&eacute;curit&eacute;';
$GLOBALS['translations']['WARNING'] = 'AVERTISSEMENT';
$GLOBALS['translations']['CONFIGURATION_FILE'] = 'le fichier de configuration';
$GLOBALS['translations']['CONFIGURATION_FILE_NOT_CREATED'] = 'n\'a pu &ecirc;tre cr&eacute;&eacute;';
$GLOBALS['translations']['TRY_CHANGE_ACCESS_RIGHTS_OR_FTP_TRANSFERT'] = 'Veuillez vous assurez que votre serveur a les droits d\'acc&egrave;s en &eacute;criture pour ce fichier. Si pour une raison quelconque vous ne pouvez pas faire &ccedil;a, vous devez copier les informations suivantes dans un fichier et les transf&eacute;rer au moyen d\'un logiciel de transfert de fichier (ftp) sur le serveur dans un fichier ';
$GLOBALS['translations']['DIRECTLY_IN_THE_YESWIKI_FOLDER'] = 'directement dans le r&eacute;pertoire de YesWiki. Une fois que vous aurez fait cela, votre site YesWiki devrait fonctionner correctement';
$GLOBALS['translations']['TRY_AGAIN'] = 'Essayer &agrave; nouveau';

?>
