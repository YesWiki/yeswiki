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
// CVS : $Id: desabonnement.php,v 1.2 2010-10-19 15:59:15 mrflos Exp $
/**
* desabonnement.php
*
* Description : action permettant l'envoi par mail d'une demande de désinscription à une newsletter
*
*@package contact
//Auteur original :
*@author        Florian SCHMITT <florian@outils-reseaux.org>
//Autres auteurs :
*@copyright     outils-reseaux.org 2008
*@version       $Revision: 1.2 $ $Date: 2010-10-19 15:59:15 $
// +------------------------------------------------------------------------------------------------------+
*/
if (!defined("WIKINI_VERSION"))
{
        die ("acc&egrave;s direct interdit");
}

//recuperation des parametres
$mail = $this->GetParameter('mail');
if (empty($mail)) {die('<div class="error_box">Action desabonnement : param&ecirc;tre mail obligatoire.</div>');}
echo '<div class="formulairemail">
<div class="note"></div>
<form id="ajax-desabonne-form" action="'.$this->href('mail').'">
	<label class="grid_2 label-right">Votre adresse mail</label>
	<input class="grid_2 textbox" type="text" name="email" value="" />
	<input class="grid_2 button" type="submit" name="submitnewsletter" value="Se d&eacute;sabonner" />
	<input type="hidden" name="mail" value="'.$mail.'" />
	<input type="hidden" name="type" value="desabonne" />	
</form>
<div class="clear"></div>
</div>
';
?>
