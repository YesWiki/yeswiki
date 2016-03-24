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
* Fichier de traduction en espagnol de l'extension Contact
*
*@package 		login
*@author        Louise Didier <louise@quincaillere.org>
*@copyright     2016 Outils-Réseaux
*/

$GLOBALS['translations'] = array_merge($GLOBALS['translations'], array(

// actions/abonnement.php
'CONTACT_ACTION_ABONNEMENT' => 'Acción {{suscripción ...}}',
'CONTACT_MAIL_REQUIRED' => 'el parámetro email es obligatorio.',

// actions/contact.php
'CONTACT_ACTION_CONTACT' => 'Acción {{contacto ...}}',

// actions/desabonnement.php
'CONTACT_ACTION_DESABONNEMENT' => 'Acción {{desabono ...}}',

// actions/listsubscription.php
'CONTACT_ACTION_LISTSUBSCRIPTION' => 'Acción {{listsubscription ...}}',

// actions/mailinglist.php
'CONTACT_ACTION_MAILINGLIST' => 'Acción {{mailinglist ...}}',
'CONTACT_PARAMETER_LIST_REQUIRED' => 'parámetro "list" obligatorio (es la dirección email de la lista de difusión)',
'CONTACT_MAILS_TO_ADD_OR_REMOVE' => 'Direcciónes de correo electrónico para añadir o para suprimir de la lista',
'CONTACT_SENT_TO_THE_LIST' => 'Envio a la lista',
'CONTACT_THE_EMAIL' => 'la dirección de correo electrónico ',
'CONTACT_SUBMIT_OTHER_EMAILS' => 'Entrar otros e-mails',
'CONTACT_OK' => 'OK',
'CONTACT_BTN_SUBSCRIBE' => 'Suscribir',
'CONTACT_BTN_UNSUBSCRIBE' => 'Desabonarse',
'CONTACT_FOR_ALL_THOSE_EMAILS' => 'Para todas estas direcciónes de correo electrónico',
'CONTACT_TRY_WITH_OTHER_EMAILS' => 'Intentar con otras direcciónes de correo electrónico en el texto',
'CONTACT_NO_EMAILS_FOUND_IN_THIS_TEXT' => 'Ningun e-mail encontrado en el texto',
'CONTACT_ENTER_TEXT_WITH_EMAILS_INSIDE' => 'Entra un texto que contiene las direcciónes de correo electrónico, para extraerlas, no importa lo que les separa (comas, punto-comas, dos puntos, espacios, tabuladores, punto y aparte) o el texto entre',
'CONTACT_YOUR_EMAIL_LIST' => 'Tu lista de direcciónes de correo electrónico',
'CONTACT_EXTRACT_EMAILS_FROM_TEXT' => 'Extraer las direcciónes de correo electrónicode este texto',
'CONTACT_MUST_BE_ADMIN_TO_USE_THIS_ACTION' => 'Tienes que pertenecer al grupo admins para usar esta acción',


'CONTACT_YOUR_NAME' => 'Tu nombre',
'CONTACT_YOUR_MAIL' => 'Tu dirección de correo electrónico',
'CONTACT_SUBJECT' => 'Objeto del mensaje',
'CONTACT_YOUR_MESSAGE' => 'Tu mensaje',
'CONTACT_SEND_MESSAGE' => 'Enviar el mensaje',
'CONTACT_LISTSUBSCRIBE_REQUIRED' => 'el parámetro "list", con la dirección de la lista, tipo nombrelista@dominio.ext, es obligatorio',
'CONTACT_USER_NOT_LOGGED_IN' => 'te tienes que identificar para acceder a las acciones posibles.',
'CONTACT_USER_NO_EMAIL' => 'te tienes que identificar para acceder a las acciones posibles.',

'CONTACT_ENTER_NAME' => 'Tienes que entrar un nombre.',
'CONTACT_ENTER_SENDER_MAIL' => 'Tienes que entrar una dirección de correo electrónico para el expedidor.',
'CONTACT_SENDER_MAIL_INVALID' => 'Tienes que entrar una dirección de correo electrónico vàlida para el expedidor.',
'CONTACT_ENTER_RECEIVER_MAIL' => 'Tienes que entrar una dirección de correo electrónico para el destinatorio.',
'CONTACT_RECEIVER_MAIL_INVALID' => 'Tienes que entrar una dirección de correo electrónico vàlida para el destinatorio.',
'CONTACT_ENTER_MESSAGE' => 'Entra tu mensaje. Tiene que componerse de 10 caracteres como minimo.',

'CONTACT_MESSAGE_SUCCESSFULLY_SENT' => 'Tu mensaje ha sido enviado. Gracias!',
'CONTACT_MESSAGE_NOT_SENT' => 'El mensaje no ha sido enviado... Problema en la configuración del servidor?',
'CONTACT_SUBSCRIBE_ORDER_SENT' => 'Tu petición de abono ha sido enviada. Gracias!',
'CONTACT_UNSUBSCRIBE_ORDER_SENT' => 'Tu petición de desabono ha sido enviada. Gracias!',

'CONTACT_HANDLER_MAIL_FOR_ADMINS' => 'El handler /mail es reservado al grup de los administradores.',
'CONTACT_LOGIN_IF_ADMIN' => 'Si formas parte de este grupo, te tienes que identificar.',

'CONTACT_SUBSCRIBE' => 'Suscribir',
'CONTACT_UNSUBSCRIBE' => 'Desabonarse',

'CONTACT_FROM' => 'de',

));
