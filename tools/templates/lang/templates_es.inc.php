<?php
/*vim: set expandtab tabstop=4 shiftwidth=4: */
// +------------------------------------------------------------------------------------------------------+
// | PHP version 5                                                                                        |
// +------------------------------------------------------------------------------------------------------+
// | Copyright (C) 2012 Outils-Réseaux (accueil@outils-reseaux.org)                                       |
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
* Fichier de traduction en espagnol de l'extension Templates
*
*@package 		templates
*@author        Louise Didier <louise@quincaillere.org>
*@copyright     2016 Outils-Réseaux
*/

$GLOBALS['translations'] = array_merge($GLOBALS['translations'], array(

// actions/button.php
'TEMPLATE_ACTION_BUTTON' => 'Acción {{button ...}}',
'TEMPLATE_LINK_PARAMETER_REQUIRED' => 'parámetro "link" obligatorio',
'TEMPLATE_RSS_LAST_CHANGES' => 'Flujo RSS de las última páginas modificadas',
'TEMPLATE_RSS_LAST_COMMENTS' => 'Flujo RSS de los últimos comentarios',
'TEMPLATE_NO_DEFAULT_THEME' => 'Los archivos del template por defecto han desaparecido, el uso de los templates es imposible.<br />Por favor, reinstala el tools template o contactar el administrador del sitio',
'TEMPLATE_CUSTOM_GRAPHICS' => 'Apariencia de la página',
'TEMPLATE_SAVE' => 'Guardar',
'TEMPLATE_APPLY' => 'Aplicar',
'TEMPLATE_CANCEL' => 'Cancelar',
'TEMPLATE_THEME' => 'Tema',
'TEMPLATE_SQUELETTE' => 'Esqueleto',
'TEMPLATE_STYLE' => 'Estilo',
'TEMPLATE_BG_IMAGE' => 'Imagen de fondo',
'TEMPLATE_ERROR_NO_DATA' => 'ERROR : no hay nada que añadir en los metadatos.',
'TEMPLATE_ERROR_NO_ACCESS' => 'ERROR : no tienes los derechos de acceso.',

// barre de redaction
'TEMPLATE_VIEW_PAGE' => 'Ver la página',
'TEMPLATE_EDIT' => 'Editar',
'TEMPLATE_EDIT_THIS_PAGE' => 'Editar la página',
'TEMPLATE_CLICK_TO_SEE_REVISIONS' => 'Las última modificaciones de la página',
'TEMPLATE_LAST_UPDATE' => 'Modificado el',
'TEMPLATE_OWNER' => 'Proprietario',
'TEMPLATE_YOU' => 'tu',
'TEMPLATE_NO_OWNER' => 'No proprietario',
'TEMPLATE_CLAIM' => 'Apropriación',
'TEMPLATE_CLICK_TO_CHANGE_PERMISSIONS' => 'Editar las permisiones de la página',
'TEMPLATE_PERMISSIONS' => 'Permisiones',
'TEMPLATE_DELETE' => 'Suprimir',
'TEMPLATE_DELETE_PAGE' => 'Suprimir la página',
'TEMPLATE_CLICK_TO_SEE_REFERENCES' => 'Las URLs que se refieren a la página',
'TEMPLATE_REFERENCES' => 'Referencias',
'TEMPLATE_SLIDESHOW_MODE' => 'Abrir esta página en modo diaporama.',
'TEMPLATE_SLIDESHOW' => 'Diaporama',
'TEMPLATE_SEE_SHARING_OPTIONS' => 'Compartir la página',
'TEMPLATE_SHARE' => 'Compartir',

// formatage des dates
'TEMPLATE_DATE_FORMAT' => 'd.m.Y \a \l\a\s H:i:s',

// recherche
'TEMPLATE_SEARCH_INPUT_TITLE' => 'Buscar en YesWiki [alt-shift-C]',
'TEMPLATE_SEARCH_BUTTON_TITLE' => 'Buscar las páginas con este texto.',
'TEMPLATE_SEARCH_PLACEHOLDER' => 'Buscar...',
'TEMPLATE_SEARCH' => 'Buscar',

// handler widget
'TEMPLATE_WIDGET_TITLE' => 'Widget : integrar el contenido de esta página en otra parte',
'TEMPLATE_WIDGET_COPY_PASTE' => 'Copiar-pegar el codigo HTML más arriba para integrar le contenido tal como aparece más abajo.',

// handler share
'TEMPLATE_SHARE_INCLUDE_CODE' => 'Codigo de integración de contenido en una página HTML',
'TEMPLATE_SHARE_MUST_READ' => 'Para leer : ',
'TEMPLATE_SHARE_FACEBOOK' => 'Compartir en Facebook',
'TEMPLATE_SHARE_TWITTER' => 'Compartir en Twitter',
'TEMPLATE_SHARE_NETVIBES' => 'Compartir en Netvibes',
'TEMPLATE_SHARE_DELICIOUS' => 'Compartir en Delicious',
'TEMPLATE_SHARE_GOOGLEREADER' => 'Compartir en Google Reader',
'TEMPLATE_SHARE_MAIL' => 'Enviar el contenido de esta página por mail',
'TEMPLATE_ADD_SHARE_BUTTON' => 'Añadir en la parte superior derecha un botón para compartir la página',
'TEMPLATE_ADD_EDIT_BAR' => 'Añadir la barra de edición abajo de la página',

// handler diaporama
'TEMPLATE_NO_ACCESS_TO_PAGE' => 'Nos tienes los derechos de acceso a esta página.',
'TEMPLATE_PAGE_DOESNT_EXIST' => 'No existe esta página',
'PAGE_CANNOT_BE_SLIDESHOW' => 'La página no se puede cortar en diapositives (no hay títulos de nivel 2)',

// handler edit
'TEMPLATE_CUSTOM_PAGE' => 'Preferencias de la página',
'TEMPLATE_PAGE_PREFERENCES' => 'Parámetros de la página',
'PAGE_LANGUAGE' => 'Idioma de la página',
'CHOOSE_PAGE_FOR' => 'Elegir una página para',
'HORIZONTAL_MENU_PAGE' => 'el menú horizontal',
'FAST_ACCESS_RIGHT_PAGE' => 'acceso rapido en la parte superior derecha',
'HEADER_PAGE' => 'la cabecera (bandera)',
'FOOTER_PAGE' => 'el pie de página',
'FOR_2_OR_3_COLUMN_THEMES' => 'Para los temas con 2 o 3 columnas',
'VERTICAL_MENU_PAGE' => 'el menú vertical',
'RIGHT_COLUMN_PAGE' => 'la columna de derecha',

// actions/yeswikiversion.php
'RUNNING_WITH' => 'Disfruta con',

'TEMPLATE_NO_THEME_FILES' => 'Archivo de los temas que faltan',
'TEMPLATE_DEFAULT_THEME_USED' => 'El tema por defecto est donc utilisé',

// actions/end.php
'TEMPLATE_ACTION_END' => 'Acción {{end ...}}',
'TEMPLATE_ELEM_PARAMETER_REQUIRED' => 'parámetro "elem" obligatorio',

// actions/col.php
'TEMPLATE_ACTION_COL' => 'Acción {{col ...}}',
'TEMPLATE_SIZE_PARAMETER_REQUIRED' => 'parámetro "size" obligatorio',
'TEMPLATE_SIZE_PARAMETER_MUST_BE_INTEGER_FROM_1_TO_12' => 'el parámetro "size" tiene que ser un número entero entre 1 y 12',
'TEMPLATE_ELEM_COL_NOT_CLOSED' => 'la acción {{col ...}} tiene que ser cerrada por una acción {{end elem="col"}}',

// actions/grid.php
'TEMPLATE_ACTION_GRID' => 'Acción {{grid ...}}',
'TEMPLATE_ELEM_GRID_NOT_CLOSED' => 'la acción {{grid ...}} tiene que ser cerrada por una acción {{end elem="grid"}}',

// actions/buttondropdown.php
'TEMPLATE_ACTION_BUTTONDROPDOWN' => 'Acción {{buttondropdown ...}}',
'TEMPLATE_ELEM_BUTTONDROPDOWN_NOT_CLOSED' => 'la acción {{buttondropdown ...}} tiene que ser cerrada por una acción {{end elem="buttondropdown"}}',

// actions/adminpages.php
'TEMPLATE_ACTION_FOR_ADMINS_ONLY' => 'acción reservada a los administradores',
'TEMPLATE_CONFIRM_DELETE_PAGE' => 'Estas seguro de querer suprimir definitivamente esta página ?',
'TEMPLATE_PAGE' => 'Página',
'TEMPLATE_LAST_MODIFIED' => 'última modificación',
'TEMPLATE_OWNER' => 'Proprietario',
'TEMPLATE_ACTIONS' => 'Acciones',
'TEMPLATE_MODIFY' => 'Modificar',
)
);
