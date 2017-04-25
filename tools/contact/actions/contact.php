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

/**
* contact.php
*
* Description :
*
*@package contact
*@author        Florian SCHMITT <florian@outils-reseaux.org>
*@copyright     outils-reseaux.org 2008
*@version       $Revision: 1.5 $ $Date: 2011-07-13 10:24:11 $
*
*/

if (!defined("WIKINI_VERSION")) {
        die ("acc&egrave;s direct interdit");
}

//recuperation des parametres
$contactelements['mail'] = $this->GetParameter('mail');
if (empty($contactelements['mail'])) {
	echo '<div class="alert alert-danger"><strong>'._t('CONTACT_ACTION_CONTACT').' :</strong>&nbsp;'._t('CONTACT_MAIL_REQUIRED').'</div>';
}
else {
	// on utilise une variable globale pour savoir de quel formulaire la demande est envoyee, s'il y en a plusieurs sur la meme page
	if (isset($GLOBALS['nbactionmail'])) {
		$GLOBALS['nbactionmail']++;
	}
	else {
		$GLOBALS['nbactionmail'] = 1;
	}
	$contactelements['nbactionmail'] = $GLOBALS['nbactionmail'];

	$contactelements['entete'] = $this->GetParameter('entete');
	if (empty($contactelements['entete'])) {
		$contactelements['entete'] = $this->config['wakka_name'];
	}

	// on choisit le template utilisé
	$template = $this->GetParameter('template');
	if (empty($template)) {
		$template = 'complete-contact-form.tpl.html';
	}

	// on peut ajouter des classes à la classe par défaut
	$contactelements['class'] = ($this->GetParameter('class') ? 'form-contact '.$this->GetParameter('class') : 'form-contact');

	// adresse url d'envoi du mail
	$contactelements['mailerurl'] = $this->href('mail');

    include_once('tools/libs/squelettephp.class.php');

    // On cherche un template personnalise dans le repertoire themes/tools/bazar/templates
    $templatetoload = 'themes/tools/contact/templates/'.$template;
    if (!is_file($templatetoload)) {
        $templatetoload = 'tools/contact/presentation/templates/'.$template;
    }

	$contacttemplate = new SquelettePhp($templatetoload);
	$contacttemplate->set($contactelements);
	echo $contacttemplate->analyser();

	$GLOBALS['js'] = ((isset($GLOBALS['js'])) ? str_replace('	<script src="tools/contact/libs/contact.js"></script>'."\n", '', $GLOBALS['js']) : '').'	<script src="tools/contact/libs/contact.js"></script>'."\n";
}
?>
