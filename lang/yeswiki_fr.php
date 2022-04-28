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
*@package       yeswiki
*@author        Florian Schmitt <florian@outils-reseaux.org>
*@copyright     2014 Outils-Réseaux
*/

return [

    // Commons
    'ANSWER' => 'Répondre',
    'BY' => 'par',
    'CAUTION' => 'Attention',
    'CLEAN' => 'Nettoyer',
    'COMMENTS' => 'Commentaires',
    'DELETE' => 'Supprimer',
    'DELETE_ALL' => 'Supprimer tout',
    'DELETE_ALL_SELECTED_ITEMS' => 'Supprimer tous les éléments sélectionnés',
    'DELETE_SELECTION' => 'Supprimer la sélection',
    'DEL' => 'Suppr.', // fives chars max.
    'EMAIL' => 'Email',
    'INVERT' => 'Inverser',
    'MODIFY' => 'Modifier',
    'NAME' => 'Nom',
    'PAGES' => 'Pages',
    'SUBSCRIPTION' => 'Inscription',
    'TRIPLES' => 'Triples',
    'UNKNOWN' => 'Inconnu',
    'WARNING' => 'AVERTISSEMENT',

    // wakka.php
    'INVALID_ACTION' => 'Action invalide',
    'ERROR_NO_ACCESS' => 'Accès interdit',
    'NOT_FOUND' => 'N\'existe pas',
    'NO_REQUEST_FOUND' => '$_REQUEST[] pas trouv&eacute;. Wakka n&eacute;cessite PHP 4.1.0 ou plus r&eacute;cent!',
    'SITE_BEING_UPDATED' => 'Ce site est en cours de mise &agrave; jour. Veuillez essayer plus tard.',
    'DB_CONNECT_FAIL' => 'Pour des raisons ind&eacute;pendantes de notre volont&eacute;, le contenu de ce YesWiki est temporairement inaccessible.<br>Probablement l\'acc&egrave;s &agrave; la base de donn&eacute;es a &eacute;chou&eacute;. <br><br>Veuillez r&eacute;essayer ult&eacute;rieurement, merci de votre compr&eacute;hension.',
    'LOG_DB_CONNECT_FAIL' => 'YesWiki : la connexion BDD a echouee', // sans accents car commande systeme
    'INCORRECT_PAGENAME' => 'Le nom de la page est incorrect.',
    'PERFORMABLE_ERROR' => 'Une erreur inattendue s\'est produite. Veuillez contacter l\'administrateur du site et lui communiquer l\'erreur suivante :',
    'HOMEPAGE_WIKINAME' => 'PagePrincipale',
    'MY_YESWIKI_SITE' => 'Mon site YesWiki',
    'FILE_WRITE_PROTECTED' => 'le fichier de configuration est protégé en écriture',

    // ACLs
    'DENY_READ' => 'Vous n\'êtes pas autorisé à lire cette page',
    'DENY_WRITE' => 'Vous n\'êtes pas autorisé à écrire sur cette page',
    'DENY_COMMENT' => 'Vous n\'êtes pas autorisé à commenter cette page',
    'DENY_DELETE' => 'Vous n\'êtes pas autorisé à supprimer cette page',

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

    // actions/changestyle.php
    'STYLE_SHEET' => 'Feuille de style',
    'CHANGESTYLE_ERROR' => 'Le nom \'{name}\' n\'est pas conforme à la règle de nommage imposée par l\'action ChangeStyle.'.
        'Reportez-vous à la documentation de cette action pour plus de précisions',

    // handlers/page/acls.php
    'YW_ACLS_LIST' => 'Liste des droits d\'acc&egrave;s de la page',
    'YW_ACLS_UPDATED' => 'Droits d\'acc&egrave;s mis &agrave; jour',
    'YW_NEW_OWNER' => ' et changement du propri&eacute;taire. Nouveau propri&eacute;taire : ',
    'YW_CANCEL' => 'Annuler',
    'YW_ACLS_READ' => 'Droits de lecture',
    'YW_ACLS_WRITE' => 'Droits d\'écriture',
    'YW_ACLS_COMMENT' => 'Droits pour commenter',
    'YW_CHANGE_OWNER' => 'Changer le propri&eacute;taire',
    'YW_CHANGE_NOTHING' => 'Ne rien modifier',
    'YW_CANNOT_CHANGE_ACLS' => 'Vous ne pouvez pas g&eacute;rer les permissions de cette page',

    // handlers/page/claim.php
    'YW_YOU_ARE_NOW_OWNER_OF_PAGE' => 'Vous êtes maintenant le propriétaire de cette page',
    // actions/editactionsacls.class.php
    'ACTION_RIGHTS' => 'Droits de l\'action',
    'SEE' => 'Voir',
    'ERROR_WHILE_SAVING_ACL' => 'Une erreur s\'est produite pendant l\'enregistrement de l\'ACL pour l\'action',
    'ERROR_CODE' => 'code d\'erreur',
    'NEW_ACL_FOR_ACTION' => 'Nouvelle ACL pour l\'action',
    'NEW_ACL_SUCCESSFULLY_SAVED_FOR_ACTION' => 'Nouvelle ACL enregistr&eacute;e avec succ&egrave;s pour l\'action',
    'EDIT_RIGHTS_FOR_ACTION' => '&Eacute;diter les droits de l\'action',
    'SAVE' => 'Enregistrer',
    'YW_COMMENTS_ARE_NOW_OPEN' => 'Les commentaires ont été ouverts sur cette page',
    'YW_COMMENTS_ARE_NOW_CLOSED' => 'Les commentaires ont été fermés sur cette page',
    'YW_PROBLEM_WITH_ACLS_LIST' => 'Pas de groupe valide d\'indiqué pour ouvrir les commentaires',

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
    'EDIT_EXISTING_GROUP' => 'Éditer un groupe existant',
    'DELETE_EXISTING_GROUP' => 'Supprimer un groupe existant',
    'GROUP_NAME' => 'Nom du groupe',
    'SEE_EDIT' => 'Voir / Éditer',
    'ONLY_ALPHANUM_FOR_GROUP_NAME' => 'Les noms de groupes ne peuvent contenir que des caract&egrave;res alphanum&eacute;riques',
    'LIST_GROUP_MEMBERS' => 'Liste des membres du groupe {groupName}',
    'ONE_NAME_BY_LINE' => 'un nom d\'utilisateur par ligne',

    // actions/edithandlersacls.class.php
    'HANDLER_RIGHTS' => 'Droits du handler',
    'ERROR_WHILE_SAVING_HANDLER_ACL' => 'Une erreur s\'est produite pendant l\'enregistrement de l\'ACL pour le handler',
    'NEW_ACL_FOR_HANDLER' => 'Nouvelle ACL pour le handler',
    'NEW_ACL_SUCCESSFULLY_SAVED_FOR_HANDLER' => 'Nouvelle ACL enregistr&eacute;e avec succ&egrave;s pour le handler',
    'EDIT_RIGHTS_FOR_HANDLER' => '&Eacute;diter les droits du handler',

    // actions/erasespamedcomments.class.php
    'ERASED_COMMENTS' => 'Commentaire(s) effacé(s)',
    'FORM_RETURN' => 'Retour au formulaire',
    'NO_RECENT_COMMENTS' => 'Pas de commentaires récents',
    'NO_SELECTED_COMMENTS_TO_ERASE' => 'Aucun commentaire n\'a été sélectionné pour étre effacé',

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

    // actions/mychanges.php
    'YOUR_MODIFIED_PAGES_ORDERED_BY_MODIFICATION_DATE' => 'Liste des pages que vous avez modifi&eacute;es, tri&eacute;e par date de modification',
    'YOUR_MODIFIED_PAGES_ORDERED_BY_NAME' => 'Liste des pages que vous avez modifi&eacute;es, tri&eacute;e par ordre alphab&eacute;tique',
    'YOU_DIDNT_MODIFY_ANY_PAGE' => 'Vous n\'avez pas modifi&eacute; de page',
    'YOU_ARENT_LOGGED_IN' => 'Vous n\'&ecirc;tes pas identifi&eacute;',
    'IMPOSSIBLE_TO_SHOW_YOUR_MODIFIED_PAGES' => 'impossible d\'afficher la liste des pages que vous avez modifi&eacute;es',
    'LIST_OF_PAGES_WHERE_YOU_ARE_THE_OWNER' => 'Liste des pages dont vous &ecirc;tes le propri&eacute;taire',
    'YOU_DONT_OWN_ANY_PAGE' => 'Vous n\'&ecirc;tes le propri&eacute;taire d\'aucune page',

    // actions/nextextsearch.php
    'NEWTEXTSEARCH_HINT' => 'Un caractère inconnu peut être remplacé par « ? » plusieurs par « * »',
    'NO_SEARCH_RESULT' => 'Désolé mais il n\'y a aucun de résultat pour votre recherche',
    'SEARCH_RESULTS' => 'Résultats de la recherche',

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
    'LAST COMMENT' => 'dernier commentaire',
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
    'SEARCH_RESULT_OF' => 'R&eacute;sultat(s) de la recherche de',
    'NO_RESULT_FOR' => 'Aucun r&eacute;sultat pour',

    // actions/testtriples.php
    'END_OF_EXEC' => 'Fin de l\'exécution',

    // actions/trail.php
    'ERROR_ACTION_TRAIL' => 'Erreur action {{trail ...}}',
    'INDICATE_THE_PARAMETER_TOC' => 'Indiquez le nom de la page sommaire, param&egrave;tre "toc"',

    // actions/usersettings.php
    'USER_SETTINGS' => 'Paramètres utilisateur',
    'USER_SIGN_UP' => 'S\'inscrire',
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
    'CHANGE_THE_PASSWORD' => 'Changement de mot de passe',
    'YOUR_OLD_PASSWORD' => 'Votre ancien mot de passe',
    'NEW_PASSWORD' => 'Nouveau mot de passe',
    'CHANGE' => 'Changer',
    'USERNAME_MUST_BE_WIKINAME' => 'Votre nom d\'utilisateur ne doit pas commencer par \'!\',\'@\',\'\\\', \'/\' ni \'#\' avec 3 caractères minimum.',
    'YOU_MUST_SPECIFY_AN_EMAIL' => 'Vous devez sp&eacute;cifier une adresse de messagerie &eacute;lectronique',
    'THIS_IS_NOT_A_VALID_EMAIL' => 'Ceci ne ressemble pas &agrave; une adresse de messagerie &eacute;lectronique',
    'PASSWORDS_NOT_IDENTICAL' => 'Les mots de passe n\'&eacute;taient pas identiques',
    'PASSWORD_SHOULD_HAVE_5_CHARS_MINIMUM' => 'doit contenir au minimum 5 caract&egrave;res alphanum&eacute;riques',
    'YOU_MUST_ACCEPT_COOKIES_TO_GET_CONNECTED' => 'Vous devez accepter les cookies pour pouvoir vous connecter',
    'IF_YOU_ARE_REGISTERED_LOGGIN_HERE' => 'Si vous &ecirc;tes d&eacute;j&agrave; enregistr&eacute;, identifiez-vous ici',
    'PASSWORD_5_CHARS_MINIMUM' => 'Mot de passe (5 caract&egrave;res minimum)',
    'REMEMBER_ME' => 'Se souvenir de moi',
    'IDENTIFICATION' => 'Identification',
    'FILL_THE_NEXT_FIELDS_IF_YOU_LOGGIN_FOR_THE_FIRST_TIME_AND_REGISTER' => 'Les champs suivants sont &agrave; remplir si vous vous identifiez pour la premi&egrave;re fois (vous cr&eacute;erez ainsi un compte)',
    'PASSWORD_CONFIRMATION' => 'Confirmation du mot de passe',
    'NEW_ACCOUNT' => 'Nouveau compte',
    'LOGGED_USERS_ONLY_ACTION' => 'Il faut être connecté pour pouvoir exécuter cette action',
    'USER_DELETE' => 'Supprimer l\'utilisateur',


    // actions/wantedpages.php
    'NO_PAGE_TO_CREATE' => 'Aucune page &agrave; cr&eacute;er',

    // includes/controllers/CsrfController.php
    'NO_CSRF_TOKEN_ERROR' => 'Erreur de conception du site : Le formulaire de soumission ne contenait pas '.
        'le jeton d\'identification unique nécessaire aux mécanismes internes de sécurité.',
    'CSRF_TOKEN_FAIL_ERROR' => 'Cette page a peut-être été ouverte une seconde fois. '.
        'Veuillez renouveler la demande depuis cette fenêtre (le jeton interne de sécurité n\'était pas bon).',

    // javascripts/favorites.js
    'FAVORITES_ADD' => 'Ajouter aux favoris',
    'FAVORITES_REMOVE' => 'Retirer des favoris',

    // templates/actions/my-favorites.twig
    'FAVORITES_DELETE_ALL' => 'Supprimer tous les favoris',
    'FAVORITES_MY_FAVORITES' => 'Mes favoris',
    'FAVORITES_NO_FAVORITE' => 'Pas de favori',
    'FAVORITES_NOT_ACTIVATED' => 'L\'usage des favoris n\'est pas activé sur ce site.',
    'FAVORITES_NOT_CONNECTED' => 'L\'usage des favoris n\'est possible que pour les personnes connectées.',

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
    'NAVIGATOR_LANGUAGE' => 'Langue du navigateur',
    'DEFAULT_LANGUAGE_INFOS' => 'Langue utilis&eacute;e par d&eacute;faut pour l\'interface de YesWiki, il sera toujours possible de changer de langue pour chacune des pages cr&eacute;&eacute;es',
    'GENERAL_CONFIGURATION' => 'Configuration générale',
    'DATABASE_CONFIGURATION' => 'Base de donn&eacute;es',
    'MORE_INFOS' => 'Aide',
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
    'CREATION_OF_ADMIN_ACCOUNT' => 'Compte administrateur',
    'ADMIN_ACCOUNT_CAN' => 'Le compte administrateur permet de',
    'MODIFY_AND_DELETE_ANY_PAGE' => 'Modifier et supprimer n\'importe quelle page',
    'MODIFY_ACCESS_RIGHTS_ON_ANY_PAGE' => 'Modifier les droits d\'acc&egrave;s &agrave; n\'importe quelle page',
    'GENERATE_ACCESS_RIGHTS_ON_ANY_ACTION_OR_HANDLER' => 'G&eacute;rer les droits d\'acc&egrave;s &agrave; n\'importe quelle action ou handler',
    'GENERATE_GROUPS' => 'G&eacute;rer les groupes, ajouter/supprimer des utilisateurs au groupe administrateur (ayant les m&ecirc;mes droits que lui)',
    'ALL_ADMIN_TASKS_ARE_DESCRIBED_IN_THE_PAGE' => 'Toutes les t&acirc;ches d\'administration sont accessibles depuis le bouton avec la roue crant&eacute;e',
    'USE_AN_EXISTING_ACCOUNT' => 'Utiliser un compte existant',
    'NO' => 'Non',
    'YES' => 'Oui',
    'OR_CREATE_NEW_ACCOUNT' => 'Ou cr&eacute;er un nouveau compte',
    'ADMIN' => 'Administrateur',
    'MUST_BE_WIKINAME' => 'doit &ecirc;tre un NomWiki',
    'PASSWORD' => 'Mot de passe',
    'EMAIL_ADDRESS' => 'Adresse e-mail',
    'MORE_OPTIONS' => 'Options suppl&eacute;mentaires',
    'ADVANCED_CONFIGURATION' => '+ Configuration avanc&eacute;e',
    'URL_REDIRECTION' => 'Redirection d\'URL',
    'NEW_INSTALL_VALUES_CHANGE_ONLY_IF_YOU_KNOW_WHAT_YOU_ARE_DOING' => 'Ceci est une nouvelle installation. Le programme d\'installation va essayer de trouver les valeurs appropri&eacute;es. Changez-les uniquement si vous savez ce que vous faites',
    'PAGENAME_WILL_BE_ADDED_AFTER_CHANGE_JUST_FOR_REDIRECTION' => 'Les noms des pages seront directement rajout&eacute;s &agrave; l\'URL de base de votre site YesWiki. Supprimez la partie "?" uniquement si vous utilisez la redirection (voir ci apr&egrave;s)',
    'BASE_URL' => 'URL de base',
    'REDIRECTION_SHOULD_BE_ACTIVE_ONLY_IF_USED_IN_YESWIKI' => 'ATTENTION : à n\'activer que si nginx ou apache sont configurés pour ré-ecrire les URL sans le point d\'interrogation',
    'HTML_INSERTION_HELP_TEXT' => 'Augmente grandement les fonctionnalités du wiki, en permettant l\'ajout de vidéos et iframe par exemple, mais est moins sécurisé',
    'INDEX_HELP_TEXT' => 'Indique dans les meta-données html et dans le fichier robots.txt si votre site doit etre indexé par les moteurs de recherche ou pas',
    'ACTIVATE_REDIRECTION_MODE' => 'Activation du mode "redirection automatique"',
    'OTHER_OPTIONS' => 'Autres options',
    'OBLIGE_TO_PREVIEW_BEFORE_SAVING_PAGE' => 'Imposer de faire un aper&ccedil;u avant de pouvoir sauver une page',
    'AUTHORIZE_HTML_INSERTION' => 'Autoriser l\'insertion de HTML brut',
    'AUTHORIZE_INDEX_BY_ROBOTS' => 'Autoriser l\'indexation par les moteurs de recherche',
    'CONTINUE' => 'Continuer',

    // setup/install.php
    'PROBLEM_WHILE_INSTALLING' => 'probl&egrave;me dans la proc&eacute;dure d\'installation',
    'VERIFICATION_OF_DATAS_AND_DATABASE_INSTALLATION' => 'Test de la configuration et installation de la base de donn&eacute;es',
    'VERIFY_MYSQL_PASSWORD' => 'V&eacute;rification mot de passe MySQL',
    'INCORRECT_MYSQL_PASSWORD' => 'Le mot de passe MySQL est incorrect',
    'TEST_MYSQL_CONNECTION' => 'Test connexion MySQL',
    'SEARCH_FOR_DATABASE' => 'Recherche base de donn&eacute;es',
    'GO_BACK' => 'Retour',
    'NO_DATABASE_FOUND_TRY_TO_CREATE' => 'La base de donn&eacute;es que vous avez choisie n\'existe pas. Nous allons tenter de la cr&eacute;er',
    'TRYING_TO_CREATE_DATABASE' => 'Tentative de cr&eacute;ation de la base de donn&eacute;es',
    'DATABASE_COULD_NOT_BE_CREATED_YOU_MUST_CREATE_IT_MANUALLY' => 'Cr&eacute;ation de la base impossible. Vous devez cr&eacute;er cette base manuellement avant d\'installer YesWiki',
    'DATABASE_DOESNT_EXIST_YOU_MUST_CREATE_IT' => 'La base de donn&eacute;es que vous avez choisie n\'existe pas, vous devez la cr&eacute;er avant d\'installer YesWiki',
    'CHECKING_THE_ADMIN_PASSWORD' => 'V&eacute;rification du mot de passe Administrateur',
    'CHECKING_THE_ADMIN_PASSWORD_CONFIRMATION' => 'V&eacute;rification de la concordance des deux mots de passes administrateurs',
    'CHECKING_ROOT_PAGE_NAME' => 'V&eacute;rification du nom de la page d\'accueil',
    'INCORRECT_ROOT_PAGE_NAME' => 'Le nom de la page d\'accueil doit uniquement contenir des lettres non accentuées, des chiffres, \'_\', \'-\' ou \'.\'',
    'ADMIN_PASSWORD_ARE_DIFFERENT' => 'Les mots de passe Aaministrateur sont diff&eacute;rents',
    'DATABASE_INSTALLATION' => 'Installation de la base de donn&eacute;es',
    'CREATION_OF_TABLES' => 'Cr&eacute;ation des tables, de l\'administrateur et du groupe admins',
    'SQL_FILE_NOT_FOUND' => 'Fichier SQL non trouv&eacute;',
    'NOT_POSSIBLE_TO_CREATE_SQL_TABLES' => 'Impossible de créer les tables SQL.',
    'ALREADY_CREATED' => 'D&eacute;j&agrave; cr&eacute;&eacute;e',
    'ADMIN_ACCOUNT_CREATION' => 'Cr&eacute;ation du compte Administrateur',
    'INSERTION_OF_PAGES' => 'Insertion des pages par d&eacute;faut',
    'ALREADY_EXISTING' => 'Existe d&eacute;j&agrave;',
    'UPDATING_FROM_WIKINI_0_1' => 'En cours de mise &agrave; jour de WikiNi 0.1',
    'TINY_MODIFICATION_OF_PAGES_TABLE' => 'Modification très légère de la table des pages',
    'ALREADY_DONE' => 'Already done? Hmm!',
    'INSERTION_OF_USER_IN_ADMIN_GROUP' => 'Insertion de l\'utilisateur sp&eacute;cifi&eacute; dans le groupe admin',
    'NEXT_STEP_WRITE_CONFIGURATION_FILE' => 'A l\'&eacute;tape suivante, le programme d\'installation va essayer d\'&eacute;crire le fichier de configuration ',
    'VERIFY_YOU_HAVE_RIGHTS_TO_WRITE_FILE' => 'Assurez vous que le serveur web a bien le droit d\'&eacute;crire dans ce fichier, sinon vous devrez le modifier manuellement',
    'CHECK_EXISTING_TABLE_PREFIX' => 'Vérification de l\'existence du préfixe de table',
    'TABLE_PREFIX_ALREADY_USED' => 'Le préfixe de table est déjà utilisé. Veuillez en choisir un nouveau.',

    // setup/writeconfig.php
    'WRITING_CONFIGURATION_FILE' => '&Eacute;criture du fichier de configuration',
    'CREATED' => 'cr&eacute;&eacute;e',
    'DONT_CHANGE_YESWIKI_VERSION_MANUALLY' => 'ne changez pas la yeswiki_version manuellement',
    'WRITING_CONFIGURATION_FILE_WIP' => 'Cr&eacute;ation du fichier de configuration en cours',
    'FINISHED_CONGRATULATIONS' => 'Voila c\'est termin&eacute;, f&eacute;licitations',
    'GO_TO_YOUR_NEW_YESWIKI_WEBSITE' => 'Aller sur votre nouveau site YesWiki',
    'IT_IS_RECOMMANDED_TO_REMOVE_WRITE_ACCESS_TO_CONFIG_FILE' => 'Il est conseill&eacute; de retirer l\'acc&egrave;s en &eacute;criture au fichier',
    'THIS_COULD_BE_UNSECURE' => 'ceci peut &ecirc;tre une faille dans la s&eacute;curit&eacute;',
    'CONFIGURATION_FILE' => 'le fichier de configuration',
    'CONFIGURATION_FILE_NOT_CREATED' => 'n\'a pu &ecirc;tre cr&eacute;&eacute;',
    'TRY_CHANGE_ACCESS_RIGHTS_OR_FTP_TRANSFERT' => 'Veuillez vous assurez que votre serveur a les droits d\'acc&egrave;s en &eacute;criture pour ce fichier. Si pour une raison quelconque vous ne pouvez pas faire &ccedil;a, vous devez copier les informations suivantes dans un fichier et les transf&eacute;rer au moyen d\'un logiciel de transfert de fichier (ftp) sur le serveur dans un fichier ',
    'DIRECTLY_IN_THE_YESWIKI_FOLDER' => 'directement dans le r&eacute;pertoire de YesWiki. Une fois que vous aurez fait cela, votre site YesWiki devrait fonctionner correctement',
    'TRY_AGAIN' => 'Essayer &agrave; nouveau',

    // API
    'USERS' => 'Utilisateurs',
    'GROUPS' => 'Groupes',

    // YesWiki\User class
    'USER_CONFIRM_DELETE' => 'Êtes-vous sûr·e de vouloir supprimer l’utilisateur·ice ?',
    'USER_DELETE_LONE_MEMBER_OF_GROUP' => 'Vous ne pouvez pas supprimer un utilisateur qui est seul dans au moins un groupe',
    'USER_DELETE_QUERY_FAILED' => 'La requête de suppression de l\'utilisateur dans la base de données a échoué',
    'USER_EMAIL_S_MAXIMUM_LENGTH_IS' => 'Le nomnbre maximum de caractères d\'un email d\'utilisateur est',
    'USER_LISTGROUPMEMBERSHIPS_QUERY_FAILED' => 'La requête pour lsiter les groupes auquels l\'utilisateur appartient a échoué',
    'USER_MUST_BE_ADMIN_TO_DELETE' => 'Vous devez être administrateur pour supprimer un utilisateur',
    'USER_NAME_S_MAXIMUM_LENGTH_IS' => 'Le nomnbre maximum de caractères d\'un nom d\'utilisateur est',
    'USER_NO_SPACES_IN_PASSWORD' => 'Les espaces ne sont pas autorisés dans un mot de passe',
    'USER_PASSWORD_MINIMUM_NUMBER_OF_CHARACTERS_IS' => 'Le nombre minimum de caractères d\'un mot de passe est',
    'USER_PASSWORDS_NOT_IDENTICAL' => 'Les deux mots de passe saisis doivent être identiques',
    'USER_PASSWORD_TOO_SHORT' => 'Mot de passe trop court',
    'USER_THIS_EMAIL_IS_ALLREADY_USED_ON_THIS_WIKI' => 'L\'email saisi est déjà utilisé sur ce wiki',
    'USER_THIS_IS_NOT_A_VALID_NAME' => 'Ceci n\'est pas un nom d\'utilisateur valide',
    'USER_THIS_IS_NOT_A_VALID_EMAIL' => 'Ceci n\'est pas un email valide',
    'USER_UPDATE_QUERY_FAILED' => 'La requête de mise à jour de l\'utilisateur dans la base de données a échoué',
    'USER_YOU_MUST_SPECIFY_A_NAME' => 'Veuillez saisir un nom pour l\'utilisateur',
    'USER_YOU_MUST_SPECIFY_AN_EMAIL' => 'Veuillez saisir un email pour l\'utilisateur',
    'USER_USERSTABLE_MISTAKEN_ARGUMENT' => 'l\'action usertable a reçu un argument non autorisé',
    'USER_WRONG_PASSWORD' => 'Mot de passe incorrect',
    'USER_INCORRECT_PASSWORD_KEY' => 'La clef de validation du mot de passe est incorrecte',
    'USER_PASSWORD_UPDATE_FAILED' => 'La modification du mot de passe a échoué',
    'USER_NOT_LOGGED_IN_CANT_LOG_OUT' => 'Déconnexion impossible car personne n\'est connecté',
    'USER_TRYING_TO_LOG_WRONG_USER_OUT' => 'Vous essayez de déconnecter quelqu\'un d\'autre',
    'USER_CREATION_FAILED' => 'La création de l\'utilisateur a échoué',
    'USER_LOAD_BY_NAME_QUERY_FAILED' => 'La requête de chargement de l\'utilisateur par son nom depuis la base de données a échoué',
    'USER_NO_USER_WITH_THAT_NAME' => 'Il n\'y a aucun utilisateur avec ce nom',
    'USER_LOAD_BY_EMAIL_QUERY_FAILED' => 'La requête de chargement de l\'utilisateur par son email depuis la base de données a échoué',
    'USER_NO_USER_WITH_THAT_EMAIL' => 'Il n\'y a aucun utilisateur avec cet email',
    'USER_UPDATE_MISSPELLED_PROPERTIES' => 'La liste des champs à modifier par updateIntoDB est certainement défectueuse',
    'USER_CANT_DELETE_ONESELF' => 'Vous ne pouvez supprimer votre compte',
    'USER_TRYING_TO_MODIFY_AN_INEXISTANT_USER' => 'L\'utilisateur en cours de modification n\'existe pas dans la base de données',
    'USER_YOU_ARE_NOW_DISCONNECTED' => 'Vous êtes à présent déconnecté',
    'USER_PARAMETERS_SAVED' => 'Paramètres sauvegardés',
    'USER_DELETED' => 'utilisateur supprimé',
    'USER_PASSWORD_CHANGED' => 'Mot de passe modifié',
    'USER_EMAIL_ADDRESS' => 'Adresse de messagerie électronique',
    'USER_DOUBLE_CLICK_TO_EDIT' => 'Éditer en double-cliquant',
    'USER_SHOW_COMMENTS_BY_DEFAULT' => 'Par défaut, montrer les commentaires',
    'USER_MAX_NUMBER_OF_LASTEST_COMMENTS' => 'Nombre maximum de derniers commentaires',
    'USER_MAX_NUMBER_OF_VERSIONS' => 'Nombre maximum de versions',
    'USER_MOTTO' => 'Votre devise',
    'USER_UPDATE' => 'Mise &agrave; jour',
    'USER_DISCONNECT' => 'Déconnexion',
    'USER_CHANGE_THE_PASSWORD' => 'Changement de mot de passe',
    'USER_OLD_PASSWORD' => 'Votre ancien mot de passe',
    'USER_NEW_PASSWORD' => 'Nouveau mot de passe',
    'USER_CHANGE' => 'Changer',
    'USER_MUST_ACCEPT_COOKIES_TO_GET_CONNECTED' => 'Vous devez accepter les cookies pour pouvoir vous connecter',
    'USER_WIKINAME' => 'Votre NomWiki',
    'USER_USERNAME' => 'Votre nom d\'utilisateur, utilisatrice',
    'USER_PASSWORD_CONFIRMATION' => 'Confirmation du mot de passe',
    'USER_NEW_ACCOUNT' => 'Nouveau compte',
    'USER_PASSWORD' => 'Mot de passe',
    'USER_ERRORS_FOUND' => 'Erreur(s) trouvée(s)',
    'USER_YOU_MUST_SPECIFY_A_POSITIVE_INTEGER_FOR' => 'Il faut une valeur entier positif pour ',

    // YesWiki\Database class
    'DATABASE_QUERY_FAILED' => 'La requête a échoué {\YesWiki\Database}',
    'DATABASE_YOU_MUST_FIRST_SET_ARGUMENT' => 'Il manque des arguments pour un objet de la classe \YesWiki\Database',
    'DATABASE_MISSING_ARGUMENT' => ' manque(nt)',

    // YesWiki\Session class
    'SESSION_YOU_MUST_FIRST_SET_ARGUMENT' => 'Il manque l\'argument pour un objet de la classe \YesWiki\Session',

    // gererdroits
    'ACLS_RESERVED_FOR_ADMINS' => 'Cette action est r&eacute;serv&eacute;e aux admins',
    'ACLS_NO_SELECTED_PAGE' => 'Aucune page n\'a &eacute;t&eacute; s&eacute;lectionn&eacute;e.',
    'ACLS_NO_SELECTED_RIGHTS' => 'Vous n\'avez pas s&eacute;lectionn&eacute; de droits &agrave; modifier.',
    'ACLS_RIGHTS_WERE_SUCCESFULLY_CHANGED' => 'Droit modifi&eacute;s avec succ&egrave;s',
    'ACLS_SELECT_PAGES_TO_MODIFY' => 'Cochez les pages que vous souhaitez modifier et choisissez une action en bas de page',
    'ACLS_PAGE' => 'Page',
    'ACLS_FOR_SELECTED_PAGES' => 'Actions pour les pages cochées ci dessus',
    'ACLS_RESET_SELECTED_PAGES' => 'Réinitialiser (avec les valeurs par défaut définies dans',
    'ACLS_REPLACE_SELECTED_PAGES' => 'Remplacer (Les droits actuels seront supprim&eacute;s)',
    'ACLS_HELPER' => 'Séparez chaque entrée des virgules, par exemple</br>
    <b>*</b> (tous les utilisateurs)</br>
    <b>+</b> (utilisateurs enregistrés)</br>
    <b>%</b> (créateur de la fiche/page)</br>
    <b>@nom_du_groupe</b> (groupe d\'utilisateur, ex: @admins)</br>
    <b>JamesBond</b> (nom YesWiki d\'un utilisateur)</br>
    <b>!SuperCat</b> (négation, SuperCat n\'est pas autorisé)</br>',
    'ACLS_MODE_SIMPLE' => 'Mode simple',
    'ACLS_MODE_ADVANCED' => 'Mode avancé',
    'ACLS_NO_CHANGE' => 'Ne rien changer',
    'ACLS_EVERYBODY' => 'Tout le monde',
    'ACLS_AUTHENTIFICATED_USERS' => 'Utilisateurs connectés',
    'ACLS_OWNER' => 'Propriétaire de la page',
    'ACLS_ADMIN_GROUP' => 'Groupe admin',
    'ACLS_LIST_OF_ACLS' => 'Liste des droits séparés par des virgules',
    'ACLS_UPDATE' => 'Mettre &agrave; jour',
    'ACLS_COMMENTS_CLOSED' => 'Commentaires fermés',

    // include/services/ThemeManager.php
    'THEME_MANAGER_THEME_FOLDER' => 'Le dossier du thème ',
    'THEME_MANAGER_SQUELETTE_FILE' => 'Le fichier du squelette ',
    'THEME_MANAGER_NOT_FOUND' => ' n\'a pas été trouvé.',
    'THEME_MANAGER_ERROR_GETTING_FILE' => 'Une erreur s\'est produite en chargeant ce fichier : ',
    'THEME_MANAGER_CLICK_TO_INSTALL' => 'Cliquer pour installer le thème ',
    'THEME_MANAGER_AND_REPAIR' => ' et réparer le site',
    'THEME_MANAGER_LOGIN_AS_ADMIN' => 'Veuillez vous connecter en tant qu\'administrateur pour faire la mise à jour.',

    // actions/EditConfigAction.php
    'EDIT_CONFIG_TITLE' => 'Modification du fichier de configuration',
    'EDIT_CONFIG_CURRENT_VALUE' => 'Valeur actuelle ',
    'EDIT_CONFIG_SAVE' => 'Configuration sauvegardée',
    'EDIT_CONFIG_HINT_WAKKA_NAME' => 'Titre de votre wiki',
    'EDIT_CONFIG_HINT_ROOT_PAGE' => 'Nom de la page d\'accueil',
    'EDIT_CONFIG_HINT_DEFAULT_WRITE_ACL' => 'Droits d\'écriture par défaut des pages (* pour tous, + pour personnes identifiées, @admins pour groupe admin)',
    'EDIT_CONFIG_HINT_DEFAULT_READ_ACL' => 'Droits de lecture par défaut des pages (* pour tous, + pour personnes identifiées, @admins pour groupe admin)',
    'EDIT_CONFIG_HINT_DEFAULT_COMMENT_ACL' => 'Droits de commentaires par défaut des pages (comment-closed pour ferme, * pour tous, + pour personnes identifiées, @admins pour groupe admin)',
    'EDIT_CONFIG_HINT_COMMENTS_ACTIVATED' => 'Commentaires activés (true ou false)',
    'EDIT_CONFIG_HINT_DEBUG' => 'Activer le mode de debug (yes ou no)',
    'EDIT_CONFIG_HINT_DEFAULT_LANGUAGE' => 'Langue par défaut (fr ou en ou ... auto = langue du navigateur)',
    'EDIT_CONFIG_HINT_CONTACT_FROM' => 'Remplacer le mail utilisé comme expéditeur des messages',
    'EDIT_CONFIG_HINT_MAIL_CUSTOM_MESSAGE' => 'Message personnalisé des mails envoyés depuis l\'action contact',
    'EDIT_CONFIG_HINT_PASSWORD_FOR_EDITING' => 'Mot de passe demandé pour modifier les pages (voir doc gestion des spams)',
    'EDIT_CONFIG_HINT_PASSWORD_FOR_EDITING_MESSAGE' => 'Message informatif pour demander le mot de passe (voir doc gestion des spams)',
    'EDIT_CONFIG_HINT_ALLOW_DOUBLECLIC' => 'Autoriser le doubleclic pour éditer les menus et pages spéciales (true ou false)',
    'EDIT_CONFIG_HINT_TIMEZONE' => 'Fuseau horaire du site (ex. UCT, Europe/Paris, Europe/London, GMT = utiliser celui du serveur,)',
    'EDIT_CONFIG_HINT_FAVICON' => 'Icône du site (emoji ou URL vers une image PNG)',
    'EDIT_CONFIG_HINT_ALLOWED_METHODS_IN_IFRAME' => 'Méthodes autorisées à être affichées dans les iframes (iframe,editiframe,bazariframe,render,all = autoriser tout)',
    'EDIT_CONFIG_HINT_REVISIONSCOUNT' => 'Nombre maximum de versions d\'une page affichées par le handler `/revisions`.',
    'EDIT_CONFIG_HINT_DEFAULT_COMMENT_AVATAR' => 'Image d\'avatar par défaut pour les commentaires (URL vers une image)',
    'EDIT_CONFIG_HINT_HTMLPURIFIERACTIVATED' => 'Activer le nettoyage HTML avant sauvegarde. Attention, modifie le contenu à la sauvegarde ! (true ou false)',
    'EDIT_CONFIG_HINT_FAVORITES_ACTIVATED' => 'Activer les favoris (true ou false)',
    'EDIT_CONFIG_GROUP_CORE' => 'Paramètres Principaux',
    'EDIT_CONFIG_GROUP_ACCESS' => 'Droit d\'accès',
    'EDIT_CONFIG_GROUP_EMAIL' => 'Emails',

    // actions/userstable.php
    'USERSTABLE_USER_DELETED' => 'L\'utilisateur "{username}" a été supprimé.',
    'USERSTABLE_USER_NOT_DELETED' => 'L\'utilisateur "{username}" n\'a pas été supprimé.',
    'USERSTABLE_NOT_EXISTING_USER' => 'L\'utilisateur "{username}" n\'existe pas !',
    'GROUP_S' => 'Groupe(s)',

    // handlers/deletepage
    'DELETEPAGE_CANCEL' => 'Annuler',
    'DELETEPAGE_CONFIRM' => 'Voulez-vous vraiment supprimer d&eacute;finitivement la page {tag}&nbsp;?',
    'DELETEPAGE_CONFIRM_WHEN_BACKLINKS' => 'Voulez-vous vraiment supprimer d&eacute;finitivement la page {tag} malgr&eacute; la pr&eacute;sence de liens&nbsp;?',
    'DELETEPAGE_DELETE' => 'Supprimer',
    'DELETEPAGE_MESSAGE' => 'La page {tag} a d&eacute;finitivement &eacute;t&eacute; supprim&eacute;e',
    'DELETEPAGE_NOT_ORPHEANED' => 'Cette page n\'est pas orpheline.',
    'DELETEPAGE_NOT_OWNER' => 'Vous n\'&ecirc;tes pas le propri&eacute;taire de cette page.',
    'DELETEPAGE_PAGES_WITH_LINKS_TO' => 'Pages ayant un lien vers {tag} :',
    'DELETEPAGE_NOT_DELETED' => 'Page non supprimée.',

    // handlers/edit
    'EDIT_ALERT_ALREADY_SAVED_BY_ANOTHER_USER' => 'ALERTE : '.
        'Cette page a &eacute;t&eacute; modifi&eacute;e par quelqu\'un d\'autre pendant que vous l\'&eacute;ditiez.'."\n".
        'Veuillez copier vos changements et r&eacute;&eacute;diter cette page.',
    'EDIT_NO_WRITE_ACCESS' => 'Vous n\'avez pas acc&egrave;s en &eacute;criture &agrave; cette page !',
    'EDIT_NO_CHANGE_MSG' => 'Cette page n\'a pas &eacute;t&eacute; enregistr&eacute;e car elle n\'a subi aucune modification.',
    'EDIT_PREVIEW' => 'Aper&ccedil;u',

    // handlers/update
    'UPDATE_ADMIN_PAGES' => 'Mettre à jour les pages de gestion',
    'UPDATE_ADMIN_PAGES_CONFIRM' => 'Confirmer la mise à jour des pages : ',
    'UPDATE_ADMIN_PAGES_HINT' => 'Mets à jour les pages de gestion avec les dernières fonctionnalités. Ceci est réversible.',
    'UPDATE_ADMIN_PAGES_ERROR' => 'Il n\'a pas été possible de mettre à jour toutes les pages de gestion !',
    'UPDATE_PAGE_NOT_FOUND_IN_DEFAULT_SQL' => 'la page "{{page}}" n\'a pas été trouvée dans default-content.sql',

    // handlers/referrers_sites.php
    'LINK_TO_REFERRERS_DOMAINS' => 'Domaines faisant r&eacute;f&eacute;rence &agrave; ce wiki ({beginLink}voir la liste des pages externes{endLink}):',
    'LINK_TO_REFERRERS_SITES' => 'Sites faisant r&eacute;f&eacute;rence &agrave; ce wiki ({beginLink}voir la liste des domaines{endLink}):',
    'LINK_TO_REFERRERS_SITES_ONLY_TAG' => 'Voir les domaines faisant r&eacute;f&eacute;rence &agrave; {tag} seulement',
    'LINK_TO_REFERRERS_SITES_PAGES_ONLY_TAG' => 'Voir les r&eacute;f&eacute;rences &agrave; {tag} seulement',
    'LINK_TO_REFERRERS_ALL_DOMAINS' => 'Voir tous les domaines faisant r&eacute;f&eacute;rence',
    'LINK_TO_REFERRERS_ALL_REFS' => 'Voir toutes les r&eacute;f&eacute;rences',
    'LINK_TO_REFERRERS_SITES_NO_GLOBAL' => 'Domaines faisant r&eacute;f&eacute;rence &agrave; {tag}{since} ({beginLink}voir la liste des pages externes{endLink}):',
    'LINK_TO_REFERRERS_NO_GLOBAL' => 'Pages externes faisant r&eacute;f&eacute;rence &agrave; {tag}{since} ({beginLink}voir la liste des domaines{endLink}):',
    'REFERRERS_SITES_SINCE' => 'depuis {time}',
    'REFERRERS_SITES_24_HOURS' => '24 heures',
    'REFERRERS_SITES_X_DAYS' => '{nb} jours',

    // handlers/revisions
    'SUCCESS_RESTORE_REVISION' => 'La version a bien été restaurée',
    'TITLE_PAGE_HISTORY' => 'Historique de la page',
    'TITLE_ENTRY_HISTORY' => 'Historique de la fiche',
    'REVISION_VERSION' => 'Version N°',
    'REVISION_ON' => 'du',
    'REVISION_BY' => 'par',
    'CURRENT_VERSION' => 'Version actuelle',
    'RESTORE_REVISION' => 'Restaurer cette version',
    'DISPLAY_WIKI_CODE' => 'Afficher le code Wiki',

    // handlers/show
    'COMMENT_INFO' => 'Ceci est un commentaire sur {tag} post&eacute; par {user} &agrave; {time}',
    'EDIT_ARCHIVED_REVISION' => 'R&eacute;&eacute;diter cette version archiv&eacute;e',
    'REVISION_IS_ARCHIVE_OF_TAG_ON_TIME' => 'Ceci est une version archivée de {link} à {time}',
    'REDIRECTED_FROM' => 'Redirig&eacute; depuis {linkFrom}',

    // handlers/page/show + handlers/page/iframe
    'NOT_FOUND_PAGE' => 'Cette page n\'existe pas encore, voulez-vous la {beginLink}créer{endLink} ?',
    'NOT_AUTORIZED_TO_READ_PAGE' => 'Vous n\'êtes pas autorisé à lire cette page',

    'YW_ADD_COMMENT' => 'Ajouter votre commentaire',
    'YW_WRITE_COMMENT_HERE' => 'Ecrire votre commentaire ici...',

    // templates/multidelete-macro.twig
    'NUMBER_OF_ELEMENTS' => 'Nombre d\'éléments sélectionnés',

    // Comments
    'COMMENT_EMPTY_NOT_SAVED' => 'Commentaire vide, pas de sauvegarde.',
    'COMMENT_LOGIN' => 'Se connecter pour commenter',
    'COMMENT_NOT_ENOUGH_RIGHTS' => 'Vous n\'avez pas les droits suffisants pour commenter.',
    'COMMENT_PUBLISHED' => 'Commentaire publié !',
    'COMMENT_REMOVED' => 'Le commentaire a été supprimé.',
    'COMMENTS_CURRENTLY_CLOSED' => 'L\'ajout de commentaires sur cette page est présentement désactivé',
    'USER_MUST_BE_LOGGED_TO_COMMENT' => 'Vous devez vous identifier pour pouvoir publier un commentaire.',
    'USER_NOT_ALLOWED_TO_COMMENT' => 'Vous n\'êtes pas dans un groupe autorisé à laisser des commentaires.',
    'NOT_AUTORIZED_TO_REMOVE_COMMENT' => 'Vous n\'êtes pas autorisé à supprimer ce commentaire.',

    // YesWiki
    'UNKNOWN_INTERWIKI' => 'interwiki inconnu',

    // reactions
    'REACTION_EMPTY_ID' => 'le paramètre "id" doit obligatoirement être renseigné',
    'REACTION_LIKE' => 'J\'approuve',
    'REACTION_DISLIKE' => 'Je n\'approuve pas',
    'REACTION_ANGRY' => 'Faché·e',
    'REACTION_SURPRISED' => 'Surpris·e',
    'REACTION_THINKING' => 'Dubitatif·ve',
    'REACTION_LOGIN_TO_REACT' => 'Pour réagir, identifiez-vous!',
    'REACTION_SHARE_YOUR_REACTION' => 'Partagez votre réaction à propos de ce contenu',
    'REACTION_TO_ALLOW_REACTION' => 'Pour vous permettre de réagir',
    'REACTION_PLEASE_LOGIN' => 's\'identifier',
    'REACTION_NB_REACTIONS_LEFT' => 'choix possible(s)',
    'REACTION_ADMINISTER_REACTIONS' => 'Administrer les réactions',
    'REACTION_CONNECT_AS_ADMIN' => 'Veuillez vous connecter en tant qu\'admin pour administrer les réactions.',
    'REACTION_USER' => 'Utilisateur·ice',
    'REACTION_YOUR_REACTIONS' => 'Vos réactions',
    'REACTION_VOTE' => 'Vote',
    'REACTION_DATE' => 'Date',
    'REACTION_DATE_UNKNOWN' => 'Date inconnue',
    'REACTION_DELETE' => 'Supprimer',
    'REACTION_DELETE_ALL' => 'Tout supprimer',
    'REACTION_LOGIN_TO_SEE_YOUR_REACTION' => 'Se connecter pour voir vos réactions.',
    'REACTION_YOU_VOTED' => 'Vous avez voté',
    'REACTION_FOR_POLL' => 'au sondage',
    'REACTION_FROM_PAGE' => 'de la page',
    'REACTION_ON_ENTRY' => 'Réaction sur une fiche',
    'REACTION_TITLE_PARAM_NEEDED' => 'Le paramètre \'titre\' est obligatoire',
];
