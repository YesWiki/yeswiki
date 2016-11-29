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
* Fichier de traduction en espagnol de l'extension Hashcash
*
*@package 		tags
*@author        Louise Didier <louise@quincaillere.org>
*@copyright     2016 Outils-Réseaux
*/

$GLOBALS['translations'] = array_merge($GLOBALS['translations'], array(

'TAGS_ACTION_ADMINTAGS' => 'Acción {{admintags ...}}',
'TAGS_ACTION_ADMINTAGS_ONLY_FOR_ADMINS' => 'la acción está reservada al grupo de administradores',
'TAGS_NO_WRITE_ACCESS' => 'No tienes los derechos de acceso para escribir en esta página',
'TAGS_FROM_THIS_PAGE' => 'de esta página',
'TAGS_FROM_ALL_PAGES' => 'de todas las páginas',
'TAGS_PRESENT_IN' => 'presente en',
'TAGS_DELETE_MINUSCULE' => 'suprimir',
'TAGS_CANCEL' => 'Cancelar',
'TAGS_MODIFY' => 'Modificar',
'TAGS_ADD_TAGS' => 'Añadir palabras clave',
'TAGS_COMMENTS_ACTIVATED' => 'Los comentarios de esta página han sido activados.',
'TAGS_ACTIVATE_COMMENTS' => 'Activar los comentarios',
'TAGS_ACTIVATE_COMMENTS_ON_THIS_PAGE' => 'Activar los comentarios en esta página',
'TAGS_DESACTIVATE_COMMENTS' => 'Desactivar los comentarios',
'TAGS_DESACTIVATE_COMMENTS_ON_THIS_PAGE' => 'Desactivar los comentarios en esta página',
'TAGS_COMMENTS_ON_THIS_PAGE' => 'Comentarios en esta página.',
'TAGS_COMMENTS_DESACTIVATED' => 'Comentarios desactivados.',
'TAGS_SEE_ALL_PAGES_WITH_THIS_TAGS' => 'Ver todas las páginas con la palabra clave',
'TAGS_ALERT_PAGE_ALREADY_MODIFIED' => 'Atención : Esta página ha sido modificada por otra persona mientras la estabas modificando tu.<br />Por favor, copia tus cambios y vuelve a editar esta página.',
'TAGS_ANSWER_THIS_COMMENT' => 'Responder a este comentario',
'TAGS_DATE_FORMAT' => "\l\e d.m.Y \a \l\e\s H:i:s", 
'TAGS_WRITE_YOUR_COMMENT_HERE' => 'Escribir tu comentario aquí...',
'TAGS_ADD_YOUR_COMMENT' => 'Añadir tu comentario',
'TAGS_ACTION_FILTERTAGS' => 'Acción {{filtertags ...}}',
'TAGS_NO_FILTERS' => 'no se ha encontrado el parámetro "filter1", que es obligatorio.',
'TAGS_ONLY_ONE_DOUBLEPOINT' => 'sólo puede haber una vez los dos puntos (:) para indicar la etiqueta, varios han sido encontrados.',

'TAGS_ACTION_INCLUDEPAGES' => 'Acción {{includepages ...}}',
'TAGS_NO_PARAM_PAGES' => 'no se ha encontrado el parámetro "páginas", que es obligatorio.',

'TAGS_NO_RESULTS' => 'Ningun resultado con estas palabras clave.',
'TAGS_RESULTS' => 'resultados',
'TAGS_FILTER' => 'Filtrar',
'TAGS_CONTAINING_TAG' => 'con la palabra clave',
'TAGS_ONE_PAGE' => 'Una página',
'TAGS_PAGES' => 'páginas',

// actions/rss.php
'TAGS_RSS_FEED_FOR_NEW_PAGES_WITH_TAGS' => 'Flujo RSS de las nuevas páginas con las palabras clave',

// handler exportpages
'TAGS_YOUR_EBOOK' => 'Tu Ebook',
'TAGS_SPAM_RISK' => 'Hay que activar javascript para no ser considerado como spam.',
'TAGS_GENERATE_EBOOK' => 'Generar el Ebook',
'TAGS_EXPORT_PAGES_INFO' => 'Selectiona tus páginas para el Ebook haciendo clic sobre',
'TAGS_ORDER_PAGES_INFO' => 'Pon las páginas en el orden que te conviene.',
'TAGS_EBOOK_TITLE' => 'Título de la obra',
'TAGS_EBOOK_DESC' => 'Descripción',
'TAGS_EBOOK_AUTHOR' => 'Nombre y apellido del autor',
'TAGS_EXAMPLE_AUTHOR' => 'Ej: Lola Flores',
'TAGS_EBOOK_BIBLIO_AUTHOR' => 'Apellido del autor, coma nombre',
'TAGS_EXAMPLE_BIBLIO_AUTHOR' => 'Ej: Flores, Lola',
'TAGS_EBOOK_COVER_IMAGE' => 'Enlace hacia la imagen de portada de la obra',
'TAGS_NO_TITLE_FOUND' => 'ERROR : Falta el título.',
'TAGS_NO_DESC_FOUND' => 'ERROR : Falta la descripción.',
'TAGS_NO_AUTHOR_FOUND' => 'ERROR : Falta el autor.',
'TAGS_NO_BIBLIO_AUTHOR_FOUND' => 'ERROR : Falta el autor (versión biblio).',
'TAGS_NO_IMAGE_FOUND' => 'ERROR : Falta el enlace hacia la imagen de portada.',
'TAGS_NOT_IMAGE_FILE' => 'ERROR : el enlace hacia la imagen de portada no es una imagen con la extensión .jpg.',
'TAGS_EBOOK_PAGE_CREATED' => 'La página del Ebook ha sido creada con éxito',
'TAGS_GOTO_EBOOK_PAGE' => 'Ir a la página : ',
'TAGS_FILTER_PAGES' => 'Filtrar las páginas',
'TAGS_SEE_PAGE' => 'Ver la página',
'TAGS_SELECT_PAGE' => 'Seleccionar la página',
'TAGS_DELETE_PAGE' => 'Suprimir la página',
'TAGS_DELETE' => 'Suprimir',
'TAGS_FOR_THE_EBOOK' => 'para el Ebook',
'TAGS_FROM_THE_EBOOK' => 'desde el Ebook',
'TAGS_AVAILABLE_PAGES' => 'Páginas disponibles',
'TAGS_START_PAGE' => 'Página de inicio',
'TAGS_END_PAGE' => 'Ultima página',
'TAGS_PUBLISHED_UNDER_CREATIVE_COMMONS_BY_SA' => 'Esta obra está publicada bajo la licencia Creative Commons BY SA.',
'TAGS_BY' => 'Por',
'TAGS_ABOUT_THIS_EBOOK' => 'Información sobre esta obra',
'TAGS_DOWNLOAD_EPUB' => '.epub',
'TAGS_DOWNLOAD' => 'Descargar',
'TAGS_CONTENT_VISIBLE_ONLINE_FROM_PAGE' => 'Contenido web en la página',
'TAGS_NO_EBOOK_METADATAS' => 'Esta página no tiene los meta datos necesarios para crear el ebook.',
'TAGS_NO_EBOOK_FOUND' => 'Ningun ebook encontrado.'

));
