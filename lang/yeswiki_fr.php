<?php
/*vim: set expandtab tabstop=4 shiftwidth=4: */
// +------------------------------------------------------------------------------------------------------+
// | PHP version 5                                                                                        |
// +------------------------------------------------------------------------------------------------------+
// | Copyright (C) 2014 Outils-Réseaux (accueil@outils-reseaux.org)                                       |
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
* Fichier de traduction en francais de YesWiki
*
*@package 		yeswiki
*@author        Florian Schmitt <florian@outils-reseaux.org>
*@copyright     2014 Outils-Réseaux
*/

$GLOBALS['translations'] = array(

// wakka.php
'UNKNOWN_ACTION' => 'Action inconnue',
'INVALID_ACTION' => 'Action invalide',
'ERROR_NO_ACCESS' => 'Erreur: vous n\'avez pas acc&egrave;s &agrave; l\'action',
'INCORRECT_CLASS' => 'classe incorrecte',
'UNKNOWN_METHOD' => 'M&eacute;thode inconnue',
'FORMATTER_NOT_FOUND' => 'Impossible de trouver le formateur',
'HANDLER_NO_ACCESS' => 'Vous ne pouvez pas acc&eacute;der &agrave; cette page par le handler sp&eacute;cifi&eacute;.',
'NO_REQUEST_FOUND' => '$_REQUEST[] pas trouv&eacute;. Wakka n&eacute;cessite PHP 4.1.0 ou plus r&eacute;cent!',
'SITE_BEING_UPDATED' => 'Ce site est en cours de mise &agrave; jour. Veuillez essayer plus tard.',
'INCORRECT_PAGENAME' => 'Le nom de la page est incorrect.',
'DB_CONNECT_FAIL' => 'Pour des raisons ind&eacute;pendantes de notre volont&eacute;, le contenu de ce YesWiki est temporairement inaccessible. Veuillez r&eacute;essayer ult&eacute;rieurement, merci de votre compr&eacute;hension.',
'LOG_DB_CONNECT_FAIL' => 'YesWiki : la connexion BDD a echouee', // sans accents car commande systeme
'INCORRECT_PAGENAME' => 'Le nom de la page est incorrect.',
'HOMEPAGE_WIKINAME' => 'PagePrincipale',
'MY_YESWIKI_SITE' => 'Mon site YesWiki',

// tools.php
'YESWIKI_TOOLS_CONFIG' => 'Configuration extension(s) de YesWiki',
'DISCONNECT' => 'D&eacute;connexion',
'RETURN_TO_EXTENSION_LIST' => 'Retour &agrave; la liste des extensions actives',
'NO_TOOL_AVAILABLE' => 'Aucun outil n\'est disponible ou actif',
'LIST_OF_ACTIVE_TOOLS' => 'Liste des extensions actives',

// actions/backlinks.php
'PAGES_WITH_LINK' => 'Pages ayant un lien vers',
'PAGES_WITH_LINK_TO_CURRENT_PAGE' => 'Pages ayant un lien vers la page courante',
'NO_PAGES_WITH_LINK_TO' => 'Aucune page n\'a de lien vers',

// actions/changestyle.php ignoree...

// actions/editactionsacls.class.php
'ACTION_RIGHTS' => 'Droits de l\'action',
'SEE' => 'Voir',
'ERROR_WHILE_SAVING_ACL' => 'Une erreur s\'est produite pendant l\'enregistrement de l\'ACL pour l\'action',
'ERROR_CODE' => 'code d\'erreur',
'NEW_ACL_FOR_ACTION' => 'Nouvelle ACL pour l\'action',
'NEW_ACL_SUCCESSFULLY_SAVED_FOR_ACTION' => 'Nouvelle ACL enregistr&eacute;e avec succ&egrave;s pour l\'action',
'EDIT_RIGHTS_FOR_ACTION' => '&Eacute;diter les droits de l\'action',
'SAVE' => 'Enregistrer',

// actions/editgroups.class.php
'DEFINITION_OF_THE_GROUP' => 'D&eacute;finition du groupe',
'DEFINE' => 'D&eacute;finir',
'CREATE_NEW_GROUP' => 'Ou cr&eacute;er un nouveau groupe',
'ONLY_ADMINS_CAN_CHANGE_MEMBERS' => 'Vous ne pouvez pas changer les membres du groupe des administrateurs car vous n\'&ecirc;tes pas administrateur',
'YOU_CANNOT_REMOVE_YOURSELF' => 'Vous ne pouvez pas vous retirer vous-m&ecirc;me du groupe des administrateurs',
'ERROR_RECURSIVE_GROUP' => 'Erreur: vous ne pouvez pas d&eacute;finir un groupe r&eacute;cursivement',
'ERROR_WHILE_SAVING_GROUP' => 'Une erreur s\'est produite pendant l\'enregistrement du groupe',
'NEW_ACL_FOR_GROUP' => 'Nouvelle ACL pour le groupe',
'NEW_ACL_SUCCESSFULLY_SAVED_FOR_THE_GROUP' => 'Nouvelle ACL enr&eacute;gistr&eacute;e avec succ&egrave;s pour le groupe',
'EDIT_GROUP' => '&Eacute;diter le groupe',
'ONLY_ALPHANUM_FOR_GROUP_NAME' => 'Les noms de groupes ne peuvent contenir que des caract&egrave;res alphanum&eacute;riques',

// actions/edithandlersacls.class.php
'HANDLER_RIGHTS' => 'Droits du handler',
'ERROR_WHILE_SAVING_HANDLER_ACL' => 'Une erreur s\'est produite pendant l\'enregistrement de l\'ACL pour le handler',
'NEW_ACL_FOR_HANDLER' => 'Nouvelle ACL pour le handler',
'NEW_ACL_SUCCESSFULLY_SAVED_FOR_HANDLER' => 'Nouvelle ACL enregistr&eacute;e avec succ&egrave;s pour le handler',
'EDIT_RIGHTS_FOR_HANDLER' => '&Eacute;diter les droits du handler',

// actions/erasespamedcomments.class.php ignoree...
// actions/footer.php ignoree, car le tools templates court circuite
// actions/header.php ignoree, car le tools templates court circuite

// actions/include.php
'ERROR' => 'Erreur',
'ACTION' => 'Action',
'MISSING_PAGE_PARAMETER' => 'le param&egrave;tre "page" est manquant',
'IMPOSSIBLE_FOR_THIS_PAGE' => 'Impossible pour la page',
'TO_INCLUDE_ITSELF' => 'de s\'inclure en elle m&ecirc;me',
'INCLUSIONS_CHAIN' => 'Chaine d\'inclusions',
'EDITION' => '&Eacute;dition',
'READING_OF_INCLUDED_PAGE' => 'Lecture de la page inclue',
'NOT_ALLOWED' => 'non autoris&eacute;e',
'INCLUDED_PAGE' => 'La page inclue',
'DOESNT_EXIST' => 'ne semble pas exister',

// actions/listpages.php
'THE_PAGE' => 'La page',
'BELONGING_TO' => 'appartenant &agrave;',
'UNKNOWN' => 'Inconnu',
'LAST_CHANGE_BY' => 'derni&egrave;re modification par',
'LAST_CHANGE' => 'derni&egrave;re modification',
'PAGE_LIST_WHERE' => 'Liste des pages auxquelles',
'HAS_PARTICIPATED' => 'a particip&eacute;',
'EXCLUDING_EXCLUSIONS' => 'hors exclusions',
'INCLUDING' => 'et dont',
'IS_THE_OWNER' => 'est le propri&eacute;taire',
'NO_PAGE_FOUND' => 'Aucune page trouv&eacute;e',
'IN_THIS_WIKI' => 'dans ce wiki',
'LIST_PAGES_BELONGING_TO' => 'Liste des pages appartenant &agrave;',
'THIS_USER_HAS_NO_PAGE' => 'Cet utilisateur ne poss&egrave;de aucune page',
'UNKNOWN' => 'Inconnu',
'BY' => 'par',

// actions/mychanges.php
'YOUR_MODIFIED_PAGES_ORDERED_BY_MODIFICATION_DATE' => 'Liste des pages que vous avez modifi&eacute;es, tri&eacute;e par date de modification',
'YOUR_MODIFIED_PAGES_ORDERED_BY_NAME' => 'Liste des pages que vous avez modifi&eacute;es, tri&eacute;e par ordre alphab&eacute;tique',
'YOU_DIDNT_MODIFY_ANY_PAGE' => 'Vous n\'avez pas modifi&eacute; de page',
'YOU_ARENT_LOGGED_IN' => 'Vous n\'&ecirc;tes pas identifi&eacute;',
'IMPOSSIBLE_TO_SHOW_YOUR_MODIFIED_PAGES' => 'impossible d\'afficher la liste des pages que vous avez modifi&eacute;es',
'LIST_OF_PAGES_WHERE_YOU_ARE_THE_OWNER' => 'Liste des pages dont vous &ecirc;tes le propri&eacute;taire',
'YOU_DONT_OWN_ANY_PAGE' => 'Vous n\'&ecirc;tes le propri&eacute;taire d\'aucune page',

// actions/orphanedpages.php
'NO_ORPHAN_PAGES' => 'Pas de pages orphelines',

// actions/recentchanges.php
'HISTORY' => 'historique',

// actions/recentchangesrss.php
'TO_OBTAIN_RSS_FEED_TO_GO_THIS_ADDRESS' => 'Pour obtenir le fil RSS des derniers changements, utilisez l\'adresse suivante',
'LATEST_CHANGES_ON' => 'Derniers changements sur',

// actions/recentcomments.php
'NO_RECENT_COMMENTS' => 'Pas de commentaires r&eacute;cents',

// actions/recentcommentsrss.php
'TO_OBTAIN_COMMENTS_RSS_FEED_TO_GO_THIS_ADDRESS' => 'Pour obtenir le fil RSS des derniers commentaires, utilisez l\'adresse suivante',
'LATEST_COMMENTS_ON' => 'Derniers commentaires sur',

// actions/recentlycommented.php
'NO_RECENT_COMMENTS_ON_PAGES' => 'Aucune page n\'a &eacute;t&eacute; comment&eacute;e r&eacute;cemment',

// actions/redirect.php
'ERROR_ACTION_REDIRECT' => 'Erreur action {{redirect ...}}',
'CIRCULAR_REDIRECTION_FROM_PAGE' => 'redirection circulaire depuis la page',
'CLICK_HERE_TO_EDIT' => 'cliquer ici pour l\'&eacute;diter',
'PRESENCE_OF_REDIRECTION_TO' => 'Pr&eacute;sence d\'une redirection vers',

// actions/resetpassword.php
'ACTION_RESETPASSWORD' => 'Action {{resetpassword ...}}',
'PASSWORD_UPDATED' => 'Mot de passe r&eacute;initialis&eacute;',
'RESETTING_THE_PASSWORD' => 'R&eacute;initialisation du mot de passe',
'WIKINAME' => 'NomWiki',
'NEW_PASSWORD' => 'Nouveau mot de passe',
'RESET_PASSWORD' => 'Reset password',
'NO_PERMISSIONS_TO_EXECUTE_THIS_ACTION' => 'vous n\'avez pas les permissions n&eacute;cessaires pour ex&eacute;cuter cette action',

// actions/textsearch.php
'WHAT_YOU_SEARCH' => 'Ce que vous souhaitez chercher',
'SEARCH' => 'Chercher',
//'SEARCH' => 'Rechercher',
'SEARCH_RESULT_OF' => 'R&eacute;sultat(s) de la recherche de',
'NO_RESULT_FOR' => 'Aucun r&eacute;sultat pour',

// actions/trail.php
'ERROR_ACTION_TRAIL' => 'Erreur action {{trail ...}}',
'INDICATE_THE_PARAMETER_TOC' => 'Indiquez le nom de la page sommaire, param&egrave;tre "toc"',

// actions/usersettings.php
'USER_SETTINGS' => 'Paramètres utilisateur',
'YOU_ARE_NOW_DISCONNECTED' => 'Vous &ecirc;tes maintenant d&eacute;connect&eacute;',
'PARAMETERS_SAVED' => 'Param&egrave;tres sauvegard&eacute;s',
'NO_SPACES_IN_PASSWORD' => 'Les espaces ne sont pas permis dans les mots de passe',
'PASSWORD_TOO_SHORT' => 'Mot de passe trop court',
'WRONG_PASSWORD' => 'Mauvais mot de passe',
'PASSWORD_CHANGED' => 'Mot de passe chang&eacute;',
'GREETINGS' => 'Bonjour',
'YOUR_EMAIL_ADDRESS' => 'Votre adresse de messagerie &eacute;lectronique',
'DOUBLE_CLICK_TO_EDIT' => '&Eacute;dition en double-cliquant',
'SHOW_COMMENTS_BY_DEFAULT' => 'Par d&eacute;faut, montrer les commentaires',
'MAX_NUMBER_OF_LASTEST_COMMENTS' => 'Nombre maximum de derniers commentaires',
'MAX_NUMBER_OF_VERSIONS' => 'Nombre maximum de versions',
'YOUR_MOTTO' => 'Votre devise',
'UPDATE' => 'Mise &agrave; jour',
'CHANGE_THE_PASSWORD' => 'Changement de mot de passe',
'YOUR_OLD_PASSWORD' => 'Votre ancien mot de passe',
'NEW_PASSWORD' => 'Nouveau mot de passe',
'CHANGE' => 'Changer',
'USERNAME_MUST_BE_WIKINAME' => 'Votre nom d\'utilisateur doit &ecirc;tre format&eacute; en NomWiki (<em>ex: CapitaineHaddock</em>)',
'YOU_MUST_SPECIFY_AN_EMAIL' => 'Vous devez sp&eacute;cifier une adresse de messagerie &eacute;lectronique',
'THIS_IS_NOT_A_VALID_EMAIL' => 'Ceci ne ressemble pas &agrave; une adresse de messagerie &eacute;lectronique',
'PASSWORDS_NOT_IDENTICAL' => 'Les mots de passe n\'&eacute;taient pas identiques',
'PASSWORD_SHOULD_HAVE_5_CHARS_MINIMUM' => 'doit contenir au minimum 5 caract&egrave;res alphanum&eacute;riques',
'YOU_MUST_ACCEPT_COOKIES_TO_GET_CONNECTED' => 'Vous devez accepter les cookies pour pouvoir vous connecter',
'IF_YOU_ARE_REGISTERED_LOGGIN_HERE' => 'Si vous &ecirc;tes d&eacute;j&agrave; enregistr&eacute;, identifiez-vous ici',
'YOUR_WIKINAME' => 'Votre NomWiki',
'PASSWORD_5_CHARS_MINIMUM' => 'Mot de passe (5 caract&egrave;res minimum)',
'REMEMBER_ME' => 'Se souvenir de moi',
'IDENTIFICATION' => 'Identification',
'FILL_THE_NEXT_FIELDS_IF_YOU_LOGGIN_FOR_THE_FIRST_TIME_AND_REGISTER' => 'Les champs suivants sont &agrave; remplir si vous vous identifiez pour la premi&egrave;re fois (vous cr&eacute;erez ainsi un compte)',
'PASSWORD_CONFIRMATION' => 'Confirmation du mot de passe',
'NEW_ACCOUNT' => 'Nouveau compte',


// actions/wantedpages.php
'NO_PAGE_TO_CREATE' => 'Aucune page &agrave; cr&eacute;er',

// setup/header.php
'OK' => 'OK',
'FAIL' => 'ECHEC',
'END_OF_INSTALLATION_BECAUSE_OF_ERRORS' => 'Fin de l\'installation suite &agrave; des erreurs dans la configuration',

// setup/default.php
'INSTALLATION_OF_YESWIKI' => 'Installation de YesWiki',
'YOUR_SYSTEM' => 'Votre syst&egrave;me',
'EXISTENT_SYSTEM_RECOGNISED_AS_VERSION' => 'existant a &eacute;t&eacute;  reconnu comme &eacute;tant la version',
'YOU_ARE_UPDATING_YESWIKI_TO_VERSION' => 'Vous &ecirc;tes sur le point de mettre &agrave; jour YesWiki pour la version',
'CHECK_YOUR_CONFIG_INFORMATION_BELOW' => 'Veuillez revoir vos informations de configuration ci-dessous',
'FILL_THE_FORM_BELOW' => 'Veuillez compl&eacute;ter le formulaire suivant',
'DEFAULT_LANGUAGE' => 'Langue par d&eacute;faut',
'DEFAULT_LANGUAGE_INFOS' => 'Langue utilis&eacute;e par d&eacute;faut pour l\'interface de YesWiki, il sera toujours possible de changer de langue pour chacune des pages cr&eacute;&eacute;es',
'GENERAL_CONFIGURATION' => 'Configuration générale',
'DATABASE_CONFIGURATION' => 'Configuration de la base de donn&eacute;es',
'MORE_INFOS' => '+ Infos',
'MYSQL_SERVER' => 'Machine MySQL',
'MYSQL_SERVER_INFOS' => 'L\'adresse IP ou le nom r&eacute;seau de la machine sur laquelle se trouve votre serveur MySQL',
'MYSQL_DATABASE' => 'Base de donn&eacute;es MySQL',
'MYSQL_DATABASE_INFOS' => 'Cette base de donn&eacute;es doit d&eacute;j&agrave; exister avant de pouvoir continuer',
'MYSQL_USERNAME' => 'Nom de l\'utilisateur MySQL',
'MYSQL_USERNAME_INFOS' => 'N&eacute;cessaire pour se connecter &agrave; votre base de donn&eacute;es',
'TABLE_PREFIX' => 'Pr&eacute;fixe des tables',
'TABLE_PREFIX_INFOS' => 'Permet d\'utiliser plusieurs YesWiki sur une m&ecirc;me base de donn&eacute;es : chaque nouveau YesWiki install&eacute; devra avoir un pr&eacute;fixe des tables diff&eacute;rent',
'MYSQL_PASSWORD' => 'Mot de passe MySQL',
'YESWIKI_WEBSITE_CONFIGURATION' => 'Configuration de votre site YesWiki',
'YOUR_WEBSITE_NAME' => 'Nom de votre site',
'YOUR_WEBSITE_NAME_INFOS' => 'Ceci peut &ecirc;tre un NomWiki ou tout autre titre qui apparaitra sur les onglets et fen&ecirc;tres',
'HOMEPAGE' => 'Page d\'accueil',
'HOMEPAGE_INFOS' => 'La page d\'accueil de votre YesWiki. Elle doit &ecirc;tre un NomWiki',
'KEYWORDS' => 'Mots cl&eacute;s',
'KEYWORDS_INFOS' => 'Mots cl&eacute;s qui seront ins&eacute;r&eacute;s dans les codes HTML (m&eacute;ta-donn&eacute;es)',
'DESCRIPTION' => 'Description',
'DESCRIPTION_INFOS' => 'La description de votre site  qui sera ins&eacute;r&eacute; dans les codes HTML (m&eacute;ta-donn&eacute;es)',
'CREATION_OF_ADMIN_ACCOUNT' => 'Cr&eacute;ation d\'un compte administrateur',
'ADMIN_ACCOUNT_CAN' => 'Le compte administrateur permet de',
'MODIFY_AND_DELETE_ANY_PAGE' => 'Modifier et supprimer n\'importe quelle page',
'MODIFY_ACCESS_RIGHTS_ON_ANY_PAGE' => 'Modifier les droits d\'acc&egrave;s &agrave; n\'importe quelle page',
'GENERATE_ACCESS_RIGHTS_ON_ANY_ACTION_OR_HANDLER' => 'G&eacute;rer les droits d\'acc&egrave;s &agrave; n\'importe quelle action ou handler',
'GENERATE_GROUPS' => 'G&eacute;rer les groupes, ajouter/supprimer des utilisateurs au groupe administrateur (ayant les m&ecirc;mes droits que lui)',
'ALL_ADMIN_TASKS_ARE_DESCRIBED_IN_THE_PAGE' => 'Toutes les t&acirc;ches d\'administration sont d&eacute;crites dans la page "AdministrationDeYesWiki" accessible depuis la page d\'accueil',
'USE_AN_EXISTING_ACCOUNT' => 'Utiliser un compte existant',
'NO' => 'Non',
'OR_CREATE_NEW_ACCOUNT' => 'Ou cr&eacute;er un nouveau compte',
'ADMIN' => 'Administrateur',
'MUST_BE_WIKINAME' => 'doit &ecirc;tre un NomWiki',
'PASSWORD' => 'Mot de passe',
'EMAIL_ADDRESS' => 'Adresse e-mail',
'MORE_OPTIONS' => 'Options suppl&eacute;mentaires',
'ADVANCED_CONFIGURATION' => '+ Configuration avanc&eacute;e',
'URL_REDIRECTION' => 'Redirection d\'URL',
'NEW_INSTALL_VALUES_CHANGE_ONLY_IF_YOU_KNOW_WHAT_YOU_ARE_DOING' => 'Ceci est une nouvelle installation. Le programme d\'installation va essayer de trouver les valeurs appropri&eacute;es. Changez-les uniquement si vous savez ce que vous faites',
'PAGENAME_WILL_BE_ADDED_AFTER_CHANGE_JUST_FOR_REDIRECTION' => 'Les noms des pages seront directement rajout&eacute;s &agrave; l\'URL de base de votre site YesWiki. Supprimez la partie "?wiki=" uniquement si vous utilisez la redirection (voir ci apr&egrave;s)',
'BASE_URL' => 'URL de base',
'REDIRECTION_SHOULD_BE_ACTIVE_ONLY_IF_USED_IN_YESWIKI' => 'Le mode "redirection automatique" doit &ecirc;tre s&eacute;lectionn&eacute; uniquement si vous utilisez YesWiki avec la redirection d\'URL (si vous ne savez pas ce qu\'est la redirection d\'URL n\'activez pas cette option)',
'ACTIVATE_REDIRECTION_MODE' => 'Activation du mode "redirection automatique"',
'OTHER_OPTIONS' => 'Autres options',
'OBLIGE_TO_PREVIEW_BEFORE_SAVING_PAGE' => 'Imposer de faire un aper&ccedil;u avant de pouvoir sauver une page',
'AUTHORIZE_HTML_INSERTION' => 'Autoriser l\'insertion de HTML brut',
'CONTINUE' => 'Continuer',

// setup/install.php
'PROBLEM_WHILE_INSTALLING' => 'probl&egrave;me dans la proc&eacute;dure d\'installation',
'VERIFICATION_OF_DATAS_AND_DATABASE_INSTALLATION' => 'Test de la configuration et installation de la base de donn&eacute;es',
'VERIFY_MYSQL_PASSWORD' => 'V&eacute;rification mot de passe MySQL',
'INCORRECT_MYSQL_PASSWORD' => 'Le mot de passe MySQL est incorrect',
'TEST_MYSQL_CONNECTION' => 'Test connexion MySQL',
'SEARCH_FOR_DATABASE' => 'Recherche base de donn&eacute;es',
'GO_BACK' => 'Revenir',
'NO_DATABASE_FOUND_TRY_TO_CREATE' => 'La base de donn&eacute;es que vous avez choisie n\'existe pas. Nous allons tenter de la cr&eacute;er',
'TRYING_TO_CREATE_DATABASE' => 'Tentative de cr&eacute;ation de la base de donn&eacute;es',
'DATABASE_COULD_NOT_BE_CREATED_YOU_MUST_CREATE_IT_MANUALLY' => 'Cr&eacute;ation de la base impossible. Vous devez cr&eacute;er cette base manuellement avant d\'installer YesWiki',
'DATABASE_DOESNT_EXIST_YOU_MUST_CREATE_IT' => 'La base de donn&eacute;es que vous avez choisie n\'existe pas, vous devez la cr&eacute;er avant d\'installer YesWiki',
'CHECKING_THE_ADMIN_PASSWORD' => 'V&eacute;rification du mot de passe Administrateur',
'CHECKING_THE_ADMIN_PASSWORD_CONFIRMATION' => 'V&eacute;rification de l\'identit&eacute; des mots de passes administrateurs',
'ADMIN_PASSWORD_ARE_DIFFERENT' => 'Les mots de passe Aaministrateur sont diff&eacute;rents',
'DATABASE_INSTALLATION' => 'Installation de la base de donn&eacute;es',
'CREATION_OF_TABLE' => 'Cr&eacute;ation de la table',
'ALREADY_CREATED' => 'D&eacute;j&agrave; cr&eacute;&eacute;e',
'ADMIN_ACCOUNT_CREATION' => 'Cr&eacute;ation du compte Administrateur',
'INSERTION_OF_PAGE' => 'Insertion de la page',
'ALREADY_EXISTING' => 'Existe d&eacute;j&agrave;',
'UPDATING_FROM_WIKINI_0_1' => 'En cours de mise &agrave; jour de WikiNi 0.1',
'TINY_MODIFICATION_OF_PAGES_TABLE' => 'Modification très légère de la table des pages',
'ALREADY_DONE' => 'Already done? Hmm!',
'INSERTION_OF_USER_IN_ADMIN_GROUP' => 'Insertion de l\'utilisateur sp&eacute;cifi&eacute; dans le groupe admin',
'NEXT_STEP_WRITE_CONFIGURATION_FILE' => 'A l\'&eacute;tape suivante, le programme d\'installation va essayer d\'&eacute;crire le fichier de configuration ',
'VERIFY_YOU_HAVE_RIGHTS_TO_WRITE_FILE' => 'Assurez vous que le serveur web a bien le droit d\'&eacute;crire dans ce fichier, sinon vous devrez le modifier manuellement',

// setup/writeconfig.php
'WRITING_CONFIGURATION_FILE' => '&Eacute;criture du fichier de configuration',
'CREATED' => 'cr&eacute;&eacute;e',
'DONT_CHANGE_YESWIKI_VERSION_MANUALLY' => 'ne changez pas la yeswiki_version manuellement',
'WRITING_CONFIGURATION_FILE_WIP' => 'Cr&eacute;ation du fichier de configuration en cours',
'FINISHED_CONGRATULATIONS' => 'Voila c\'est termin&eacute;, f&eacute;licitations',
'GO_TO_YOUR_NEW_YESWIKI_WEBSITE' => 'Aller sur votre nouveau site YesWiki',
'IT_IS_RECOMMANDED_TO_REMOVE_WRITE_ACCESS_TO_CONFIG_FILE' => 'Il est conseill&eacute; de retirer l\'acc&egrave;s en &eacute;criture au fichier',
'THIS_COULD_BE_UNSECURE' => 'ceci peut &ecirc;tre une faille dans la s&eacute;curit&eacute;',
'WARNING' => 'AVERTISSEMENT',
'CONFIGURATION_FILE' => 'le fichier de configuration',
'CONFIGURATION_FILE_NOT_CREATED' => 'n\'a pu &ecirc;tre cr&eacute;&eacute;',
'TRY_CHANGE_ACCESS_RIGHTS_OR_FTP_TRANSFERT' => 'Veuillez vous assurez que votre serveur a les droits d\'acc&egrave;s en &eacute;criture pour ce fichier. Si pour une raison quelconque vous ne pouvez pas faire &ccedil;a, vous devez copier les informations suivantes dans un fichier et les transf&eacute;rer au moyen d\'un logiciel de transfert de fichier (ftp) sur le serveur dans un fichier ',
'DIRECTLY_IN_THE_YESWIKI_FOLDER' => 'directement dans le r&eacute;pertoire de YesWiki. Une fois que vous aurez fait cela, votre site YesWiki devrait fonctionner correctement',
'TRY_AGAIN' => 'Essayer &agrave; nouveau',

);
