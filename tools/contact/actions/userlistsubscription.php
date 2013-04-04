<?php
/*vim: set expandtab tabstop=4 shiftwidth=4: */
// +------------------------------------------------------------------------------------------------------+
// | PHP version 5.1                                                                                      |
// +------------------------------------------------------------------------------------------------------+
// | Copyright (C) 1999-2006 outils-reseaux.org                                                           |
// +------------------------------------------------------------------------------------------------------+
// | This file is part of contact.                                                                        |
// |                                                                                                      |
// | Foobar is free software; you can redistribute it and/or modify                                       |
// | it under the terms of the GNU General Public License as published by                                 |
// | the Free Software Foundation; either version 2 of the License, or                                    |
// | (at your option) any later version.                                                                  |
// |                                                                                                      |
// | Foobar is distributed in the hope that it will be useful,                                            |
// | but WITHOUT ANY WARRANTY; without even the implied warranty of                                       |
// | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the                                        |
// | GNU General Public License for more details.                                                         |
// |                                                                                                      |
// | You should have received a copy of the GNU General Public License                                    |
// | along with Foobar; if not, write to the Free Software                                                |
// | Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA                            |
// +------------------------------------------------------------------------------------------------------+
// CVS : $Id: listsubscription.php,v 1.2 2010-10-19 15:59:15 mrflos Exp $
/**
* listsubscription.php
*
* Description : action permettant l'envoi par mail d'une demande d'inscription ou desinscription a une liste
*
*@package contact
*@author        Florian SCHMITT <florian@outils-reseaux.org>
*@copyright     outils-reseaux.org 2013
*/
if (!defined("WIKINI_VERSION"))
{
        die ("acc&egrave;s direct interdit");
}

// valable que pour les utilisateurs connectes
if ($user = $this->GetUser()) {
	if ($user['email'] != '') {

		//recuperation des parametres
		$list = $this->GetParameter('list');
		if (!empty($list)) {
			if (isset($_GET['list']) && $_GET['list']==$list) {
				include_once 'tools/contact/libs/contact.functions.php';
				if ($_GET['action'] == 'unsubscribe') {
					send_mail($user['email'], $user['name'], str_replace('@','-unsubscribe@',$list), $_GET['action'], $_GET['action'], $$_GET['action']);
					$this->DeleteTriple($user['name'], 'http://outils-reseaux.org/_vocabulary/userlistsubscription', $list, '', '');

				} 
				elseif ($_GET['action'] == 'subscribe') {
					send_mail($user['email'], $user['name'], str_replace('@','-subscribe@',$list), $_GET['action'], $_GET['action'], $$_GET['action']);
					$this->DeleteTriple($user['name'], 'http://outils-reseaux.org/_vocabulary/userlistsubscription', $list, '', '');
					$this->InsertTriple($user['name'], 'http://outils-reseaux.org/_vocabulary/userlistsubscription', $list, '', '');
				}
			}

			$output = '<p><strong>Liste '.$list.' :</strong> ';
			if ($this->TripleExists($user['name'], 'http://outils-reseaux.org/_vocabulary/userlistsubscription', $list, '', '')) {
				$output .= 'vous &ecirc;tes inscrit <a href="'.$this->href('', $this->GetPageTag(), 'list='.$list.'&amp;action=unsubscribe').'" class="btn">Se d&eacute;sinscrire de cette liste</a></p>'."\n";
			}
			else {
				$output .= 'vous n\'&ecirc;tes pas inscrit <a href="'.$this->href('', $this->GetPageTag(), 'list='.$list.'&amp;action=subscribe').'" class="btn">S\'inscrire &agrave; cette liste</a></p>'."\n";
			}

		} 
		else {
			$output = '<div class="alert alert-danger"><strong>Action userlistsubscription</strong> : '.CONTACT_LIST_REQUIRED.'.</div>';
		}
		
		
	}
	else {
		$output = '<div class="alert alert-danger"><strong>Action userlistsubscription</strong> : '.CONTACT_USER_NO_EMAIL.'</div>';
	}	
}
else {
	$output = '<div class="alert alert-danger"><strong>Action userlistsubscription</strong> : '.CONTACT_USER_NOT_LOGGED_IN.'</div>';
}

echo $output;

?>
