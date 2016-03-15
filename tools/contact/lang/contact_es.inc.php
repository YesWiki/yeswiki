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
'CONTACT_ENTER_TEXT_WITH_EMAILS_INSIDE' => 'Entrez un texte contenant des mails dedans pour les extraire, qu\'importe les s&eacute;parateurs (virgules, point-virgules, deux points, espaces, tabulations, retours &agrave; la ligne) ou le texte entre',
'CONTACT_YOUR_EMAIL_LIST' => 'Votre liste d\'adresses mails',
'CONTACT_EXTRACT_EMAILS_FROM_TEXT' => 'Extraire les mails de ce texte',
'CONTACT_MUST_BE_ADMIN_TO_USE_THIS_ACTION' => 'il faut &ecirc;tre dans le groupe admins pour utiliser cette action',


'CONTACT_YOUR_NAME' => 'Tu nombre',
'CONTACT_YOUR_MAIL' => 'Tu dirección de correo electrónico ',
'CONTACT_SUBJECT' => 'Objeto del mensaje',
'CONTACT_YOUR_MESSAGE' => 'Tu mensaje',
'CONTACT_SEND_MESSAGE' => 'Enviar el mensaje',
'CONTACT_LISTSUBSCRIBE_REQUIRED' => 'le param&egrave;tre "list", contenant l\'adresse &eacute;lectronique de la liste, du type nomliste@domaine.ext, est obligatoire',
'CONTACT_USER_NOT_LOGGED_IN' => 'il faut &ecirc;tre identifi&eacute; pour acc&eacute;der aux actions possibles.',
'CONTACT_USER_NO_EMAIL' => 'il faut &ecirc;tre identifi&eacute; pour acc&eacute;der aux actions possibles.',

'CONTACT_ENTER_NAME' => 'Vous devez entrer un nom.',
'CONTACT_ENTER_SENDER_MAIL' => 'Vous devez entrer une adresse mail pour l\'exp&eacute;diteur.',
'CONTACT_SENDER_MAIL_INVALID' => 'Vous devez entrer une adresse mail valide pour l\'exp&eacute;diteur.',
'CONTACT_ENTER_RECEIVER_MAIL' => 'Vous devez entrer une adresse mail pour le destinataire.',
'CONTACT_RECEIVER_MAIL_INVALID' => 'Vous devez entrer une adresse mail valide pour le destinataire.',
'CONTACT_ENTER_MESSAGE' => 'Veuillez entrer un message. Il doit faire au minimum 10 caract&egrave;res.',

'CONTACT_MESSAGE_SUCCESSFULLY_SENT' => 'Tu mensaje ha sido enviado. Gracias!',
'CONTACT_MESSAGE_NOT_SENT' => 'El mensaje no ha sido enviado... Problema en la configuración del servidor?',
'CONTACT_SUBSCRIBE_ORDER_SENT' => 'Votre demande concernant votre abonnement a bien &eacute;t&eacute; prise en compte. Merci!',
'CONTACT_UNSUBSCRIBE_ORDER_SENT' => 'Votre demande concernant votre d&eacute;sabonnement a bien &eacute;t&eacute; prise en compte. Merci!',

'CONTACT_HANDLER_MAIL_FOR_ADMINS' => 'Le handler /mail est r&eacute;serv&eacute; au groupe des administrateurs.',
'CONTACT_LOGIN_IF_ADMIN' => 'Si vous faites parti ce groupe, veuillez vous identifier.',

'CONTACT_SUBSCRIBE' => 'Suscribir',
'CONTACT_UNSUBSCRIBE' => 'Desabonarse',

'CONTACT_FROM' => 'de',

));
