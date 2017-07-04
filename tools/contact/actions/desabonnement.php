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
* desabonnement.php
*
* Description : action permettant l'envoi par mail d'une demande de desinscription a une liste de discussion
*
*@package contact
*
*@author        Florian SCHMITT <florian@outils-reseaux.org>
*
*@copyright     outils-reseaux.org 2008
*@version       $Revision: 1.2 $ $Date: 2010-10-19 15:59:15 $
*
*/
if (!defined("WIKINI_VERSION")) {
    die("acc&egrave;s direct interdit");
}


//recuperation des parametres
$listelements['mail'] = $this->GetParameter('mail');
if (empty($listelements['mail'])) {
    echo '<div class="alert alert-danger"><strong>'._t('CONTACT_ACTION_DESABONNEMENT').' :</strong>&nbsp;'._t('CONTACT_MAIL_REQUIRED').'</div>';
} else {
    // on utilise une variable globale pour savoir de quel formulaire la demande est envoyee, s'il y en a plusieurs sur la meme page
    if (isset($GLOBALS['nbactionmail'])) {
        $GLOBALS['nbactionmail']++;
    } else {
        $GLOBALS['nbactionmail'] = 1;
    }
    $listelements['nbactionmail'] = $GLOBALS['nbactionmail'];

    // on choisit le template utilisé
    $template = $this->GetParameter('template');
    if (empty($template)) {
        $template = 'subscribe-form.tpl.html';
    }

    $listelements['hiddeninputs'] = '';
    // on indique quel type de liste est utilisé pour formatter les envois de mail de facon adaptee
    $mailinglist = $this->GetParameter('mailinglist');
    if (!empty($mailinglist) and ($mailinglist == 'ezmlm' or $mailinglist == 'sympa')) {
        $listelements['hiddeninputs'] .= '<input type="hidden" name="mailinglist" value="'.$mailinglist.'">';
    }

    // on peut ajouter des classes à la classe par défaut
    $listelements['class'] = ($this->GetParameter('class') ? 'form-desabonnement '.$this->GetParameter('class') : 'form-desabonnement');

    // adresse url d'envoi du mail
    $listelements['mailerurl'] = $this->href('mail');

    // type de demande et placeholder
    $listelements['demand'] = 'desabonnement';
    $listelements['placeholder'] = _t('CONTACT_UNSUBSCRIBE');

    include_once('tools/libs/squelettephp.class.php');
    $listtemplate = new SquelettePhp('tools/contact/presentation/templates/'.$template);
    $listtemplate->set($listelements);
    echo $listtemplate->analyser();

    $GLOBALS['js'] = ((isset($GLOBALS['js'])) ? str_replace('	<script src="tools/contact/libs/contact.js"></script>'."\n", '', $GLOBALS['js']) : '').'	<script src="tools/contact/libs/contact.js"></script>'."\n";
}
