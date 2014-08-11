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
* Arquivo de tradução em português (do Brasil) da extensão contact
*
*@package 		contact
*@author        François Labastie <francois@outils-reseaux.org>
*@copyright     2014 Outils-Réseaux
*/

$GLOBALS['translations'] = array_merge($GLOBALS['translations'], array(

// actions/abonnement.php
'CONTACT_ACTION_ABONNEMENT' => 'Ação {{abonnement ...}}',
'CONTACT_MAIL_REQUIRED' => 'o parâmetro mail, obrigatório está faltando.',

// actions/contact.php
'CONTACT_ACTION_CONTACT' => 'Ação {{contact ...}}',

// actions/desabonnement.php
'CONTACT_ACTION_DESABONNEMENT' => 'Ação {{desabonnement ...}}',

// actions/listsubscription.php
'CONTACT_ACTION_LISTSUBSCRIPTION' => 'Ação {{listsubscription ...}}',

// actions/mailinglist.php
'CONTACT_ACTION_MAILINGLIST' => 'Ação {{mailinglist ...}}',
'CONTACT_PARAMETER_LIST_REQUIRED' => 'parâmetro "list" obrigatório (este é o endereço de e-mail da lista de discussão)',
'CONTACT_MAILS_TO_ADD_OR_REMOVE' => 'Mails para adicionar ou remover da lista',
'CONTACT_SENT_TO_THE_LIST' => 'Envio à lista',
'CONTACT_THE_EMAIL' => 'Email',
'CONTACT_SUBMIT_OTHER_EMAILS' => 'Entrar outros emails',
'CONTACT_OK' => 'OK',
'CONTACT_BTN_SUBSCRIBE' => 'Assinar',
'CONTACT_BTN_UNSUBSCRIBE' => 'Cancelar inscrição',
'CONTACT_FOR_ALL_THOSE_EMAILS' => 'Por todos esses endereços de e-mail',
'CONTACT_TRY_WITH_OTHER_EMAILS' => 'Tentar com outros emails no texto',
'CONTACT_NO_EMAILS_FOUND_IN_THIS_TEXT' => 'Nenhum endereço de e-mail encontrado no texto fornecido',
'CONTACT_ENTER_TEXT_WITH_EMAILS_INSIDE' => 'Digite o texto contendo mails para ser extraídos, não importa separadores (vírgulas, ponto e vírgula, dois pontos, espaços, tabulações, quebras de linha)',
'CONTACT_YOUR_EMAIL_LIST' => 'A sua lista de endereços de emails',
'CONTACT_EXTRACT_EMAILS_FROM_TEXT' => 'Extrair os emails deste texto',
'CONTACT_MUST_BE_ADMIN_TO_USE_THIS_ACTION' => 'deve estar no grupo de administradores para usar esta ação',


'CONTACT_YOUR_NAME' => 'Seu nome',
'CONTACT_YOUR_MAIL' => 'Seu endereço de email',
'CONTACT_SUBJECT' => 'Assunto',
'CONTACT_YOUR_MESSAGE' => 'Sua mensagem',
'CONTACT_SEND_MESSAGE' => 'Enviar a mensagem',
'CONTACT_LISTSUBSCRIBE_REQUIRED' => 'O parâmetro "list" que contém o endereço de e-mail da lista, do tipo nomelista@domínio.ext é obrigatório',
'CONTACT_USER_NOT_LOGGED_IN' => 'Você deve ser identificado para acessar as possíveis ações.',
'CONTACT_USER_NO_EMAIL' => 'Você deve ser identificado para acessar as possíveis ações.',

'CONTACT_ENTER_NAME' => 'Você deve digitar um nome.',
'CONTACT_ENTER_SENDER_MAIL' => 'Você deve digitar um endereço de e-mail para o remetente.',
'CONTACT_SENDER_MAIL_INVALID' => 'Você deve digitar um endereço de e-mail válido para o remetente.',
'CONTACT_ENTER_RECEIVER_MAIL' => 'Você deve digitar um endereço de e-mail para o destinatário.',
'CONTACT_RECEIVER_MAIL_INVALID' => 'Você deve digitar um endereço de e-mail válido para o destinatário.',
'CONTACT_ENTER_MESSAGE' => 'Por favor escreva uma mensagem. Deve haver pelo menos 10 caracteres.',

'CONTACT_MESSAGE_SUCCESSFULLY_SENT' => 'Sua mensagem foi enviada. Obrigado!',
'CONTACT_MESSAGE_NOT_SENT' => 'A mensagem não pôde ser enviada... Problema do lado do servidor de configuração?',
'CONTACT_SUBSCRIBE_ORDER_SENT' => 'O seu pedido de assinatura foi tido em conta. Obrigado!',
'CONTACT_UNSUBSCRIBE_ORDER_SENT' => 'O seu pedido de cancelamento de inscrição foi tido em conta. Obrigado!',

'CONTACT_HANDLER_MAIL_FOR_ADMINS' => 'O handler mail é reservado para o grupo de administradores.',
'CONTACT_LOGIN_IF_ADMIN' => 'Se você está entre esse grupo, identifique-se.',

'CONTACT_SUBSCRIBE' => 'Assinar',
'CONTACT_UNSUBSCRIBE' => 'Cancelar inscrição',

'CONTACT_FROM' => 'de',

));