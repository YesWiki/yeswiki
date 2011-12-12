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
// CVS : $Id: contact.php,v 1.5 2011-07-13 10:24:11 mrflos Exp $
/**
* contact.php
*
* Description :
*
*@package contact
//Auteur original :
*@author        Florian SCHMITT <florian@outils-reseaux.org>
//Autres auteurs :
*@author        Aucun
*@copyright     outils-reseaux.org 2008
*@version       $Revision: 1.5 $ $Date: 2011-07-13 10:24:11 $
// +------------------------------------------------------------------------------------------------------+
*/

if (!defined("WIKINI_VERSION")) {
        die ("acc&egrave;s direct interdit");
}

//recuperation des parametres
$mail = $this->GetParameter('mail');
if (empty($mail)) {
	echo '<div class="error_box">Action contact : le param&ecirc;tre mail, obligatoire, est manquant.</div>';
}
else {

	$entete = $this->GetParameter('entete');
	if (empty($entete)) {
		$entete = $this->config['wakka_name'];
	}

	$class = $this->GetParameter('class');


	echo '<div class="contact-form '.$class.'">
		<div class="note"></div>
		<form id="ajax-contact-form" class="ajax-form" action="'.$this->href('mail').'">
			<div class="contact-row">
					<label class="contact-label">Votre nom</label>
					<input class="contact-input contact-name" type="text" name="name" value="" />
					<div class="clear"></div>
			</div>

			<div class="contact-row">
					<label class="contact-label">Votre adresse mail</label>
					<input class="contact-input contact-mail" type="text" name="email" value="" />
					<div class="clear"></div>
			</div>
	
			<div class="contact-row">
					<label class="contact-label">Sujet du message</label>
					<input class="contact-input contact-subject" type="text" name="subject" value="" />
					<div class="clear"></div>
			</div>
	
			<div class="contact-row">
					<label class="contact-label">Corps du message</label>
					<textarea class="contact-textarea contact-message" name="message" rows="5" cols="25"></textarea>
					<div class="clear"></div>
			</div>
	
			<div class="contact-row">
					<label class="contact-label">&nbsp;</label>
					<input class="contact-submit" type="submit" name="submit" value="Envoyer" />
					<input type="hidden" name="mail" value="'.$mail.'" />
					<input type="hidden" name="entete" value="'.$entete.'" />	
					<input type="hidden" name="type" value="contact" />	
					<div class="clear"></div>
			</div>
			<div class="clear"></div>	
		</form>
	</div>';
}
?>
