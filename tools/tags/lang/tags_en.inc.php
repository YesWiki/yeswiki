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
* Fichier de traduction en francais de l'extension Hashcash
*
*@package 		tags
*@author        Florian Schmitt <florian@outils-reseaux.org>
*@copyright     2012 Outils-Réseaux
*/

$GLOBALS['translations'] = array_merge($GLOBALS['translations'], array(

'TAGS_ACTION_ADMINTAGS' => 'Action {{admintags ...}}',
'TAGS_ACTION_ADMINTAGS_ONLY_FOR_ADMINS' => 'this action is for the admins only',
'TAGS_NO_WRITE_ACCESS' => 'You have no rights to write on this page !',
'TAGS_FROM_THIS_PAGE' => 'from this page',
'TAGS_FROM_ALL_PAGES' => 'from all pages',
'TAGS_PRESENT_IN' => 'present in',
'TAGS_DELETE_MINUSCULE' => 'delete',
'TAGS_CANCEL' => 'Cancel',
'TAGS_MODIFY' => 'Modidy',
'TAGS_ADD_TAGS' => 'Add tags',
'TAGS_COMMENTS_ACTIVATED' => 'Comments on this page were desactivated.',
'TAGS_ACTIVATE_COMMENTS' => 'Activate comments',
'TAGS_ACTIVATE_COMMENTS_ON_THIS_PAGE' => 'Activate comments on this page',
'TAGS_DESACTIVATE_COMMENTS' => 'Desactivate comments',
'TAGS_DESACTIVATE_COMMENTS_ON_THIS_PAGE' => 'Desactivate comments on this page',
'TAGS_COMMENTS_ON_THIS_PAGE' => 'Comments on this page.',
'TAGS_COMMENTS_DESACTIVATED' => 'Comments desactivated.',
'TAGS_SEE_ALL_PAGES_WITH_THIS_TAGS' => 'See all the pages with this keyword',
'TAGS_ALERT_PAGE_ALREADY_MODIFIED' => 'ALERT : Someone else modified this page while you were editing it.<br />Please copy your changes and paste the in edition after refreshing the page.',
'TAGS_ANSWER_THIS_COMMENT' => 'Answer this comment',
'TAGS_DATE_FORMAT' => "\o\\n d.m.Y \a\\t H:i:s",
'TAGS_WRITE_YOUR_COMMENT_HERE' => 'Write your comment here...',
'TAGS_ADD_YOUR_COMMENT' => 'Add your comment',
'TAGS_ACTION_FILTERTAGS' => 'Action {{filtertags ...}}',
'TAGS_NO_FILTERS' => 'no parameter "filter1" found but it is required.',
'TAGS_ONLY_ONE_DOUBLEPOINT' => 'Only only double point character (:) allowed to indicate the label, more were found.',

'TAGS_ACTION_INCLUDEPAGES' => 'Action {{includepages ...}}',
'TAGS_NO_PARAM_PAGES' => 'parameter "pages" required.',

'TAGS_NO_RESULTS' => 'No results with those keywords.',
'TAGS_RESULTS' => 'results',
'TAGS_FILTER' => 'Filter',
'TAGS_CONTAINING_TAG' => 'with the keyword',
'TAGS_ONE_PAGE' => 'One page',
'TAGS_PAGES' => 'pages',

// actions/rss.php
'TAGS_RSS_FEED_FOR_NEW_PAGES_WITH_TAGS' => 'RSS feed from pages with tag',

// handler exportpages
'TAGS_YOUR_EBOOK' => 'Your Ebook',
'TAGS_SPAM_RISK' => 'You must activate Javascript or you will be considered as spam.',
'TAGS_GENERATE_EBOOK' => 'Create the Ebook',
'TAGS_EXPORT_PAGES_INFO' => 'Select the pages for your Ebook by clicking on ',
'TAGS_ORDER_PAGES_INFO' => 'Move the different pages to file them as you like it.',
'TAGS_EBOOK_TITLE' => 'Book title',
'TAGS_EBOOK_DESC' => 'Description',
'TAGS_EBOOK_AUTHOR' => 'The author\'s first name then surname',
'TAGS_EXAMPLE_AUTHOR' => 'Ex: William Shakespeare',
'TAGS_EBOOK_BIBLIO_AUTHOR' => 'The author\'s name, coma first name',
'TAGS_EXAMPLE_BIBLIO_AUTHOR' => 'Ex: Shakespeare, William',
'TAGS_EBOOK_COVER_IMAGE' => 'Link to your Ebook\'s cover
',
'TAGS_NO_TITLE_FOUND' => 'ERROR : no title entered.',
'TAGS_NO_DESC_FOUND' => 'ERROR : no description entered.',
'TAGS_NO_AUTHOR_FOUND' => 'ERROR : no author entered.',
'TAGS_NO_BIBLIO_AUTHOR_FOUND' => 'ERROR : no author (with name coma first name) entered .',
'TAGS_NO_IMAGE_FOUND' => 'ERROR : no link to cover image entered.',
'TAGS_NOT_IMAGE_FILE' => 'ERROR : the link to cover image must be a .jpg image.',
'TAGS_EBOOK_PAGE_CREATED' => 'The Ebook page was successfully created',
'TAGS_GOTO_EBOOK_PAGE' => 'Go to see the page : ',
'TAGS_FILTER_PAGES' => 'Filter the pages',
'TAGS_SEE_PAGE' => 'See the page',
'TAGS_SELECT_PAGE' => 'Select the page',
'TAGS_DELETE_PAGE' => 'Remove the page',
'TAGS_DELETE' => 'Delete',
'TAGS_FOR_THE_EBOOK' => 'for the Ebook',
'TAGS_FROM_THE_EBOOK' => 'from the Ebook',
'TAGS_AVAILABLE_PAGES' => 'Available pages',
'TAGS_START_PAGE' => 'Introduction page',
'TAGS_END_PAGE' => 'End page',
'TAGS_PUBLISHED_UNDER_CREATIVE_COMMONS_BY_SA' => 'This Ebook is published under the Creative Commons BY SA licence.',
'TAGS_BY' => 'By',
'TAGS_ABOUT_THIS_EBOOK' => 'Information about this book',
'TAGS_DOWNLOAD_EPUB' => '.epub',
'TAGS_DOWNLOAD' => 'Download',
'TAGS_NO_EBOOK_METADATAS' => 'This page has no meta-datas pour creating an ebook.',
'TAGS_NO_EBOOK_FOUND' => 'No Ebook found.',

));