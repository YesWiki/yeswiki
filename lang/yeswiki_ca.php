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
*@copyright     2014 Outils-Réseaux
*/

$GLOBALS['translations'] = array(

// wakka.php
'UNKNOWN_ACTION' => 'Acció desconeguda',
'INVALID_ACTION' => 'Acció no permesa',
'ERROR_NO_ACCESS' => 'Error: no teniu accés a l\'acció',
'INCORRECT_CLASS' => 'classe incorrecta',
'UNKNOWN_METHOD' => 'Mètode desconegut',
'FORMATTER_NOT_FOUND' => 'No es troba el formatejador',
'HANDLER_NO_ACCESS' => 'No podeu accedir a aquesta pàgina a través del handler específic.',
'NO_REQUEST_FOUND' => '$_REQUEST[] No s\'ha trobat. Wakka requereix PHP 4.1.0 o superior!',
'SITE_BEING_UPDATED' => 'En procés d\'actualització; proveu-ho més tard.',
'INCORRECT_PAGENAME' => 'El nom de la pàgina és incorrecte.',
'DB_CONNECT_FAIL' => 'Per raons alienes a la nostra voluntat, el contingut de YesWiki és inaccessible temporalment. Proveu-ho més tard. Gràcies per la vostra comprensió.',
'LOG_DB_CONNECT_FAIL' => 'YesWiki: la connexió a la base de dades no s\'ha pogut fer',
'INCORRECT_PAGENAME' => 'El nom de la pàgina és incorrecte.',
'HOMEPAGE_WIKINAME' => 'PaginaPrincipal',
'MY_YESWIKI_SITE' => 'El meu lloc YesWiki',

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

// actions/changestyle.php ignorada...

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
'ONLY_ALPHANUM_FOR_GROUP_NAME' => 'Els noms dels grups només poden contenir caràcters alfanumèrics',

// actions/edithandlersacls.class.php
'HANDLER_RIGHTS' => 'Drets del handler',
'ERROR_WHILE_SAVING_HANDLER_ACL' => 'Hi ha hagut un error durant l\'enregistrament de l\'ACL per al handler',
'NEW_ACL_FOR_HANDLER' => 'Nova ACL per al handler',
'NEW_ACL_SUCCESSFULLY_SAVED_FOR_HANDLER' => 'S\'ha enregistrat amb èxit la nova ACL per al handler',
'EDIT_RIGHTS_FOR_HANDLER' => 'Edita els drets del handler',

// actions/erasespamedcomments.class.php ignorada...
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
'UNKNOWN' => 'Desconegut',
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
'UNKNOWN' => 'Desconegut',
'BY' => 'per',

// actions/mychanges.php
'YOUR_MODIFIED_PAGES_ORDERED_BY_MODIFICATION_DATE' => 'Llista de pàgines que heu modificat, per data',
'YOUR_MODIFIED_PAGES_ORDERED_BY_NAME' => 'Llista de pàgines que heu modificat, per ordre alfabetic',
'YOU_DIDNT_MODIFY_ANY_PAGE' => 'No heu modificat cap pàgina',
'YOU_ARENT_LOGGED_IN' => 'No esteu identificat',
'IMPOSSIBLE_TO_SHOW_YOUR_MODIFIED_PAGES' => 'impossible de carregar la llista de pàgines que heu modificat',
'LIST_OF_PAGES_WHERE_YOU_ARE_THE_OWNER' => 'Llista de les vostres pàgines',
'YOU_DONT_OWN_ANY_PAGE' => 'No sou propietari de cap pàgina',

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

// actions/trail.php
'ERROR_ACTION_TRAIL' => 'Error d\'acció {{trail ...}}',
'INDICATE_THE_PARAMETER_TOC' => 'Indiqueu la pàgina d\'índex, paràmetre "toc"',

// actions/usersettings.php
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
'UPDATE' => 'Actualització',
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
'YOUR_WIKINAME' => 'El vostre NomWiki',
'PASSWORD_5_CHARS_MINIMUM' => 'Contrasenya (5 caràcters com a mínim)',
'REMEMBER_ME' => 'Recorda\'m',
'IDENTIFICATION' => 'Identificar-se',
'FILL_THE_NEXT_FIELDS_IF_YOU_LOGGIN_FOR_THE_FIRST_TIME_AND_REGISTER' => 'Ompliu els camps següents si accediu per primer cop i us enregistreu',
'PASSWORD_CONFIRMATION' => 'Confirmació de contrasenya',
'NEW_ACCOUNT' => 'Crea un compte nou',


// actions/wantedpages.php 
'NO_PAGE_TO_CREATE' => 'No hi ha cap pàgina per crear',

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
'DEFAULT_LANGUAGE_INFOS' => 'El llenguatge utilitzat per a la YesWiki interfície, sempre és possible canviar l\'idioma de cada pàgina creada',
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
'ACTIVATE_REDIRECTION_MODE' => 'Activa el mode "redireccionament automàtic"',
'OTHER_OPTIONS' => 'Altres opcions',
'OBLIGE_TO_PREVIEW_BEFORE_SAVING_PAGE' => 'Obliga a una previsualització abans de desar la pàgina',
'AUTHORIZE_HTML_INSERTION' => 'Autoritza la inserció directa d\'HTML',
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
'SEARCH' => 'Cerca de la base de dades',
'DATABASE_DOESNT_EXIST_YOU_MUST_CREATE_IT' => 'La base de dades no existeix, creeu-la abans d\'instal·lar YesWiki',
'CHECKING_THE_ADMIN_PASSWORD' => 'Verificació de la contrasenya d\'administrador',
'CHECKING_THE_ADMIN_PASSWORD_CONFIRMATION' => 'Verificació de la identificació de les contrasenyes d\'administrador',
'ADMIN_PASSWORD_ARE_DIFFERENT' => 'Les contrasenyes d\'administració són diferents',
'DATABASE_INSTALLATION' => 'Instal·lació de la base de dades',
'CREATION_OF_TABLE' => 'Creació de la taula',
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

// setup/writeconfig.php
'WRITING_CONFIGURATION_FILE' => 'Escrivint el fitxer de configuració',
'CREATED' => 'Ha estat creat',
'DONT_CHANGE_YESWIKI_VERSION_MANUALLY' => 'no canvieu manualment la yeswiki_version',
'WRITING_CONFIGURATION_FILE_WIP' => 'S\'està creant el fitxer de configuració',
'FINISHED_CONGRATULATIONS' => 'Procés completat. Felicitats',
'GO_TO_YOUR_NEW_YESWIKI_WEBSITE' => 'Accediu al vostre nou lloc YesWiki',
'IT_IS_RECOMMANDED_TO_REMOVE_WRITE_ACCESS_TO_CONFIG_FILE' => 'Es recomana eliminar l\'accés d\'escriptura al fitxer de configuració',
'THIS_COULD_BE_UNSECURE' => 'Això podria ser una fallada de seguretat',
'WARNING' => 'ADVERTIMENT',
'CONFIGURATION_FILE' => 'el fitxer de configuració',
'CONFIGURATION_FILE_NOT_CREATED' => 'no s\'ha pogut crear',
'TRY_CHANGE_ACCESS_RIGHTS_OR_FTP_TRANSFERT' => 'Verifiqueu que el vostre servidor té drets d\'accés per l\'escriptura d\'aquest fitxer. Si per alguna raó no podeu fer-ho, heu de copiar les informacions següents en un fitxer i transferir-les mitjançant un programa FTP al servidor.',
'DIRECTLY_IN_THE_YESWIKI_FOLDER' => 'Directament al directori de YesWiki. Així que ho hagueu fet, el vostre YesWiki ha de funcionar correctament',
'TRY_AGAIN' => 'Proveu-ho una altra vegada',

);