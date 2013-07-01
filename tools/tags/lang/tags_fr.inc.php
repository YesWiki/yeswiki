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


define ('TAGS_NO_WRITE_ACCESS', 'Vous n\'avez pas acc&egrave;s en &eacute;criture &agrave; cette page !');
define ('TAGS_CANCEL', 'Annuler');
define ('TAGS_MODIFY', 'Modifier');
define ('TAGS_COMMENTS_ACTIVATED', 'Les commentaires de cette page ont &eacute;t&eacute; activ&eacute;s.');
define ('TAGS_ACTIVATE_COMMENTS', 'Activer les commentaires');
define ('TAGS_ACTIVATE_COMMENTS_ON_THIS_PAGE', 'Activer les commentaires sur cette page');
define ('TAGS_DESACTIVATE_COMMENTS', 'D&eacute;sactiver les commentaires');
define ('TAGS_DESACTIVATE_COMMENTS_ON_THIS_PAGE', 'D&eacute;sactiver les commentaires sur cette page');
define ('TAGS_COMMENTS_ON_THIS_PAGE', 'Commentaires sur cette page.');
define ('TAGS_COMMENTS_DESACTIVATED', 'Commentaires d&eacute;sactiv&eacute;s.');
define ('TAGS_SEE_ALL_PAGES_WITH_THIS_TAGS', 'Voir toutes les pages contenant ce mot cl&eacute;');
define ('TAGS_ALERT_PAGE_ALREADY_MODIFIED', 'ALERTE : Cette page a &eacute;t&eacute; modifi&eacute;e par quelqu\'un d\'autre pendant que vous l\'&eacute;ditiez.<br />Veuillez copier vos changements et r&eacute;&eacute;diter cette page.');
define ('TAGS_ANSWER_THIS_COMMENT', 'R&eacute;pondre &agrave; ce commentaire');
define ('TAGS_DATE_FORMAT', "\l\e d.m.Y &\a\g\\r\av\e; H:i:s");
define ('TAGS_WRITE_YOUR_COMMENT_HERE', 'Ecrire votre commentaire ici...');
define ('TAGS_ADD_YOUR_COMMENT', 'Ajouter votre commentaire');
define ('TAGS_NO_FILTERS', 'Action filtertags : pas de parametre filter1 trouv&eacute;, alors qu\'il est obligatoire.');
define ('TAGS_ONLY_ONE_DOUBLEPOINT', 'Action filtertags : il ne peut y avoir qu\'une fois le double point (:) pour indiquer le label, plusieurs trouv&eacute;s.');
define ('TAGS_NO_RESULTS', 'Pas de r&eacute;sultats avec ces mots cl&eacute;s.');
define ('TAGS_RESULTS', 'r&eacute;sultats');
define ('TAGS_FILTER', 'Filtrer');
define ('TAGS_CONTAINING_TAG', 'avec le mot cl&eacute;');

// handler exportpages
define ('TAGS_GENERATE_EBOOK', 'Cr&eacute;er l\'Ebook &agrave; partir de cette s&eacute;lection');
define ('TAGS_EXPORT_PAGES_INFO', 'Vous pouvez changer l\'ordre des pages en cliquant dessus puis en les d&eacute;pla&ccedil;ant.<br />Visualisez la page en cliquant sur le bouton <a href="#" class="btn btn-mini"><i class="icon-eye-open"></i></a>, et enlevez des pages de cette s&eacute;lection en appuyant sur le bouton <a href="#" class="btn btn-mini"><i class="icon-minus"></i></a>.');
define ('TAGS_EBOOK_TITLE', 'Titre de l\'ouvrage');
define ('TAGS_EBOOK_DESC', 'Description');
define ('TAGS_EBOOK_AUTHOR', 'Pr&eacute;nom puis nom de l\'auteur');
define ('TAGS_EXAMPLE_AUTHOR', 'Ex: Victor Hugo');
define ('TAGS_EBOOK_BIBLIO_AUTHOR', 'Nom de l\'auteur, virgule pr&eacute;nom');
define ('TAGS_EXAMPLE_BIBLIO_AUTHOR', 'Ex: Hugo, Victor');
define ('TAGS_EBOOK_COVER_IMAGE', 'Lien vers une l\'image de couverture de l\'ouvrage');
define ('TAGS_NO_TITLE_FOUND', 'ERREUR : Le titre n\'a pas &eacute;t&eacute; renseign&eacute;.');
define ('TAGS_NO_DESC_FOUND', 'ERREUR : La description n\'a pas &eacute;t&eacute; renseign&eacute;.');
define ('TAGS_NO_AUTHOR_FOUND', 'ERREUR : L\'auteur n\'a pas &eacute;t&eacute; renseign&eacute;.');
define ('TAGS_NO_BIBLIO_AUTHOR_FOUND', 'ERREUR : L\'auteur (version biblio) n\'a pas &eacute;t&eacute; renseign&eacute;.');
define ('TAGS_NO_IMAGE_FOUND', 'ERREUR : Le lien vers l\'image de couverture n\'a pas &eacute;t&eacute; renseign&eacute;.');
define ('TAGS_NOT_IMAGE_FILE', 'ERREUR : Le lien vers l\'image de couverture n\'est pas une image avec l\'extension .jpg.');
define ('TAGS_EBOOK_PAGE_CREATED','La page de l\'Ebook a &eacute;t&eacute; cr&eacute;&eacute;e avec succ&egrave;s' );
define ('TAGS_GOTO_EBOOK_PAGE', 'Aller voir la page : ');
define ('TAGS_PUBLISHED_UNDER_CREATIVE_COMMONS_BY_SA', 'Cet ouvrage est publi&eacute; sous licence Creative Commons BY SA.');
define ('TAGS_BY', 'Par');
define ('TAGS_ABOUT_THIS_EBOOK', "Information sur cet ouvrage");
define ('TAGS_DOWNLOAD_EPUB', 'Ebook au format Epub');
define ('TAGS_NO_EBOOK_METADATAS', 'Cette page ne poss&egrave;de pas les m&eacute;tadonn&eacute;es n&eacute;cessaires pour cr&eacute;er l\'ebook.');
define ('TAGS_NO_EBOOK_FOUND', 'Pas d\'ebook trouv&eacute;.');


?>