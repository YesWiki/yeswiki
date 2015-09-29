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
* Fitxer de traducció al català de l'extensió syndication
*
*@package 		syndication
*@author        Jordi Picart <jordi.picart@aposta.coop>
*@copyright     2014 Outils-Réseaux
*/

$GLOBALS['translations'] = array_merge($GLOBALS['translations'], array(

// actions/syndication.php
'SYNDICATION_ACTION_SYNDICATION' => 'Acció {{syndication ...}}',
'SYNDICATION_PARAM_URL_REQUIRED' => 'El paràmetre "url" és obligatori per sindicar un flux RSS',

// actions/twitter.php
'SYNDICATION_ACTION_TWITTER' => 'Acció {{twitter ...}}',
'SYNDICATION_PARAM_USER_REQUIRED' => 'Falta el paràmetre "user", que és oblogatori per definir l\'usuari de Twitter',

// actions/twittersearch.php
'SYNDICATION_ACTION_TWITTERSEARCH' => 'Acció {{twittersearch ...}}',
'SYNDICATION_PARAM_QUERY_REQUIRED' => 'Falta el paràmetre "query", que és obligatori per definir la vostra cerca',
'SYNDICATION_TEMPLATE_DOESNT_EXISTS' => 'El fitxer de patró',
'SYNDICATION_USE_OF_DEFAULT_TEMPLATE' => 'no existeix, s\'utilitzarà el patró per defecte'

));