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
			$output =  '<div class="note"></div>
				<form id="ajax-abonne-form" class="form-mail" action="'.$this->href('mail').'">
					'.$list.' : '."\n".
				'</form>'."\n";
				$GLOBALS['js'] = ((isset($GLOBALS['js'])) ? str_replace('	<script src="tools/contact/libs/contact.js"></script>'."\n", '', $GLOBALS['js']) : '').'	<script src="tools/contact/libs/contact.js"></script>'."\n";
		} 
		else {
			echo '<div class="alert alert-danger"><strong>'._t('CONTACT_ACTION_LISTSUBSCRIPTION').'</strong> : '._t('CONTACT_LIST_REQUIRED').'.</div>';
		}
		
		
	}
	else {
		echo '<div class="alert alert-danger"><strong>'._t('CONTACT_ACTION_LISTSUBSCRIPTION').'</strong> : '._t('CONTACT_USER_NO_EMAIL').'</div>';
	}	
}
else {
	echo '<div class="alert alert-danger"><strong>'._t('CONTACT_ACTION_LISTSUBSCRIPTION').'</strong> : '._t('CONTACT_USER_NOT_LOGGED_IN').'</div>';
}

?>
