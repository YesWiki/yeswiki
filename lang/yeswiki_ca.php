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
* Fitxer de traducció al català de YesWiki
*
*@package 		yeswiki
*@author        Jordi Picart <jordi.picart@aposta.coop>
*@author        Jérémy Dufraisse <jeremy.dufraisse-info@orange.fr>
*@copyright     2014 Outils-Réseaux
*/

return [

    // Commons
    // 'CAUTION' => 'Attention',
    'BY' => 'per',
    // 'CLEAN' => 'Nettoyer',
    // 'DELETE' => 'Supprimer',
    // 'DELETE_ALL_SELECTED_ITEMS' => 'Supprimer tous les éléments sélectionnés',
    // 'DELETE_SELECTION' => 'Supprimer la sélection',
    // 'DEL' => 'Suppr.', // fives chars max.
    // 'EMAIL' => 'Email',
    // 'INVERT' => 'Inverser',
    // 'MODIFY' => 'Modifier',
    // 'NAME' => 'Nom',
    // 'SUBSCRIPTION' => 'Inscription',
    'TRIPLES' => 'Triples',
    'UNKNOWN' => 'Desconegut',
    'WARNING' => 'ADVERTIMENT',

    // wakka.php
    'INVALID_ACTION' => 'Acció no permesa',
    'ERROR_NO_ACCESS' => 'Error: no teniu accés a l\'acció',
    'NOT_FOUND' => 'No es troba el formatejador',
    'NO_REQUEST_FOUND' => '$_REQUEST[] No s\'ha trobat. Wakka requereix PHP 4.1.0 o superior!',
    'SITE_BEING_UPDATED' => 'En procés d\'actualització; proveu-ho més tard.',
    'DB_CONNECT_FAIL' => 'Per raons alienes a la nostra voluntat, el contingut de YesWiki és inaccessible temporalment. Proveu-ho més tard. Gràcies per la vostra comprensió.',
    'LOG_DB_CONNECT_FAIL' => 'YesWiki: la connexió a la base de dades no s\'ha pogut fer',
    'INCORRECT_PAGENAME' => 'El nom de la pàgina és incorrecte.',
    'HOMEPAGE_WIKINAME' => 'PaginaPrincipal',
    'MY_YESWIKI_SITE' => 'El meu lloc YesWiki',
    // 'FILE_WRITE_PROTECTED' => 'le fichier de configuration est protégé en écriture',
    
    // ACLs
    // 'DENY_READ' => 'Vous n\'êtes pas autorisé à lire cette page',
    // 'DENY_WRITE' => 'Vous n\'êtes pas autorisé à écrire sur cette page',
    // 'DENY_COMMENT' => 'Vous n\'êtes pas autorisé à commenter cette page',
    // 'DENY_DELETE' => 'Vous n\'êtes pas autorisé à supprimer cette page',


    // tools.php
    'YESWIKI_TOOLS_CONFIG' => 'Configura les extensions de YesWiki',
    'DISCONNECT' => 'Surt',
    'RETURN_TO_EXTENSION_LIST' => 'Torna a la llista d\'extensions actives',
    'NO_TOOL_AVAILABLE' => 'No hi ha cap eina disponible o activa',
    'LIST_OF_ACTIVE_TOOLS' => 'Llista d\'extensions actives',

    // actions/backlinks.php
    'PAGES_WITH_LINK' => 'Pàgines que enllacen amb',
    'PAGES_WITH_LINK_TO_CURRENT_PAGE' => 'Pàgines que enllacen a l\'actual',
    'NO_PAGES_WITH_LINK_TO' => 'Cap pàgina no enllaça amb',

    // actions/changestyle.php
    // 'STYLE_SHEET' => 'Feuille de style',
    // 'CHANGESTYLE_ERROR' => 'Le nom \'{name}\' n\'est pas conforme à la règle de nommage imposée par l\'action ChangeStyle.'.
        // 'Reportez-vous à la documentation de cette action pour plus de précisions',

    // handlers/page/acls.php
    // 'YW_ACLS_LIST' => 'Liste des droits d\'acc&egrave;s de la page',
    // 'YW_ACLS_UPDATED' => 'Droits d\'acc&egrave;s mis &agrave; jour',
    // 'YW_NEW_OWNER' => ' et changement du propri&eacute;taire. Nouveau propri&eacute;taire : ',
    // 'YW_CANCEL' => 'Annuler',
    // 'YW_ACLS_READ' => 'Droits de lecture',
    // 'YW_ACLS_WRITE' => 'Droits d\'écriture',
    // 'YW_ACLS_COMMENT' => 'Droits pour commenter',
    // 'YW_CHANGE_OWNER' => 'Changer le propri&eacute;taire',
    // 'YW_CHANGE_NOTHING' => 'Ne rien modifier',
    // 'YW_CANNOT_CHANGE_ACLS' => 'Vous ne pouvez pas g&eacute;rer les permissions de cette page',


    // actions/editactionsacls.class.php
    'ACTION_RIGHTS' => 'Drets de l\'acció',
    'SEE' => 'Vegeu',
    'ERROR_WHILE_SAVING_ACL' => 'S\'ha produït un error en desar l\'ACL per l\'acció',
    'ERROR_CODE' => 'codi d\'error',
    'NEW_ACL_FOR_ACTION' => 'Nova ACL per a l\'acció',
    'NEW_ACL_SUCCESSFULLY_SAVED_FOR_ACTION' => 'S\'ha desat amb èxit la nova ACL per a l\'acció',
    'EDIT_RIGHTS_FOR_ACTION' => 'Edita els drets de l\'acció',
    'SAVE' => 'Desa',

    // actions/editgroups.class.php
    'DEFINITION_OF_THE_GROUP' => 'Definició del grup',
    'DEFINE' => 'Defineix',
    'CREATE_NEW_GROUP' => 'O crear un grup nou',
    'ONLY_ADMINS_CAN_CHANGE_MEMBERS' => 'No podeu canviar els membres del grup d\'administradors perquè no en sou',
    'YOU_CANNOT_REMOVE_YOURSELF' => 'No podeu donar-vos de baixa dels administradors',
    'ERROR_RECURSIVE_GROUP' => 'Error: no podeu definir un grup de manera repetitiva',
    'ERROR_WHILE_SAVING_GROUP' => 'Hi ha hagut un error en enregistrar un grup',
    'NEW_ACL_FOR_GROUP' => 'Nova ACL per al grup',
    'NEW_ACL_SUCCESSFULLY_SAVED_FOR_THE_GROUP' => 'S\'ha desat amb èxit la nova ACL per al grup',
    'EDIT_GROUP' => 'Edita el grup',
    // 'EDIT_EXISTING_GROUP' => 'Éditer un groupe existant',
    // 'DELETE_EXISTING_GROUP' => 'Supprimer un groupe existant',
    // 'GROUP_NAME' => 'Nom du groupe',
    // 'SEE_EDIT' => 'Voir / Éditer',
    'ONLY_ALPHANUM_FOR_GROUP_NAME' => 'Els noms dels grups només poden contenir caràcters alfanumèrics',
    // 'LIST_GROUP_MEMBERS' => 'Liste des membres du groupe {groupName}',
    // 'ONE_NAME_BY_LINE' => 'un nom d\'utilisateur par ligne',

    // actions/edithandlersacls.class.php
    'HANDLER_RIGHTS' => 'Drets del handler',
    'ERROR_WHILE_SAVING_HANDLER_ACL' => 'Hi ha hagut un error durant l\'enregistrament de l\'ACL per al handler',
    'NEW_ACL_FOR_HANDLER' => 'Nova ACL per al handler',
    'NEW_ACL_SUCCESSFULLY_SAVED_FOR_HANDLER' => 'S\'ha enregistrat amb èxit la nova ACL per al handler',
    'EDIT_RIGHTS_FOR_HANDLER' => 'Edita els drets del handler',

    // actions/erasespamedcomments.class.php
    // 'ERASED_COMMENTS' => 'Commentaire(s) effacé(s)',
    // 'FORM_RETURN' => 'Retour au formulaire',
    // 'NO_RECENT_COMMENTS' => 'Pas de commentaires récents',
    // 'NO_SELECTED_COMMENTS_TO_ERASE' => 'Aucun commentaire n\'a été sélectionné pour étre effacé',
    
    // actions/footer.php ignorada, perquè els patrons d'eines han donat un error
    // actions/header.php ignorada, perquè els patrons d'eines han donat un error

    // actions/include.php
    'ERROR' => 'Error',
    'ACTION' => 'Acció',
    'MISSING_PAGE_PARAMETER' => 'Falta el paràmetre "pàgina"',
    'IMPOSSIBLE_FOR_THIS_PAGE' => 'Impossible per a la plana',
    'TO_INCLUDE_ITSELF' => 'd\'ésser inclosa en ella mateixa',
    'INCLUSIONS_CHAIN' => 'Cadena d\'inclusions',
    'EDITION' => 'Edició',
    'READING_OF_INCLUDED_PAGE' => 'Lectura de la pàgina inclosa',
    'NOT_ALLOWED' => 'Ho teniu autorització',
    'INCLUDED_PAGE' => 'Pàgina inclosa',
    'DOESNT_EXIST' => 'sembla que no existeix',

    // actions/listpages.php
    'THE_PAGE' => 'La pàgina',
    'BELONGING_TO' => 'Pertany a',
    'LAST_CHANGE_BY' => 'última modificació de',
    'LAST_CHANGE' => 'última modificació',
    'PAGE_LIST_WHERE' => 'Llista de pàgines en què',
    'HAS_PARTICIPATED' => 'ha participat',
    'EXCLUDING_EXCLUSIONS' => 'sense les exclusions',
    'INCLUDING' => 'inclòs',
    'IS_THE_OWNER' => 'n\'és el propietari',
    'NO_PAGE_FOUND' => 'No s\'ha trobat cap pàgina',
    'IN_THIS_WIKI' => 'en el wiki',
    'LIST_PAGES_BELONGING_TO' => 'Llista de pàgines que pertanyen a',
    'THIS_USER_HAS_NO_PAGE' => 'Aquest usuari no posseeix cap pàgina',

    // actions/mychanges.php
    'YOUR_MODIFIED_PAGES_ORDERED_BY_MODIFICATION_DATE' => 'Llista de pàgines que heu modificat, per data',
    'YOUR_MODIFIED_PAGES_ORDERED_BY_NAME' => 'Llista de pàgines que heu modificat, per ordre alfabetic',
    'YOU_DIDNT_MODIFY_ANY_PAGE' => 'No heu modificat cap pàgina',
    'YOU_ARENT_LOGGED_IN' => 'No esteu identificat',
    'IMPOSSIBLE_TO_SHOW_YOUR_MODIFIED_PAGES' => 'impossible de carregar la llista de pàgines que heu modificat',
    'LIST_OF_PAGES_WHERE_YOU_ARE_THE_OWNER' => 'Llista de les vostres pàgines',
    'YOU_DONT_OWN_ANY_PAGE' => 'No sou propietari de cap pàgina',

    // actions/nextextsearch.php
    // 'NEWTEXTSEARCH_HINT' => 'Un caractère inconnu peut être remplacé par « ? » plusieurs par « * »',
    // 'NO_SEARCH_RESULT' => 'Désolé mais il n\'y a aucun de résultat pour votre recherche',
    // 'SEARCH_RESULTS' => 'Résultats de la recherche',

    // actions/orphanedpages.php
    'NO_ORPHAN_PAGES' => 'No hi ha pàgines orfes',

    // actions/recentchanges.php
    'HISTORY' => 'històric',

    // actions/recentchangesrss.php
    'TO_OBTAIN_RSS_FEED_TO_GO_THIS_ADDRESS' => 'Per obtenir el fil RSS dels darrers canvis, utilitzeu l\'adreça següent',
    'LATEST_CHANGES_ON' => 'Darrers canvis a',

    // actions/recentcomments.php
    'NO_RECENT_COMMENTS' => 'No hi ha comentaris recents',

    // actions/recentcommentsrss.php
    'TO_OBTAIN_COMMENTS_RSS_FEED_TO_GO_THIS_ADDRESS' => 'Per obtenir el fil RSS dels darrers comentaris, utilitzeu l\'adreça següent',
    'LATEST_COMMENTS_ON' => 'Darrers comentaris a',

    // actions/recentlycommented.php
    // 'LAST COMMENT' => 'dernier commentaire',
    'NO_RECENT_COMMENTS_ON_PAGES' => 'No s\'ha comentat cap pàgina darrerament',

    // actions/redirect.php
    'ERROR_ACTION_REDIRECT' => 'Error d\'acció {{redirect ...}}',
    'CIRCULAR_REDIRECTION_FROM_PAGE' => 'redireccionament circular a partir de la pàgina',
    'CLICK_HERE_TO_EDIT' => 'cliqueu aquí per editar',
    'PRESENCE_OF_REDIRECTION_TO' => 'Hi ha un redireccionament a',

    // actions/resetpassword.php
    'ACTION_RESETPASSWORD' => 'Acció {{resetpassword ...}}',
    'PASSWORD_UPDATED' => 'S\'ha canviat la contrasenya',
    'RESETTING_THE_PASSWORD' => 'S\'està esborrant la contrasenya',
    'WIKINAME' => 'NomWiki',
    'NEW_PASSWORD' => 'Contrasenya nova',
    'RESET_PASSWORD' => 'Reinicia la contrasenya',
    'NO_PERMISSIONS_TO_EXECUTE_THIS_ACTION' => 'no teniu permisos per realitzar aquesta acció',

    // actions/textsearch.php
    'WHAT_YOU_SEARCH' => 'El que voleu cercar',
    'SEARCH' => 'Cercar',
    'SEARCH_RESULT_OF' => 'Resultats de la recerca de',
    'NO_RESULT_FOR' => 'No hi ha resultats per',

    // actions/testtriples.php
    // 'END_OF_EXEC' => 'Fin de l\'exécution',

    // actions/trail.php
    'ERROR_ACTION_TRAIL' => 'Error d\'acció {{trail ...}}',
    'INDICATE_THE_PARAMETER_TOC' => 'Indiqueu la pàgina d\'índex, paràmetre "toc"',

    // actions/usersettings.php
    // 'USER_SETTINGS' => 'Paramètres utilisateur',
    // 'USER_SIGN_UP' => 'S\'inscrire',
    'YOU_ARE_NOW_DISCONNECTED' => 'No esteu connectat',
    'PARAMETERS_SAVED' => 'S\'han desat els paràmetres',
    'NO_SPACES_IN_PASSWORD' => 'No es permeten espais a la contrasenya',
    'PASSWORD_TOO_SHORT' => 'Contrasenya massa curta',
    'WRONG_PASSWORD' => 'Contrasenya errònia',
    'PASSWORD_CHANGED' => 'S\'ha canviat la contrasenya',
    'GREETINGS' => 'Felicitacions',
    'YOUR_EMAIL_ADDRESS' => 'El vostre email',
    'DOUBLE_CLICK_TO_EDIT' => 'Cliqueu dues vegades per editar',
    'SHOW_COMMENTS_BY_DEFAULT' => 'Mostra els comentaris per defecte',
    'MAX_NUMBER_OF_LASTEST_COMMENTS' => 'Nombre màxim de comentaris recents',
    'MAX_NUMBER_OF_VERSIONS' => 'Nombre màxim de versions',
    'YOUR_MOTTO' => 'El vostre àlies',
    'CHANGE_THE_PASSWORD' => 'Canvi de contrasenya',
    'YOUR_OLD_PASSWORD' => 'La vostra contrasenya antiga',
    'NEW_PASSWORD' => 'Contrasenya nova',
    'CHANGE' => 'Canvia',
    'USERNAME_MUST_BE_WIKINAME' => 'El vostre nom d\'usuari ha de tenir format de NomWiki',
    'YOU_MUST_SPECIFY_AN_EMAIL' => 'Cal especificar una adreça d\'email',
    'THIS_IS_NOT_A_VALID_EMAIL' => 'No és un email vàlid',
    'PASSWORDS_NOT_IDENTICAL' => 'Les contrasenyes no coincideixen',
    'PASSWORD_SHOULD_HAVE_5_CHARS_MINIMUM' => 'ha de contenir almenys cinc caracters alfanumèrics',
    'YOU_MUST_ACCEPT_COOKIES_TO_GET_CONNECTED' => 'Cal que accepteu les galetes per connectar-vos',
    'IF_YOU_ARE_REGISTERED_LOGGIN_HERE' => 'Si esteu donat d\'alta, identifiqueu-vos aquí',
    'PASSWORD_5_CHARS_MINIMUM' => 'Contrasenya (5 caràcters com a mínim)',
    'REMEMBER_ME' => 'Recorda\'m',
    'IDENTIFICATION' => 'Identificar-se',
    'FILL_THE_NEXT_FIELDS_IF_YOU_LOGGIN_FOR_THE_FIRST_TIME_AND_REGISTER' => 'Ompliu els camps següents si accediu per primer cop i us enregistreu',
    'PASSWORD_CONFIRMATION' => 'Confirmació de contrasenya',
    'NEW_ACCOUNT' => 'Crea un compte nou',
    // 'LOGGED_USERS_ONLY_ACTION' => 'Il faut être connecté pour pouvoir exécuter cette action',
    'USER_DELETE' => 'Suprimeix l\'usuari',


    // actions/wantedpages.php
    'NO_PAGE_TO_CREATE' => 'No hi ha cap pàgina per crear',

    // includes/controllers/CsrfController.php
    'NO_CSRF_TOKEN_ERROR' => 'Error de disseny del lloc: el formulari d\'enviament no contenia el testimoni '.
        'd\'identificació únic necessari per als mecanismes de seguretat interns.',
    'CSRF_TOKEN_FAIL_ERROR' => 'Pot ser que aquesta pàgina s\'hagi obert per segona vegada. '.
        'Si us plau, renoveu la sol·licitud des d\'aquesta finestra (el testimoni de seguretat intern no era bo).',

    // javascripts/favorites.js
    'FAVORITES_ADD' => 'Afegeix-ho als preferits',
    'FAVORITES_REMOVE' => 'Suprimeix dels preferits',

    // templates/actions/my-favorites.twig
    'FAVORITES_DELETE_ALL' => 'Suprimeix tots els meus preferits',
    'FAVORITES_MY_FAVORITES' => 'Els meus favorits',
    'FAVORITES_NO_FAVORITE' => 'No s\'ha desat cap favorit',
    'FAVORITES_NOT_ACTIVATED' => 'L\'ús de preferits no està habilitat en aquest lloc.',
    'FAVORITES_NOT_CONNECTED' => 'L\'ús de favorits només és possible per a les persones connectades.',

    // templates/actions/my-favorites-table.twig
    'FAVORITES_TITLE' => 'Títol',
    'FAVORITES_LINK' => 'Lligam',

    // setup/header.php
    'OK' => 'D\'acord',
    'FAIL' => 'Error',
    'END_OF_INSTALLATION_BECAUSE_OF_ERRORS' => 'S\'ha interromput la instal·lació per errors en la configuració',

    // setup/default.php
    'INSTALLATION_OF_YESWIKI' => 'Instal·lació de YesWiki',
    'YOUR_SYSTEM' => 'El vostre sistema',
    'EXISTENT_SYSTEM_RECOGNISED_AS_VERSION' => 'El sistema té la versió',
    'YOU_ARE_UPDATING_YESWIKI_TO_VERSION' => 'Esteu actualitzant YesWiki a la versió',
    'CHECK_YOUR_CONFIG_INFORMATION_BELOW' => 'Reviseu la informació de configuració següent',
    'FILL_THE_FORM_BELOW' => 'Ompliu el formulari següent',
    'DEFAULT_LANGUAGE' => 'Idioma triat',
    // 'NAVIGATOR_LANGUAGE' => 'Langue du navigateur',
    'DEFAULT_LANGUAGE_INFOS' => 'El llenguatge utilitzat per a la YesWiki interfície, sempre és possible canviar l\'idioma de cada pàgina creada',
    // 'GENERAL_CONFIGURATION' => 'Configuration générale',
    'DATABASE_CONFIGURATION' => 'Configuració de la base de dades',
    'MORE_INFOS' => 'Més informació',
    'MYSQL_SERVER' => 'Servidor MySQL',
    'MYSQL_SERVER_INFOS' => 'L\'adreça IP o el nom de xarxa de la màquina on es troba el vostre servidor MySQL',
    'MYSQL_DATABASE' => 'Base de dades MySQL',
    'MYSQL_DATABASE_INFOS' => 'Cal que la base de dades existeixi per poder continuar',
    'MYSQL_USERNAME' => 'Nom de l\'usuari MySQL',
    'MYSQL_USERNAME_INFOS' => 'Necessari per connectar a la vostra base de dades',
    'TABLE_PREFIX' => 'Prefix de les taules',
    'TABLE_PREFIX_INFOS' => '***Permet utilitzar múltiples instàncies de YesWiki amb una mateixa base de dades: cada nou YesWiki que instal·leu ha de tenir un prefix de taules diferent',
    'MYSQL_PASSWORD' => 'Contrasenya MySQL',
    'YESWIKI_WEBSITE_CONFIGURATION' => 'Configuració del lloc web YesWiki',
    'YOUR_WEBSITE_NAME' => 'Nom del vostre lloc web',
    'YOUR_WEBSITE_NAME_INFOS' => 'Pot ser un NomWiki o qualsevol altre nom, que apareixerà a les pestanyes i finestres',
    'HOMEPAGE' => 'Pàgina principal',
    'HOMEPAGE_INFOS' => 'La pàgina principal del vostre YesWiki. Ha de tenir un NomWiki',
    'KEYWORDS' => 'Paraules clau',
    'KEYWORDS_INFOS' => 'Paraules clau que seran inserides en els codis HTML (metadades)',
    'DESCRIPTION' => 'Descripció',
    'DESCRIPTION_INFOS' => 'La descripció del lloc, que apareixerà als codis HTML (metadades)',
    'CREATION_OF_ADMIN_ACCOUNT' => 'Crea un compte d\'administrador',
    'ADMIN_ACCOUNT_CAN' => 'El compte d\'administrador permet',
    'MODIFY_AND_DELETE_ANY_PAGE' => 'Modifica i suprimeix qualsevol pàgina',
    'MODIFY_ACCESS_RIGHTS_ON_ANY_PAGE' => 'Modifica els drets d\'accés de qualsevol pàgina',
    'GENERATE_ACCESS_RIGHTS_ON_ANY_ACTION_OR_HANDLER' => 'Gestionar els drets de qualsevol acció o handler',
    'GENERATE_GROUPS' => 'Gestionar els grups, afegir o suprimir usuaris al grup administrador (amb els vostres mateixos drets)',
    'ALL_ADMIN_TASKS_ARE_DESCRIBED_IN_THE_PAGE' => 'Totes les tasques d\'administració es descriuen a la pàgina "AdministracioDeYesWiki" que es troba a la pàgina principal',
    'USE_AN_EXISTING_ACCOUNT' => 'Utilitzar un compte existent',
    'NO' => 'No',
    // 'YES' => 'Oui',
    'OR_CREATE_NEW_ACCOUNT' => 'O bé crear un compte nou',
    'ADMIN' => 'Administrador',
    'MUST_BE_WIKINAME' => 'Ha de ser un NomWiki',
    'PASSWORD' => 'Contrasenya',
    'EMAIL_ADDRESS' => 'Adreça de correu electrònic',
    'MORE_OPTIONS' => 'Opcions addicionals',
    'ADVANCED_CONFIGURATION' => '+ Configuració avançada',
    'URL_REDIRECTION' => 'Redireccionament d\'URL',
    'NEW_INSTALL_VALUES_CHANGE_ONLY_IF_YOU_KNOW_WHAT_YOU_ARE_DOING' => 'Modifiqueu els valors d\'instal·lació només si sou un usuari avançat',
    'PAGENAME_WILL_BE_ADDED_AFTER_CHANGE_JUST_FOR_REDIRECTION' => 'Els noms de les pàgines seran afegits a l\'URL principal del vostre lloc YesWiki. Suprimiu-ne la partícula "?wiki=" només si esteu utilitzant el redireccionament (vegeu més avall)',
    'BASE_URL' => 'URL de base',
    'REDIRECTION_SHOULD_BE_ACTIVE_ONLY_IF_USED_IN_YESWIKI' => 'Cal que el mode de "redireccionament automàtic" estigui seleccionat només si utilitzeu YesWiki amb la redirecció d\'URL (si no sabeu què és això, deixeu aquesta opció sense activar)',
    // 'HTML_INSERTION_HELP_TEXT' => 'Augmente grandement les fonctionnalités du wiki, en permettant l\'ajout de vidéos et iframe par exemple, mais est moins sécurisé',
    // 'INDEX_HELP_TEXT' => 'Indique dans les meta-données html et dans le fichier robots.txt si votre site doit etre indexé par les moteurs de recherche ou pas',
    'ACTIVATE_REDIRECTION_MODE' => 'Activa el mode "redireccionament automàtic"',
    'OTHER_OPTIONS' => 'Altres opcions',
    'OBLIGE_TO_PREVIEW_BEFORE_SAVING_PAGE' => 'Obliga a una previsualització abans de desar la pàgina',
    'AUTHORIZE_HTML_INSERTION' => 'Autoritza la inserció directa d\'HTML',
    // 'AUTHORIZE_INDEX_BY_ROBOTS' => 'Autoriser l\'indexation par les moteurs de recherche',
    'CONTINUE' => 'Endavant',

    // setup/install.php
    'PROBLEM_WHILE_INSTALLING' => 'Hi ha un problema en el procés d\'instal·lació',
    'VERIFICATION_OF_DATAS_AND_DATABASE_INSTALLATION' => 'Prova de la configuració i instal·lació de la base de dades',
    'VERIFY_MYSQL_PASSWORD' => 'Verificació de la contrasenya MySQL',
    'INCORRECT_MYSQL_PASSWORD' => 'La contrasenya MySQL és incorrecta',
    'TEST_MYSQL_CONNECTION' => 'Provant la connexió MySQL',
    'SEARCH_FOR_DATABASE' => 'Recerca de la base de dades',
    'GO_BACK' => 'Enrere',
    'NO_DATABASE_FOUND_TRY_TO_CREATE' => 'La base de dades no existeix. Intenteu crear-ne una',
    'TRYING_TO_CREATE_DATABASE' => 'Provant de crear la base de dades',
    'DATABASE_COULD_NOT_BE_CREATED_YOU_MUST_CREATE_IT_MANUALLY' => 'La base de dades no s\'ha pogut crear. Creeu-la manualment abans d\'instal·lar YesWiki',
    'DATABASE_DOESNT_EXIST_YOU_MUST_CREATE_IT' => 'La base de dades no existeix, creeu-la abans d\'instal·lar YesWiki',
    'CHECKING_THE_ADMIN_PASSWORD' => 'Verificació de la contrasenya d\'administrador',
    'CHECKING_THE_ADMIN_PASSWORD_CONFIRMATION' => 'Verificació de la identificació de les contrasenyes d\'administrador',
    // 'CHECKING_ROOT_PAGE_NAME' => 'V&eacute;rification du nom de la page d\'accueil',
    // 'INCORRECT_ROOT_PAGE_NAME' => 'Le nom de la page d\'accueil doit uniquement contenir des lettres non accentuées, des chiffres, \'_\', \'-\' ou \'.\'',
    'ADMIN_PASSWORD_ARE_DIFFERENT' => 'Les contrasenyes d\'administració són diferents',
    'DATABASE_INSTALLATION' => 'Instal·lació de la base de dades',
    'CREATION_OF_TABLE' => 'Creació de la taula',
    // 'SQL_FILE_NOT_FOUND' => 'Fichier SQL non trouv&eacute;',
    // 'NOT_POSSIBLE_TO_CREATE_SQL_TABLES' => 'Impossible de créer les tables SQL.',
    'ALREADY_CREATED' => 'Ja s\'ha creat',
    'ADMIN_ACCOUNT_CREATION' => 'Creació del compte d\'administrador',
    'INSERTION_OF_PAGE' => 'Inserció de la pàgina',
    'ALREADY_EXISTING' => 'Ja existeix',
    'UPDATING_FROM_WIKINI_0_1' => 'Actualitzant WikiNi 0.1',
    'TINY_MODIFICATION_OF_PAGES_TABLE' => 'Modificació parcial de la taula de pàgines',
    'ALREADY_DONE' => 'Fet',
    'INSERTION_OF_USER_IN_ADMIN_GROUP' => 'Inserció de l\'usuari al grup d\'administració',
    'NEXT_STEP_WRITE_CONFIGURATION_FILE' => 'Tot seguit el programa d\'instal·lació crearà el fitxer de configuració',
    'VERIFY_YOU_HAVE_RIGHTS_TO_WRITE_FILE' => 'Assegureu-vos que teniu el dret de reescriure els fitxers',
    // 'CHECK_EXISTING_TABLE_PREFIX' => 'Vérification de l\'existence du préfixe de table',
    // 'TABLE_PREFIX_ALREADY_USED' => 'Le préfixe de table est déjà utilisé. Veuillez en choisir un nouveau.',

    // setup/writeconfig.php
    'WRITING_CONFIGURATION_FILE' => 'Escrivint el fitxer de configuració',
    'CREATED' => 'Ha estat creat',
    'DONT_CHANGE_YESWIKI_VERSION_MANUALLY' => 'no canvieu manualment la yeswiki_version',
    'WRITING_CONFIGURATION_FILE_WIP' => 'S\'està creant el fitxer de configuració',
    'FINISHED_CONGRATULATIONS' => 'Procés completat. Felicitats',
    'GO_TO_YOUR_NEW_YESWIKI_WEBSITE' => 'Accediu al vostre nou lloc YesWiki',
    'IT_IS_RECOMMANDED_TO_REMOVE_WRITE_ACCESS_TO_CONFIG_FILE' => 'Es recomana eliminar l\'accés d\'escriptura al fitxer de configuració',
    'THIS_COULD_BE_UNSECURE' => 'Això podria ser una fallada de seguretat',
    'CONFIGURATION_FILE' => 'el fitxer de configuració',
    'CONFIGURATION_FILE_NOT_CREATED' => 'no s\'ha pogut crear',
    'TRY_CHANGE_ACCESS_RIGHTS_OR_FTP_TRANSFERT' => 'Verifiqueu que el vostre servidor té drets d\'accés per l\'escriptura d\'aquest fitxer. Si per alguna raó no podeu fer-ho, heu de copiar les informacions següents en un fitxer i transferir-les mitjançant un programa FTP al servidor.',
    'DIRECTLY_IN_THE_YESWIKI_FOLDER' => 'Directament al directori de YesWiki. Així que ho hagueu fet, el vostre YesWiki ha de funcionar correctament',
    'TRY_AGAIN' => 'Proveu-ho una altra vegada',

    // API
    // 'USERS' => 'Utilisateurs',
    // 'GROUPS' => 'Groupes',

    // YesWiki\User class
    // 'USER_CONFIRM_DELETE' => 'Êtes-vous sûr·e de vouloir supprimer l’utilisateur·ice ?',
    // 'USER_DELETE_LONE_MEMBER_OF_GROUP' => 'Vous ne pouvez pas supprimer un utilisateur qui est seul dans au moins un groupe',
    // 'USER_DELETE_QUERY_FAILED' => 'La requête de suppression de l\'utilisateur dans la base de données a échoué',
    // 'USER_EMAIL_S_MAXIMUM_LENGTH_IS' => 'Le nomnbre maximum de caractères d\'un email d\'utilisateur est',
    // 'USER_LISTGROUPMEMBERSHIPS_QUERY_FAILED' => 'La requête pour lsiter les groupes auquels l\'utilisateur appartient a échoué',
    // 'USER_MUST_BE_ADMIN_TO_DELETE' => 'Vous devez être administrateur pour supprimer un utilisateur',
    // 'USER_NAME_S_MAXIMUM_LENGTH_IS' => 'Le nomnbre maximum de caractères d\'un nom d\'utilisateur est',
    // 'USER_NO_SPACES_IN_PASSWORD' => 'Les espaces ne sont pas autorisés dans un mot de passe',
    // 'USER_PASSWORD_MINIMUM_NUMBER_OF_CHARACTERS_IS' => 'Le nombre minimum de caractères d\'un mot de passe est',
    // 'USER_PASSWORDS_NOT_IDENTICAL' => 'Les deux mots de passe saisis doivent être identiques',
    // 'USER_PASSWORD_TOO_SHORT' => 'Mot de passe trop court',
    // 'USER_THIS_EMAIL_IS_ALLREADY_USED_ON_THIS_WIKI' => 'L\'email saisi est déjà utilisé sur ce wiki',
    // 'USER_THIS_IS_NOT_A_VALID_NAME' => 'Ceci n\'est pas un nom d\'utilisateur valide',
    // 'USER_THIS_IS_NOT_A_VALID_EMAIL' => 'Ceci n\'est pas un email valide',
    // 'USER_UPDATE_QUERY_FAILED' => 'La requête de mise à jour de l\'utilisateur dans la base de données a échoué',
    // 'USER_YOU_MUST_SPECIFY_A_NAME' => 'Veuillez saisir un nom pour l\'utilisateur',
    // 'USER_YOU_MUST_SPECIFY_AN_EMAIL' => 'Veuillez saisir un email pour l\'utilisateur',
    // 'USER_USERSTABLE_MISTAKEN_ARGUMENT' => 'l\'action usertable a reçu un argument non autorisé',
    // 'USER_WRONG_PASSWORD' => 'Mot de passe incorrect',
    // 'USER_INCORRECT_PASSWORD_KEY' => 'La clef de validation du mot de passe est incorrecte',
    // 'USER_PASSWORD_UPDATE_FAILED' => 'La modification du mot de passe a échoué',
    // 'USER_NOT_LOGGED_IN_CANT_LOG_OUT' => 'Déconnexion impossible car personne n\'est connecté',
    // 'USER_TRYING_TO_LOG_WRONG_USER_OUT' => 'Vous essayez de déconnecter quelqu\'un d\'autre',
    // 'USER_CREATION_FAILED' => 'La création de l\'utilisateur a échoué',
    // 'USER_LOAD_BY_NAME_QUERY_FAILED' => 'La requête de chargement de l\'utilisateur par son nom depuis la base de données a échoué',
    // 'USER_NO_USER_WITH_THAT_NAME' => 'Il n\'y a aucun utilisateur avec ce nom',
    // 'USER_LOAD_BY_EMAIL_QUERY_FAILED' => 'La requête de chargement de l\'utilisateur par son email depuis la base de données a échoué',
    // 'USER_NO_USER_WITH_THAT_EMAIL' => 'Il n\'y a aucun utilisateur avec cet email',
    // 'USER_UPDATE_MISSPELLED_PROPERTIES' => 'La liste des champs à modifier par updateIntoDB est certainement défectueuse',
    // 'USER_CANT_DELETE_ONESELF' => 'Vous ne pouvez supprimer votre compte',
    // 'USER_TRYING_TO_MODIFY_AN_INEXISTANT_USER' => 'L\'utilisateur en cours de modification n\'existe pas dans la base de données',
    // 'USER_YOU_ARE_NOW_DISCONNECTED' => 'Vous êtes à présent déconnecté',
    // 'USER_PARAMETERS_SAVED' => 'Paramètres sauvegardés',
    // 'USER_DELETED' => 'utilisateur supprimé',
    // 'USER_PASSWORD_CHANGED' => 'Mot de passe modifié',
    // 'USER_EMAIL_ADDRESS' => 'Adresse de messagerie électronique',
    // 'USER_DOUBLE_CLICK_TO_EDIT' => 'Éditer en double-cliquant',
    // 'USER_SHOW_COMMENTS_BY_DEFAULT' => 'Par défaut, montrer les commentaires',
    // 'USER_MAX_NUMBER_OF_LASTEST_COMMENTS' => 'Nombre maximum de derniers commentaires',
    // 'USER_MAX_NUMBER_OF_VERSIONS' => 'Nombre maximum de versions',
    // 'USER_MOTTO' => 'Votre devise',
    // 'USER_UPDATE' => 'Mise &agrave; jour',
    // 'USER_DISCONNECT' => 'Déconnexion',
    // 'USER_CHANGE_THE_PASSWORD' => 'Changement de mot de passe',
    // 'USER_OLD_PASSWORD' => 'Votre ancien mot de passe',
    // 'USER_NEW_PASSWORD' => 'Nouveau mot de passe',
    // 'USER_CHANGE' => 'Changer',
    // 'USER_MUST_ACCEPT_COOKIES_TO_GET_CONNECTED' => 'Vous devez accepter les cookies pour pouvoir vous connecter',
    // 'USER_WIKINAME' => 'Votre NomWiki',
    // 'USER_USERNAME' => 'Votre nom d\'utilisateur, utilisatrice',
    // 'USER_PASSWORD_CONFIRMATION' => 'Confirmation du mot de passe',
    // 'USER_NEW_ACCOUNT' => 'Nouveau compte',
    // 'USER_PASSWORD' => 'Mot de passe',
    // 'USER_ERRORS_FOUND' => 'Erreur(s) trouvée(s)',
    // 'USER_YOU_MUST_SPECIFY_A_POSITIVE_INTEGER_FOR' => 'Il faut une valeur entier positif pour %{name}.',
    // 'USER_YOU_MUST_SPECIFY_YES_OR_NO' => 'Il faut une valuer \'Y\' ou  \'N\' pour %{name}.',
    // 'USER_YOU_MUST_SPECIFY_A_STRING' => 'Il faut une chaîne de caractères pour %{name}.',

    // YesWiki\Database class
    // 'DATABASE_QUERY_FAILED' => 'La requête a échoué {\YesWiki\Database}',
    // 'DATABASE_YOU_MUST_FIRST_SET_ARGUMENT' => 'Il manque des arguments pour un objet de la classe \YesWiki\Database',
    // 'DATABASE_MISSING_ARGUMENT' => ' manque(nt)',

    // YesWiki\Session class
    // 'SESSION_YOU_MUST_FIRST_SET_ARGUMENT' => 'Il manque l\'argument pour un objet de la classe \YesWiki\Session',

    // gererdroits
    // 'ACLS_RESERVED_FOR_ADMINS' => 'Cette action est r&eacute;serv&eacute;e aux admins',
    // 'ACLS_NO_SELECTED_PAGE' => 'Aucune page n\'a &eacute;t&eacute; s&eacute;lectionn&eacute;e.',
    // 'ACLS_NO_SELECTED_RIGHTS' => 'Vous n\'avez pas s&eacute;lectionn&eacute; de droits &agrave; modifier.',
    // 'ACLS_RIGHTS_WERE_SUCCESFULLY_CHANGED' => 'Droit modifi&eacute;s avec succ&egrave;s',
    // 'ACLS_SELECT_PAGES_TO_MODIFY' => 'Cochez les pages que vous souhaitez modifier et choisissez une action en bas de page',
    // 'ACLS_PAGE' => 'Page',
    // 'ACLS_FOR_SELECTED_PAGES' => 'Actions pour les pages cochées ci dessus',
    // 'ACLS_RESET_SELECTED_PAGES' => 'Réinitialiser (avec les valeurs par défaut définies dans',
    // 'ACLS_REPLACE_SELECTED_PAGES' => 'Remplacer (Les droits actuels seront supprim&eacute;s)',
    // 'ACLS_HELPER' => 'Séparez chaque entrée des virgules, par exemple</br>
    // <b>*</b> (tous les utilisateurs)</br>
    // <b>+</b> (utilisateurs enregistrés)</br>
    // <b>%</b> (créateur de la fiche/page)</br>
    // <b>@nom_du_groupe</b> (groupe d\'utilisateur, ex: @admins)</br>
    // <b>JamesBond</b> (nom YesWiki d\'un utilisateur)</br>
    // <b>!SuperCat</b> (négation, SuperCat n\'est pas autorisé)</br>',
    // 'ACLS_MODE_SIMPLE' => 'Mode simple',
    // 'ACLS_MODE_ADVANCED' => 'Mode avancé',
    // 'ACLS_NO_CHANGE' => 'Ne rien changer',
    // 'ACLS_EVERYBODY' => 'Tout le monde',
    // 'ACLS_AUTHENTIFICATED_USERS' => 'Utilisateurs connectés',
    // 'ACLS_OWNER' => 'Propriétaire de la page',
    // 'ACLS_ADMIN_GROUP' => 'Groupe admin',
    // 'ACLS_LIST_OF_ACLS' => 'Liste des droits séparés par des virgules',
    // 'ACLS_UPDATE' => 'Mettre &agrave; jour',
    // 'ACLS_COMMENTS_CLOSED' => 'Commentaires fermés',

    // include/services/ThemeManager.php
    // 'THEME_MANAGER_THEME_FOLDER' => 'Le dossier du thème ',
    // 'THEME_MANAGER_SQUELETTE_FILE' => 'Le fichier du squelette ',
    // 'THEME_MANAGER_NOT_FOUND' => ' n\'a pas été trouvé.',
    // 'THEME_MANAGER_ERROR_GETTING_FILE' => 'Une erreur s\'est produite en chargeant ce fichier : ',
    // 'THEME_MANAGER_CLICK_TO_INSTALL' => 'Cliquer pour installer le thème ',
    // 'THEME_MANAGER_AND_REPAIR' => ' et réparer le site',
    // 'THEME_MANAGER_LOGIN_AS_ADMIN' => 'Veuillez vous connecter en tant qu\'administrateur pour faire la mise à jour.',

    // actions/EditConfigAction.php
    // 'EDIT_CONFIG_TITLE' => 'Modification du fichier de configuration',
    // 'EDIT_CONFIG_CURRENT_VALUE' => 'Valeur actuelle ',
    // 'EDIT_CONFIG_SAVE' => 'Configuration sauvegardée',
    // 'EDIT_CONFIG_HINT_WAKKA_NAME' => 'Titre de votre wiki',
    // 'EDIT_CONFIG_HINT_ROOT_PAGE' => 'Nom de la page d\'accueil',
    // 'EDIT_CONFIG_HINT_DEFAULT_WRITE_ACL' => 'Droits d\'écriture par défaut des pages (* pour tous, + pour personnes identifiées, @admins pour groupe admin)',
    // 'EDIT_CONFIG_HINT_DEFAULT_READ_ACL' => 'Droits de lecture par défaut des pages (* pour tous, + pour personnes identifiées, @admins pour groupe admin)',
    // 'EDIT_CONFIG_HINT_DEFAULT_COMMENT_ACL' => 'Droits de commentaires par défaut des pages (comment-closed pour fermer, + pour personnes identifiées, @admins pour groupe admin)',
    // 'EDIT_CONFIG_HINT_COMMENTS_ACTIVATED' => 'Commentaires activés (true ou false)',
    // 'EDIT_CONFIG_HINT_DEBUG' => 'Activer le mode de debug (yes ou no)',
    // 'EDIT_CONFIG_HINT_DEFAULT_LANGUAGE' => 'Langue par défaut (fr ou en ou ... auto = langue du navigateur)',
    // 'EDIT_CONFIG_HINT_CONTACT_FROM' => 'Remplacer le mail utilisé comme expéditeur des messages',
    // 'EDIT_CONFIG_HINT_MAIL_CUSTOM_MESSAGE' => 'Message personnalisé des mails envoyés depuis l\'action contact',
    // 'EDIT_CONFIG_HINT_PASSWORD_FOR_EDITING' => 'Mot de passe demandé pour modifier les pages (voir doc gestion des spams)',
    // 'EDIT_CONFIG_HINT_PASSWORD_FOR_EDITING_MESSAGE' => 'Message informatif pour demander le mot de passe (voir doc gestion des spams)',
    // 'EDIT_CONFIG_HINT_ALLOW_DOUBLECLIC' => 'Autoriser le doubleclic pour éditer les menus et pages spéciales (true ou false)',
    'EDIT_CONFIG_HINT_TIMEZONE' => 'Fus horari del lloc (per exemple, UCT, Europa / París, Europa / Londres, GMT = utilitzeu la zona horària del servidor,)',
    'EDIT_CONFIG_HINT_ALLOWED_METHODS_IN_IFRAME' => 'Mètodes permesos per ser mostrats en iframes (iframe,editiframe,bazariframe,render,all = allow all)',
    // 'EDIT_CONFIG_HINT_REVISIONSCOUNT' => 'Nombre maximum de versions d\'une page affichées par le handler `/revisions`.',
    'EDIT_CONFIG_HINT_HTMLPURIFIERACTIVATED' => 'Habilita la neteja HTML abans de fer la còpia de seguretat. Aneu amb compte, modifiqueu el contingut a la còpia de seguretat! (vertader o fals)',
    'EDIT_CONFIG_HINT_FAVORITES_ACTIVATED' => 'Activer les favoris (true ou false)',
    'EDIT_CONFIG_GROUP_CORE' => 'Paràmetres principals',
    'EDIT_CONFIG_GROUP_ACCESS' => 'Dret d\'accés',
    'EDIT_CONFIG_GROUP_EMAIL' => 'Correus electrònics',

    // actions/userstable.php
    'USERSTABLE_USER_DELETED' => 'S\'ha suprimit l\'usuari "{username}".',
    'USERSTABLE_USER_NOT_DELETED' => 'L\'usuari "{username}" no s\'ha suprimit.',
    'USERSTABLE_NOT_EXISTING_USER' => 'L\'usuari "{username}" no existeix!',
    'GROUP_S' => 'Grup(s)',

    // handlers/deletepage
    // 'DELETEPAGE_CANCEL' => 'Annuler',
    // 'DELETEPAGE_CONFIRM' => 'Voulez-vous vraiment supprimer d&eacute;finitivement la page {tag}&nbsp;?',
    // 'DELETEPAGE_CONFIRM_WHEN_BACKLINKS' => 'Voulez-vous vraiment supprimer d&eacute;finitivement la page {tag} malgr&eacute; la pr&eacute;sence de liens&nbsp;?',
    // 'DELETEPAGE_DELETE' => 'Supprimer',
    // 'DELETEPAGE_MESSAGE' => 'La page {tag} a d&eacute;finitivement &eacute;t&eacute; supprim&eacute;e',
    // 'DELETEPAGE_NOT_ORPHEANED' => 'Cette page n\'est pas orpheline.',
    // 'DELETEPAGE_NOT_OWNER' => 'Vous n\'&ecirc;tes pas le propri&eacute;taire de cette page.',
    // 'DELETEPAGE_PAGES_WITH_LINKS_TO' => 'Pages ayant un lien vers {tag} :',
    'DELETEPAGE_NOT_DELETED' => 'La pàgina no s\'ha suprimit.',

    // handlers/edit
    // 'EDIT_ALERT_ALREADY_SAVED_BY_ANOTHER_USER' => 'ALERTE : '.
    //     'Cette page a &eacute;t&eacute; modifi&eacute;e par quelqu\'un d\'autre pendant que vous l\'&eacute;ditiez.'."\n".
    //     'Veuillez copier vos changements et r&eacute;&eacute;diter cette page.',
    // 'EDIT_NO_WRITE_ACCESS' => 'Vous n\'avez pas acc&egrave;s en &eacute;criture &agrave; cette page !',
    // 'EDIT_NO_CHANGE_MSG' => 'Cette page n\'a pas &eacute;t&eacute; enregistr&eacute;e car elle n\'a subi aucune modification.',
    // 'EDIT_PREVIEW' => 'Aper&ccedil;u',

    // handlers/update
    // 'UPDATE_ADMIN_PAGES' => 'Mettre à jour les pages de gestion',
    // 'UPDATE_ADMIN_PAGES_CONFIRM' => 'Confirmer la mise à jour des pages : ',
    // 'UPDATE_ADMIN_PAGES_HINT' => 'Mets à jour les pages de gestion avec les dernières fonctionnalités. Ceci est réversible.',
    // 'UPDATE_ADMIN_PAGES_ERROR' => 'Il n\'a pas été possible de mettre à jour toutes les pages de gestion !',
    // 'UPDATE_PAGE_NOT_FOUND_IN_DEFAULT_SQL' => 'la page "{{page}}" n\'a pas été trouvée dans default-content.sql',

    // handlers/referrers_sites.php
    // 'LINK_TO_REFERRERS_DOMAINS' => 'Domaines faisant r&eacute;f&eacute;rence &agrave; ce wiki ({beginLink}voir la liste des pages externes{endLink}):',
    // 'LINK_TO_REFERRERS_SITES' => 'Sites faisant r&eacute;f&eacute;rence &agrave; ce wiki ({beginLink}voir la liste des domaines{endLink}):',
    // 'LINK_TO_REFERRERS_SITES_ONLY_TAG' => 'Voir les domaines faisant r&eacute;f&eacute;rence &agrave; {tag} seulement',
    // 'LINK_TO_REFERRERS_SITES_PAGES_ONLY_TAG' => 'Voir les r&eacute;f&eacute;rences &agrave; {tag} seulement',
    // 'LINK_TO_REFERRERS_ALL_DOMAINS' => 'Voir tous les domaines faisant r&eacute;f&eacute;rence',
    // 'LINK_TO_REFERRERS_ALL_REFS' => 'Voir toutes les r&eacute;f&eacute;rences',
    // 'LINK_TO_REFERRERS_SITES_NO_GLOBAL' => 'Domaines faisant r&eacute;f&eacute;rence &agrave; {tag}{since} ({beginLink}voir la liste des pages externes{endLink}):',
    // 'LINK_TO_REFERRERS_NO_GLOBAL' => 'Pages externes faisant r&eacute;f&eacute;rence &agrave; {tag}{since} ({beginLink}voir la liste des domaines{endLink}):',
    // 'REFERRERS_SITES_SINCE' => 'depuis {time}',
    // 'REFERRERS_SITES_24_HOURS' => '24 heures',
    // 'REFERRERS_SITES_X_DAYS' => '{nb} jours',

    // handlers/revisions
    // 'SUCCESS_RESTORE_REVISION' => 'La version a bien été restaurée',
    // 'TITLE_PAGE_HISTORY' => 'Historique de la page',
    // 'TITLE_ENTRY_HISTORY' => 'Historique de la fiche',
    // 'REVISION_VERSION' => 'Version N°',
    // 'REVISION_ON' => 'du',
    // 'REVISION_BY' => 'par',
    // 'CURRENT_VERSION' => 'Version actuelle',
    // 'RESTORE_REVISION' => 'Restaurer cette version',
    // 'DISPLAY_WIKI_CODE' => 'Afficher le code Wiki',

    // handlers/show
    // 'COMMENT_INFO' => 'Ceci est un commentaire sur {tag} post&eacute; par {user} &agrave; {time}',
    // 'EDIT_ARCHIVED_REVISION' => 'R&eacute;&eacute;diter cette version archiv&eacute;e',
    // 'REVISION_IS_ARCHIVE_OF_TAG_ON_TIME' => 'Ceci est une version archivée de {link} à {time}',
    // 'REDIRECTED_FROM' => 'Redirig&eacute; depuis {linkFrom}',

    // handlers/page/show + handlers/page/iframe
    // 'NOT_FOUND_PAGE' => 'Cette page n\'existe pas encore, voulez-vous la {beginLink}créer{endLink} ?',

    // YesWiki
    // 'UNKNOWN_INTERWIKI' => 'interwiki inconnu',

    // templates/multidelete-macro.twig
    // 'NUMBER_OF_ELEMENTS' => 'Nombre d\'éléments sélectionnés',

    // reactions
    // 'REACTION_EMPTY_ID' => 'le paramètre "id" doit obligatoirement être renseigné',
    // 'REACTION_LIKE' => 'J\'approuve',
    // 'REACTION_DISLIKE' => 'Je n\'approuve pas',
    // 'REACTION_ANGRY' => 'Faché·e',
    // 'REACTION_SURPRISED' => 'Surpris·e',
    // 'REACTION_THINKING' => 'Dubitatif·ve',
    // 'REACTION_LOGIN_TO_REACT' => 'Pour réagir, identifiez-vous!',
    // 'REACTION_SHARE_YOUR_REACTION' => 'Partagez votre réaction à propos de ce contenu',
    // 'REACTION_TO_ALLOW_REACTION' => 'Pour vous permettre de réagir',
    // 'REACTION_PLEASE_LOGIN' => 's\'identifier',
    // 'REACTION_NB_REACTIONS_LEFT' => 'choix possible(s)',
    // 'REACTION_ADMINISTER_REACTIONS' => 'Administrer les réactions',
    // 'REACTION_CONNECT_AS_ADMIN' => 'Veuillez vous connecter en tant qu\'admin pour administrer les réactions.',
    // 'REACTION_USER' => 'Utilisateur·ice',
    // 'REACTION_YOUR_REACTIONS' => 'Vos réactions',
    // 'REACTION_VOTE' => 'Vote',
    // 'REACTION_DATE' => 'Date',
    // 'REACTION_DATE_UNKNOWN' => 'Date inconnue',
    // 'REACTION_DELETE' => 'Supprimer',
    // 'REACTION_DELETE_ALL' => 'Tout supprimer',
    // 'REACTION_LOGIN_TO_SEE_YOUR_REACTION' => 'Se connecter pour voir vos réactions.',
    // 'REACTION_YOU_VOTED' => 'Vous avez voté',
    // 'REACTION_FOR_POLL' => 'au sondage',
    // 'REACTION_FROM_PAGE' => 'de la page',
    // 'REACTION_ON_ENTRY' => 'Réaction sur une fiche',
    // 'REACTION_TITLE_PARAM_NEEDED' => 'Le paramètre \'titre\' est obligatoire',
];
