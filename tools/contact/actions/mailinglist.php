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
// CVS : $Id: mailinglist.php,v 1.2 2010-10-19 15:59:15 mrflos Exp $
/**
* mailinglist.php
*
* Description : action permettant d'inscrire ou d?sinscrire massivement des mails a une newsletter
*
*@package contact
*@author        Florian SCHMITT <florian@outils-reseaux.org>
*@copyright     outils-reseaux.org 2012
*@version       $Revision: 1.2 $ $Date: 2010-10-19 15:59:15 $
*
*/
if (!defined("WIKINI_VERSION"))
{
        die ("acc&egrave;s direct interdit");
}

//recuperation des parametres
$list = $this->GetParameter('list');
if (empty($list)) {
	echo '<div class="alert alert-danger"><strong>'._t('CONTACT_ACTION_MAILINGLIST').'</strong> : '._t('CONTACT_PARAMETER_LIST_REQUIRED').'.</div>';
}
elseif ($this->UserIsAdmin()) {
	
	echo '<h2>'._('CONTACT_MAILS_TO_ADD_OR_REMOVE').' '.$list.'</h2>';
	
	// les mails formates sont prets a etre envoyes
	if (isset($_POST['mails'])) {
		if (is_array($_POST['mails'])) {
			
			//inclusion de la bibliotheque de fonctions pour l'envoi des mails
			include_once 'tools/contact/libs/contact.functions.php';
			
			$tab_listadress = explode('@',$list);
			
			// en fonction de l'action demand	
			if ($_POST['action_mails'] == _t('CONTACT_BTN_SUBSCRIBE')) {
				$listaction = $tab_listadress[0].'-subscribe@'.$tab_listadress[1];
			} elseif ($_POST['action_mails'] == _t('CONTACT_BTN_UNSUBSCRIBE')) {
				$listaction = $tab_listadress[0].'-unsubscribe@'.$tab_listadress[1];
			}
			echo '<div class="well" style="width:600px; height:150px; overflow:auto; ">';
			foreach($_POST['mails'] as $email) {
				echo _t('CONTACT_SENT_TO_THE_LIST').' : '.$listaction.' '._t('CONTACT_THE_EMAIL').' : '.$email;
				echo send_mail($email, $email, $listaction, $_POST['action_mails'], $_POST['action_mails'], '', ' <span class="text-success">'._t('CONTACT_OK').'</span>').'<br />';

			}
			echo '</div>
			<a href="'.$this->href().'" title="'._t('CONTACT_SUBMIT_OTHER_EMAILS').'">'._t('CONTACT_SUBMIT_OTHER_EMAILS').'</a>';	
		}	
	}
	// la liste des mails non formatee est disponible
	elseif (isset($_POST['mailinglist'])) {
		//extrait les mails
		$regEx = "/([\s]*)[\._a-zA-Z0-9-]+@[\._a-zA-Z0-9-]+/i";
		preg_match_all($regEx, $_POST['mailinglist'], $emails);
		if (is_array($emails) && count($emails[0])>0) {
			sort($emails[0]);	
			echo '<form id="ajax-mailing-form" method="post" action="'.$this->href().'">
			<div class="well" style="width:600px; height:150px; overflow:auto; ">';
			
			foreach($emails[0] as $email) {
				echo $email.'<br /><input name="mails[]" type="hidden" value="'.htmlspecialchars($email, ENT_COMPAT, YW_CHARSET).'" />';
				$emails[] = $email;
			}
			echo '</div>
			<strong>'._t('CONTACT_FOR_ALL_THOSE_EMAILS').' : </strong><input class="btn button_save" type="submit" name="action_mails" value="'._t('CONTACT_BTN_UNSUBSCRIBE').'" />
			<input class="btn button_cancel" type="submit" name="action_mails" value="'._t('CONTACT_BTN_UNSUBSCRIBE').'" />
			</form><br /><br />
			<a href="'.$this->href().'" title="'._t('CONTACT_TRY_WITH_OTHER_EMAILS').'">'._t('CONTACT_TRY_WITH_OTHER_EMAILS').'</a>';
		} else {
			echo '<div class="alert alert-danger">'._t('CONTACT_NO_EMAILS_FOUND_IN_THIS_TEXT').'.</div>
			<a href="'.$this->href().'" title="'._t('CONTACT_TRY_WITH_OTHER_EMAILS').'">'._t('CONTACT_TRY_WITH_OTHER_EMAILS').'</a>';	
		}

	}
	// rien n'a ete fait, on propose un formulaire pour ajouter les mails 
	else {
		echo '<div class="alert alert-info">'._t('CONTACT_ENTER_TEXT_WITH_EMAILS_INSIDE').'.</div>
		<form id="ajax-mailing-form" method="post" action="'.$this->href().'">
			<label style="display:inline-block;width:200px;text-align:right;">'._t('CONTACT_YOUR_EMAIL_LIST').'</label>		
			<textarea name="mailinglist" rows="6" cols="20" style="width:600px;height:150px;"></textarea>
			<input class="btn button_save" style="margin:10px 0 10px 205px;" type="submit" name="submit" value="'._t('CONTACT_EXTRACT_EMAILS_FROM_TEXT').'" />
		</form>';
	}
}
else{
	echo '<div class="alert alert-danger"><strong>'._t('CONTACT_ACTION_MAILINGLIST').'</strong> : '._t('CONTACT_MUST_BE_ADMIN_TO_USE_THIS_ACTION').'.</div>'."\n";
}

?>
