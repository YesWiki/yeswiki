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
* English translation for Login extension
*
*@package       login
*@author        Florian Schmitt <florian@outils-reseaux.org>
*@copyright     2014 Outils-Réseaux
*/

return [
    
    'LOGIN_SIGNUP' => 'Signup',
    'LOGIN_LOGIN' => 'Login',
    'LOGIN_WIKINAME' => 'Email or WikiName',
    'LOGIN_EMAIL' => 'Email',
    'LOGIN_PASSWORD' => 'Password',
    'LOGIN_MODIFY' => 'Modify',
    'LOGIN_MODIFY_USER' => 'Modify my account',
    'LOGIN_REMEMBER_ME' => 'Remember me',
    'LOGIN_LOGOUT' => 'Logout',
    'LOGIN_NEW_MEMBER' => 'New member',
    // 'LOGIN_NOT_AUTORIZED' => 'Vous n\'&ecirc;tes pas autoris&eacute; &agrave; lire cette page',
    // 'LOGIN_NOT_AUTORIZED_EDIT' => 'Vous n\'&ecirc;tes pas autoris&eacute; &agrave; éditer cette page',
    // 'LOGIN_WRONG_PASSWORD' => 'Identification impossible : mauvais mot de passe.',
    // 'LOGIN_WRONG_USER' => 'Identification impossible : Identifiant non reconnu.',
    // 'LOGIN_NO_CONNECTED_USER' => 'Pas d\'utilisateur connecté.',
    'LOGIN_ALREADY_MEMBER' => 'Already member',
    // 'LOGIN_PLEASE_REGISTER' => 'veuillez vous identifier',
    'LOGIN_CONNECTED_AS' => 'Connected as',
    'LOGIN_YOU_ARE_NOW_DISCONNECTED' => 'You are now disconnected !',
    'LOGIN_LOST_PASSWORD' => 'Lost password ?',
    'LOGIN_CHANGE_PASSWORD' => 'Change the password',
    'LOGIN_UNKNOWN_USER' => 'Unknown email, no user registered under this email',
    'LOGIN_ADD_EMAIL_TO_CONTINUE' => 'Add an email to continue',
    'LOGIN_BACK' => 'Back',
    'LOGIN_SEND' => 'Send',
    'LOGIN_NEW_PASSWORD' => 'New password',
    'LOGIN_CONFIRM_PASSWORD' => 'Confirm your password',
    'LOGIN_WELCOME' => 'Welcome',
    'LOGIN_WRITE_PASSWORD' => 'Write your new password in the fields below',
    'LOGIN_PASSWORD_SHOULD_BE_IDENTICAL' => 'Passwords should be identical and not empty',
    'LOGIN_MESSAGE_SENT' => 'A message was sent to you by mail to reset your password',
    'LOGIN_INVALID_KEY' => 'Invalid key',
    'LOGIN_PASSWORD_WAS_RESET' => 'Your password was succesfully changed',
    'LOGIN_DEAR' => 'Dear',
    'LOGIN_CLICK_FOLLOWING_LINK' => 'Click on the link below to reset your password',
    'LOGIN_THE_TEAM' => 'The team from',
    'LOGIN_PASSWORD_LOST_FOR' => 'Lost password for',
    // 'LOGIN_NO_SIGNUP_IN_THIS_PERIOD' => 'Il n\'y a pas d\'inscription pour cette période.',

    // actions/login.php
    // 'LOGIN_COOKIES_ERROR' => 'Vous devez accepter les cookies pour pouvoir vous connecter.',

    // actions/usersettings.php
    'USERSETTINGS_EMAIL_NOT_CHANGED' => 'Email not modified.',
    'USERSETTINGS_PASSWORD_NOT_CHANGED' => 'Password not changed.',
    'USERSETTINGS_USER_NOT_DELETED' => 'User not deleted.',
    'USERSETTINGS_CAPTCHA_USER_CREATION' => 'Verification to create a user',
    'USERSETTINGS_SIGNUP_MISSING_INPUT' => 'The \'{parameters}\' parameters cannot be empty!',
    'USERSETTINGS_NAME_ALREADY_USED' => 'The identifier "{currentName}" already exists!',
    'USERSETTINGS_EMAIL_ALREADY_USED' => 'The email "{email}" is already used by another account!',
    'USERSETTINGS_CHANGE_PWD_IN_IFRAME' => "You are about to change your password in an iframe window.\n".
        "To avoid keylogging attacks, make sure the site url starts with {baseUrl}.\n".
        "If in doubt, open this form in a dedicated page by clicking on this link {link}.",
];
