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
* Fitxer de traducció al català de l'extensió Contact
*
*@package 		contact
*@author        Jordi Picart <jordi.picart@aposta.coop>
*@copyright     2014 Outils-Réseaux
*/

$GLOBALS['translations'] = array_merge($GLOBALS['translations'], array(

// actions/abonnement.php
'CONTACT_ACTION_ABONNEMENT' => 'Acció {{abonnement ...}}',
'CONTACT_MAIL_REQUIRED' => 'L\'adreça email de contacte és obligatòria.',

// actions/contact.php
'CONTACT_ACTION_CONTACT' => 'Acció {{contact ...}}',

// actions/desabonnement.php
'CONTACT_ACTION_DESABONNEMENT' => 'Acció {{desabonnement ...}}',

// actions/listsubscription.php
'CONTACT_ACTION_LISTSUBSCRIPTION' => 'Acció {{listsubscription ...}}',

// actions/mailinglist.php
'CONTACT_ACTION_MAILINGLIST' => 'Acció {{mailinglist ...}}',
'CONTACT_PARAMETER_LIST_REQUIRED' => 'El paràmetre "list" és obligatori (és l\'adreça email de la llista de difusió)',
'CONTACT_MAILS_TO_ADD_OR_REMOVE' => 'Correus que cal afegir o suprimir de la llista',
'CONTACT_SENT_TO_THE_LIST' => 'Enviat a la llista',
'CONTACT_THE_EMAIL' => 'l\'email',
'CONTACT_SUBMIT_OTHER_EMAILS' => 'Afegiu altres emails',
'CONTACT_OK' => 'D\'acord',
'CONTACT_BTN_SUBSCRIBE' => 'Registrar',
'CONTACT_BTN_UNSUBSCRIBE' => 'Donar de baixa',
'CONTACT_FOR_ALL_THOSE_EMAILS' => 'Per totes aquestes adreces de correu',
'CONTACT_TRY_WITH_OTHER_EMAILS' => 'Intentar amb altres adreces al text',
'CONTACT_NO_EMAILS_FOUND_IN_THIS_TEXT' => 'No s\'han trobat adreces al text',
'CONTACT_ENTER_TEXT_WITH_EMAILS_INSIDE' => 'Escriviu un text que contingui adreces per extreure-les, els separadors són indiferents (comes, punt-i-comes, dos punts, espais, tabulacions, salts de línia)',
'CONTACT_YOUR_EMAIL_LIST' => 'La vostra llista d\'adreces',
'CONTACT_EXTRACT_EMAILS_FROM_TEXT' => 'Extreu els mails d\'aquest text',
'CONTACT_MUST_BE_ADMIN_TO_USE_THIS_ACTION' => 'necessiteu permisos d\'administrador per completar aquesta acció',


'CONTACT_YOUR_NAME' => 'El vostre nom',
'CONTACT_YOUR_MAIL' => 'La vostra adreça de correu',
'CONTACT_SUBJECT' => 'Tema del missatge',
'CONTACT_YOUR_MESSAGE' => 'El vostre missatge',
'CONTACT_SEND_MESSAGE' => 'Envia',
'CONTACT_LISTSUBSCRIBE_REQUIRED' => 'el paràmetre "list", que conté l\'adreça electrònica de la llista, del tipus nomllista@domini.ext, és obligatori',
'CONTACT_USER_NOT_LOGGED_IN' => 'Cal estar identificat.',
'CONTACT_USER_NO_EMAIL' => 'cal estar identificat.',

'CONTACT_ENTER_NAME' => 'Escriviu un nom.',
'CONTACT_ENTER_SENDER_MAIL' => 'Escriviu una adreça electrònica del remitent.',
'CONTACT_SENDER_MAIL_INVALID' => 'L\'adreça de correu del remitent no és vàlida.',
'CONTACT_ENTER_RECEIVER_MAIL' => 'Escriviu una adreça electrònica de destinació.',
'CONTACT_RECEIVER_MAIL_INVALID' => 'L\'adreça de correu del destinatari no és vàlida.',
'CONTACT_ENTER_MESSAGE' => 'Escriviu el cos del missatge; calen 10 caràcters com a mínim.',

'CONTACT_MESSAGE_SUCCESSFULLY_SENT' => 'El vostre missatge s\'ha enviat amb èxit',
'CONTACT_MESSAGE_NOT_SENT' => 'El missatge no s\'ha pogut enviar... potser és un problema de la configuració del servidor?',
'CONTACT_SUBSCRIBE_ORDER_SENT' => 'La petició de registre ha estat enviada. Moltes gràcies!',
'CONTACT_UNSUBSCRIBE_ORDER_SENT' => 'La petició de baixa ha estat enviada. Moltes gràcies!',

'CONTACT_HANDLER_MAIL_FOR_ADMINS' => 'El gestor de correu està reservat als administradors.',
'CONTACT_LOGIN_IF_ADMIN' => 'Si sou membre d\'aquest grup, identifiqueu-vos sisplau.',

'CONTACT_SUBSCRIBE' => 'Registrar-se',
'CONTACT_UNSUBSCRIBE' => 'Donar-se de baixa',

'CONTACT_FROM' => 'De'

));