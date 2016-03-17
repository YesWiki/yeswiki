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
* Fichier de traduction en anglais de l'extension Contact
*
*@package       contact
*@author        Florian Schmitt <florian@outils-reseaux.org>
*@copyright     2012 Outils-Réseaux
*/

$GLOBALS['translations'] = array_merge(
    $GLOBALS['translations'],
    array(
        // actions/abonnement.php
        'CONTACT_ACTION_ABONNEMENT' => 'Action {{abonnement ...}}',
        'CONTACT_MAIL_REQUIRED' => 'the email, required, is missing',

        // actions/contact.php
        'CONTACT_ACTION_CONTACT' => 'Action {{contact ...}}',

        // actions/desabonnement.php
        'CONTACT_ACTION_DESABONNEMENT' => 'Action {{desabonnement ...}}',

        // actions/listsubscription.php
        'CONTACT_ACTION_LISTSUBSCRIPTION' => 'Action {{listsubscription ...}}',

        // actions/mailinglist.php
        'CONTACT_ACTION_MAILINGLIST' => 'Action {{mailinglist ...}}',
        'CONTACT_PARAMETER_LIST_REQUIRED' => 'parameter list required (list\'s email)',
        'CONTACT_MAILS_TO_ADD_OR_REMOVE' => 'emails to add or remove of the list',
        'CONTACT_SENT_TO_THE_LIST' => 'send to the list',
        'CONTACT_THE_EMAIL' => 'the email',
        'CONTACT_SUBMIT_OTHER_EMAILS' => 'submit other emails',
        'CONTACT_OK' => 'OK',
        'CONTACT_BTN_SUBSCRIBE' => 'Suscribe',
        'CONTACT_BTN_UNSUBSCRIBE' => 'Unsubscribe',
        'CONTACT_FOR_ALL_THOSE_EMAILS' => 'For all those emails',
        'CONTACT_TRY_WITH_OTHER_EMAILS' => 'Try with other emails in the text',
        'CONTACT_NO_EMAILS_FOUND_IN_THIS_TEXT' => 'No email found in this text',
        'CONTACT_ENTER_TEXT_WITH_EMAILS_INSIDE' => 'Enter text with emails inside to extract them, no matter the splitters (comma, semi-colon, colon, tab, wordwrap)',
        'CONTACT_YOUR_EMAIL_LIST' => 'Your emails list',
        'CONTACT_EXTRACT_EMAILS_FROM_TEXT' => 'Extract emails from the text',
        'CONTACT_MUST_BE_ADMIN_TO_USE_THIS_ACTION' => 'You have to be in the admins group to use this action',


        'CONTACT_YOUR_NAME' => 'Your name',
        'CONTACT_YOUR_MAIL' => 'Your email',
        'CONTACT_SUBJECT' => 'message subject',
        'CONTACT_YOUR_MESSAGE' => 'Your message',
        'CONTACT_SEND_MESSAGE' => 'Send message',
        'CONTACT_LISTSUBSCRIBE_REQUIRED' => ' The "list" parameter, which have the list\'s email, like namelist@domain.ext, is required',
        'CONTACT_USER_NOT_LOGGED_IN' => 'You have to be logged to make any action',
        'CONTACT_USER_NO_EMAIL' => 'You have to be logged to make any action',

        'CONTACT_ENTER_NAME' => 'Enter a name',
        'CONTACT_ENTER_SENDER_MAIL' => 'Your email is needed as sender',
        'CONTACT_SENDER_MAIL_INVALID' => 'Valid email is needed as sender',
        'CONTACT_ENTER_RECEIVER_MAIL' => 'Recipient email is needed',
        'CONTACT_RECEIVER_MAIL_INVALID' => 'Recipient valid email is needed',
        'CONTACT_ENTER_MESSAGE' => 'Please enter a message, containing at least 10 characters',

        'CONTACT_MESSAGE_SUCCESSFULLY_SENT' => 'Your message have been sent. Thank you !',
        'CONTACT_MESSAGE_NOT_SENT' => 'Your message could not be sent... Server configuration problem ?',
        'CONTACT_SUBSCRIBE_ORDER_SENT' => 'Subscribe order sent. Thank you!',
        'CONTACT_UNSUBSCRIBE_ORDER_SENT' => 'Unsubscribe order sent. Thank you!',

        'CONTACT_HANDLER_MAIL_FOR_ADMINS' => 'Handler mail is only for admins group',
        'CONTACT_LOGIN_IF_ADMIN' => 'Please login if you are admin',

        'CONTACT_SUBSCRIBE' => 'Subscribe',
        'CONTACT_UNSUBSCRIBE' => 'Unsubscribe',

        'CONTACT_FROM' => 'from'
    )
);
