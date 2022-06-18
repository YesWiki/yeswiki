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
* Fitxer de traducció al català de l'extensió de tags
*
*@package 		tags
*@author        Jordi Picart <jordi.picart@aposta.coop>
*@copyright     2014 Outils-Réseaux
*/

return [
    
    // 'TAGS_TAGS' => 'Mots clés',
    'TAGS_ACTION_ADMINTAGS' => 'Acció {{admintags ...}}',
    'TAGS_ACTION_ADMINTAGS_ONLY_FOR_ADMINS' => 'l\'acció és exclusiva de l\'administrador',
    'TAGS_NO_WRITE_ACCESS' => 'No teniu dret d\'escriptura en aquesta pàgina',
    // 'TAGS_FROM_THIS_PAGE' => 'de cette page',
    // 'TAGS_FROM_ALL_PAGES' => 'de toutes les pages',
    // 'TAGS_PRESENT_IN' => 'pr&eacute;sent dans',
    // 'TAGS_DELETE_MINUSCULE' => 'supprimer',
    'TAGS_CANCEL' => 'Descarta',
    'TAGS_MODIFY' => 'Modifica',
    // 'TAGS_ADD_TAGS' => 'Ajouter mots cl&eacute;s en utilisant la touche entrée',
    'TAGS_COMMENTS_ACTIVATED' => 'Els comentaris han estat activats.',
    'TAGS_ACTIVATE_COMMENTS' => 'Activa els comentaris',
    'TAGS_ACTIVATE_COMMENTS_ON_THIS_PAGE' => 'Activa els comentaris en aquesta pàgina',
    'TAGS_DESACTIVATE_COMMENTS' => 'Desactiva els comentaris',
    'TAGS_DESACTIVATE_COMMENTS_ON_THIS_PAGE' => 'Desactiva els comentaris en aquesta pàgina',
    'TAGS_COMMENTS_ON_THIS_PAGE' => 'Comentaris en aquesta pàgina.',
    'TAGS_COMMENTS_DESACTIVATED' => 'Comentaris desactivats.',
    'TAGS_SEE_ALL_PAGES_WITH_THIS_TAGS' => 'Mostra totes les pàgines que contenen aquest mot clau',
    'TAGS_ALERT_PAGE_ALREADY_MODIFIED' => 'COMPTE: aquesta pàgina ha estat modificada per algú altre mentre l\'estàveu editant.<br />Copieu els vostres canvis i torneu a iniciar l\'edició.',
    'TAGS_ANSWER_THIS_COMMENT' => 'Respon a aquest comentari',
    'TAGS_DATE_FORMAT' => "\l\e d.m.Y \a \l\e\s H:i:s",
    'TAGS_WRITE_YOUR_COMMENT_HERE' => 'Escriviu el vostre comentari aquí:',
    'TAGS_ADD_YOUR_COMMENT' => 'Afegiu-hi el vostre comentari',
    'TAGS_ACTION_FILTERTAGS' => 'Acció {{filtertags ...}}',
    'TAGS_NO_FILTERS' => 'No s\'ha trobat el paràmetre "filter1", que és obligatori.',
    'TAGS_ONLY_ONE_DOUBLEPOINT' => 'només es pot escriure els dos punts (:) una vegada per indicar l\'etiqueta.',

    'TAGS_ACTION_INCLUDEPAGES' => 'Acció {{includepages ...}}',
    'TAGS_NO_PARAM_PAGES' => 'No s\'ha trobat el paràmetre "pages", que és obligatori.',

    'TAGS_NO_RESULTS' => 'No s\'han obtingut resultats amb aquests mots clau.',
    'TAGS_RESULTS' => 'Resultats',
    'TAGS_FILTER' => 'Filtra',
    'TAGS_CONTAINING_TAG' => 'amb el mot clau',
    'TAGS_ONE_PAGE' => 'Una pàgina',
    'TAGS_PAGES' => 'pàgines',

    // actions/admintag.php
    // 'TAGS_NO_TAG' => 'Aucun mot clé utilisé pour le moment',

    // actions/listpagestag.php
    // 'TAGS_TOTAL_NB_PAGES' => 'Un total de %{nb_total} pages a &eacute;t&eacute; trouv&eacute;',
    // 'TAGS_ONE_PAGE_FOUND' => 'Une page a &eacute;t&eacute; trouv&eacute;e',
    // 'TAGS_NO_PAGE' => 'Aucune page trouv&eacute;e',
    // 'TAGS_WITH_KEYWORD' => 'avec le mot cl&eacute;',
    // 'TAGS_WITH_KEYWORD_SEPARATOR' => 'et',

    // actions/rss.php
    'TAGS_RSS_FEED_FOR_NEW_PAGES_WITH_TAGS' => 'Flux RSS de pàgines noves amb els mots clau',

    // handler exportpages
    'TAGS_YOUR_EBOOK' => 'El vostre Ebook',
    'TAGS_SPAM_RISK' => 'Cal activar el javascript per evitar que us prenguin per spam.',
    'TAGS_GENERATE_EBOOK' => 'Genera l\'Ebook',
    'TAGS_EXPORT_PAGES_INFO' => 'Seleccioneu les vostres pàgines per l\'ebook clicant a ',
    'TAGS_ORDER_PAGES_INFO' => 'Desplaceu les pàgines per ordenar-les al vostre gust.',
    'TAGS_EBOOK_TITLE' => 'Títol del llibre',
    'TAGS_EBOOK_DESC' => 'Descripció',
    'TAGS_EBOOK_AUTHOR' => 'Nom i cognom de l\'autor',
    'TAGS_EXAMPLE_AUTHOR' => 'P.ex: Joanot Martorell',
    'TAGS_EBOOK_BIBLIO_AUTHOR' => 'Cognom de l\'autor, coma nom',
    'TAGS_EXAMPLE_BIBLIO_AUTHOR' => 'P.ex: Martorell, Joanot',
    'TAGS_EBOOK_COVER_IMAGE' => 'Enllaç a la imatge de coberta del llibre',
    'TAGS_NO_TITLE_FOUND' => 'ERROR: no s\'ha facilitat un títol.',
    'TAGS_NO_DESC_FOUND' => 'ERROR: no s\'ha facilitat la descripció.',
    'TAGS_NO_AUTHOR_FOUND' => 'ERROR: no s\'ha facilitat l\'autor.',
    'TAGS_NO_BIBLIO_AUTHOR_FOUND' => 'ERROR: no s\'ha facilitat l\'autor (versió bibliogràfica).',
    'TAGS_NO_IMAGE_FOUND' => 'ERROR: no s\'ha facilitat l\'enllaç a la imatge de coberta.',
    'TAGS_NOT_IMAGE_FILE' => 'ERROR: l\'enllaç a la imatge de coberta no és una imatge amb l\'extensió .jpg.',
    'TAGS_EBOOK_PAGE_CREATED' => 'La pàgina de l\'ebook ha estat creada',
    'TAGS_GOTO_EBOOK_PAGE' => 'Vés a la pàgina: ',
    'TAGS_FILTER_PAGES' => 'Filtra les pàgines',
    'TAGS_SEE_PAGE' => 'Mostra la pàgina',
    'TAGS_SELECT_PAGE' => 'Selecciona la pàgina',
    'TAGS_DELETE_PAGE' => 'Elimina la pàgina',
    'TAGS_DELETE' => 'Elimina',
    'TAGS_FOR_THE_EBOOK' => 'per l\'ebook',
    'TAGS_FROM_THE_EBOOK' => 'de l\'ebook',
    'TAGS_AVAILABLE_PAGES' => 'Pàgines disponibles',
    'TAGS_START_PAGE' => 'Pàgina d\'inici',
    'TAGS_END_PAGE' => 'Última pàgina',
    'TAGS_PUBLISHED_UNDER_CREATIVE_COMMONS_BY_SA' => 'Aquesta obra es publica amb llicència Creative Commons BY SA.',
    'TAGS_BY' => 'Per',
    'TAGS_ABOUT_THIS_EBOOK' => 'Informació sobre aquesta obra',
    'TAGS_DOWNLOAD_EPUB' => '.epub',
    'TAGS_DOWNLOAD_PDF' => '.pdf',
    // 'TAGS_DOWNLOAD' => 'Download',
    // 'TAGS_CONTENT_VISIBLE_ONLINE_FROM_PAGE' => 'Contenu en ligne sur la page',
    'TAGS_NO_EBOOK_METADATAS' => 'Aquesta pàgina no inclou les metadades necessàries per crear l\'ebook.',
    'TAGS_NO_EBOOK_FOUND' => 'No s\'ha trobat cap ebook.',

    // handler/tagrss.php
    // 'TAGS_HIDDEN_CONTENT' => 'Contenu masqué',

    // action builder tags group
    // 'AB_tags_group_label' => 'Gestion des tags',
    // 'AB_tags_admintag_label' => 'Gestion des tags',
    // 'AB_tags_listpagestag_label' => 'Liste des pages par tag',
    // 'AB_tags_listpagestag_type_label' => 'Type',
    // 'AB_tags_listpagestag_type_all' => 'toutes les pages et les fiches du wiki',
    // 'AB_tags_listpagestag_type_wiki' => 'uniquement les pages du wiki',
    // 'AB_tags_listpagestag_type_bazar'=> 'uniquement les fiches des formulaires',
    // 'AB_tags_listpagestag_tags_label'=> 'Tags',
    // 'AB_tags_listpagestag_tags_hint'=> 'séparés par des virgules',
    // 'AB_tags_listpagestag_nb_label'=> 'Nombre de pages',
    // 'AB_tags_listpagestag_tri_label'=> 'Tri',
    // 'AB_tags_listpagestag_tri_date'=> 'tri par date décroissante',
    // 'AB_tags_listpagestag_tri_alpha'=> 'tri par ordre alphabétique',
    // 'AB_tags_listpagestag_template_label'=> 'template',
    // 'AB_tags_listpagestag_template_list'=> 'liste',
    // 'AB_tags_listpagestag_template_list_panel'=> 'liste avec encadré',
    // 'AB_tags_listpagestag_template_list_num'=> 'liste avec puces',
    // 'AB_tags_listpagestag_shownumberinfo_label'=> 'afficher le nombre de pages',
    // 'AB_tags_listpagestag_nuagetag_label'=> 'nuage de tags',
    // 'AB_tags_listpagestag_rss_label'=> 'lien rss vers la liste pour des tags donnés',
    // 'AB_tags_listpagestag_nbclasses_label' => 'nombre de classes CSS disponibles pour le nuage',
    // 'AB_tags_nuagetag_tri_shuffle' => 'aléatoire',

];
