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
// CVS : $Id: abonnement.php,v 1.2 2010-10-19 15:59:15 mrflos Exp $
/**
* abonnement.php
*
* Description : action permettant l'envoi par mail d'une demande d'inscription ? une newsletter
*
*@package contact
*
*@author        Florian SCHMITT <florian@outils-reseaux.org>
*
*@copyright     outils-reseaux.org 2008
*@version       $Revision: 1.2 $ $Date: 2010-10-19 15:59:15 $
*
*/
if (!defined("WIKINI_VERSION"))
{
        die ("acc&egrave;s direct interdit");
}

//recuperation des parametres
$mail = $this->GetParameter('mail');
if (empty($mail)) {die('<div class="alert alert-error">Action abonnement : param&ecirc;tre mail obligatoire.</div>');}

// on utilise une variable globale pour savoir de quel formulaire la demande est envoyee, s'il y en a plusieurs sur la meme page
if (isset($GLOBALS['nbactionmail'])) {
	$GLOBALS['nbactionmail']++;
}
else {
	$GLOBALS['nbactionmail'] = 1;
}

echo '<div class="formulairemail">
<div class="note"></div>
<form id="ajax-abonne-form" class="ajax-mail-form" action="'.$this->href('mail').'">
	<label class="grid_2 label-right">Votre adresse mail</label>
	<input class="grid_2 textbox" type="text" name="email" value="" />
	<input class="grid_2 button contact-submit" type="submit" name="submitnewsletter" value="S\'abonner" />
	<input type="hidden" name="mail" value="'.$mail.'" />
	<input type="hidden" name="nbactionmail" value="'.$GLOBALS['nbactionmail'].'" />
	<input type="hidden" name="type" value="abonne" />	
</form>
<div class="clear"></div>
</div>
';
$GLOBALS['js'] = ((isset($GLOBALS['js'])) ? str_replace('	<script src="tools/contact/libs/contact.js"></script>'."\n", '', $GLOBALS['js']) : '').'	<script src="tools/contact/libs/contact.js"></script>'."\n";
?>
