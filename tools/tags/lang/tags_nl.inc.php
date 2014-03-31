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

'TAGS_ACTION_ADMINTAGS' => 'Actie {{admintags ...}}',
'TAGS_ACTION_ADMINTAGS_ONLY_FOR_ADMINS' => 'de actie is voorbehouden aan de beheerdersgroep',
'TAGS_NO_WRITE_ACCESS' => 'U hebt geen schrijftoelating op deze pagina!',
'TAGS_CANCEL' => 'Annuleren',
'TAGS_MODIFY' => 'Wijzigen',
'TAGS_COMMENTS_ACTIVATED' => 'De commentaren voor deze pagina werden geactiveerd.',
'TAGS_ACTIVATE_COMMENTS' => 'De commentaren activeren',
'TAGS_ACTIVATE_COMMENTS_ON_THIS_PAGE' => 'Commentaren activeren op deze pagina',
'TAGS_DESACTIVATE_COMMENTS' => 'Commentaren deactiveren',
'TAGS_DESACTIVATE_COMMENTS_ON_THIS_PAGE' => 'Commentaren deactiveren op deze pagina',
'TAGS_COMMENTS_ON_THIS_PAGE' => 'Commentaren op deze pagina.',
'TAGS_COMMENTS_DESACTIVATED' => 'Commentaren uitgeschakeld.',
'TAGS_SEE_ALL_PAGES_WITH_THIS_TAGS' => 'Alle pagina’s met dit sleutelwoord bekijken',
'TAGS_ALERT_PAGE_ALREADY_MODIFIED' => 'ALARM: Deze pagina werd door iemand anders gewijzigd terwijl u ze bewerkte.<br />Gelieve uw wijzigingen te kopiëren en de pagina opnieuw te bewerken.',
'TAGS_ANSWER_THIS_COMMENT' => 'Antwoorden op deze commentaar',
'TAGS_DATE_FORMAT' => "d.m.J &\a\g\\r\av\e; H:i:s",
'TAGS_WRITE_YOUR_COMMENT_HERE' => 'Schrijf hier uw commentaren...',
'TAGS_ADD_YOUR_COMMENT' => 'Uw commentaar toevoegen',
'TAGS_ACTION_FILTERTAGS' => 'Actie {{filtertags ...}}',
'TAGS_NO_FILTERS' => 'parameter "filter1" verplicht maar niet gevonden.',
'TAGS_ONLY_ONE_DOUBLEPOINT' => 'Er mag maar een dubbel punt (:) staan om het label aan te duiden. Meerdere dubbele punten gevonden.',

'TAGS_ACTION_INCLUDEPAGES' => 'Actie {{includepages ...}}',
'TAGS_NO_PARAM_PAGES' => 'Parameter "pages" verplicht maar niet gevonden.',

'TAGS_NO_RESULTS' => 'Geen resultaten met de sleutelwoorden.',
'TAGS_RESULTS' => 'resultaten',
'TAGS_FILTER' => 'Filteren',
'TAGS_CONTAINING_TAG' => 'met het sleutelwoord',
'TAGS_ONE_PAGE' => 'Een pagina',
'TAGS_PAGES' => 'pagina’s',


// actions/rss.php
'TAGS_RSS_FEED_FOR_NEW_PAGES_WITH_TAGS' => 'RSS-feed van nieuwe pagina’s met tags',

// handler exportpages
'TAGS_YOUR_EBOOK' => 'Uw Ebook',
'TAGS_SPAM_RISK' => 'U dient Javascript te activeren om niet als spam te worden beschouwd.',
'TAGS_GENERATE_EBOOK' => 'Ebook genereren',
'TAGS_EXPORT_PAGES_INFO' => 'Kies uw pagina’s voor het Ebook door op te klikken op',
'TAGS_ORDER_PAGES_INFO' => 'Verplaats de pagina’s om ze in de gewenste volgorde te plaatsen.',
'TAGS_EBOOK_TITLE' => 'Titel van het werk',
'TAGS_EBOOK_DESC' => 'Beschrijving',
'TAGS_EBOOK_AUTHOR' => 'Voornaam en naam van de auteur',
'TAGS_EXAMPLE_AUTHOR' => 'Bv.: Victor Hugo',
'TAGS_EBOOK_BIBLIO_AUTHOR' => 'Naam van de auteur komma voornaam',
'TAGS_EXAMPLE_BIBLIO_AUTHOR' => 'Bv.: Hugo, Victor',
'TAGS_EBOOK_COVER_IMAGE' => 'Koppeling naar de coverafbeelding van het werk',
'TAGS_NO_TITLE_FOUND' => 'FOUT: de titel werd niet ingevuld.',
'TAGS_NO_DESC_FOUND' => 'FOUT: de beschrijving werd niet ingevuld.',
'TAGS_NO_AUTHOR_FOUND' => 'Fout: de auteur werd niet ingevuld.',
'TAGS_NO_BIBLIO_AUTHOR_FOUND' => 'FOUT: de auteur (BIBLIO-versie) werd niet ingevuld.',
'TAGS_NO_IMAGE_FOUND' => 'FOUT: de koppeling naar de coverafbeelding werd niet ingevuld.',
'TAGS_NOT_IMAGE_FILE' => 'FOUT: de koppeling naar de coverafbeelding is geen afbeelding met de extensie .jpg.',
'TAGS_EBOOK_PAGE_CREATED' => 'De pagina van het Ebook werd met succes gecreëerd',
'TAGS_GOTO_EBOOK_PAGE' => 'Ga naar de pagina:',
'TAGS_FILTER_PAGES' => 'Pagina’s filteren',
'TAGS_SEE_PAGE' => 'De pagina bekijken',
'TAGS_SELECT_PAGE' => 'De pagina selecteren',
'TAGS_DELETE_PAGE' => 'De pagina wissen',
'TAGS_FOR_THE_EBOOK' => 'voor het Ebook',
'TAGS_FROM_THE_EBOOK' => 'uit het Ebook',
'TAGS_AVAILABLE_PAGES' => 'Beschikbare pagina’s',
'TAGS_START_PAGE' => 'Introductiepagina',
'TAGS_END_PAGE' => 'Laatste pagina',
'TAGS_PUBLISHED_UNDER_CREATIVE_COMMONS_BY_SA' => 'Dit werk wordt gepubliceerd onder licentie van Creative Commons BY SA.',
'TAGS_BY' => 'Door',
'TAGS_ABOUT_THIS_EBOOK' => 'Informatie over dit werk',
'TAGS_DOWNLOAD_EPUB' => 'Ebook in het Epub-formaat',
'TAGS_NO_EBOOK_METADATAS' => 'Deze pagina beschikt niet over de nodige metagegevens om het Ebook aan te maken.',
'TAGS_NO_EBOOK_FOUND' => 'Geen Ebook gevonden.'

));