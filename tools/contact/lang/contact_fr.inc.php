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
*@package       contact
*@author        Florian Schmitt <florian@outils-reseaux.org>
*@copyright     2012 Outils-Réseaux
*/

$GLOBALS['translations'] = array_merge($GLOBALS['translations'], array(

// actions/abonnement.php
'CONTACT_ACTION_ABONNEMENT' => 'Action {{abonnement ...}}',
'CONTACT_MAIL_REQUIRED' => 'le param&egrave;tre mail, obligatoire, est manquant.',

// actions/contact.php
'CONTACT_ACTION_CONTACT' => 'Action {{contact ...}}',

// actions/desabonnement.php
'CONTACT_ACTION_DESABONNEMENT' => 'Action {{desabonnement ...}}',

// actions/listsubscription.php
'CONTACT_ACTION_LISTSUBSCRIPTION' => 'Action {{listsubscription ...}}',

// actions/mailinglist.php
'CONTACT_ACTION_MAILINGLIST' => 'Action {{mailinglist ...}}',
'CONTACT_PARAMETER_LIST_REQUIRED' => 'param&egrave;tre "list" obligatoire (il s\'agit de l\'adresse mail de la liste de diffusion)',
'CONTACT_MAILS_TO_ADD_OR_REMOVE' => 'Mails &agrave; ajouter ou &agrave; supprimer de la liste',
'CONTACT_SENT_TO_THE_LIST' => 'Envoi &agrave; la liste',
'CONTACT_THE_EMAIL' => 'l\'email',
'CONTACT_SUBMIT_OTHER_EMAILS' => 'Entrer d\'autres mails',
'CONTACT_OK' => 'OK',
'CONTACT_BTN_SUBSCRIBE' => 'Abonner',
'CONTACT_BTN_UNSUBSCRIBE' => 'D&eacute;sabonner',
'CONTACT_FOR_ALL_THOSE_EMAILS' => 'Pour toutes ces adresses mails',
'CONTACT_TRY_WITH_OTHER_EMAILS' => 'Essayer avec d\'autres mails dans le texte',
'CONTACT_NO_EMAILS_FOUND_IN_THIS_TEXT' => 'Pas d\'adresses mails trouv&eacute;es dans le texte fourni',
'CONTACT_ENTER_TEXT_WITH_EMAILS_INSIDE' => 'Entrez un texte contenant des mails dedans pour les extraire, qu\'importe les s&eacute;parateurs (virgules, point-virgules, deux points, espaces, tabulations, retours &agrave; la ligne) ou le texte entre',
'CONTACT_YOUR_EMAIL_LIST' => 'Votre liste d\'adresses mails',
'CONTACT_EXTRACT_EMAILS_FROM_TEXT' => 'Extraire les mails de ce texte',
'CONTACT_MUST_BE_ADMIN_TO_USE_THIS_ACTION' => 'il faut &ecirc;tre dans le groupe admins pour utiliser cette action',


'CONTACT_YOUR_NAME' => 'Votre nom',
'CONTACT_YOUR_MAIL' => 'Votre adresse mail',
'CONTACT_SUBJECT' => 'Sujet du message',
'CONTACT_YOUR_MESSAGE' => 'Votre message',
'CONTACT_SEND_MESSAGE' => 'Envoyer le message',
'CONTACT_LISTSUBSCRIBE_REQUIRED' => 'le param&egrave;tre "list", contenant l\'adresse &eacute;lectronique de la liste, du type nomliste@domaine.ext, est obligatoire',
'CONTACT_USER_NOT_LOGGED_IN' => 'il faut &ecirc;tre identifi&eacute; pour acc&eacute;der aux actions possibles.',
'CONTACT_USER_NO_EMAIL' => 'il faut &ecirc;tre identifi&eacute; pour acc&eacute;der aux actions possibles.',

'CONTACT_ENTER_NAME' => 'Vous devez entrer un nom.',
'CONTACT_ENTER_SENDER_MAIL' => 'Vous devez entrer une adresse mail pour l\'exp&eacute;diteur.',
'CONTACT_SENDER_MAIL_INVALID' => 'Vous devez entrer une adresse mail valide pour l\'exp&eacute;diteur.',
'CONTACT_ENTER_RECEIVER_MAIL' => 'Vous devez entrer une adresse mail pour le destinataire.',
'CONTACT_RECEIVER_MAIL_INVALID' => 'Vous devez entrer une adresse mail valide pour le destinataire.',
'CONTACT_ENTER_MESSAGE' => 'Veuillez entrer un message. Il doit faire au minimum 10 caract&egrave;res.',

'CONTACT_MESSAGE_SUCCESSFULLY_SENT' => 'Votre message a bien &eacute;t&eacute; envoy&eacute;. Merci!',
'CONTACT_MESSAGE_NOT_SENT' => 'Le message n\'a pas pu &ecirc;tre envoy&eacute;... Probleme du cot&eacute; de la configuration du serveur?',
'CONTACT_SUBSCRIBE_ORDER_SENT' => 'Votre demande concernant votre abonnement a bien &eacute;t&eacute; prise en compte. Merci!',
'CONTACT_UNSUBSCRIBE_ORDER_SENT' => 'Votre demande concernant votre d&eacute;sabonnement a bien &eacute;t&eacute; prise en compte. Merci!',

'CONTACT_HANDLER_MAIL_FOR_CONNECTED' => 'L\'envoi de mail est r&eacute;serv&eacute; aux personnes identifiées.',
'CONTACT_LOGIN_IF_CONNECTED' => 'Si vous avez un identifiant, veuillez vous identifier.',

'CONTACT_SUBSCRIBE' => 'S\'abonner',
'CONTACT_UNSUBSCRIBE' => 'Se d&eacute;sabonner',

'CONTACT_FROM' => 'de',
'CONTACT_TEMPLATE_NOT_FOUND' => 'Fichier de template non trouvé',
'CONTACT_MESSAGE_SENT_FROM' => 'Message envoyé à partir de',

));
