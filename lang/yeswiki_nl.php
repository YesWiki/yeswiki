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
* Fichier de traduction en néerlandais de YesWiki
*
*@package 		yeswiki
*@author        Florian Schmitt <florian@outils-reseaux.org>
*@copyright     2014 Outils-Réseaux
*/

$GLOBALS['translations'] = array(

// wakka.php
'UNKNOWN_ACTION' => 'Actie onbekend',
'INVALID_ACTION' => 'Actie ongeldig',
'ERROR_NO_ACCESS' => 'Fout: u hebt geen toegang tot de actie',
'INCORRECT_CLASS' => 'onjuiste klasse',
'UNKNOWN_METHOD' => 'Onbekende methode',
'FORMATTER_NOT_FOUND' => 'Formatter niet gevonden',
'HANDLER_NO_ACCESS' => 'U hebt geen toegang tot deze pagina met de gespecificeerde handler.',
'NO_REQUEST_FOUND' => '$_REQUEST[] niet gevonden. Wakka vereist PHP 4.1.0 of hoger!',
'SITE_BEING_UPDATED' => 'Deze site wordt momenteel bijgewerkt. Probeer het later nog een keer.',
'INCORRECT_PAGENAME' => 'De naam van de pagina is niet correct.',
'DB_CONNECT_FAIL' => 'Om redenen buiten onze wil om is de inhoud van deze YesWiki tijdelijk niet bereikbaar. Probeer het later nog een keer. Dank u voor uw begrip.',
'LOG_DB_CONNECT_FAIL' => 'YesWiki: de BDD-verbinding is verbroken', // sans accents car commande systeme
'INCORRECT_PAGENAME' => 'De naam van de pagina is niet correct.',
'HOMEPAGE_WIKINAME' => 'Hoofdpagina',
'MY_YESWIKI_SITE' => 'Mijn YesWiki-site',

// tools.php
'YESWIKI_TOOLS_CONFIG' => 'Configuratie YesWiki-extensie(s)',
'DISCONNECT' => 'Verbinding verbreken',
'RETURN_TO_EXTENSION_LIST' => 'Terug naar de lijst met actieve extensies',
'NO_TOOL_AVAILABLE' => 'Er is geen enkel instrument beschikbaar of actief',
'LIST_OF_ACTIVE_TOOLS' => 'Lijst met actieve extensies',

// actions/backlinks.php
'PAGES_WITH_LINK' => 'Pagina’s met een koppeling naar',
'PAGES_WITH_LINK_TO_CURRENT_PAGE' => 'Pagina’s met een koppeling naar de huidige pagina',
'NO_PAGES_WITH_LINK_TO' => 'Geen enkele pagina heeft een koppeling naar',

// actions/changestyle.php ignoree...

// actions/editactionsacls.class.php
'ACTION_RIGHTS' => 'Rechten van de actie',
'SEE' => 'Bekijken',
'ERROR_WHILE_SAVING_ACL' => 'Er heeft zich een fout voorgedaan tijdens het opslaan van de ACL voor de actie',
'ERROR_CODE' => 'Foutcode',
'NEW_ACL_FOR_ACTION' => 'Nieuwe ACL voor de actie',
'NEW_ACL_SUCCESSFULLY_SAVED_FOR_ACTION' => 'Nieuwe ACL voor de actie met succes opgeslagen',
'EDIT_RIGHTS_FOR_ACTION' => 'de rechten van de actie bewerken',
'SAVE' => 'Opslaan',

// actions/editgroups.class.php
'DEFINITION_OF_THE_GROUP' => 'Definitie van de groep',
'DEFINE' => 'Definitie',
'CREATE_NEW_GROUP' => 'Of een nieuwe groep aanmaken',
'ONLY_ADMINS_CAN_CHANGE_MEMBERS' => 'U kunt de leden van de groep van beheerders niet wijzigen omdat u geen beheerder bent',
'YOU_CANNOT_REMOVE_YOURSELF' => 'U kunt zichzelf niet schrappen uit de groep van beheerders',
'ERROR_RECURSIVE_GROUP' => 'Fout: u kunt een groep niet recursief definiëren',
'ERROR_WHILE_SAVING_GROUP' => 'Er heeft zich een fout voorgedaan tijdens het opslaan van de groep',
'NEW_ACL_FOR_GROUP' => 'Nieuwe ACL voor de groep',
'NEW_ACL_SUCCESSFULLY_SAVED_FOR_THE_GROUP' => 'Nieuwe ACL met succes geregistreerd voor de groep',
'EDIT_GROUP' => 'De groep bewerken',
'ONLY_ALPHANUM_FOR_GROUP_NAME' => 'De namen van groepen mogen enkel alfanumerieke karakters bevatten',

// actions/edithandlersacls.class.php
'HANDLER_RIGHTS' => 'Rechten van de handler',
'ERROR_WHILE_SAVING_HANDLER_ACL' => ' Er heeft zich een fout voorgedaan tijdens het opslaan van de ACL voor de handler',
'NEW_ACL_FOR_HANDLER' => 'Nieuwe ACL voor de handler',
'NEW_ACL_SUCCESSFULLY_SAVED_FOR_HANDLER' => 'Nieuwe ACL voor de handler met succes geregistreerd',
'EDIT_RIGHTS_FOR_HANDLER' => 'de rechten van de handler bewerken',

// actions/erasespamedcomments.class.php ignoree...
// actions/footer.php ignoree, car le tools templates court circuite
// actions/header.php ignoree, car le tools templates court circuite

// actions/include.php
'ERROR' => 'Fout',
'ACTION' => 'Actie',
'MISSING_PAGE_PARAMETER' => 'de parameter "pagina" ontbreekt',
'IMPOSSIBLE_FOR_THIS_PAGE' => 'Deze pagina kan niet',
'TO_INCLUDE_ITSELF' => 'in zichzelf worden ingesloten',
'INCLUSIONS_CHAIN' => 'Insluitingsketen',
'EDITION' => 'editie',
'READING_OF_INCLUDED_PAGE' => 'Lezen van ingesloten pagina',
'NOT_ALLOWED' => 'niet toegelaten',
'INCLUDED_PAGE' => 'De ingesloten pagina',
'DOESNT_EXIST' => 'lijkt niet te bestaan',

// actions/listpages.php
'THE_PAGE' => 'De pagina',
'BELONGING_TO' => 'eigendom van',
'UNKNOWN' => 'Onbekend',
'LAST_CHANGE_BY' => 'laatste wijziging door',
'LAST_CHANGE' => 'laatste wijziging',
'PAGE_LIST_WHERE' => 'Lijst met pagina’s waaraan',
'HAS_PARTICIPATED' => 'heeft meegewerkt',
'EXCLUDING_EXCLUSIONS' => 'exclusief uitsluitingen',
'INCLUDING' => 'en waarvan',
'IS_THE_OWNER' => 'de eigenaar is',
'NO_PAGE_FOUND' => 'Geen enkele pagina gevonden',
'IN_THIS_WIKI' => 'in deze wiki',
'LIST_PAGES_BELONGING_TO' => 'Lijst met pagina’s die eigendom zijn van',
'THIS_USER_HAS_NO_PAGE' => 'Deze gebruiker bezit geen enkele pagina',
'UNKNOWN' => 'Onbekend',
'BY' => 'Door',

// actions/mychanges.php
'YOUR_MODIFIED_PAGES_ORDERED_BY_MODIFICATION_DATE' => 'Lijst met pagina\'s die u hebt gewijzigd, volgens datum van wijziging',
'YOUR_MODIFIED_PAGES_ORDERED_BY_NAME' => 'Lijst met pagina\'s die u hebt gewijzigd, in alfabetische volgorde',
'YOU_DIDNT_MODIFY_ANY_PAGE' => 'U hebt geen pagina\'s gewijzigd',
'YOU_ARENT_LOGGED_IN' => 'U bent niet aangemeld,',
'IMPOSSIBLE_TO_SHOW_YOUR_MODIFIED_PAGES' => 'De lijst met pagina\'s die u hebt gewijzigd, kan niet worden weergegeven',
'LIST_OF_PAGES_WHERE_YOU_ARE_THE_OWNER' => 'Lijst met pagina\'s waarvan u de eigenaar bent',
'YOU_DONT_OWN_ANY_PAGE' => 'U bezit geen enkele pagina',

// actions/orphanedpages.php
'NO_ORPHAN_PAGES' => 'Geen weespagina\'s',

// actions/recentchanges.php
'HISTORY' => 'historiek',

// actions/recentchangesrss.php
'TO_OBTAIN_RSS_FEED_TO_GO_THIS_ADDRESS' => 'Gebruik het volgende adres om de RSS-feed van de laatste wijzigingen te krijgen',
'LATEST_CHANGES_ON' => 'Laatste wijzigingen aan',

// actions/recentcomments.php
'NO_RECENT_COMMENTS' => 'Geen recente commentaren',

// actions/recentcommentsrss.php
'TO_OBTAIN_COMMENTS_RSS_FEED_TO_GO_THIS_ADDRESS' => 'Gebruik het volgende adres om de RSS-feed van de laatste commentaren te krijgen',
'LATEST_COMMENTS_ON' => 'Laatste commentaren over',

// actions/recentlycommented.php
'NO_RECENT_COMMENTS_ON_PAGES' => 'Er werd recent geen enkele pagina becommentarieerd',

// actions/redirect.php
'ERROR_ACTION_REDIRECT' => 'Actiefout {{redirect ...}}',
'CIRCULAR_REDIRECTION_FROM_PAGE' => 'kringverwijzing van de pagina',
'CLICK_HERE_TO_EDIT' => 'klik hier om te bewerken',
'PRESENCE_OF_REDIRECTION_TO' => 'Aanwezigheid van een verwijzing naar',

// actions/resetpassword.php
'ACTION_RESETPASSWORD' => 'Actie {{resetpassword ...}}',
'PASSWORD_UPDATED' => 'Wachtwoord opnieuw ingesteld',
'RESETTING_THE_PASSWORD' => 'Wachtwoord opnieuw ingesteld',
'WIKINAME' => 'WikiNaam',
'NEW_PASSWORD' => 'Nieuw wachtwoord',
'RESET_PASSWORD' => 'Wachtwoord terugstellen',
'NO_PERMISSIONS_TO_EXECUTE_THIS_ACTION' => 'u beschikt niet over de nodige machtigingen om deze actie uit te voeren',

// actions/textsearch.php
'WHAT_YOU_SEARCH' => 'Wat u wilt zoeken',
'SEARCH' => 'Zoeken',
'SEARCH_RESULT_OF' => 'Resulta(a)t(en) van de opzoeking van',
'NO_RESULT_FOR' => 'Geen enkel resultaat voor',

// actions/trail.php
'ERROR_ACTION_TRAIL' => 'Actiefout {{trail ...}}',
'INDICATE_THE_PARAMETER_TOC' => 'Geef de naam van de beknopte pagina in, "toc"',

// actions/usersettings.php
'YOU_ARE_NOW_DISCONNECTED' => 'U bent niet langer verbonden',
'PARAMETERS_SAVED' => 'Parameters opgeslagen',
'NO_SPACES_IN_PASSWORD' => 'Spaties zijn niet toegestaan in het wachtwoord',
'PASSWORD_TOO_SHORT' => 'Het wachtwoord is te kort',
'WRONG_PASSWORD' => 'Het wachtwoord is niet juist',
'PASSWORD_CHANGED' => 'Het wachtwoord is gewijzigd',
'GREETINGS' => 'Goeiedag',
'YOUR_EMAIL_ADDRESS' => 'Uw e-mailadres',
'DOUBLE_CLICK_TO_EDIT' => 'Dubbelklik om te bewerken',
'SHOW_COMMENTS_BY_DEFAULT' => 'Commentaren standaard weergeven',
'MAX_NUMBER_OF_LASTEST_COMMENTS' => 'Maximaal aantal recentste commentaren',
'MAX_NUMBER_OF_VERSIONS' => 'Maximaal aantal versies',
'YOUR_MOTTO' => 'Uw devies',
'UPDATE' => 'Update',
'CHANGE_THE_PASSWORD' => 'Wachtwoord wijzigen',
'YOUR_OLD_PASSWORD' => 'Uw vorige wachtwoord',
'NEW_PASSWORD' => 'Nieuw wachtwoord',
'CHANGE' => 'Wijzigen',
'USERNAME_MUST_BE_WIKINAME' => 'Uw gebruikersnaam moet als WikiNaam geformatteerd zijn',
'YOU_MUST_SPECIFY_AN_EMAIL' => 'U dient een e-mailadres op te geven',
'THIS_IS_NOT_A_VALID_EMAIL' => 'Dit lijkt niet op een e-mailadres',
'PASSWORDS_NOT_IDENTICAL' => 'De wachtwoorden waren niet identiek',
'PASSWORD_SHOULD_HAVE_5_CHARS_MINIMUM' => 'moet ten minste vijf alfanumerieke karakters bevatten',
'YOU_MUST_ACCEPT_COOKIES_TO_GET_CONNECTED' => 'U dient cookies te aanvaarden om zich aan te melden',
'IF_YOU_ARE_REGISTERED_LOGGIN_HERE' => 'Als u reeds geregistreerd bent, kunt u zich hier aanmelden',
'YOUR_WIKINAME' => 'Uw WikiNaam',
'PASSWORD_5_CHARS_MINIMUM' => 'Wachtwoord (minimaal vijf karakters)',
'REMEMBER_ME' => 'Gegevens onthouden',
'IDENTIFICATION' => 'Identificatie',
'FILL_THE_NEXT_FIELDS_IF_YOU_LOGGIN_FOR_THE_FIRST_TIME_AND_REGISTER' => 'De volgende velden dient u in te vullen als u zich voor het eerst aanmeldt (zo maakt u een account aan)',
'PASSWORD_CONFIRMATION' => 'Wachtwoordbevestiging',
'NEW_ACCOUNT' => 'Nieuwe account',


// actions/wantedpages.php 
'NO_PAGE_TO_CREATE' => 'Geen enkele pagina aan te maken',

// setup/header.php
'OK' => 'OK',
'FAIL' => 'MISLUKT',
'END_OF_INSTALLATION_BECAUSE_OF_ERRORS' => 'Installatie beëindigd omwille van fouten in de configuratie',

// setup/default.php
'INSTALLATION_OF_YESWIKI' => 'Installatie van YesWiki',
'YOUR_SYSTEM' => 'Uw systeem',
'EXISTENT_SYSTEM_RECOGNISED_AS_VERSION' => 'Bestaand systeem erkend als versie',
'YOU_ARE_UPDATING_YESWIKI_TO_VERSION' => 'U staat op het punt YesWiki te updaten voor versie',
'CHECK_YOUR_CONFIG_INFORMATION_BELOW' => 'Bekijk hieronder uw configuratiegegevens',
'FILL_THE_FORM_BELOW' => 'Vul het volgende formulier in',
'DEFAULT_LANGUAGE' => 'Standaardtaal',
'DEFAULT_LANGUAGE_INFOS' => 'Standaardtaal gebruikt voor de interface van YesWiki. Deze taal kan voor elk van de aangemaakte pagina’s worden gewijzigd',
'DATABASE_CONFIGURATION' => 'Configuratie van de database',
'MORE_INFOS' => 'Meer info',
'MYSQL_SERVER' => 'Machine MySQL',
'MYSQL_SERVER_INFOS' => 'Het IP-adres of de netwerknaam van het toestel waarop uw MySQL-server zich bevindt',
'MYSQL_DATABASE' => 'MySQL-database',
'MYSQL_DATABASE_INFOS' => 'Deze database moet reeds bestaan alvorens u kunt doorgaan',
'MYSQL_USERNAME' => 'Naam van de MySQL-gebruiker',
'MYSQL_USERNAME_INFOS' => 'U dient zich aan te melden bij uw database',
'TABLE_PREFIX' => 'Voorvoegsel van de tabellen',
'TABLE_PREFIX_INFOS' => 'Maakt het mogelijk om meerdere YesWiki’s te gebruiken op een en dezelfde database: elke nieuw geïnstalleerde YesWiki zal een ander tabelvoorvoegsel moeten hebben',
'MYSQL_PASSWORD' => 'MySQL-wachtwoord',
'YESWIKI_WEBSITE_CONFIGURATION' => 'Configuratie van uw YesWiki-site',
'YOUR_WEBSITE_NAME' => 'Naam van uw site',
'YOUR_WEBSITE_NAME_INFOS' => 'Dit kan een WikiNaam of andere titel zijn en wordt weergegeven op de tabbladen en vensters',
'HOMEPAGE' => 'Startpagina',
'HOMEPAGE_INFOS' => 'De startpagina van uw YesWiki-account. Deze moet een WikiNaam hebben',
'KEYWORDS' => 'Sleutelwoorden',
'KEYWORDS_INFOS' => 'Sleutelwoorden die in de HTML-codes zullen worden geïntegreerd (metagegevens)',
'DESCRIPTION' => 'Omschrijving',
'DESCRIPTION_INFOS' => 'De omschrijving van uw site, die in de HML-codes wordt geïntegreerd (metagegevens)',
'CREATION_OF_ADMIN_ACCOUNT' => 'Een beheerdersaccount aanmaken',
'ADMIN_ACCOUNT_CAN' => 'De beheerdersaccount maakt het mogelijk om',
'MODIFY_AND_DELETE_ANY_PAGE' => 'Gelijk welke pagina te wijzigen of te wissen',
'MODIFY_ACCESS_RIGHTS_ON_ANY_PAGE' => 'De toegangsrechten van gelijk welke pagina te wijzigen',
'GENERATE_ACCESS_RIGHTS_ON_ANY_ACTION_OR_HANDLER' => 'Toegangsrechten te genereren voor om het even welke actie of handler',
'GENERATE_GROUPS' => 'Groepen te beheren, gebruikers toe te voegen aan/te schrappen uit de beheerdersgroep (met dezelfde rechten)',
'ALL_ADMIN_TASKS_ARE_DESCRIBED_IN_THE_PAGE' => 'Alle beheertaken worden beschreven op de pagina ‘AdministratieVanYesWiki’, die men vanaf de startpagina kan raadplegen',
'USE_AN_EXISTING_ACCOUNT' => 'Een bestaande account gebruiken',
'NO' => 'Neen',
'OR_CREATE_NEW_ACCOUNT' => 'Of een nieuwe account aanmaken',
'ADMIN' => 'Beheerder',
'MUST_BE_WIKINAME' => 'moet een WikiNaam zijn',
'PASSWORD' => 'Wachtwoord',
'EMAIL_ADDRESS' => 'E-mailadres',
'MORE_OPTIONS' => 'Bijkomende opties',
'ADVANCED_CONFIGURATION' => '+ geavanceerde configuratie',
'URL_REDIRECTION' => 'URL-omleiding',
'NEW_INSTALL_VALUES_CHANGE_ONLY_IF_YOU_KNOW_WHAT_YOU_ARE_DOING' => 'Dit is een nieuwe installatie. Het installatieprogramma zal proberen om de juiste waarden te vinden. Wijzig deze waarden enkel als u weet wat u doet',
'PAGENAME_WILL_BE_ADDED_AFTER_CHANGE_JUST_FOR_REDIRECTION' => 'De namen van de pagina’s zullen rechtstreeks worden toegevoegd aan de basis-URL van uw YesWiki-site. Wis het gedeelte "?wiki=" enkel als u de omleiding gebruikt (zie verder)',
'BASE_URL' => 'Basis-URL',
'REDIRECTION_SHOULD_BE_ACTIVE_ONLY_IF_USED_IN_YESWIKI' => 'De modus "automatische omleiding" moet enkel geselecteerd worden als u YesWiki gebruikt met de URL-omleiding (als u niet weet wat URL-omleiding is, activeert u deze optie niet) ',
'ACTIVATE_REDIRECTION_MODE' => 'Activering van de modus "automatische omleiding"',
'OTHER_OPTIONS' => 'Andere opties',
'OBLIGE_TO_PREVIEW_BEFORE_SAVING_PAGE' => 'Voorbeeldweergave verplichten alvorens een pagina op te slaan',
'AUTHORIZE_HTML_INSERTION' => 'Invoeging van HTML toestaan',
'CONTINUE' => 'Verder',

// setup/install.php
'PROBLEM_WHILE_INSTALLING' => 'probleem tijdens de installatieprocedure',
'VERIFICATION_OF_DATAS_AND_DATABASE_INSTALLATION' => 'Test van de configuratie en installatie van de database',
'VERIFY_MYSQL_PASSWORD' => 'Controle van het MySQL-wachtwoord',
'INCORRECT_MYSQL_PASSWORD' => 'Het MySQL-wachtwoord is niet juist',
'TEST_MYSQL_CONNECTION' => 'Test MySQL-verbinding',
'SEARCH_FOR_DATABASE' => 'Opzoeking database',
'GO_BACK' => 'Terug',
'NO_DATABASE_FOUND_TRY_TO_CREATE' => 'De database die u hebt gekozen, bestaat niet. We zullen trachten ze aan te maken',
'TRYING_TO_CREATE_DATABASE' => 'Poging tot aanmaken van de database',
'DATABASE_COULD_NOT_BE_CREATED_YOU_MUST_CREATE_IT_MANUALLY' => 'De database kon niet worden aangemaakt. U dient deze database manueel aan te maken alvorens YesWiki te installeren',
'SEARCH' => 'Databaseopzoeking',
'DATABASE_DOESNT_EXIST_YOU_MUST_CREATE_IT' => 'De database die u hebt gekozen, bestaat niet. U dient ze aan te maken alvorens YesWiki te installeren',
'CHECKING_THE_ADMIN_PASSWORD' => 'Controle van het beheerderswachtwoord',
'CHECKING_THE_ADMIN_PASSWORD_CONFIRMATION' => 'Controle van de identiteit van de beheerderswachtwoorden',
'ADMIN_PASSWORD_ARE_DIFFERENT' => 'De beheerderswachtwoorden zijn verschillend',
'DATABASE_INSTALLATION' => 'Installatie van de database',
'CREATION_OF_TABLE' => 'Aanmaak van de tabel',
'ALREADY_CREATED' => 'Reeds aangemaakt',
'ADMIN_ACCOUNT_CREATION' => 'Aanmaak van de beheerdersaccount',
'INSERTION_OF_PAGE' => 'Insluiting van de pagina',
'ALREADY_EXISTING' => 'Bestaat reeds',
'UPDATING_FROM_WIKINI_0_1' => 'Wordt geüpdatet vanaf WikiNi 0.1',
'TINY_MODIFICATION_OF_PAGES_TABLE' => 'Heel lichte wijziging van de paginatabel',
'ALREADY_DONE' => 'Al klaar? Hmm!',
'INSERTION_OF_USER_IN_ADMIN_GROUP' => 'Opname van de gespecificeerde gebruiker in de beheerdersgroep',
'NEXT_STEP_WRITE_CONFIGURATION_FILE' => 'Bij de volgende fase zal het installatieprogramma het configuratiebestand proberen te schrijven ',
'VERIFY_YOU_HAVE_RIGHTS_TO_WRITE_FILE' => 'Zorg ervoor dat de webserver het recht heeft om in dit bestand te schrijven. Anders dient u het manueel te wijzigen',

// setup/writeconfig.php
'WRITING_CONFIGURATION_FILE' => 'Schrijven van het configuratiebestand',
'CREATED' => 'aangemaakt',
'DONT_CHANGE_YESWIKI_VERSION_MANUALLY' => 'Wijzig de yeswiki-versie niet manueel',
'WRITING_CONFIGURATION_FILE_WIP' => 'Het configuratiebestand wordt aangemaakt',
'FINISHED_CONGRATULATIONS' => 'Proficiat, het is gelukt',
'GO_TO_YOUR_NEW_YESWIKI_WEBSITE' => 'Ga nu naar uw nieuwe YesWiki-site',
'IT_IS_RECOMMANDED_TO_REMOVE_WRITE_ACCESS_TO_CONFIG_FILE' => 'Het verdient aanbeveling om de schrijfmachtiging voor het bestand in te trekken',
'THIS_COULD_BE_UNSECURE' => 'dit kan een beveiligingsprobleem geven',
'WARNING' => 'WAARSCHUWING',
'CONFIGURATION_FILE' => 'Het configuratiebestand',
'CONFIGURATION_FILE_NOT_CREATED' => 'kon niet worden aangemaakt',
'TRY_CHANGE_ACCESS_RIGHTS_OR_FTP_TRANSFERT' => 'Controleer of uw server schrijfrechten heeft voor dit bestand. Als u dit om een of andere reden niet kunt, dient u de volgende gegevens te kopiëren in een bestand en dat bestand met een software voor bestandsoverdracht (ftp) op de server over te zetten naar een bestand',
'DIRECTLY_IN_THE_YESWIKI_FOLDER' => 'rechtstreeks in de YesWiki-map. Zodra u dat hebt gedaan, zou uw YesWiki-site correct moeten werken',
'TRY_AGAIN' => 'Opnieuw proberen',

);