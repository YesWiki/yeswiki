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
'TAGS_ACTION_ADMINTAGS_ONLY_FOR_ADMINS' => 'l\'action est r&eacute;serv&eacute;e au groupe des administrateurs',
'TAGS_NO_WRITE_ACCESS' => 'Vous n\'avez pas acc&egrave;s en &eacute;criture &agrave; cette page !',
'TAGS_FROM_THIS_PAGE' => 'de cette page',
'TAGS_FROM_ALL_PAGES' => 'de toutes les pages',
'TAGS_PRESENT_IN' => 'pr&eacute;sent dans',
'TAGS_DELETE_MINUSCULE' => 'supprimer',
'TAGS_CANCEL' => 'Annuler',
'TAGS_MODIFY' => 'Modifier',
'TAGS_ADD_TAGS' => 'Ajouter mots cl&eacute;s en utilisant la touche entrée',
'TAGS_COMMENTS_ACTIVATED' => 'Les commentaires de cette page ont &eacute;t&eacute; activ&eacute;s.',
'TAGS_ACTIVATE_COMMENTS' => 'Activer les commentaires',
'TAGS_ACTIVATE_COMMENTS_ON_THIS_PAGE' => 'Activer les commentaires sur cette page',
'TAGS_DESACTIVATE_COMMENTS' => 'D&eacute;sactiver les commentaires',
'TAGS_DESACTIVATE_COMMENTS_ON_THIS_PAGE' => 'D&eacute;sactiver les commentaires sur cette page',
'TAGS_COMMENTS_ON_THIS_PAGE' => 'Commentaires sur cette page.',
'TAGS_COMMENTS_DESACTIVATED' => 'Commentaires d&eacute;sactiv&eacute;s.',
'TAGS_SEE_ALL_PAGES_WITH_THIS_TAGS' => 'Voir toutes les pages contenant ce mot cl&eacute;',
'TAGS_ALERT_PAGE_ALREADY_MODIFIED' => 'ALERTE : Cette page a &eacute;t&eacute; modifi&eacute;e par quelqu\'un d\'autre pendant que vous l\'&eacute;ditiez.<br />Veuillez copier vos changements et r&eacute;&eacute;diter cette page.',
'TAGS_ANSWER_THIS_COMMENT' => 'R&eacute;pondre &agrave; ce commentaire',
'TAGS_DATE_FORMAT' => "\l\e d.m.Y &\a\g\\r\av\e; H:i:s",
'TAGS_WRITE_YOUR_COMMENT_HERE' => 'Ecrire votre commentaire ici...',
'TAGS_ADD_YOUR_COMMENT' => 'Ajouter votre commentaire',
'TAGS_ACTION_FILTERTAGS' => 'Action {{filtertags ...}}',
'TAGS_NO_FILTERS' => 'pas de param&egrave;tre "filter1" trouv&eacute;, alors qu\'il est obligatoire.',
'TAGS_ONLY_ONE_DOUBLEPOINT' => 'il ne peut y avoir qu\'une fois le double point (:) pour indiquer le label, plusieurs trouv&eacute;s.',

'TAGS_ACTION_INCLUDEPAGES' => 'Action {{includepages ...}}',
'TAGS_NO_PARAM_PAGES' => 'pas de param&egrave;tre "pages" trouv&eacute;, alors qu\'il est obligatoire.',

'TAGS_NO_RESULTS' => 'Pas de r&eacute;sultats avec ces mots cl&eacute;s.',
'TAGS_RESULTS' => 'r&eacute;sultats',
'TAGS_FILTER' => 'Filtrer',
'TAGS_CONTAINING_TAG' => 'avec le mot cl&eacute;',
'TAGS_ONE_PAGE' => 'Une page',
'TAGS_PAGES' => 'pages',

// actions/rss.php
'TAGS_RSS_FEED_FOR_NEW_PAGES_WITH_TAGS' => 'Flux RSS des nouvelles pages avec les tags',

// handler exportpages
'TAGS_YOUR_EBOOK' => 'Votre Ebook',
'TAGS_SPAM_RISK' => 'Il faut activer javascript pour ne pas &ecirc;tre consid&eacute;r&eacute; comme du spam.',
'TAGS_GENERATE_EBOOK' => 'G&eacute;n&eacute;rer l\'Ebook',
'TAGS_EXPORT_PAGES_INFO' => 'S&eacute;lectionnez vos pages pour l\'Ebook en cliquant sur ',
'TAGS_ORDER_PAGES_INFO' => 'D&eacute;placez les pages pour les mettre dans l\'ordre qui vous convient.',
'TAGS_EBOOK_TITLE' => 'Titre de l\'ouvrage',
'TAGS_EBOOK_DESC' => 'Description',
'TAGS_EBOOK_AUTHOR' => 'Pr&eacute;nom puis nom de l\'auteur',
'TAGS_EXAMPLE_AUTHOR' => 'Ex: Victor Hugo',
'TAGS_EBOOK_BIBLIO_AUTHOR' => 'Nom de l\'auteur, virgule pr&eacute;nom',
'TAGS_EXAMPLE_BIBLIO_AUTHOR' => 'Ex: Hugo, Victor',
'TAGS_EBOOK_COVER_IMAGE' => 'Lien vers l\'image de couverture de l\'ouvrage',
'TAGS_NO_TITLE_FOUND' => 'ERREUR : Le titre n\'a pas &eacute;t&eacute; renseign&eacute;.',
'TAGS_NO_DESC_FOUND' => 'ERREUR : La description n\'a pas &eacute;t&eacute; renseign&eacute;.',
'TAGS_NO_AUTHOR_FOUND' => 'ERREUR : L\'auteur n\'a pas &eacute;t&eacute; renseign&eacute;.',
'TAGS_NO_BIBLIO_AUTHOR_FOUND' => 'ERREUR : L\'auteur (version biblio) n\'a pas &eacute;t&eacute; renseign&eacute;.',
'TAGS_NO_IMAGE_FOUND' => 'ERREUR : Le lien vers l\'image de couverture n\'a pas &eacute;t&eacute; renseign&eacute;.',
'TAGS_NOT_IMAGE_FILE' => 'ERREUR : Le lien vers l\'image de couverture n\'est pas une image avec l\'extension .jpg.',
'TAGS_EBOOK_PAGE_CREATED' => 'La page de l\'Ebook a &eacute;t&eacute; cr&eacute;&eacute;e avec succ&egrave;s',
'TAGS_GOTO_EBOOK_PAGE' => 'Aller voir la page : ',
'TAGS_FILTER_PAGES' => 'Filtrer les pages',
'TAGS_SEE_PAGE' => 'Voir la page',
'TAGS_SELECT_PAGE' => 'S&eacute;lectionner la page',
'TAGS_DELETE_PAGE' => 'Enlever la page',
'TAGS_DELETE' => 'Supprimer',
'TAGS_FOR_THE_EBOOK' => 'pour l&apos;Ebook',
'TAGS_FROM_THE_EBOOK' => 'de l&apos;Ebook',
'TAGS_AVAILABLE_PAGES' => 'Pages disponibles',
'TAGS_START_PAGE' => 'Page d\'introduction',
'TAGS_END_PAGE' => 'Page de fin',
'TAGS_PUBLISHED_UNDER_CREATIVE_COMMONS_BY_SA' => 'Cet ouvrage est publi&eacute; sous licence Creative Commons BY SA.',
'TAGS_BY' => 'Par',
'TAGS_ABOUT_THIS_EBOOK' => 'Information sur cet ouvrage',
'TAGS_DOWNLOAD_EPUB' => '.epub',
'TAGS_DOWNLOAD_PDF' => '.pdf',
'TAGS_DOWNLOAD' => 'Télécharger',
'TAGS_CONTENT_VISIBLE_ONLINE_FROM_PAGE' => 'Contenu en ligne sur la page',
'TAGS_NO_EBOOK_METADATAS' => 'Cette page ne poss&egrave;de pas les m&eacute;tadonn&eacute;es n&eacute;cessaires pour cr&eacute;er l\'ebook.',
'TAGS_NO_EBOOK_FOUND' => 'Pas d\'ebook trouv&eacute;.'

));
