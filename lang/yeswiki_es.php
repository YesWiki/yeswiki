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
* Archivo de traducción en español de YesWiki

*@package 		yeswiki
*@author        Louise Didier <louise@quincaillere.org>
*@author        Jérémy Dufraisse <jeremy.dufraisse-info@orange.fr>
*@copyright     2016 Outils-Réseaux
*/

return [

    // Commons
    // 'CAUTION' => 'Attention',
    'BY' => 'por',
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
    'UNKNOWN' => 'Desconocido',
    'WARNING' => 'AVISO',

    // wakka.php
    'INVALID_ACTION' => 'Acción inválida',
    'ERROR_NO_ACCESS' => 'Error: no tienes permisos de acceso a esta acción',
    // 'NOT_FOUND' => 'N\'existe pas',
    'FORMATTER_NOT_FOUND' => 'Imposible encontrar el formateador',
    'HANDLER_NO_ACCESS' => 'No tienes acceso a esta página por el handler específico',
    'NO_REQUEST_FOUND' => '$_REQUEST[] no encontrada;. Wakka necesita PHP 4.1.0 o más reciente',
    'SITE_BEING_UPDATED' => 'Este sitio se esta actualizando. Intenta más tarde.',
    'DB_CONNECT_FAIL' => 'Por razones ajenas a nuestra voluntad, el contenido de este YesWiki esta temporalmente inaccesible. Intenta más tarde, gracias por su comprensión.',
    'LOG_DB_CONNECT_FAIL' => 'YesWiki : la conexión BDD ha fracasado', // sans accents car commande systeme
                                                               
    'INCORRECT_PAGENAME' => 'El nombre de la página es incorrecto.',
    // 'PERFORMABLE_ERROR' => 'Une erreur inattendue s\'est produite. Veuillez contacter l\'administrateur du site et lui communiquer l\'erreur suivante :',
    'HOMEPAGE_WIKINAME' => 'PaginaPrincipal',
    'MY_YESWIKI_SITE' => 'Mi sitio YesWiki',
    // 'FILE_WRITE_PROTECTED' => 'le fichier de configuration est protégé en écriture',

    // ACLs
    // 'DENY_READ' => 'Vous n\'êtes pas autorisé à lire cette page',
    // 'DENY_WRITE' => 'Vous n\'êtes pas autorisé à écrire sur cette page',
    // 'DENY_COMMENT' => 'Vous n\'êtes pas autorisé à commenter cette page',
    // 'DENY_DELETE' => 'Vous n\'êtes pas autorisé à supprimer cette page',

    // tools.php
    'YESWIKI_TOOLS_CONFIG' => 'Configuración de las extensiones de YesWiki',
    'DISCONNECT' => 'Desconexión',
    'RETURN_TO_EXTENSION_LIST' => 'Regreso a la lista de las extensiones activas',
    'NO_TOOL_AVAILABLE' => 'Ninguna herramienta esta disponible o activa',
    'LIST_OF_ACTIVE_TOOLS' => 'Lista de las extensiones activas',

    // actions/backlinks.php
    'PAGES_WITH_LINK' => 'Paginas teniendo un enlace hacia',
    'PAGES_WITH_LINK_TO_CURRENT_PAGE' => 'Paginas teniendo un enlace hacia la página corriente',
    'NO_PAGES_WITH_LINK_TO' => 'Ninguna página tiene enlace hacia',

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
    'ACTION_RIGHTS' => 'Derechos de la acción',
    'SEE' => 'Ver',
    'ERROR_WHILE_SAVING_ACL' => 'Ha ocurrido un error durante la grabación de la ACL para la acción',
    'ERROR_CODE' => 'codigo de error',
    'NEW_ACL_FOR_ACTION' => 'Nueva ACL para la acción',
    'NEW_ACL_SUCCESSFULLY_SAVED_FOR_ACTION' => 'Nueva ACL grabada con éxito para la acción',
    'EDIT_RIGHTS_FOR_ACTION' => 'Editar los derechos de la acción',
    'SAVE' => 'Guardar',

    // actions/editgroups.class.php
    'DEFINITION_OF_THE_GROUP' => 'Definición del grupo',
    'DEFINE' => 'Definir',
    'CREATE_NEW_GROUP' => 'O crear un nuevo grupo',
    'ONLY_ADMINS_CAN_CHANGE_MEMBERS' => 'No puedes cambiar los miembros del grupo de administradores porque no eres administrador',
    'YOU_CANNOT_REMOVE_YOURSELF' => 'Uno no puede retirarse el mismo del grupo de los administradores',
    'ERROR_RECURSIVE_GROUP' => 'Error: no puedes definir un grupo recursivamente',
    'ERROR_WHILE_SAVING_GROUP' => 'Ha ocurrido un error durante la grabación del grupo',
    'NEW_ACL_FOR_GROUP' => 'Nueva ACL para el grupo',
    'NEW_ACL_SUCCESSFULLY_SAVED_FOR_THE_GROUP' => 'Nueva ACL grabada con éxito para el grupo',
    'EDIT_GROUP' => 'Editar el grupo',
    // 'EDIT_EXISTING_GROUP' => 'Éditer un groupe existant',
    // 'DELETE_EXISTING_GROUP' => 'Supprimer un groupe existant',
    // 'GROUP_NAME' => 'Nom du groupe',
    // 'SEE_EDIT' => 'Voir / Éditer',
    'ONLY_ALPHANUM_FOR_GROUP_NAME' => 'Los nombres de los grupos solo pueden componerse de caracteres alfanuméricas',
    // 'LIST_GROUP_MEMBERS' => 'Liste des membres du groupe {groupName}',
    // 'ONE_NAME_BY_LINE' => 'un nom d\'utilisateur par ligne',

    // actions/edithandlersacls.class.php
    'HANDLER_RIGHTS' => 'Derechos del handler',
    'ERROR_WHILE_SAVING_HANDLER_ACL' => 'Ha ocurrido un error durante la grabación de la ACL para el handler',
    'NEW_ACL_FOR_HANDLER' => 'Nueva ACL para el handler',
    'NEW_ACL_SUCCESSFULLY_SAVED_FOR_HANDLER' => 'Nueva ACL grabada con éxito para el handler',
    'EDIT_RIGHTS_FOR_HANDLER' => 'Editar los derechos del handler',

    // actions/erasespamedcomments.class.php
    // 'ERASED_COMMENTS' => 'Commentaire(s) effacé(s)',
    // 'FORM_RETURN' => 'Retour au formulaire',
    // 'NO_RECENT_COMMENTS' => 'Pas de commentaires récents',
    // 'NO_SELECTED_COMMENTS_TO_ERASE' => 'Aucun commentaire n\'a été sélectionné pour étre effacé',

    // actions/footer.php ignoree, car le tools templates court circuite
    // actions/header.php ignoree, car le tools templates court circuite

    // actions/include.php
    'ERROR' => 'Error',
    'ACTION' => 'Acción',
    'MISSING_PAGE_PARAMETER' => 'el parámetro "página" falta',
    'IMPOSSIBLE_FOR_THIS_PAGE' => 'Imposible para la página',
    'TO_INCLUDE_ITSELF' => 'incluir en si misma',
    'INCLUSIONS_CHAIN' => 'Cadena de inclusiones',
    'EDITION' => 'Edición',
    'READING_OF_INCLUDED_PAGE' => 'Lectura de la página incluida',
    'NOT_ALLOWED' => 'no autorizado',
    'INCLUDED_PAGE' => 'La página incluida',
    'DOESNT_EXIST' => 'parace no existir',

    // actions/listpages.php
    'THE_PAGE' => 'La página',
    'BELONGING_TO' => 'perteneciendo a',
    'LAST_CHANGE_BY' => 'última modificación por',
    'LAST_CHANGE' => 'última modificación',
    'PAGE_LIST_WHERE' => 'Lista de las páginas en las cuales',
    'HAS_PARTICIPATED' => 'ha participado',
    'EXCLUDING_EXCLUSIONS' => 'fuera de exclusiones',
    'INCLUDING' => 'incluyendo',
    'IS_THE_OWNER' => 'es el proprietario',
    'NO_PAGE_FOUND' => 'Ninguna página encontrada',
    'IN_THIS_WIKI' => 'en este wiki',
    'LIST_PAGES_BELONGING_TO' => 'Lista de las páginas perteneciendo a',
    'THIS_USER_HAS_NO_PAGE' => 'Este usuario no posee ninguna página',

    // actions/mychanges.php
    'YOUR_MODIFIED_PAGES_ORDERED_BY_MODIFICATION_DATE' => 'Lista de las páginas que modificaste, clasificadas por fecha de modificación',
    'YOUR_MODIFIED_PAGES_ORDERED_BY_NAME' => 'Lista de las páginas que modificaste, clasificadas par orden alfabético',
    'YOU_DIDNT_MODIFY_ANY_PAGE' => 'No modificaste ninguna página',
    'YOU_ARENT_LOGGED_IN' => 'No estas registrado',
    'IMPOSSIBLE_TO_SHOW_YOUR_MODIFIED_PAGES' => 'imposible visualizar la lista de las páginas que modificaste',
    'LIST_OF_PAGES_WHERE_YOU_ARE_THE_OWNER' => 'Lista de las páginas de las que eres proprietario',
    'YOU_DONT_OWN_ANY_PAGE' => 'No eres proprietario de ninguna página',

    // actions/nextextsearch.php
    // 'NEWTEXTSEARCH_HINT' => 'Un caractère inconnu peut être remplacé par « ? » plusieurs par « * »',
    // 'NO_SEARCH_RESULT' => 'Désolé mais il n\'y a aucun de résultat pour votre recherche',
    // 'SEARCH_RESULTS' => 'Résultats de la recherche',

    // actions/orphanedpages.php
    'NO_ORPHAN_PAGES' => 'No páginas huérfanas',

    // actions/recentchanges.php
    'HISTORY' => 'historial',

    // actions/recentchangesrss.php
    'TO_OBTAIN_RSS_FEED_TO_GO_THIS_ADDRESS' => 'Para obtener RSS feeds de los últimos cambios, usa la dirección siguiente',
    'LATEST_CHANGES_ON' => 'últimos cambios en',

    // actions/recentcomments.php
    'NO_RECENT_COMMENTS' => 'No comentarios recientes',

    // actions/recentcommentsrss.php
    'TO_OBTAIN_COMMENTS_RSS_FEED_TO_GO_THIS_ADDRESS' => 'Para obtener RSS feeds de los últimos comentarios, usa la dirección siguiente',
    'LATEST_COMMENTS_ON' => 'últimos comentarios en',

    // actions/recentlycommented.php
    // 'LAST COMMENT' => 'dernier commentaire',
    'NO_RECENT_COMMENTS_ON_PAGES' => 'ninguna página tiene comentarios recientes',

    // actions/redirect.php
    'ERROR_ACTION_REDIRECT' => 'Error acción {{redirect ...}}',
    'CIRCULAR_REDIRECTION_FROM_PAGE' => 'redirección circular desde la página',
    'CLICK_HERE_TO_EDIT' => 'haz clic aquí para editar',
    'PRESENCE_OF_REDIRECTION_TO' => 'Presencia de una redirección hacia',

    // actions/resetpassword.php
    'ACTION_RESETPASSWORD' => 'Acción {{resetpassword ...}}',
    'PASSWORD_UPDATED' => 'Contraseña inicializada',
    'RESETTING_THE_PASSWORD' => 'Inicialización de la contraseña',
    'WIKINAME' => 'NombreWiki',
    'NEW_PASSWORD' => 'Nueva contraseña',
    'RESET_PASSWORD' => 'Reset contraseña',
    'NO_PERMISSIONS_TO_EXECUTE_THIS_ACTION' => 'No tienes las permisiones necesarias par ejecutar esta acción',

    // actions/textsearch.php
    'WHAT_YOU_SEARCH' => 'Lo que quieres buscar',
    'SEARCH' => 'Buscar',
    'SEARCH_RESULT_OF' => 'Resultado(s) de la búsqueda',
    'NO_RESULT_FOR' => 'Ninugun resultado para',

    // actions/testtriples.php
    // 'END_OF_EXEC' => 'Fin de l\'exécution',

    // actions/trail.php
    'ERROR_ACTION_TRAIL' => 'Error acción {{trail ...}}',
    'INDICATE_THE_PARAMETER_TOC' => 'Indica el nombre de la página índice, parámetro "toc"',

    // actions/usersettings.php
    // 'USER_SETTINGS' => 'Paramètres utilisateur',
    // 'USER_SIGN_UP' => 'S\'inscrire',
    'YOU_ARE_NOW_DISCONNECTED' => ' Ahora estás desconectado',
    'PARAMETERS_SAVED' => 'Parámetro grabados',
    'NO_SPACES_IN_PASSWORD' => 'Los espacios no son permitidos en las contraseñas',
    'PASSWORD_TOO_SHORT' => 'Contraseña demasiado corta',
    'WRONG_PASSWORD' => 'Mala contraseña',
    'PASSWORD_CHANGED' => 'Contraseña cambiada',
    'GREETINGS' => 'Hola',
    'YOUR_EMAIL_ADDRESS' => 'Tu dirección de correo electrónico',
    'DOUBLE_CLICK_TO_EDIT' => 'Edición haciendo doble clic',
    'SHOW_COMMENTS_BY_DEFAULT' => 'Por defecto, enseñar los comentarios',
    'MAX_NUMBER_OF_LASTEST_COMMENTS' => 'Número máximo de últimos comentarios',
    'MAX_NUMBER_OF_VERSIONS' => 'Número máximo de versiones',
    'YOUR_MOTTO' => 'Tu divisa',
    'CHANGE_THE_PASSWORD' => 'Cambio de contraseña',
    'YOUR_OLD_PASSWORD' => 'Tu antigua contraseña',
    'NEW_PASSWORD' => 'Nueva contraseña',
    'CHANGE' => 'Cambiar',
    'USERNAME_MUST_BE_WIKINAME' => 'Tu nombre de usuario tiene que escribirse en el formato NombreWiki',
    'YOU_MUST_SPECIFY_AN_EMAIL' => 'Tienes que especificar una dirección de correo electrónico',
    'THIS_IS_NOT_A_VALID_EMAIL' => 'Esto no se parece a una dirección de correo electrónico',
    'PASSWORDS_NOT_IDENTICAL' => 'Las contraseñas no son idénticas',
    'PASSWORD_SHOULD_HAVE_5_CHARS_MINIMUM' => 'debe tener como mínimo 5 caracteres alfanuméricas',
    'YOU_MUST_ACCEPT_COOKIES_TO_GET_CONNECTED' => 'Necesitas tener cookies permitidos para poder conectarse',
    'IF_YOU_ARE_REGISTERED_LOGGIN_HERE' => 'Si ya tienes una cuenta, identificate aqui',
    'PASSWORD_5_CHARS_MINIMUM' => 'Contraseña (5 caracteres mínimo)',
    'REMEMBER_ME' => 'Acordarse de mi',
    'IDENTIFICATION' => 'Identificación',
    'FILL_THE_NEXT_FIELDS_IF_YOU_LOGGIN_FOR_THE_FIRST_TIME_AND_REGISTER' => 'Tienes que completar los campos siguientes acceder por primera vez (asi crearás una cuenta)',
    'PASSWORD_CONFIRMATION' => 'Confirmación de la contraseña',
    'NEW_ACCOUNT' => 'Nueva cuenta',
    // 'LOGGED_USERS_ONLY_ACTION' => 'Il faut être connecté pour pouvoir exécuter cette action',
    'USER_DELETE' => 'Eliminar usuario',

    // actions/wantedpages.php
    'NO_PAGE_TO_CREATE' => 'Ninguna página para crear',

    // includes/controllers/CsrfController.php
    'NO_CSRF_TOKEN_ERROR' => 'Error de diseño del sitio: el formulario de envío no contenía el token '.
        'de identificación único necesario para los mecanismos de seguridad internos.',
    'CSRF_TOKEN_FAIL_ERROR' => 'Es posible que esta página se haya abierto por segunda vez. '.
        'Renueve la solicitud desde esta ventana (el token de seguridad interno no era bueno).',

    // javascripts/favorites.js
    'FAVORITES_ADD' => 'Agregar a favoritos',
    'FAVORITES_REMOVE' => 'Eliminar de favoritos',

    // templates/actions/my-favorites.twig
    'FAVORITES_DELETE_ALL' => 'Eliminar todos mis favoritos',
    'FAVORITES_MY_FAVORITES' => 'Mis favoritos',
    'FAVORITES_NO_FAVORITE' => 'No se han guardado favoritos',
    'FAVORITES_NOT_ACTIVATED' => 'El uso de favoritos no está habilitado en este sitio.',
    'FAVORITES_NOT_CONNECTED' => 'El uso de favoritos solo es posible para personas conectadas.',

    // templates/actions/my-favorites-table.twig
    'FAVORITES_TITLE' => 'Título',
    'FAVORITES_LINK' => 'Enlace',

    // setup/header.php
    'OK' => 'OK',
    'FAIL' => 'FRACASO',
    'END_OF_INSTALLATION_BECAUSE_OF_ERRORS' => 'Final de la instalación como consecuencia de errores en la configuración',

    // setup/default.php
    'INSTALLATION_OF_YESWIKI' => 'Instalación de YesWiki',
    'YOUR_SYSTEM' => 'Tu sistema',
    'EXISTENT_SYSTEM_RECOGNISED_AS_VERSION' => 'el sistema tiene la versión',
    'YOU_ARE_UPDATING_YESWIKI_TO_VERSION' => 'Estas actualizando YesWiki por la versión',
    'CHECK_YOUR_CONFIG_INFORMATION_BELOW' => 'Revise las informaciones de configuración más abajo',
    'FILL_THE_FORM_BELOW' => 'Rellena el formulario suigiente',
    'DEFAULT_LANGUAGE' => 'Idioma por defecto',
    // 'NAVIGATOR_LANGUAGE' => 'Langue du navigateur',
    'DEFAULT_LANGUAGE_INFOS' => 'Idioma usada por defecto en la  interfaz de YesWiki, siempre es posible cambiar el idioma de cada página creada',
    // 'GENERAL_CONFIGURATION' => 'Configuration générale',
    'DATABASE_CONFIGURATION' => 'Configuración de la base de datos',
    'MORE_INFOS' => 'Más información',
    'MYSQL_SERVER' => 'Servidor MySQL',
    'MYSQL_SERVER_INFOS' => 'La dirección IP o el nombre red de la màquina en la que se encuentra tu servidor MySQL',
    'MYSQL_DATABASE' => 'Base de datos MySQL',
    'MYSQL_DATABASE_INFOS' => 'Esta base de datos tiene que existir para poder seguir',
    'MYSQL_USERNAME' => 'Nombre de usuario MySQL',
    'MYSQL_USERNAME_INFOS' => 'Necesario para conectar a tu base de datos',
    'TABLE_PREFIX' => 'Prefijo de las tablas',
    'TABLE_PREFIX_INFOS' => 'Permite utilizar varios sitios YesWiki en una misma base de datos : cada nuevo YesWiki instalado tiene que tener un prefijo de las tablas diferente',
    'MYSQL_PASSWORD' => 'Contraseña MySQL',
    'YESWIKI_WEBSITE_CONFIGURATION' => 'Configuración de su sitio YesWiki',
    'YOUR_WEBSITE_NAME' => 'Nombre de su sitio',
    'YOUR_WEBSITE_NAME_INFOS' => 'Puede ser un NombreWiki o cualquier otro nombre que aparezca en las pestañas y ventanas',
    'HOMEPAGE' => 'Página de inicio',
    'HOMEPAGE_INFOS' => 'Página de inicio de tu YesWiki. Tiene que ser un NombreWiki',
    'KEYWORDS' => 'Palabras clave',
    'KEYWORDS_INFOS' => 'Palabras clave que seran integradas en los códigos HTML (metadatos)',
    'DESCRIPTION' => 'Descripción',
    'DESCRIPTION_INFOS' => 'La descripción de tu sitio que sera integrada en los códigos HTML (metadatos)',
    'CREATION_OF_ADMIN_ACCOUNT' => 'Creación de una cuenta administrador',
    'ADMIN_ACCOUNT_CAN' => 'La cuenta administrador permite',
    'MODIFY_AND_DELETE_ANY_PAGE' => 'Modificar y retirar cualquier página',
    'MODIFY_ACCESS_RIGHTS_ON_ANY_PAGE' => 'Modificar los derechos de acceso a cualquier página',
    'GENERATE_ACCESS_RIGHTS_ON_ANY_ACTION_OR_HANDLER' => 'Administrar los derechos de acceso a cualquier acción o handler',
    'GENERATE_GROUPS' => 'Administrar los grupos, añadir/retirar usuarios al grupo administrador (teniendo los mismos derechos que el)',
    'ALL_ADMIN_TASKS_ARE_DESCRIBED_IN_THE_PAGE' => 'Todas las tareas de administración están descritas en la página "AdministracionDeYesWiki" accesible desde la página de inicio',
    'USE_AN_EXISTING_ACCOUNT' => 'Utilizar una cuenta existente',
    'NO' => 'No',
    'YES' => 'Si',
    'OR_CREATE_NEW_ACCOUNT' => 'O crear una nueva cuenta',
    'ADMIN' => 'Administrador',
    'MUST_BE_WIKINAME' => 'tiene que ser un NombreWiki',
    'PASSWORD' => 'Contraseña',
    'EMAIL_ADDRESS' => 'Dirección de correo electrónico ',
    'MORE_OPTIONS' => 'Opciones suplementarias',
    'ADVANCED_CONFIGURATION' => '+ Configuración avanzada',
    'URL_REDIRECTION' => 'Redirección por URL',
    'NEW_INSTALL_VALUES_CHANGE_ONLY_IF_YOU_KNOW_WHAT_YOU_ARE_DOING' => 'Es una nueva instalación. El programa de instalación intenta encontrar las valores apropriadas. Cambialas solo si eres un usuario avanzado',
    'PAGENAME_WILL_BE_ADDED_AFTER_CHANGE_JUST_FOR_REDIRECTION' => 'Los nombres de páginas son directamente puestos al final de la URL de base de tu sitio YesWiki. Quita la parte "?wiki=" solo si utilizas la redirección (ver más abajo)',
    'BASE_URL' => 'URL de base',
    'REDIRECTION_SHOULD_BE_ACTIVE_ONLY_IF_USED_IN_YESWIKI' => 'Selectionna el modo "redirección automática" solo si utilizas YesWiki con la redirección de URL (si no sabes que es la redirección de URL no active esta opción)',
    // 'HTML_INSERTION_HELP_TEXT' => 'Augmente grandement les fonctionnalités du wiki, en permettant l\'ajout de vidéos et iframe par exemple, mais est moins sécurisé',
    // 'INDEX_HELP_TEXT' => 'Indique dans les meta-données html et dans le fichier robots.txt si votre site doit etre indexé par les moteurs de recherche ou pas',
    'ACTIVATE_REDIRECTION_MODE' => 'Activación del modo "redirección automática"',
    'OTHER_OPTIONS' => 'Otras optiones',
    'OBLIGE_TO_PREVIEW_BEFORE_SAVING_PAGE' => 'Obliga a una previsualización antes de poder guardar una página',
    'AUTHORIZE_HTML_INSERTION' => 'Autorizar a la  inserción directa de HTML',
    // 'AUTHORIZE_INDEX_BY_ROBOTS' => 'Autoriser l\'indexation par les moteurs de recherche',
    'CONTINUE' => 'Seguir',

    // setup/install.php
    'PROBLEM_WHILE_INSTALLING' => 'problema en el proceso de instalación',
    'VERIFICATION_OF_DATAS_AND_DATABASE_INSTALLATION' => 'Test de la configuración e instalación de la base de datos',
    'VERIFY_MYSQL_PASSWORD' => 'Verificación de la contraseña MySQL',
    'INCORRECT_MYSQL_PASSWORD' => 'La contraseña MySQL es incorrecta',
    'TEST_MYSQL_CONNECTION' => 'Test conexión MySQL',
    'SEARCH_FOR_DATABASE' => 'Búsqueda base de datos',
    'GO_BACK' => 'Volver',
    'NO_DATABASE_FOUND_TRY_TO_CREATE' => 'La base de datos no existe. Intentamos crearla',
    'TRYING_TO_CREATE_DATABASE' => 'Intento de creación de la base de datos',
    'DATABASE_COULD_NOT_BE_CREATED_YOU_MUST_CREATE_IT_MANUALLY' => 'Creación de la base imposible. Tienes que crear esta base manualmente antes  de instalar YesWiki',
    'DATABASE_DOESNT_EXIST_YOU_MUST_CREATE_IT' => 'La base de datos no existe, Tienes que crearla antes  de instalar YesWiki',
    'CHECKING_THE_ADMIN_PASSWORD' => 'Verificación de la contraseña Administrador',
    'CHECKING_THE_ADMIN_PASSWORD_CONFIRMATION' => 'Verificación de la identidad de las contraseñas administradores',
    // 'CHECKING_ROOT_PAGE_NAME' => 'V&eacute;rification du nom de la page d\'accueil',
    // 'INCORRECT_ROOT_PAGE_NAME' => 'Le nom de la page d\'accueil doit uniquement contenir des lettres non accentuées, des chiffres, \'_\', \'-\' ou \'.\'',
    'ADMIN_PASSWORD_ARE_DIFFERENT' => 'Las contraseñas Administrador son diferentes',
    'DATABASE_INSTALLATION' => 'Instalación de la base de datos',
    'CREATION_OF_TABLE' => 'Creación de la tabla',
    // 'SQL_FILE_NOT_FOUND' => 'Fichier SQL non trouv&eacute;',
    // 'NOT_POSSIBLE_TO_CREATE_SQL_TABLES' => 'Impossible de créer les tables SQL.',
    'ALREADY_CREATED' => 'Ya creado',
    'ADMIN_ACCOUNT_CREATION' => 'Creación de la cuenta Administrador',
    'INSERTION_OF_PAGE' => 'Inserción de la página',
    'ALREADY_EXISTING' => 'Ya existe',
    'UPDATING_FROM_WIKINI_0_1' => 'Actualizando WikiNi 0.1',
    'TINY_MODIFICATION_OF_PAGES_TABLE' => 'Pequeña modificación de la tabla de las páginas',
    'ALREADY_DONE' => 'Hecho',
    'INSERTION_OF_USER_IN_ADMIN_GROUP' => 'Inserción del usuario en el grupo admin',
    'NEXT_STEP_WRITE_CONFIGURATION_FILE' => 'En la etapa siguiente, el programa de instalación intentara escribir el archivo de configuración',
    'VERIFY_YOU_HAVE_RIGHTS_TO_WRITE_FILE' => 'Verifica que tienes los derechos para escribir en este archivo, sino lo tendras que modificar manualmente',
    // 'CHECK_EXISTING_TABLE_PREFIX' => 'Vérification de l\'existence du préfixe de table',
    // 'TABLE_PREFIX_ALREADY_USED' => 'Le préfixe de table est déjà utilisé. Veuillez en choisir un nouveau.',

    // setup/writeconfig.php
    'WRITING_CONFIGURATION_FILE' => 'Escribiendo el archivo de configuración',
    'CREATED' => 'creado',
    'DONT_CHANGE_YESWIKI_VERSION_MANUALLY' => 'no cambie la yeswiki_version manualmente',
    'WRITING_CONFIGURATION_FILE_WIP' => 'Esta creando el archivo de configuración',
    'FINISHED_CONGRATULATIONS' => 'Proceso acabado, felicidades',
    'GO_TO_YOUR_NEW_YESWIKI_WEBSITE' => 'Acceder a tu nuevo sitio YesWiki',
    'IT_IS_RECOMMANDED_TO_REMOVE_WRITE_ACCESS_TO_CONFIG_FILE' => 'Se recomenda eliminar el acceso de escritura al archivo de configuración',
    'THIS_COULD_BE_UNSECURE' => 'esto puede ser un fallo de seguridad',
    'CONFIGURATION_FILE' => 'el archivo de configuración',
    'CONFIGURATION_FILE_NOT_CREATED' => 'no ha podido crearse',
    'TRY_CHANGE_ACCESS_RIGHTS_OR_FTP_TRANSFERT' => 'Verifica que tu servidor tiene los derechos de acceso de escritura para este archivo. Si por cualquier razón no lo puedes hacer, tienes que copiar las informaciones siguientes en un archivo y transferirlas con un programa ftp al servidor',
    'DIRECTLY_IN_THE_YESWIKI_FOLDER' => 'directamente en el repertorio de YesWiki. Una vez hecho, tu sitio YesWiki deberia funcionar bien',
    'TRY_AGAIN' => 'Intentar de nuevo',
    
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
    // 'USER_YOU_MUST_SPECIFY_A_POSITIVE_INTEGER_FOR' => 'Il faut une valeur entier positif pour ',

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
    'THEME_MANAGER_THEME_FOLDER' => 'La carpeta del tema ',
    'THEME_MANAGER_SQUELETTE_FILE' => 'El fichero del esqueleto ',
    'THEME_MANAGER_NOT_FOUND' => ' no fue encontrado/encontrada.',
    'THEME_MANAGER_ERROR_GETTING_FILE' => 'Una error estalle cargando el fichero : ',
    'THEME_MANAGER_CLICK_TO_INSTALL' => 'Clicar para instalar el tema ',
    'THEME_MANAGER_AND_REPAIR' => ' y reparar el sitio',
    'THEME_MANAGER_LOGIN_AS_ADMIN' => 'Conectaya como administrador para actualizar.',

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
    // 'EDIT_CONFIG_HINT_TIMEZONE' => 'Fuseau horaire du site (ex. UCT, Europe/Paris, Europe/London, GMT = utiliser celui du serveur,)',
    // 'EDIT_CONFIG_HINT_ALLOWED_METHODS_IN_IFRAME' => 'Méthodes autorisées à être affichées dans les iframes (iframe,editiframe,bazariframe,render,all = autoriser tout)',
    // 'EDIT_CONFIG_HINT_REVISIONSCOUNT' => 'Nombre maximum de versions d\'une page affichées par le handler `/revisions`.',
    // 'EDIT_CONFIG_HINT_HTMLPURIFIERACTIVATED' => 'Activer le nettoyage HTML avant sauvegarde. Attention, modifie le contenu à la sauvegarde ! (true ou false)',
    // 'EDIT_CONFIG_HINT_FAVORITES_ACTIVATED' => 'Activer les favoris (true ou false)',
    // 'EDIT_CONFIG_GROUP_CORE' => 'Paramètres Principaux',
    // 'EDIT_CONFIG_GROUP_ACCESS' => 'Droit d\'accès',
    // 'EDIT_CONFIG_GROUP_EMAIL' => 'Emails',

    // actions/userstable.php
    'USERSTABLE_USER_DELETED' => 'Se ha eliminado el usuario "{username}".',
    'USERSTABLE_USER_NOT_DELETED' => 'El usuario "{username}" no se ha eliminado.',
    'USERSTABLE_NOT_EXISTING_USER' => '¡El usuario "{username}" no existe!',
    'GROUP_S' => 'Grupo(s)',

    // handlers/deletepage
    // 'DELETEPAGE_CANCEL' => 'Annuler',
    // 'DELETEPAGE_CONFIRM' => 'Voulez-vous vraiment supprimer d&eacute;finitivement la page {tag}&nbsp;?',
    // 'DELETEPAGE_CONFIRM_WHEN_BACKLINKS' => 'Voulez-vous vraiment supprimer d&eacute;finitivement la page {tag} malgr&eacute; la pr&eacute;sence de liens&nbsp;?',
    // 'DELETEPAGE_DELETE' => 'Supprimer',
    // 'DELETEPAGE_MESSAGE' => 'La page {tag} a d&eacute;finitivement &eacute;t&eacute; supprim&eacute;e',
    // 'DELETEPAGE_NOT_ORPHEANED' => 'Cette page n\'est pas orpheline.',
    // 'DELETEPAGE_NOT_OWNER' => 'Vous n\'&ecirc;tes pas le propri&eacute;taire de cette page.',
    // 'DELETEPAGE_PAGES_WITH_LINKS_TO' => 'Pages ayant un lien vers {tag} :',
    'DELETEPAGE_NOT_DELETED' => 'Página no eliminada.',

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
