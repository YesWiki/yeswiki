<?php
/*vim: set expandtab tabstop=4 shiftwidth=4: */
// +------------------------------------------------------------------------------------------------------+
// | PHP version 5.1                                                                                      |
// +------------------------------------------------------------------------------------------------------+
// | Copyright (C) 1999-2006 outils-reseaux.org                                                            |
// +------------------------------------------------------------------------------------------------------+
// | This file is part of contact.                                                                     |
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
// CVS : $Id: contact.php,v 1.3 2010-10-19 15:59:15 mrflos Exp $
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
*@version       $Revision: 1.3 $ $Date: 2010-10-19 15:59:15 $
// +------------------------------------------------------------------------------------------------------+
*/

if (!defined("WIKINI_VERSION")) {
        die ("acc&egrave;s direct interdit");
}

//recuperation des parametres
$mail = $this->GetParameter('mail');
if (empty($mail)) {
	exit('<div class="error_box">Action contact : param&ecirc;tre mail obligatoire</div>');
}

$entete = $this->GetParameter('entete');
if (empty($entete)) {
	$entete = $this->config['wakka_name'];
}

$class = $this->GetParameter('class');
if (empty($class)) {
	$class = 'grid_8';
}
switch ($class) {
case "grid_12": $classlabel = "grid_3"; $classinput = "grid_9"; break;
case "grid_11": $classlabel = "grid_3"; $classinput = "grid_8"; break;
case "grid_10": $classlabel = "grid_3"; $classinput = "grid_7"; break;
case "grid_9": $classlabel = "grid_3"; $classinput = "grid_6"; break;
case "grid_8": $classlabel = "grid_2"; $classinput = "grid_6"; break;
case "grid_7": $classlabel = "grid_2"; $classinput = "grid_5"; break;
case "grid_6": $classlabel = "grid_2"; $classinput = "grid_4"; break;
case "grid_5": $classlabel = "grid_2"; $classinput = "grid_3"; break;
case "grid_4": $classlabel = "grid_2"; $classinput = "grid_2"; break;
case "grid_3": $classlabel = "grid_1"; $classinput = "grid_2"; break;
case "grid_2": $classlabel = "grid_1"; $classinput = "grid_1"; break;
case "grid_1": $classlabel = "grid_1"; $classinput = "grid_1"; break;
default : $classlabel = "grid_2"; $classinput = "grid_6"; 
}

echo '<div class="formulairemail '.$class.'">
<div class="note"></div>
<form id="ajax-contact-form" class="ajax-form" action="'.$this->href('mail').'">
	<div class="row">
		<div class="column '.$classlabel.' label-right" style="text-align:right;">
			<label class="label-contact">Votre nom</label>
		</div>
		<div class="column '.$classinput.' textbox">
			<input type="text" name="name" value="" style="width:99%;" />
		</div>
		<div class="clear"></div>
	</div>
	
	<div class="row">
		<div class="column '.$classlabel.' label-right" style="text-align:right;">
			<label class="label-contact">Votre adresse mail</label>
		</div>
		<div class="column '.$classinput.' textbox">
			<input type="text" name="email" value="" style="width:99%;" />
		</div>
		<div class="clear"></div>
	</div>
	
	<div class="row">
		<div class="column '.$classlabel.' label-right" style="text-align:right;">
			<label class="label-contact">Sujet du message</label>
		</div>
		<div class="column '.$classinput.' textbox">
			<input type="text" name="subject" value="" style="width:99%;" />
		</div>
		<div class="clear"></div>
	</div>
	
	<div class="row">
		<div class="column '.$classlabel.' label-right" style="text-align:right;">
			<label class="label-contact">Corps du message</label>
		</div>
		<div class="column '.$classinput.' textbox">
			<textarea name="message" rows="5" cols="25" style="width:99%;"></textarea>
		</div>
		<div class="clear"></div>
	</div>
	
	<div class="row">
		<div class="column '.$classlabel.' label-right" style="text-align:right;">
			<label class="label-contact">&nbsp;</label>
		</div>
		<div class="column '.$classinput.' textbox">
			<input type="submit" name="submit" value="Envoyer" style="width:100%;" />
		</div>
		<input type="hidden" name="mail" value="'.$mail.'" />
		<input type="hidden" name="entete" value="'.$entete.'" />	
		<input type="hidden" name="type" value="contact" />	
		<div class="clear"></div>
	</div>
	
</form>
</div>
';
?>
