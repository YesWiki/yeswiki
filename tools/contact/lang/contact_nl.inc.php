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
* Fichier de traduction en francais de l'extension Contact
*
*@package 		contact
*@author        Florian Schmitt <florian@outils-reseaux.org>
*@copyright     2012 Outils-Réseaux
*/

$GLOBALS['translations'] = array_merge($GLOBALS['translations'], array(

// actions/abonnement.php
'CONTACT_ACTION_ABONNEMENT' => 'Actie {{inschrijven...}}',
'CONTACT_MAIL_REQUIRED' => 'de verplichte parameter e-mail ontbreekt.',

// actions/contact.php
'CONTACT_ACTION_CONTACT' => 'Actie {{contact ...}}',

// actions/desabonnement.php
'CONTACT_ACTION_DESABONNEMENT' => 'Actie {{uitschrijven...}}',

// actions/listsubscription.php
'CONTACT_ACTION_LISTSUBSCRIPTION' => 'Actie {{listsubscription ...}}',

// actions/mailinglist.php
'CONTACT_ACTION_MAILINGLIST' => 'Actie {{mailinglist ...}}',
'CONTACT_PARAMETER_LIST_REQUIRED' => 'parameter "list" is verplicht (dit is het e-mailadres van de mailinglist)',
'CONTACT_MAILS_TO_ADD_OR_REMOVE' => 'Mails toevoegen aan of wissen van de lijst',
'CONTACT_SENT_TO_THE_LIST' => 'Verzenden naar de lijst',
'CONTACT_THE_EMAIL' => 'E-mail',
'CONTACT_SUBMIT_OTHER_EMAILS' => 'Andere mails invoeren',
'CONTACT_OK' => 'OK',
'CONTACT_BTN_SUBSCRIBE' => 'Inschrijven',
'CONTACT_BTN_UNSUBSCRIBE' => 'Uitschrijven',
'CONTACT_FOR_ALL_THOSE_EMAILS' => 'Voor al deze e-mailadressen',
'CONTACT_TRY_WITH_OTHER_EMAILS' => 'Proberen met andere e-mails in de tekst',
'CONTACT_NO_EMAILS_FOUND_IN_THIS_TEXT' => 'Geen e-mailadressen gevonden in de aangeleverde tekst',
'CONTACT_ENTER_TEXT_WITH_EMAILS_INSIDE' => 'Voer een tekst met e-mailadressen in om de e-mailadressen te extraheren. De scheidingstekens (komma, puntkomma, dubbele punt, spatie, tab, enter) en de ingevoerde tekst doen weinig ter zake.',
'CONTACT_YOUR_EMAIL_LIST' => 'Uw lijst met e-mailadressen',
'CONTACT_EXTRACT_EMAILS_FROM_TEXT' => 'De e-mailadressen uit deze tekst extraheren',
'CONTACT_MUST_BE_ADMIN_TO_USE_THIS_ACTION' => 'U moet deel uitmaken van de beheerdersgroep om deze actie te gebruiken',


'CONTACT_YOUR_NAME' => 'Uw naam',
'CONTACT_YOUR_MAIL' => 'Uw e-mailadres',
'CONTACT_SUBJECT' => 'Onderwerp van het bericht',
'CONTACT_YOUR_MESSAGE' => 'Uw bericht',
'CONTACT_SEND_MESSAGE' => 'Het bericht verzenden',
'CONTACT_LISTSUBSCRIBE_REQUIRED' => 'de parameter "list", met het e-mailadres van de lijst (type lijstnaam@domein.ext), is verplicht',
'CONTACT_USER_NOT_LOGGED_IN' => 'u moet aangemeld zijn om toegang te hebben tot de mogelijke acties.',
'CONTACT_USER_NO_EMAIL' => 'u moet aangemeld zijn om toegang te hebben tot de mogelijke acties.',

'CONTACT_ENTER_NAME' => 'U dient een naam in te voeren.',
'CONTACT_ENTER_SENDER_MAIL' => 'U dient een e-mailadres voor de verzender in te voeren.',
'CONTACT_SENDER_MAIL_INVALID' => 'U dient een geldig e-mailadres voor de verzender in te voeren.',
'CONTACT_ENTER_RECEIVER_MAIL' => 'U dient een e-mailadres voor de bestemmeling in te voeren.',
'CONTACT_RECEIVER_MAIL_INVALID' => 'U dient een geldig e-mailadres voor de bestemmeling in te voeren.',
'CONTACT_ENTER_MESSAGE' => 'Gelieve een bericht in te voeren. Dat moet minimaal 10 karakters lang zijn.',

'CONTACT_MESSAGE_SUCCESSFULLY_SENT' => 'Uw bericht werd goed verzonden. Bedankt!',
'CONTACT_MESSAGE_NOT_SENT' => 'Het bericht kon niet worden verzonden... Probleem met de serverconfiguratie?',
'CONTACT_SUBSCRIBE_ORDER_SENT' => 'We hebben uw vraag tot inschrijving goed ontvangen. Bedankt!',
'CONTACT_UNSUBSCRIBE_ORDER_SENT' => 'We hebben uw vraag tot uitschrijving goed ontvangen. Bedankt!',

'CONTACT_HANDLER_MAIL_FOR_ADMINS' => 'De mail handler is voorbehouden aan de beheerdersgroep.',
'CONTACT_LOGIN_IF_ADMIN' => 'Meld u aan als u deel uitmaakt van deze groep.',

'CONTACT_SUBSCRIBE' => 'Inschrijven',
'CONTACT_UNSUBSCRIBE' => 'Uitschrijven',

'CONTACT_FROM' => 'van',


));