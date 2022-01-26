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
*@author        Jérémy Dufraisse <jeremy.dufraisse-info@orange.fr>
*@copyright     2014 Outils-Réseaux
*/

return [

    // Commons
    // 'CAUTION' => 'Attention',
    'BY' => 'door',
    // 'CLEAN' => 'Nettoyer',
    // 'DELETE' => 'Supprimer',
    // 'DEL' => 'Suppr.', // fives chars max.
    // 'EMAIL' => 'Email',
    // 'INVERT' => 'Inverser',
    // 'MODIFY' => 'Modifier',
    // 'NAME' => 'Nom',
    // 'SUBSCRIPTION' => 'Inscription',
    'UNKNOWN' => 'Onbekend',
    'WARNING' => 'WAARSCHUWING',

    // wakka.php
    'INVALID_ACTION' => 'Actie ongeldig',
    'ERROR_NO_ACCESS' => 'Fout: u hebt geen toegang tot de actie',
    'NOT_FOUND' => 'Niet gevonden',
    'NO_REQUEST_FOUND' => '$_REQUEST[] niet gevonden. Wakka vereist PHP 4.1.0 of hoger!',
    'SITE_BEING_UPDATED' => 'Deze site wordt momenteel bijgewerkt. Probeer het later nog een keer.',
    'DB_CONNECT_FAIL' => 'Om redenen buiten onze wil om is de inhoud van deze YesWiki tijdelijk niet bereikbaar. Probeer het later nog een keer. Dank u voor uw begrip.',
    'LOG_DB_CONNECT_FAIL' => 'YesWiki: de BDD-verbinding is verbroken', // sans accents car commande systeme
    'INCORRECT_PAGENAME' => 'De naam van de pagina is niet correct.',
    // 'PERFORMABLE_ERROR' => 'Une erreur inattendue s\'est produite. Veuillez contacter l\'administrateur du site et lui communiquer l\'erreur suivante :',
    'HOMEPAGE_WIKINAME' => 'Hoofdpagina',
    'MY_YESWIKI_SITE' => 'Mijn YesWiki-site',
    // 'FILE_WRITE_PROTECTED' => 'le fichier de configuration est protégé en écriture',

    // ACLs
    // 'DENY_READ' => 'Vous n\'êtes pas autorisé à lire cette page',
    // 'DENY_WRITE' => 'Vous n\'êtes pas autorisé à écrire sur cette page',
    // 'DENY_COMMENT' => 'Vous n\'êtes pas autorisé à commenter cette page',
    // 'DENY_DELETE' => 'Vous n\'êtes pas autorisé à supprimer cette page',

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
    // 'YW_CHANGE_OWNER' => 'Changer le propri&eacute;taire',
    // 'YW_CHANGE_NOTHING' => 'Ne rien modifier',
    // 'YW_CANNOT_CHANGE_ACLS' => 'Vous ne pouvez pas g&eacute;rer les permissions de cette page',

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
    // 'EDIT_EXISTING_GROUP' => 'Éditer un groupe existant',
    // 'DELETE_EXISTING_GROUP' => 'Supprimer un groupe existant',
    // 'GROUP_NAME' => 'Nom du groupe',
    // 'SEE_EDIT' => 'Voir / Éditer',
    'ONLY_ALPHANUM_FOR_GROUP_NAME' => 'De namen van groepen mogen enkel alfanumerieke karakters bevatten',
    // 'LIST_GROUP_MEMBERS' => 'Liste des membres du groupe {groupName}',
    // 'ONE_NAME_BY_LINE' => 'un nom d\'utilisateur par ligne',

    // actions/edithandlersacls.class.php
    'HANDLER_RIGHTS' => 'Rechten van de handler',
    'ERROR_WHILE_SAVING_HANDLER_ACL' => ' Er heeft zich een fout voorgedaan tijdens het opslaan van de ACL voor de handler',
    'NEW_ACL_FOR_HANDLER' => 'Nieuwe ACL voor de handler',
    'NEW_ACL_SUCCESSFULLY_SAVED_FOR_HANDLER' => 'Nieuwe ACL voor de handler met succes geregistreerd',
    'EDIT_RIGHTS_FOR_HANDLER' => 'de rechten van de handler bewerken',

    // actions/erasespamedcomments.class.php
    'ERASED_COMMENTS' => 'Commentaire(s) effacé(s)',
    'FORM_RETURN' => 'Retour au formulaire',
    'NO_RECENT_COMMENTS' => 'Pas de commentaires récents',
    'NO_SELECTED_COMMENTS_TO_ERASE' => 'Aucun commentaire n\'a été sélectionné pour étre effacé',

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

    // actions/mychanges.php
    'YOUR_MODIFIED_PAGES_ORDERED_BY_MODIFICATION_DATE' => 'Lijst met pagina\'s die u hebt gewijzigd, volgens datum van wijziging',
    'YOUR_MODIFIED_PAGES_ORDERED_BY_NAME' => 'Lijst met pagina\'s die u hebt gewijzigd, in alfabetische volgorde',
    'YOU_DIDNT_MODIFY_ANY_PAGE' => 'U hebt geen pagina\'s gewijzigd',
    'YOU_ARENT_LOGGED_IN' => 'U bent niet aangemeld,',
    'IMPOSSIBLE_TO_SHOW_YOUR_MODIFIED_PAGES' => 'De lijst met pagina\'s die u hebt gewijzigd, kan niet worden weergegeven',
    'LIST_OF_PAGES_WHERE_YOU_ARE_THE_OWNER' => 'Lijst met pagina\'s waarvan u de eigenaar bent',
    'YOU_DONT_OWN_ANY_PAGE' => 'U bezit geen enkele pagina',

    // actions/nextextsearch.php
    // 'NEWTEXTSEARCH_HINT' => 'Un caractère inconnu peut être remplacé par « ? » plusieurs par « * »',
    // 'NO_SEARCH_RESULT' => 'Désolé mais il n\'y a aucun de résultat pour votre recherche',
    // 'SEARCH_RESULTS' => 'Résultats de la recherche',

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
    // 'LAST COMMENT' => 'dernier commentaire',
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

    // actions/testtriples.php
    // 'END_OF_EXEC' => 'Fin de l\'exécution',

    // actions/trail.php
    'ERROR_ACTION_TRAIL' => 'Actiefout {{trail ...}}',
    'INDICATE_THE_PARAMETER_TOC' => 'Geef de naam van de beknopte pagina in, "toc"',

    // actions/usersettings.php
    // 'USER_SETTINGS' => 'Paramètres utilisateur',
    // 'USER_SIGN_UP' => 'S\'inscrire',
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
    'PASSWORD_5_CHARS_MINIMUM' => 'Wachtwoord (minimaal vijf karakters)',
    'REMEMBER_ME' => 'Gegevens onthouden',
    'IDENTIFICATION' => 'Identificatie',
    'FILL_THE_NEXT_FIELDS_IF_YOU_LOGGIN_FOR_THE_FIRST_TIME_AND_REGISTER' => 'De volgende velden dient u in te vullen als u zich voor het eerst aanmeldt (zo maakt u een account aan)',
    'PASSWORD_CONFIRMATION' => 'Wachtwoordbevestiging',
    'NEW_ACCOUNT' => 'Nieuwe account',
    // 'LOGGED_USERS_ONLY_ACTION' => 'Il faut être connecté pour pouvoir exécuter cette action',


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
    // 'NAVIGATOR_LANGUAGE' => 'Langue du navigateur',
    'DEFAULT_LANGUAGE_INFOS' => 'Standaardtaal gebruikt voor de interface van YesWiki. Deze taal kan voor elk van de aangemaakte pagina’s worden gewijzigd',
    // 'GENERAL_CONFIGURATION' => 'Configuration générale',
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
    // 'YES' => 'Oui',
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
    // 'HTML_INSERTION_HELP_TEXT' => 'Augmente grandement les fonctionnalités du wiki, en permettant l\'ajout de vidéos et iframe par exemple, mais est moins sécurisé',
    // 'INDEX_HELP_TEXT' => 'Indique dans les meta-données html et dans le fichier robots.txt si votre site doit etre indexé par les moteurs de recherche ou pas',
    'ACTIVATE_REDIRECTION_MODE' => 'Activering van de modus "automatische omleiding"',
    'OTHER_OPTIONS' => 'Andere opties',
    'OBLIGE_TO_PREVIEW_BEFORE_SAVING_PAGE' => 'Voorbeeldweergave verplichten alvorens een pagina op te slaan',
    'AUTHORIZE_HTML_INSERTION' => 'Invoeging van HTML toestaan',
    // 'AUTHORIZE_INDEX_BY_ROBOTS' => 'Autoriser l\'indexation par les moteurs de recherche',
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
    'DATABASE_DOESNT_EXIST_YOU_MUST_CREATE_IT' => 'De database die u hebt gekozen, bestaat niet. U dient ze aan te maken alvorens YesWiki te installeren',
    'CHECKING_THE_ADMIN_PASSWORD' => 'Controle van het beheerderswachtwoord',
    'CHECKING_THE_ADMIN_PASSWORD_CONFIRMATION' => 'Controle van de identiteit van de beheerderswachtwoorden',
    // 'CHECKING_ROOT_PAGE_NAME' => 'V&eacute;rification du nom de la page d\'accueil',
    // 'INCORRECT_ROOT_PAGE_NAME' => 'Le nom de la page d\'accueil doit uniquement contenir des lettres non accentuées, des chiffres, \'_\', \'-\' ou \'.\'',
    'ADMIN_PASSWORD_ARE_DIFFERENT' => 'De beheerderswachtwoorden zijn verschillend',
    'DATABASE_INSTALLATION' => 'Installatie van de database',
    'CREATION_OF_TABLE' => 'Aanmaak van de tabel',
    // 'SQL_FILE_NOT_FOUND' => 'Fichier SQL non trouv&eacute;',
    // 'NOT_POSSIBLE_TO_CREATE_SQL_TABLES' => 'Impossible de créer les tables SQL.',
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
    // 'CHECK_EXISTING_TABLE_PREFIX' => 'Vérification de l\'existence du préfixe de table',
    // 'TABLE_PREFIX_ALREADY_USED' => 'Le préfixe de table est déjà utilisé. Veuillez en choisir un nouveau.',

    // setup/writeconfig.php
    'WRITING_CONFIGURATION_FILE' => 'Schrijven van het configuratiebestand',
    'CREATED' => 'aangemaakt',
    'DONT_CHANGE_YESWIKI_VERSION_MANUALLY' => 'Wijzig de yeswiki-versie niet manueel',
    'WRITING_CONFIGURATION_FILE_WIP' => 'Het configuratiebestand wordt aangemaakt',
    'FINISHED_CONGRATULATIONS' => 'Proficiat, het is gelukt',
    'GO_TO_YOUR_NEW_YESWIKI_WEBSITE' => 'Ga nu naar uw nieuwe YesWiki-site',
    'IT_IS_RECOMMANDED_TO_REMOVE_WRITE_ACCESS_TO_CONFIG_FILE' => 'Het verdient aanbeveling om de schrijfmachtiging voor het bestand in te trekken',
    'THIS_COULD_BE_UNSECURE' => 'dit kan een beveiligingsprobleem geven',
    'CONFIGURATION_FILE' => 'Het configuratiebestand',
    'CONFIGURATION_FILE_NOT_CREATED' => 'kon niet worden aangemaakt',
    'TRY_CHANGE_ACCESS_RIGHTS_OR_FTP_TRANSFERT' => 'Controleer of uw server schrijfrechten heeft voor dit bestand. Als u dit om een of andere reden niet kunt, dient u de volgende gegevens te kopiëren in een bestand en dat bestand met een software voor bestandsoverdracht (ftp) op de server over te zetten naar een bestand',
    'DIRECTLY_IN_THE_YESWIKI_FOLDER' => 'rechtstreeks in de YesWiki-map. Zodra u dat hebt gedaan, zou uw YesWiki-site correct moeten werken',
    'TRY_AGAIN' => 'Opnieuw proberen',

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

    // include/services/ThemeManager.php
    // 'THEME_MANAGER_THEME_FOLDER' => 'Le dossier du thème ',
    // 'THEME_MANAGER_SQUELETTE_FILE' => 'Le fichier du squelette ',
    // 'THEME_MANAGER_NOT_FOUND' => ' n\'a pas été trouvé.',
    // 'THEME_MANAGER_ERROR_GETTING_FILE' => 'Une erreur s\'est produite en chargeant ce fichier : ',
    // 'THEME_MANAGER_CLICK_TO_INSTALL' => 'Cliquer pour installer le thème ',
    // 'THEME_MANAGER_AND_REPAIR' => ' et réparer le site',
    // 'THEME_MANAGER_LOGIN_AS_ADMIN' => 'Veuillez vous connecter en tant qu\'administrateur pour faire la mise à jour.',

    // actions/EditConfigAction.php
    // 'EDIT_CONFIG_TITLE' => 'Modification du fichier de configuration',
    // 'EDIT_CONFIG_CURRENT_VALUE' => 'Valeur actuelle ',
    // 'EDIT_CONFIG_SAVE' => 'Configuration sauvegardée',
    // 'EDIT_CONFIG_HINT_WAKKA_NAME' => 'Titre de votre wiki',
    // 'EDIT_CONFIG_HINT_ROOT_PAGE' => 'Nom de la page d\'accueil',
    // 'EDIT_CONFIG_HINT_DEFAULT_WRITE_ACL' => 'Droits d\'écriture par défaut des pages (* pour tous, + pour personnes identifiées, @admins pour groupe admin)',
    // 'EDIT_CONFIG_HINT_DEFAULT_READ_ACL' => 'Droits de lecture par défaut des pages (* pour tous, + pour personnes identifiées, @admins pour groupe admin)',
    // 'EDIT_CONFIG_HINT_DEBUG' => 'Activer le mode de debug (yes ou no)',
    // 'EDIT_CONFIG_HINT_DEFAULT_LANGUAGE' => 'Langue par défaut (fr ou en ou ... auto = langue du navigateur)',
    // 'EDIT_CONFIG_HINT_CONTACT_FROM' => 'Remplacer le mail utilisé comme expéditeur des messages',
    // 'EDIT_CONFIG_HINT_MAIL_CUSTOM_MESSAGE' => 'Message personnalisé des mails envoyés depuis l\'action contact',
    // 'EDIT_CONFIG_HINT_PASSWORD_FOR_EDITING' => 'Mot de passe demandé pour modifier les pages (voir doc gestion des spams)',
    // 'EDIT_CONFIG_HINT_PASSWORD_FOR_EDITING_MESSAGE' => 'Message informatif pour demander le mot de passe (voir doc gestion des spams)',
    // 'EDIT_CONFIG_HINT_ALLOW_DOUBLECLIC' => 'Autoriser le doubleclic pour éditer les menus et pages spéciales (true ou false)',
    // 'EDIT_CONFIG_HINT_TIMEZONE' => 'Fuseau horaire du site (ex. UCT, Europe/Paris, Europe/London, GMT = utiliser celui du serveur,)',
    // 'EDIT_CONFIG_HINT_ALLOWED_METHODS_IN_IFRAME' => 'Méthodes autorisées à être affichées dans les iframes (iframe,editiframe,bazariframe,render,all = autoriser tout)',
    // 'EDIT_CONFIG_GROUP_CORE' => 'Paramètres Principaux',
    // 'EDIT_CONFIG_GROUP_ACCESS' => 'Droit d\'accès',
    // 'EDIT_CONFIG_GROUP_EMAIL' => 'Emails',

    // actions/usertable.php
    // 'GROUP_S' => 'Groupe(s)',

    // handlers/deletepage
    // 'DELETEPAGE_CANCEL' => 'Annuler',
    // 'DELETEPAGE_CONFIRM' => 'Voulez-vous vraiment supprimer d&eacute;finitivement la page {tag}&nbsp;?',
    // 'DELETEPAGE_CONFIRM_WHEN_BACKLINKS' => 'Voulez-vous vraiment supprimer d&eacute;finitivement la page {tag} malgr&eacute; la pr&eacute;sence de liens&nbsp;?',
    // 'DELETEPAGE_DELETE' => 'Supprimer',
    // 'DELETEPAGE_MESSAGE' => 'La page {tag} a d&eacute;finitivement &eacute;t&eacute; supprim&eacute;e',
    // 'DELETEPAGE_NOT_ORPHEANED' => 'Cette page n\'est pas orpheline.',
    // 'DELETEPAGE_NOT_OWNER' => 'Vous n\'&ecirc;tes pas le propri&eacute;taire de cette page.',
    // 'DELETEPAGE_PAGES_WITH_LINKS_TO' => 'Pages ayant un lien vers {tag} :',

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

];
