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
* English translation file for YesWiki's main program
*
*@package 		yeswiki
*@author        Florian Schmitt <florian@outils-reseaux.org>
*@copyright     2014 Outils-Réseaux
*/

$GLOBALS['translations'] = array(

// wakka.php
'UNKNOWN_ACTION' => 'Unknown action',
'INVALID_ACTION' => 'Invalid action',
'ERROR_NO_ACCESS' => 'Error : you have no rights to access this action',
'INCORRECT_CLASS' => 'Incorrect class',
'UNKNOWN_METHOD' => 'Unknown method',
'FORMATTER_NOT_FOUND' => 'Formatter not found',
'HANDLER_NO_ACCESS' => 'You cannot access this page with the specified handler.',
'NO_REQUEST_FOUND' => '$_REQUEST[] not found. Wakka needs PHP 4.1.0 or more recent!',
'SITE_BEING_UPDATED' => 'This website is currently updated. Please try again later.',
'INCORRECT_PAGENAME' => 'Incorrect page name.',
'DB_CONNECT_FAIL' => 'For some reasons, probably database access error, this YesWiki is temporaly unavailable. Please try again later, thank you for your patience.',
'LOG_DB_CONNECT_FAIL' => 'YesWiki : database connection failed',
'INCORRECT_PAGENAME' => 'Incorrect page name.',
'HOMEPAGE_WIKINAME' => 'HomePage',
'MY_YESWIKI_SITE' => 'My YesWiki website',

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
'NO_ORPHAN_PAGES' => 'No orphan pages',

// actions/recentchanges.php
'HISTORY' => 'history',

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
'ERROR_ACTION_REDIRECT' => 'Error action {{redirect ...}}',
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
'SEARCH_RESULT_OF' => 'R&eacute;sultat(s) de la recherche de',
'NO_RESULT_FOR' => 'Aucun r&eacute;sultat pour',

// actions/trail.php
'ERROR_ACTION_TRAIL' => 'Error action {{trail ...}}',
'INDICATE_THE_PARAMETER_TOC' => 'Indiquez le nom de la page sommaire, param&egrave;tre "toc"',

// actions/usersettings.php
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
'USERNAME_MUST_BE_WIKINAME' => 'Votre nom d\'utilisateur doit &ecirc;tre format&eacute; en NomWiki',
'YOU_MUST_SPECIFY_AN_EMAIL' => 'Vous devez sp&eacute;cifier une adresse de messagerie &eacute;lectronique',
'THIS_IS_NOT_A_VALID_EMAIL' => 'Ceci ne ressemble pas &agrave; une adresse de messagerie &eacute;lectronique',
'PASSWORDS_NOT_IDENTICAL' => 'Les mots de passe n\'&eacute;taient pas identiques',
'PASSWORD_SHOULD_HAVE_5_CHARS_MINIMUM' => 'should contain at least 5 alphanumerical characters',
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
'FAIL' => 'FAIL',
'END_OF_INSTALLATION_BECAUSE_OF_ERRORS' => 'End of installation because of errors in the configuration',

// setup/default.php
'INSTALLATION_OF_YESWIKI' => 'Installation of YesWiki',
'YOUR_SYSTEM' => 'Your system',
'EXISTENT_SYSTEM_RECOGNISED_AS_VERSION' => 'existant was recognized as version',
'YOU_ARE_UPDATING_YESWIKI_TO_VERSION' => 'You are updating YesWiki to version',
'CHECK_YOUR_CONFIG_INFORMATION_BELOW' => 'Check your configuration\'s information below',
'FILL_THE_FORM_BELOW' => 'Fill the form below',
'DEFAULT_LANGUAGE' => 'Default language',
'DEFAULT_LANGUAGE_INFOS' => 'Language used by default for YesWiki\'s interface, you still can change language for each page created',
'DATABASE_CONFIGURATION' => 'Database configuration',
'MORE_INFOS' => 'More info',
'MYSQL_SERVER' => 'MySQL server host',
'MYSQL_SERVER_INFOS' => 'IP address or network name of the computer containing your MySQL server',
'MYSQL_DATABASE' => 'MySQL database name',
'MYSQL_DATABASE_INFOS' => 'This database should exist before installation',
'MYSQL_USERNAME' => 'MySQL username',
'MYSQL_USERNAME_INFOS' => 'Required to access your database',
'TABLE_PREFIX' => 'Table prefix',
'TABLE_PREFIX_INFOS' => 'Permits to install several YesWiki on one unique database : every YesWiki installed must have a different table prefix',
'MYSQL_PASSWORD' => 'MySQL password',
'YESWIKI_WEBSITE_CONFIGURATION' => 'Configuration of your YesWiki website',
'YOUR_WEBSITE_NAME' => 'Title of your website',
'YOUR_WEBSITE_NAME_INFOS' => 'This can be a WikiName or any title which will appear on every browser\'s window or tab',
'HOMEPAGE' => 'Homepage',
'HOMEPAGE_INFOS' => 'Home page from your YesWiki. Must be a WikiName',
'KEYWORDS' => 'Keywords',
'KEYWORDS_INFOS' => 'Keywords which will appear in the HTML code (metas)',
'DESCRIPTION' => 'Description',
'DESCRIPTION_INFOS' => 'The description of your website which will appear in the HTML code (metas)',
'CREATION_OF_ADMIN_ACCOUNT' => 'Creation of the administrator account',
'ADMIN_ACCOUNT_CAN' => 'Administrator account can',
'MODIFY_AND_DELETE_ANY_PAGE' => 'Modify and delete any page',
'MODIFY_ACCESS_RIGHTS_ON_ANY_PAGE' => 'Modify access rights on any page',
'GENERATE_ACCESS_RIGHTS_ON_ANY_ACTION_OR_HANDLER' => 'Handle access rights on any action or handler',
'GENERATE_GROUPS' => 'Handle groups, add or delete users to the admin group (to have the same rights then him)',
'ALL_ADMIN_TASKS_ARE_DESCRIBED_IN_THE_PAGE' => 'All admin tasks are described in the page "AdministrationDeYesWiki" accessible from the homepage',
'USE_AN_EXISTING_ACCOUNT' => 'Use an existing account',
'NO' => 'No',
'OR_CREATE_NEW_ACCOUNT' => 'Or create a new one',
'ADMIN' => 'Administrator',
'MUST_BE_WIKINAME' => 'must be a WikiName',
'PASSWORD' => 'Password',
'EMAIL_ADDRESS' => 'Email address',
'MORE_OPTIONS' => 'Additional options',
'ADVANCED_CONFIGURATION' => '+ Advanced configuration',
'URL_REDIRECTION' => 'URL redirect',
'NEW_INSTALL_VALUES_CHANGE_ONLY_IF_YOU_KNOW_WHAT_YOU_ARE_DOING' => 'This is a fresh installation. The program will try to find appropriate values automaticaly. Only change them if you know what you are doing',
'PAGENAME_WILL_BE_ADDED_AFTER_CHANGE_JUST_FOR_REDIRECTION' => 'The names of pages will be added after the base URL of your YesWiki website. Delete the "wakka.php?wiki=" only if you use redirection (see below)',
'BASE_URL' => 'Base URL',
'REDIRECTION_SHOULD_BE_ACTIVE_ONLY_IF_USED_IN_YESWIKI' => 'The "automatic redirection" mode must be select only if you are using YesWiki with redirection of URLs (if you don\'t know what it is, don\'t activate this option)',
'ACTIVATE_REDIRECTION_MODE' => 'Activate the "automatic redirection" mode',
'OTHER_OPTIONS' => 'Other options',
'OBLIGE_TO_PREVIEW_BEFORE_SAVING_PAGE' => 'Oblige to preview before saving a page',
'AUTHORIZE_HTML_INSERTION' => 'Authorize raw HTML insertion',
'CONTINUE' => 'Continue',

// setup/install.php
'PROBLEM_WHILE_INSTALLING' => 'problem in the installation procedure',
'VERIFICATION_OF_DATAS_AND_DATABASE_INSTALLATION' => 'Verification of the configuration\'s information and installation of the database',
'VERIFY_MYSQL_PASSWORD' => 'Verification of MySQL password',
'INCORRECT_MYSQL_PASSWORD' => 'Incorrect MySQL password',
'TEST_MYSQL_CONNECTION' => 'Test of MySQL connection',
'SEARCH_FOR_DATABASE' => 'Search for database',
'GO_BACK' => 'Go back',
'NO_DATABASE_FOUND_TRY_TO_CREATE' => 'No database found with the name you indicated. We will try to create it',
'TRYING_TO_CREATE_DATABASE' => 'Trying to create database',
'DATABASE_COULD_NOT_BE_CREATED_YOU_MUST_CREATE_IT_MANUALLY' => 'Database creation impossible. You must create the database manually before installing YesWiki',
'SEARCH' => 'Search database',
'DATABASE_DOESNT_EXIST_YOU_MUST_CREATE_IT' => 'The choosen database doesn\'t exist, you must create the database manually before installing YesWiki',
'CHECKING_THE_ADMIN_PASSWORD' => 'Checking the administrator\'s password',
'CHECKING_THE_ADMIN_PASSWORD_CONFIRMATION' => 'Checking the administrator\'s password confirmation',
'ADMIN_PASSWORD_ARE_DIFFERENT' => 'The administrator\'s passwords are different',
'DATABASE_INSTALLATION' => 'Installation of the database',
'CREATION_OF_TABLE' => 'Creation of table ',
'ALREADY_CREATED' => 'Already created',
'ADMIN_ACCOUNT_CREATION' => 'Creation of administrator\'s account',
'INSERTION_OF_PAGE' => 'Insertion of page',
'ALREADY_EXISTING' => 'Already existing',
'UPDATING_FROM_WIKINI_0_1' => 'Updating WikiNi 0.1',
'TINY_MODIFICATION_OF_PAGES_TABLE' => 'Tiny modification of table for pages',
'ALREADY_DONE' => 'Already done? Hmm!',
'INSERTION_OF_USER_IN_ADMIN_GROUP' => 'Insertion of user in the admin\'s group',
'NEXT_STEP_WRITE_CONFIGURATION_FILE' => 'The next step will try to create the configuration file ',
'VERIFY_YOU_HAVE_RIGHTS_TO_WRITE_FILE' => 'Verify that le web server has the right for writing in this file, otherwise you must modify it manually',

// setup/writeconfig.php
'WRITING_CONFIGURATION_FILE' => 'Writing the configuration file',
'CREATED' => 'create',
'DONT_CHANGE_YESWIKI_VERSION_MANUALLY' => 'don\'t change yeswiki_version manually',
'WRITING_CONFIGURATION_FILE_WIP' => 'Creating the configuration file',
'FINISHED_CONGRATULATIONS' => 'It\'s finished : congratulations',
'GO_TO_YOUR_NEW_YESWIKI_WEBSITE' => 'Go to your new YesWiki website',
'IT_IS_RECOMMANDED_TO_REMOVE_WRITE_ACCESS_TO_CONFIG_FILE' => 'It is recommanded to remove write access rights to the file',
'THIS_COULD_BE_UNSECURE' => 'this could be a security issue',
'WARNING' => 'WARNING',
'CONFIGURATION_FILE' => 'the configuration file',
'CONFIGURATION_FILE_NOT_CREATED' => 'was not created',
'TRY_CHANGE_ACCESS_RIGHTS_OR_FTP_TRANSFERT' => 'Try to change the write access rights on the file. If not possible, you must create the file and copy those informations inside, then transfert it by FTP on your server in a file ',
'DIRECTLY_IN_THE_YESWIKI_FOLDER' => 'directly in the YesWiki root folder. Once you done it, your YesWiki website should work as expected',
'TRY_AGAIN' => 'Try again'

);